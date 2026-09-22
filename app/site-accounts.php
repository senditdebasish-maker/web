<?php
declare(strict_types=1);
// Front-site account flows for the university homepage: OTP sign-in (single sign-on
// into the portals), student access recovery and admission enquiries. Reuses the
// CRM's OTP, mail and rate-limit systems — no parallel security logic.
function siteSession(string $name): void {
    global $config;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    ini_set('session.use_strict_mode', '1');
    session_name($name);
    session_set_cookie_params(sessionCookieParams());
    session_start();
    $_SESSION['last_seen'] = time();
    $_SESSION['site_csrf'] ??= bin2hex(random_bytes(32));
}
// Resume whichever portal session holds a pending OTP handshake (login or creation).
function siteResumePending(): string {
    foreach (['northstar_auth', 'northstar_site', 'northstar_student', 'northstar_session'] as $name) {
        siteSession($name);
        if (isset($_SESSION['otp']) || isset($_SESSION['suid_pending']) || isset($_SESSION['site_recovery'])) return $name;
    }
    fail('Your session expired. Start again.');
}
function siteTransferSession(string $name): void {
    $data = $_SESSION;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    siteSession($name);
    session_regenerate_id(true);
    $_SESSION = $data;
    $_SESSION['last_seen'] = time();
        if ($name === 'northstar_site') {
        $params=sessionCookieParams();
        setcookie('northstar_identity','student',$params + ['expires'=>time()+1800]);
    } elseif ($name === 'northstar_session') {
        $params=sessionCookieParams();
        setcookie('northstar_identity','staff',$params + ['expires'=>time()+1800]);
    }
}
function siteProfile(): ?array {
    $preferred=($_COOKIE['northstar_identity']??'')==='student'?'northstar_site':(($_COOKIE['northstar_identity']??'')==='staff'?'northstar_session':null);
    $sessions=$preferred?array_values(array_unique([$preferred,'northstar_session','northstar_student','northstar_applicant'])):['northstar_site','northstar_session','northstar_student','northstar_applicant'];
    foreach ($sessions as $name) {
        siteSession($name);
        if ($name === 'northstar_session' && isset($_SESSION['uid'], $_SESSION['staff_stamp'])) {
            $user = one('SELECT * FROM users WHERE id=? AND active=1', [(int)$_SESSION['uid']]);
            if ($user && hash_equals(staffStamp($user), $_SESSION['staff_stamp'])) return ['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'], 'url' => 'office.php'];
        }
        if (in_array($name,['northstar_site','northstar_student'],true)) {
            if (isset($_SESSION['portal_id'], $_SESSION['portal_version'])) {
                $student = portalStudent();
                if ($student) return ['name' => $student['name'], 'email' => $student['email'], 'role' => 'Student', 'url' => 'student.php'];
            }
            if (isset($_SESSION['suid'], $_SESSION['sversion'])) {
                $user = currentStudentUser();
                if ($user) return ['name' => $user['name'], 'email' => $user['email'], 'role' => 'Student', 'url' => 'student.php'];
            }
            if ($name === 'northstar_site' && function_exists('currentApplicant')) {
                $applicant=currentApplicant();
                if ($applicant) return ['name' => $applicant['email'], 'email' => $applicant['email'], 'role' => 'Applicant', 'url' => 'student.php?page=applications'];
            }
        }
        if ($name === 'northstar_applicant' && function_exists('currentApplicant')) {
            $applicant=currentApplicant();
            if ($applicant) return ['name' => $applicant['email'], 'email' => $applicant['email'], 'role' => 'Applicant', 'url' => 'student.php?page=applications'];
        }
    }
    return null;
}
function clearSiteIdentity(): void {
    $params=sessionCookieParams();
    setcookie('northstar_identity','',$params + ['expires'=>time()-3600]);
}
function siteCsrfField(): string {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['site_csrf'] ?? '') . '">';
}
function siteCsrfCheck(): void {
    if (!hash_equals($_SESSION['site_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) fail('Your form expired. Refresh the page and try again.');
}
// Start actions may be posted from a page rendered under any of our sessions
// (site or portal handshake). Accept the token whichever session issued it.
function siteCsrfCheckAny(): void {
    foreach (['northstar_site', 'northstar_auth', 'northstar_student', 'northstar_session'] as $name) {
        siteSession($name);
        if (hash_equals($_SESSION['site_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) return;
    }
    fail('Your form expired. Refresh the page and try again.');
}
function siteTakeFlash(): ?string {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_string($flash) ? $flash : null;
}
// 'staff', 'student' or null (unknown email). Students include portal holders,
// registered users and admitted records awaiting portal access.
function siteDetectAccount(string $email): ?string {
    if (one('SELECT id FROM users WHERE LOWER(email)=? AND active=1', [$email])) return 'staff';
    if (one('SELECT a.id FROM portal_accounts a JOIN students s ON s.id=a.student_id WHERE LOWER(a.email)=? AND a.active=1 AND LOWER(s.email)=LOWER(a.email)', [$email])) return 'student';
    if (one('SELECT id FROM student_users WHERE LOWER(email)=? AND active=1', [$email])) return 'student';
    if (function_exists('applicationsReady') && applicationsReady() && one('SELECT id FROM applicant_accounts WHERE LOWER(email)=? AND active=1', [$email])) return 'student';
    return null;
}
// "Forgot password" for students: Gmail OTP proof, then sign in (student accounts
// are OTP-based, so verifying the Gmail IS the recovery). Runs under the student session.
function siteRecoveryRequest(): void {
    if (!studentUsersReady()) fail('Student accounts are not ready. The owner must run the upgrade first.');
    $email = emailInput();
    try {
        mailSettings();
        dependencies();
    } catch (Throwable $e) {
        fail('Email verification is not configured. Contact the institute.');
    }
    $user = one('SELECT * FROM student_users WHERE email=? AND active=1', [$email]);
    $portal = null;
    try {
        $portal = portalAccountByEmail($email);
    } catch (Throwable $ignored) {
    }
    if (!$user && !$portal) {
        $_SESSION['flash'] = 'If this email has a student account, a verification code has been sent.';
        return;
    }
    $now = time();
    $hash = hash('sha256', 'recovery:' . $email);
    $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $code = (string)random_int(100000, 999999);
    $id = bin2hex(random_bytes(32));
    $secret = bin2hex(random_bytes(32));
    writeTransaction();
    try {
        query('DELETE FROM auth_events WHERE attempted_at<?', [$now - 86400]);
        query('DELETE FROM student_user_codes WHERE expires_at<?', [$now - 86400]);
        if (one("SELECT id FROM auth_events WHERE kind='request' AND ((identity_hash=? AND attempted_at>?) OR (ip_hash=? AND attempted_at>?))", [$hash, $now - 60, $ip, $now - 3])
            || (int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND identity_hash=? AND attempted_at>?", [$hash, $now - 900])->fetchColumn() >= 5
            || (int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND ip_hash=? AND attempted_at>?", [$ip, $now - 900])->fetchColumn() >= 20) fail('Please wait 60 seconds before resending, or 15 minutes after reaching the limit.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'request',?)", [$hash, $ip, $now]);
        query('UPDATE student_user_codes SET consumed=1 WHERE email_hash=?', [$hash]);
        query('INSERT INTO student_user_codes (id,email_hash,code_hash,expires_at) VALUES (?,?,?,?)', [$id, $hash, hash_hmac('sha256', $code, $secret), $now + 300]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    $_SESSION['site_recovery'] = ['id' => $id, 'secret' => $secret, 'email' => $email];
    try {
        sendMail($email, 'Recover your student account', "Your account recovery code is: $code\n\nIt expires in five minutes and works only in the requesting browser. Never share it. Ignore this message if you did not request it.");
    } catch (Throwable $e) {
        query('UPDATE student_user_codes SET consumed=1 WHERE id=?', [$id]);
        error_log('Northstar student recovery code delivery failed.');
    }
    $_SESSION['flash'] = 'A verification code has been sent. Enter it below within five minutes.';
}
function siteRecoveryVerify(): void {
    $pending = $_SESSION['site_recovery'] ?? null;
    if (!$pending) fail('Request a verification code first.');
    $code = input('code', 20);
    $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $user = null;
    $portal = null;
    writeTransaction();
    try {
        $row = one('SELECT * FROM student_user_codes WHERE id=?' . lockSuffix(), [$pending['id']]);
        if ((int)query("SELECT COUNT(*) FROM auth_events WHERE kind='verify' AND ip_hash=? AND attempted_at>?", [$ip, time() - 900])->fetchColumn() >= 30) fail('Too many attempts. Try again in 15 minutes.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'verify',?)", [hash('sha256', 'recovery:' . $pending['email']), $ip, time()]);
        if ($row && !$row['consumed'] && (int)$row['expires_at'] > time() && (int)$row['attempts'] < 5) {
            query('UPDATE student_user_codes SET attempts=attempts+1 WHERE id=?', [$row['id']]);
            if (preg_match('/^\d{6}$/D', $code) && hash_equals($row['code_hash'], hash_hmac('sha256', $code, $pending['secret']))) {
                $user = one('SELECT * FROM student_users WHERE email=? AND active=1' . lockSuffix(), [$pending['email']]);
                try {
                    $portal = portalAccountByEmail($pending['email']);
                } catch (Throwable $ignored) {
                }
                if ($user || $portal) query('UPDATE student_user_codes SET consumed=1 WHERE id=?', [$row['id']]);
                else $user = null;
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    if (!$user && !$portal) fail('Invalid, expired or exhausted code, or this account is disabled. Request a new code if needed.');
    session_regenerate_id(true);
    $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'last_seen' => time()];
    if ($user) {
        $_SESSION['suid'] = (int)$user['id'];
        $_SESSION['sversion'] = (int)$user['version'];
        linkStudentUser($user);
    } elseif ($portal) {
        $_SESSION['portal_id'] = (int)$portal['id'];
        $_SESSION['portal_version'] = (int)$portal['access_version'];
        portalEvent((int)$portal['student_id'], 'login');
    }
    $_SESSION['flash'] = 'Gmail verified. Welcome back.';
}
function siteCourseOptions(): array {
    return rows('SELECT c.id,c.name,i.name iname FROM courses c JOIN institutes i ON i.id=c.institute_id WHERE c.active=1 ORDER BY i.name,c.name');
}
function siteInquirySave(): void {
    $name = input('name', 120);
    $phone = input('phone', 30);
    $email = emailInput();
    if (!preg_match('/^[+0-9 ()-]{7,30}$/D', $phone)) fail('Enter a valid contact phone number.');
    if (input('website', 200, false) !== '') fail('Unable to process this request.');
    $address = input('address', 300, false);
    $message = input('message', 1000, false);
    $course = one('SELECT * FROM courses WHERE id=? AND active=1', [(int)input('course_id', 10)]);
    if (!$course) fail('Select a program.');
    $cut = date('Y-m-d H:i:s', time() - 3600);
    $recent = (int)query('SELECT COUNT(*) FROM enquiries WHERE (email=? OR phone=?) AND created_at>?', [$email, $phone, $cut])->fetchColumn();
    if ($recent >= 3) fail('You recently sent an enquiry. The office will contact you soon.');
    $assignee = one("SELECT id FROM users WHERE active=1 AND role IN ('owner','admin','counsellor') AND (institute_id=? OR institute_id IS NULL) ORDER BY role='owner' DESC, id LIMIT 1", [(int)$course['institute_id']])
        ?: one("SELECT id FROM users WHERE active=1 AND role='owner' ORDER BY id LIMIT 1");
    if (!$assignee) fail('Admissions are not ready. Please contact the institute office.');
    $notes = trim(($message !== '' ? $message . "\n" : '') . ($address !== '' ? 'Address: ' . $address : ''));
    query('INSERT INTO enquiries (institute_id,course_id,assigned_to,name,phone,email,source,status,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)', [(int)$course['institute_id'], (int)$course['id'], (int)$assignee['id'], $name, $phone, $email, 'website', 'New', $notes, date('Y-m-d H:i:s')]);
}
