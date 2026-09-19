<?php
declare(strict_types=1);
require_once __DIR__.'/portal.php';
require_once __DIR__.'/otp.php';
require_once __DIR__.'/finance.php';
require_once __DIR__.'/operations.php';
require_once __DIR__.'/services.php';
function handleAction(): string {
    $action = input('action', 40);
    if (!hash_equals($_SESSION['csrf'], input('csrf', 128))) fail('Your form expired. Refresh the page and try again.');
    if ($action==='request_otp') return requestOtp();
    if ($action==='verify_otp') return verifyOtp();
    if ($action === 'login') {
        if (otpEnabled()) fail('Password login is disabled. Request an email sign-in code.');
        $email = emailInput();
        $password = input('password', 72);
        $identity = hash('sha256', $email);
        $ip = hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        query('DELETE FROM login_attempts WHERE attempted_at < ?', [time()-900]);
        if ((int)query('SELECT COUNT(*) FROM login_attempts WHERE identity_hash IN (?,?)', [$identity,$ip])->fetchColumn() >= 10) fail('Too many attempts. Please try again in 15 minutes.');
        $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
        $valid = password_verify($password, $u['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$u || !$valid) {
            foreach ([$identity,$ip] as $hash) query('INSERT INTO login_attempts (identity_hash,attempted_at) VALUES (?,?)', [$hash,time()]);
            fail('Email or password is incorrect.');
        }
        session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; $_SESSION['staff_stamp']=staffStamp($u); $_SESSION['csrf'] = bin2hex(random_bytes(32));
        query('DELETE FROM login_attempts WHERE identity_hash = ?', [$identity]);
        audit('login','users',(int)$u['id']); return 'dashboard';
    }
    $u = requireRole(['owner','admin','counsellor']);
    if ($action === 'logout') { $_SESSION = []; session_regenerate_id(true); return 'login'; }
    if ($action === 'password') {
        if (!password_verify(input('current_password',72),$u['password_hash'])) fail('Current password is incorrect.');
        $password = input('password',72); if (strlen($password)<12) fail('Use at least 12 characters for the new password.');
        if ($password !== input('confirm_password',72)) fail('Passwords do not match.');
        query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password,PASSWORD_DEFAULT),$u['id']]);
        audit('password_changed','users',(int)$u['id']); $_SESSION['staff_stamp']=staffStamp(one('SELECT * FROM users WHERE id=?',[$u['id']])); session_regenerate_id(true); return 'settings';
    }
    writeTransaction();
    try {
        $page = match ($action) {
            'institute' => saveInstitute(),
            'course' => saveCourse(),
            'staff' => saveStaff(),
            'staff_toggle' => toggleStaff(),
            'enquiry' => saveEnquiry(),
            'followup' => saveFollowup(),
            'complete_followup' => completeFollowup(),
            'admit' => admitStudent(),
            'payment' => recordPayment(),
            'student_email' => studentEmail(),
            'portal_access' => managePortalAccess(),
            'exam_create' => createExam(),
            'exam_grade' => gradeExam(),
            'exam_publish' => publishExam(),
            'announcement' => saveAnnouncement(),
            'support_reply' => staffSupportReply(),
            'teacher' => saveTeacher(),
            'batch' => saveBatch(),
            'enrollment' => enrollStudent(),
            'class_day' => openClassDay(),
            'attendance' => saveAttendance(),
            'fee_plan' => saveFeePlan(),
            'revoke_sessions' => revokeStaffSessions(),
            'retry_notification' => retryNotification(),
            'admission_letter' => generateAdmissionLetter(),
            default => throw new DomainException('Unknown action.')
        };
        db()->commit(); return $page;
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
}
function saveInstitute(): string {
    requireRole(['owner']); $id = (int)($_POST['id'] ?? 0);
    $values = [input('name',120),input('kind',60),input('city',100),input('phone',30)];
    if ($id) { instituteAccess($id); query('UPDATE institutes SET name=?,kind=?,city=?,phone=? WHERE id=?',[...$values,$id]); }
    else { query('INSERT INTO institutes (name,kind,city,phone,created_at) VALUES (?,?,?,?,?)',[...$values,date('Y-m-d H:i:s')]); $id=(int)db()->lastInsertId(); }
    audit('saved','institutes',$id); return 'institutes';
}
function saveCourse(): string {
    requireRole(['owner','admin']); $iid=(int)input('institute_id'); instituteAccess($iid);
    $id=(int)($_POST['id'] ?? 0); if ($id && (int)record('courses',$id)['institute_id'] !== $iid) fail('Cannot transfer courses between institutes.');
    $fee = input('fee',12); if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/',$fee)) fail('Enter a valid fee up to 9,999,999.99.');
    $minor = (int)round((float)$fee*100);
    $v = [input('name',120),input('duration',80),$minor,choice('active',['0','1'])];
    if ($id) query('UPDATE courses SET name=?,duration=?,fee_minor=?,active=? WHERE id=?',[...$v,$id]);
    else { query('INSERT INTO courses (name,duration,fee_minor,active,institute_id) VALUES (?,?,?,?,?)',[...$v,$iid]); $id=(int)db()->lastInsertId(); }
    audit('saved','courses',$id); return 'courses';
}
function saveStaff(): string {
    $u=requireRole(['owner','admin']); $iid=(int)input('institute_id'); instituteAccess($iid);
    $role=choice('role',$u['role']==='owner' ? ['admin','counsellor'] : ['counsellor']);
    $password=otpEnabled() ? bin2hex(random_bytes(24)) : input('password',72); if (strlen($password)<12) fail('Staff passwords must have at least 12 characters.');
    query('INSERT INTO users (institute_id,name,email,password_hash,role) VALUES (?,?,?,?,?)',[$iid,input('name',120),emailInput(),password_hash($password,PASSWORD_DEFAULT),$role]);
    audit('created','users',(int)db()->lastInsertId()); return 'staff';
}
function toggleStaff(): string {
    $u=requireRole(['owner','admin']); $id=(int)input('id'); $target=one('SELECT * FROM users WHERE id=?',[$id]);
    if (!$target || $target['role']==='owner' || $id===(int)$u['id']) fail('This account cannot be changed here.');
    instituteAccess((int)$target['institute_id']);
    if ($u['role']==='admin' && $target['role']!=='counsellor') fail('Only the owner can change administrators.');
    query('UPDATE users SET active=? WHERE id=?',[(int)!$target['active'],$id]);
    if(operationsReady()) bumpStaffVersion($id);
    query('UPDATE otp_challenges SET consumed=1 WHERE user_id=?',[$id]); audit('access_changed','users',$id); return 'staff';
}
function saveEnquiry(): string {
    $iid=(int)input('institute_id'); instituteAccess($iid); $id=(int)($_POST['id'] ?? 0);
    if ($id) { $old=record('enquiries',$id); if ((int)$old['institute_id']!==$iid || $old['status']==='Admitted') fail('Admitted enquiries are locked; institutes cannot be changed.'); }
    $cid=(int)input('course_id'); courseAccess($cid,$iid); $assigned=(int)input('assigned_to'); counsellorAccess($assigned,$iid);
    $email=input('email',200,false); if ($email && !filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
    $phone=input('phone',30); if (!preg_match('/^[+\d][\d\s()\-]{5,29}$/',$phone)) fail('Enter a valid phone number.');
    $v=[$cid,$assigned,input('name',120),$phone,$email,choice('source',['Walk-in','Website','Referral','Phone','Social media','Other']),choice('status',['New','Contacted','Interested','Lost']),input('notes',3000,false)];
    if ($id) query('UPDATE enquiries SET course_id=?,assigned_to=?,name=?,phone=?,email=?,source=?,status=?,notes=? WHERE id=?',[...$v,$id]);
    else { query('INSERT INTO enquiries (course_id,assigned_to,name,phone,email,source,status,notes,institute_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[...$v,$iid,date('Y-m-d H:i:s')]); $id=(int)db()->lastInsertId(); }
    audit('saved','enquiries',$id); return 'enquiries';
}
function saveFollowup(): string {
    $eid=(int)input('enquiry_id'); $r=record('enquiries',$eid);
    if (in_array($r['status'],['Admitted','Lost'],true)) fail('Follow-ups are only available for open enquiries.');
    $assigned=(int)input('assigned_to'); counsellorAccess($assigned,(int)$r['institute_id']);
    query("INSERT INTO followups (institute_id,enquiry_id,assigned_to,due_date,notes,outcome) VALUES (?,?,?,?,?,'')",[$r['institute_id'],$eid,$assigned,validDate('due_date'),input('notes',3000)]);
    audit('scheduled','followups',(int)db()->lastInsertId()); return 'followups';
}
function completeFollowup(): string {
    $id=(int)input('id'); $r=record('followups',$id); if ($r['completed_at']) fail('This follow-up has already been completed.');
    query('UPDATE followups SET completed_at=?,outcome=? WHERE id=? AND completed_at IS NULL',[date('Y-m-d H:i:s'),input('outcome',3000),$id]);
    audit('completed','followups',$id); return 'followups';
}
function admitStudent(): string {
    requireRole(['owner','admin']); $eid=(int)input('enquiry_id'); $r=record('enquiries',$eid);
    if (in_array($r['status'],['Admitted','Lost'],true)) fail('Only an open enquiry can be admitted.');
    $course=courseAccess((int)$r['course_id'],(int)$r['institute_id']);
    $date=validDate('admission_date'); if ($date>date('Y-m-d')) fail('Admission date cannot be in the future.');
    query('INSERT INTO students (institute_id,enquiry_id,course_id,name,phone,email,admission_date,fee_minor,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$r['institute_id'],$eid,$r['course_id'],$r['name'],$r['phone'],$r['email'],$date,$course['fee_minor'],date('Y-m-d H:i:s')]);
    $id=(int)db()->lastInsertId();
    query("UPDATE enquiries SET status='Admitted' WHERE id=?",[$eid]);
    query("UPDATE followups SET completed_at=?, outcome='Closed automatically on admission.' WHERE enquiry_id=? AND completed_at IS NULL",[date('Y-m-d H:i:s'),$eid]);
    createDocument($id);
    audit('admitted','students',$id);
    $_SESSION['flash']='Student admitted. The admission PDF is ready in Documents; email is queued, or blocked until a student email is added.';
    return 'students';
}
