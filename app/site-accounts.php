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
    // PHP keeps the previous session id in memory across name switches, so a
    // second session_start() would reuse it instead of reading this session's
    // own cookie (staff/applicant handshakes resumed an empty session and every
    // code step failed with "session expired"). Rebind explicitly: an unknown
    // or missing id safely starts a fresh session under strict mode.
    $cookie = $_COOKIE[$name] ?? '';
    session_id(is_string($cookie) ? $cookie : '');
    session_set_cookie_params(sessionCookieParams());
    session_start();
    $_SESSION['last_seen'] = time();
    $_SESSION['site_csrf'] ??= bin2hex(random_bytes(32));
}
// Resume whichever portal session holds a pending OTP handshake (login or creation).
function siteResumePending(): string {
<<<<<<< HEAD
    foreach (['northstar_auth', 'northstar_site', 'northstar_student', 'northstar_session', 'northstar_applicant'] as $name) {
=======
    foreach (['northstar_student', 'northstar_session'] as $name) {
>>>>>>> parent of 549483e (new)
        siteSession($name);
        if (isset($_SESSION['otp']) || isset($_SESSION['suid_pending']) || isset($_SESSION['site_recovery']) || isset($_SESSION['applicant_pending'])) return $name;
    }
    fail('Your session expired. Start again.');
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
<<<<<<< HEAD
    foreach (['northstar_site', 'northstar_auth', 'northstar_student', 'northstar_session', 'northstar_applicant'] as $name) {
=======
    foreach (['northstar_site', 'northstar_student', 'northstar_session'] as $name) {
>>>>>>> parent of 549483e (new)
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
<<<<<<< HEAD
    if (one('SELECT id FROM users WHERE LOWER(email)=? AND active=1', [$email])) return 'staff';
    if (one('SELECT a.id FROM portal_accounts a JOIN students s ON s.id=a.student_id WHERE LOWER(a.email)=? AND a.active=1 AND LOWER(s.email)=LOWER(a.email)', [$email])) return 'student';
    if (one('SELECT id FROM student_users WHERE LOWER(email)=? AND active=1', [$email])) return 'student';
    if (function_exists('applicationsReady') && applicationsReady() && one('SELECT id FROM applicant_accounts WHERE LOWER(email)=? AND active=1', [$email])) return 'student';
    if (one('SELECT id FROM students WHERE LOWER(email)=?', [$email])) return 'student';
=======
    if (one('SELECT id FROM users WHERE email=? AND active=1', [$email])) return 'staff';
    if (one('SELECT a.id FROM portal_accounts a JOIN students s ON s.id=a.student_id WHERE a.email=? AND a.active=1 AND LOWER(s.email)=a.email', [$email])
        || one('SELECT id FROM student_users WHERE email=? AND active=1', [$email])
        || one('SELECT id FROM students WHERE LOWER(email)=?', [$email])) return 'student';
>>>>>>> parent of 549483e (new)
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
// Staff password sign-in on the master login page (password-mode institutes only).
// Same protections as the former office login: throttled attempts, dummy hash,
// fresh session, audit. Runs under the staff session.
function sitePasswordLogin(): void {
    if (otpEnabled()) fail('Password login is disabled. Request an email sign-in code.');
    $email = emailInput();
    $password = input('password', 72);
    $identity = hash('sha256', $email);
    $ip = hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    query('DELETE FROM login_attempts WHERE attempted_at < ?', [time() - 900]);
    if ((int)query('SELECT COUNT(*) FROM login_attempts WHERE identity_hash IN (?,?)', [$identity, $ip])->fetchColumn() >= 10) fail('Too many attempts. Please try again in 15 minutes.');
    $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
    $valid = password_verify($password, $u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
    if (!$u || !$valid) {
        foreach ([$identity, $ip] as $hash) query('INSERT INTO login_attempts (identity_hash,attempted_at) VALUES (?,?)', [$hash, time()]);
        fail('Email or password is incorrect.');
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['staff_stamp'] = staffStamp($u);
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $_SESSION['last_seen'] = time();
    query('DELETE FROM login_attempts WHERE identity_hash = ?', [$identity]);
    audit('login', 'users', (int)$u['id']);
    $_SESSION['flash'] = 'Welcome back. Your workspace is ready.';
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
