<?php
declare(strict_types=1);
function migratePortal(): void {
    $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach ([
        'portal_accounts'=>"id $id, student_id INTEGER NOT NULL UNIQUE, email VARCHAR(200) NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1, access_version INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (student_id) REFERENCES students(id)",
        'portal_otp_challenges'=>'id VARCHAR(64) PRIMARY KEY, user_id INTEGER NULL, email_hash VARCHAR(64) NOT NULL, code_hash VARCHAR(64) NOT NULL, expires_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, consumed INTEGER NOT NULL DEFAULT 0, access_version INTEGER NOT NULL DEFAULT 0, FOREIGN KEY (user_id) REFERENCES portal_accounts(id)',
        'portal_events'=>"id $id, student_id INTEGER NOT NULL, action VARCHAR(40) NOT NULL, document_id INTEGER NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (student_id) REFERENCES students(id), FOREIGN KEY (document_id) REFERENCES documents(id)"
    ] as $table=>$definition) db()->exec("CREATE TABLE IF NOT EXISTS $table ($definition)$suffix");
    foreach(['portal_otp_challenges'=>['idx_portal_otp_email','email_hash'], 'portal_events'=>['idx_portal_event_student','student_id']] as $table=>[$index,$columns]) {
        $exists=$mysql ? one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$index]) : one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$index]);
        if (!$exists) db()->exec("CREATE INDEX $index ON $table ($columns)");
    }
    migrateStudentUsers();
}
function portalReady(): bool {
    try {
        foreach(['portal_accounts','portal_otp_challenges','portal_events'] as $table) query("SELECT id FROM $table WHERE 1=0");
        return true;
    } catch(PDOException $e) { return false; }
}
function portalAccountByEmail(string $email): ?array {
    return one('SELECT a.* FROM portal_accounts a JOIN students s ON s.id=a.student_id WHERE a.email=? AND a.active=1 AND LOWER(s.email)=a.email',[$email]);
}
function portalAccountById(int $id): ?array {
    return one('SELECT a.* FROM portal_accounts a JOIN students s ON s.id=a.student_id WHERE a.id=? AND a.active=1 AND LOWER(s.email)=a.email',[$id]);
}
function portalStudent(): ?array {
    if (!isset($_SESSION['portal_id'],$_SESSION['portal_version'])) return null;
    $a=portalAccountById((int)$_SESSION['portal_id']);
    if (!$a || (int)$a['access_version']!==(int)$_SESSION['portal_version']) return null;
    return one('SELECT s.*,c.name course_name,c.duration,i.name institute_name,i.city,i.phone institute_phone FROM students s JOIN courses c ON c.id=s.course_id JOIN institutes i ON i.id=s.institute_id WHERE s.id=?',[$a['student_id']]);
}
function portalEvent(int $student,string $action,?int $doc=null): void {
    query('INSERT INTO portal_events (student_id,action,document_id,created_at) VALUES (?,?,?,?)',[$student,$action,$doc,date('Y-m-d H:i:s')]);
}
function managePortalAccess(): string {
    requireRole(['owner','admin']);
    if (!portalReady()) fail('The owner must run the student portal upgrade first.');
    $student=record('students',(int)input('student_id'));
    $student=one('SELECT * FROM students WHERE id=?'.lockSuffix(),[$student['id']]);
    $enable=choice('access',['enable','disable'])==='enable';
    $account=one('SELECT * FROM portal_accounts WHERE student_id=?',[$student['id']]);
    if (!$enable) {
        if ($account) query('UPDATE portal_accounts SET active=0,access_version=access_version+1 WHERE id=?',[$account['id']]);
    } else {
        $email=strtolower(trim($student['email']));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Add a valid personal student email before enabling portal access.');
        if (one('SELECT id FROM portal_accounts WHERE email=? AND student_id<>?',[$email,$student['id']])) fail('This email is already reserved for another portal account. Use a unique personal email for this student; shared family emails are not supported.');
        if ($account) query('UPDATE portal_accounts SET email=?,active=1,access_version=access_version+1 WHERE id=?',[$email,$account['id']]);
        else query('INSERT INTO portal_accounts (student_id,email,created_at) VALUES (?,?,?)',[$student['id'],$email,date('Y-m-d H:i:s')]);
    }
    audit($enable?'portal_enabled':'portal_disabled','students',(int)$student['id']);
    $_SESSION['flash']=$enable?'Student portal access enabled. Share the website sign-in link with the student; they sign in using email OTP. No invitation email was sent.':'Student portal access disabled. Existing student sessions and pending codes are revoked.';
    return 'students';
}
function portalControl(array $student): string {
    if (!portalReady()) return '<small>Student portal upgrade required.</small><a class="text-link" href="upgrade.php">Upgrade instructions ↗</a>';
    $account=one('SELECT * FROM portal_accounts WHERE student_id=?',[$student['id']]);
    $enabled=$account && $account['active'] && $account['email']===strtolower($student['email']);
<<<<<<< HEAD
<<<<<<< HEAD
    return '<div class="portal-access"><small>Portal: '.($enabled?'Enabled':'Disabled').'</small><form method="post">'.csrf().'<input type="hidden" name="action" value="portal_access"><input type="hidden" name="student_id" value="'.$student['id'].'"><input type="hidden" name="access" value="'.($enabled?'disable':'enable').'"><button class="text-button">'.($enabled?'Disable student access':'Enable student access').'</button></form><a class="text-link" href="student.php">Student sign-in ↗</a></div>';
=======
    return '<div class="portal-access"><small>Portal: '.($enabled?'Enabled':'Disabled').'</small><form method="post">'.csrf().'<input type="hidden" name="action" value="portal_access"><input type="hidden" name="student_id" value="'.$student['id'].'"><input type="hidden" name="access" value="'.($enabled?'disable':'enable').'"><button class="text-button">'.($enabled?'Disable student access':'Enable student access').'</button></form><a class="text-link" href="index.php?page=login">Student sign-in ↗</a></div>';
>>>>>>> parent of ba104b3 (new)
=======
    return '<div class="portal-access"><small>Portal: '.($enabled?'Enabled':'Disabled').'</small><form method="post">'.csrf().'<input type="hidden" name="action" value="portal_access"><input type="hidden" name="student_id" value="'.$student['id'].'"><input type="hidden" name="access" value="'.($enabled?'disable':'enable').'"><button class="text-button">'.($enabled?'Disable student access':'Enable student access').'</button></form><a class="text-link" href="index.php?page=login">Student sign-in ↗</a></div>';
>>>>>>> parent of ba104b3 (new)
}
function migrateStudentUsers(): void {
    $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach ([
        'student_users'=>"id $id, name VARCHAR(120) NOT NULL, email VARCHAR(200) NOT NULL UNIQUE, phone VARCHAR(30) NOT NULL, address VARCHAR(300) NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 1, version INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(19) NOT NULL",
        'student_user_codes'=>'id VARCHAR(64) PRIMARY KEY, email_hash VARCHAR(64) NOT NULL, code_hash VARCHAR(64) NOT NULL, expires_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, consumed INTEGER NOT NULL DEFAULT 0'
    ] as $table=>$definition) db()->exec("CREATE TABLE IF NOT EXISTS $table ($definition)$suffix");
    $exists=$mysql ? one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',['student_user_codes','idx_suser_code_email']) : one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",['idx_suser_code_email']);
    if (!$exists) db()->exec('CREATE INDEX idx_suser_code_email ON student_user_codes (email_hash)');
    migrateStudentUserAddress();
}
function studentUsersReady(): bool {
    try { foreach(['student_users','student_user_codes'] as $table) query("SELECT id FROM $table WHERE 1=0"); return true; }
    catch(PDOException $e) { return false; }
}
function currentStudentUser(): ?array {
    if(!studentUsersReady() || !isset($_SESSION['suid'],$_SESSION['sversion'])) return null;
    $u=one('SELECT * FROM student_users WHERE id=? AND active=1',[(int)$_SESSION['suid']]);
    return $u && (int)$u['version']===(int)$_SESSION['sversion'] ? $u : null;
}
function linkStudentUser(array $u): void {
    $portal=portalAccountByEmail($u['email']);
    if($portal){ $_SESSION['portal_id']=(int)$portal['id']; $_SESSION['portal_version']=(int)$portal['access_version']; portalEvent((int)$portal['student_id'],'login'); }
}
function requestStudentUserCode(): string {
    if(!studentUsersReady()) fail('Student registration is not ready. The owner must run the upgrade first.');
    $name=input('name',120); $phone=input('phone',30); $email=emailInput(); $address=input('address',300,false);
    if(!preg_match('/^[+0-9 ()-]{7,30}$/D',$phone)) fail('Enter a valid contact phone number.');
    if(input('website',200,false)!=='') fail('Unable to process this request.');
    try{ mailSettings(); dependencies(); }catch(Throwable $e){ fail('Email verification is not configured. Contact the institute.'); }
    $now=time(); $hash=hash('sha256','student-user:'.$email); $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
    $code=(string)random_int(100000,999999); $id=bin2hex(random_bytes(32)); $secret=bin2hex(random_bytes(32));
    writeTransaction(); try{
        query('DELETE FROM auth_events WHERE attempted_at<?',[$now-86400]);
        query('DELETE FROM student_user_codes WHERE expires_at<?',[$now-86400]);
        if(one("SELECT id FROM auth_events WHERE kind='request' AND ((identity_hash=? AND attempted_at>?) OR (ip_hash=? AND attempted_at>?))",[$hash,$now-60,$ip,$now-3])
            || (int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND identity_hash=? AND attempted_at>?",[$hash,$now-900])->fetchColumn()>=5
            || (int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND ip_hash=? AND attempted_at>?",[$ip,$now-900])->fetchColumn()>=20) fail('Please wait 60 seconds before resending, or 15 minutes after reaching the limit.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'request',?)",[$hash,$ip,$now]);
        query('UPDATE student_user_codes SET consumed=1 WHERE email_hash=?',[$hash]);
        query('INSERT INTO student_user_codes (id,email_hash,code_hash,expires_at) VALUES (?,?,?,?)',[$id,$hash,hash_hmac('sha256',$code,$secret),$now+300]);
        db()->commit();
    }catch(Throwable $e){ if(db()->inTransaction())db()->rollBack(); throw $e; }
    $_SESSION['suid_pending']=['id'=>$id,'secret'=>$secret,'email'=>$email,'name'=>$name,'phone'=>$phone,'address'=>$address];
    try{ sendMail($email,'Your student account verification code',"Your student account verification code is: $code\n\nIt expires in five minutes and works only in the requesting browser. Never share it. Ignore this message if you did not request it."); }
    catch(Throwable $e){ query('UPDATE student_user_codes SET consumed=1 WHERE id=?',[$id]); error_log('Northstar student registration code delivery failed.'); }
    $_SESSION['flash']='A verification code has been sent. Enter it below within five minutes.';
    return 'register';
}
function verifyStudentUserCode(): string {
    if(!studentUsersReady()) fail('Student registration is not ready.');
    $pending=$_SESSION['suid_pending']??null; if(!$pending) fail('Request a verification code first.');
    $code=input('code',20); $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
    $user=null; writeTransaction(); try{
        $row=one('SELECT * FROM student_user_codes WHERE id=?'.lockSuffix(),[$pending['id']]);
        if((int)query("SELECT COUNT(*) FROM auth_events WHERE kind='verify' AND ip_hash=? AND attempted_at>?",[$ip,time()-900])->fetchColumn()>=30) fail('Too many attempts. Try again in 15 minutes.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'verify',?)",[hash('sha256','student-user:'.$pending['email']),$ip,time()]);
        if($row && !$row['consumed'] && (int)$row['expires_at']>time() && (int)$row['attempts']<5){
            query('UPDATE student_user_codes SET attempts=attempts+1 WHERE id=?',[$row['id']]);
            if(preg_match('/^\d{6}$/D',$code) && hash_equals($row['code_hash'],hash_hmac('sha256',$code,$pending['secret']))){
                $user=one('SELECT * FROM student_users WHERE email=?'.lockSuffix(),[$pending['email']]);
                if(!$user){ query('INSERT INTO student_users (name,email,phone,address,created_at) VALUES (?,?,?,?,?)',[$pending['name'],$pending['email'],$pending['phone'],$pending['address']??'',date('Y-m-d H:i:s')]); $user=one('SELECT * FROM student_users WHERE id=?',[(int)db()->lastInsertId()]); }
                elseif(!$user['active']){ $user=null; }
                if($user) query('UPDATE student_user_codes SET consumed=1 WHERE id=?',[$row['id']]);
            }
        }
        db()->commit();
    }catch(Throwable $e){ if(db()->inTransaction())db()->rollBack(); throw $e; }
    if(!$user) fail('Invalid, expired or exhausted code, or this account is disabled. Request a new code if needed.');
    session_regenerate_id(true);
    $_SESSION=['csrf'=>bin2hex(random_bytes(32)),'last_seen'=>time(),'suid'=>(int)$user['id'],'sversion'=>(int)$user['version']];
    linkStudentUser($user);
    $_SESSION['flash']=isset($_SESSION['portal_id'])?'Account verified and linked to your student record. Welcome!':'Account created. Your email is verified — now apply for admission to link your student record.';
    return isset($_SESSION['portal_id'])?'dashboard':'account';
}
function toggleStudentUser(): string {
    requireRole(['owner','admin']); if(!studentUsersReady()) fail('Student registration upgrade required.');
    $u=one('SELECT * FROM student_users WHERE id=?'.lockSuffix(),[(int)input('user_id')]); if(!$u) fail('Student account not found.');
    query('UPDATE student_users SET active=?,version=version+1 WHERE id=?',[$u['active']?0:1,$u['id']]);
    audit('student_user_toggled','student_users',(int)$u['id']); return 'student-accounts';
}

function migrateStudentUserAddress(): void {
    $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $exists=$mysql ? one('SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',['student_users','address']) : one("SELECT name FROM pragma_table_info('student_users') WHERE name='address'");
    if(!$exists) db()->exec("ALTER TABLE student_users ADD address VARCHAR(300) NOT NULL DEFAULT ''");
}
