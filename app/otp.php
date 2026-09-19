<?php
declare(strict_types=1);
require_once __DIR__.'/mail.php';
require_once __DIR__.'/portal.php';
function requestOtp(string $audience='staff'): string {
    if (!in_array($audience,['staff','student'],true)) fail('Invalid login audience.');
    $student=$audience==='student';
    $table=$student?'portal_otp_challenges':'otp_challenges';
    if (!$student && !otpEnabled()) fail('Email-code login is not enabled.');
    $email=emailInput();
    // Configuration errors are uniform for every address, without exposing credentials.
    try { mailSettings(); dependencies(); } catch (Throwable $e) { fail('Email login is not configured. Ask the server administrator to complete Gmail SMTP setup.'); }
    $identity=hash('sha256',($student?'student:':'').$email);
    $ip=hash('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now=time();
    $code=(string)random_int(100000,999999);
    $id=bin2hex(random_bytes(32));
    $secret=bin2hex(random_bytes(32));
    writeTransaction();
    try {
        query('DELETE FROM auth_events WHERE attempted_at<?',[$now-86400]);
        query("DELETE FROM $table WHERE expires_at<?",[$now-86400]);
        $recent=(int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND ((identity_hash=? AND attempted_at>?) OR (ip_hash=? AND attempted_at>?))",[$identity,$now-60,$ip,$now-3])->fetchColumn();
        $perEmail=(int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND identity_hash=? AND attempted_at>?",[$identity,$now-900])->fetchColumn();
        $perIp=(int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND ip_hash=? AND attempted_at>?",[$ip,$now-900])->fetchColumn();
        if ($recent || $perEmail>=5 || $perIp>=20) fail('Please wait at least 60 seconds before resending. If you reached the limit, try again in 15 minutes.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'request',?)",[$identity,$ip,$now]);
        $user=$student ? portalAccountByEmail($email) : one('SELECT * FROM users WHERE email=? AND active=1',[$email]);
        query("UPDATE $table SET consumed=1 WHERE email_hash=?",[$identity]);
        query("INSERT INTO $table (id,user_id,email_hash,code_hash,expires_at".($student?',access_version':'').') VALUES (?,?,?,?,?'.($student?',?':'').')',array_merge([$id,$user['id'] ?? null,$identity,hash_hmac('sha256',$code,$secret),$now+300],$student?[(int)($user['access_version'] ?? 0)]:[]));
        db()->commit();
    } catch (Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    $_SESSION['otp']=['id'=>$id,'secret'=>$secret,'email'=>$email,'audience'=>$audience,'staff_stamp'=>!$student && $user?staffStamp($user):null];
    if ($user) {
        try {
            sendMail($email,$student?'Your Northstar student sign-in code':'Your Northstar sign-in code',"Your sign-in code is: $code\n\nThis code expires in 5 minutes and can be used once, in the same browser that requested it. Never share this code.\n\nIf you did not request it, ignore this email.");
        } catch (Throwable $e) {
            query("UPDATE $table SET consumed=1 WHERE id=?",[$id]);
            // Never log message bodies, OTPs, SMTP credentials, or raw transport exceptions.
            error_log('Northstar: OTP delivery failed. Check SMTP configuration and server connectivity.');
        }
    }
    $_SESSION['flash']='If this email belongs to an active '.($student?'student portal':'staff').' account, a code has been sent. Check your inbox and spam folder. It expires in 5 minutes.';
    return 'login';
}
function verifyOtp(string $audience='staff'): string {
    if (!in_array($audience,['staff','student'],true)) fail('Invalid login audience.');
    $student=$audience==='student';
    $table=$student?'portal_otp_challenges':'otp_challenges';
    if (!$student && !otpEnabled()) fail('Email-code login is not enabled.');
    $pending=$_SESSION['otp'] ?? null;
    if (!$pending || ($pending['audience'] ?? 'staff')!==$audience) fail('Request a new sign-in code first.');
    $code=input('code',20);
    $ip=hash('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown');
    writeTransaction();
    try {
        $challenge=one("SELECT * FROM $table WHERE id=?".lockSuffix(),[$pending['id']]);
        $recent=(int)query("SELECT COUNT(*) FROM auth_events WHERE kind='verify' AND ip_hash=? AND attempted_at>?",[$ip,time()-900])->fetchColumn();
        if ($recent>=30) fail('Too many verification attempts. Try again in 15 minutes.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'verify',?)",[hash('sha256',($student?'student:':'').$pending['email']),$ip,time()]);
        $valid=$challenge && !$challenge['consumed'] && (int)$challenge['expires_at']>time() && (int)$challenge['attempts']<5;
        if ($valid) {
            query("UPDATE $table SET attempts=attempts+1 WHERE id=?",[$challenge['id']]);
            $valid=preg_match('/^\d{6}$/D',$code) && hash_equals($challenge['code_hash'],hash_hmac('sha256',$code,$pending['secret']));
        }
        $user=$valid ? ($student ? portalAccountById((int)$challenge['user_id']) : one('SELECT * FROM users WHERE id=? AND active=1',[$challenge['user_id']])) : null;
        if ($student && $user && (int)$user['access_version']!==(int)$challenge['access_version']) $user=null;
        if (!$student && $user && (!isset($pending['staff_stamp']) || !hash_equals(staffStamp($user),$pending['staff_stamp']))) $user=null;
        if ($user) query("UPDATE $table SET consumed=1 WHERE id=?",[$challenge['id']]);
        db()->commit();
    } catch (Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
    if (!$user) fail('Invalid, expired, or exhausted code. Request a new code if needed.');
    session_regenerate_id(true);
    $_SESSION=['csrf'=>bin2hex(random_bytes(32)),'last_seen'=>time()];
    if ($student) {
        $_SESSION['portal_id']=(int)$user['id']; $_SESSION['portal_version']=(int)$user['access_version'];
        portalEvent((int)$user['student_id'],'login');
    } else { $_SESSION['uid']=(int)$user['id']; $_SESSION['staff_stamp']=$pending['staff_stamp']; audit('otp_login','users',(int)$user['id']); }
    $_SESSION['flash']='Email verified. Welcome back.';
    return 'dashboard';
}
