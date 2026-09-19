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
    $_SESSION['flash']=$enable?'Student portal access enabled. Share the student.php link with the student; they sign in using email OTP. No invitation email was sent.':'Student portal access disabled. Existing student sessions and pending codes are revoked.';
    return 'students';
}
function portalControl(array $student): string {
    if (!portalReady()) return '<small>Student portal upgrade required.</small><a class="text-link" href="upgrade.php">Upgrade instructions ↗</a>';
    $account=one('SELECT * FROM portal_accounts WHERE student_id=?',[$student['id']]);
    $enabled=$account && $account['active'] && $account['email']===strtolower($student['email']);
    return '<div class="portal-access"><small>Portal: '.($enabled?'Enabled':'Disabled').'</small><form method="post">'.csrf().'<input type="hidden" name="action" value="portal_access"><input type="hidden" name="student_id" value="'.$student['id'].'"><input type="hidden" name="access" value="'.($enabled?'disable':'enable').'"><button class="text-button">'.($enabled?'Disable student access':'Enable student access').'</button></form><a class="text-link" href="student.php">Student sign-in ↗</a></div>';
}
