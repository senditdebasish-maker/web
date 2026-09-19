<?php
declare(strict_types=1);
require_once __DIR__.'/operations.php';
require_once __DIR__.'/portal.php';
function migrateServices(): void {
    $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $json=$mysql?'MEDIUMTEXT':'TEXT';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    $tables=[
        'exams'=>"id $id, institute_id INTEGER NOT NULL, batch_id INTEGER NOT NULL, title VARCHAR(150) NOT NULL, held_on VARCHAR(10) NOT NULL, maximum_minor INTEGER NOT NULL, pass_minor INTEGER NOT NULL, state VARCHAR(20) NOT NULL DEFAULT 'Draft', version INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(institute_id) REFERENCES institutes(id), FOREIGN KEY(batch_id) REFERENCES batches(id), UNIQUE(batch_id,title,held_on)",
        'exam_results'=>"id $id, exam_id INTEGER NOT NULL, student_id INTEGER NOT NULL, attendance VARCHAR(20) NOT NULL DEFAULT 'Pending', score_minor INTEGER NULL, FOREIGN KEY(exam_id) REFERENCES exams(id), FOREIGN KEY(student_id) REFERENCES students(id), UNIQUE(exam_id,student_id)",
        'exam_revisions'=>"id $id, exam_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, action VARCHAR(20) NOT NULL, reason VARCHAR(500) NOT NULL, snapshot_json $json NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(exam_id) REFERENCES exams(id), FOREIGN KEY(actor_id) REFERENCES users(id)",
        'announcements'=>"id $id, institute_id INTEGER NOT NULL, batch_id INTEGER NULL, title VARCHAR(150) NOT NULL, body TEXT NOT NULL, starts_on VARCHAR(10) NOT NULL, ends_on VARCHAR(10) NOT NULL, state VARCHAR(20) NOT NULL, version INTEGER NOT NULL DEFAULT 1, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY(institute_id) REFERENCES institutes(id), FOREIGN KEY(batch_id) REFERENCES batches(id)",
        'announcement_revisions'=>"id $id, announcement_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, reason VARCHAR(500) NOT NULL, snapshot_json $json NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(announcement_id) REFERENCES announcements(id), FOREIGN KEY(actor_id) REFERENCES users(id)",
        'support_tickets'=>"id $id, institute_id INTEGER NOT NULL, student_id INTEGER NOT NULL, subject VARCHAR(150) NOT NULL, category VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Open', version INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY(institute_id) REFERENCES institutes(id), FOREIGN KEY(student_id) REFERENCES students(id)",
        'support_messages'=>"id $id, ticket_id INTEGER NOT NULL, student_id INTEGER NULL, staff_id INTEGER NULL, body TEXT NOT NULL, request_key VARCHAR(64) NOT NULL UNIQUE, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(ticket_id) REFERENCES support_tickets(id), FOREIGN KEY(student_id) REFERENCES students(id), FOREIGN KEY(staff_id) REFERENCES users(id)",
        'runtime_jobs'=>"job_key VARCHAR(64) PRIMARY KEY, started_at VARCHAR(19) NOT NULL, finished_at VARCHAR(19) NULL, outcome VARCHAR(20) NOT NULL, details VARCHAR(250) NOT NULL"
    ];
    foreach($tables as $t=>$def)db()->exec("CREATE TABLE IF NOT EXISTS $t ($def)$suffix");
    foreach(['exam_results'=>['idx_result_student','student_id'], 'support_messages'=>['idx_support_messages','ticket_id,id'], 'support_tickets'=>['idx_support_scope','institute_id,status,updated_at'], 'announcements'=>['idx_announcement_scope','institute_id,state,starts_on'], 'exams'=>['idx_exam_scope','institute_id,held_on']] as $t=>[$idx,$cols]) {
        $exists=$mysql?one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$t,$idx]):one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$idx]);
        if(!$exists)db()->exec("CREATE INDEX $idx ON $t ($cols)");
    }
    foreach([['support_messages','idx_support_student_time','student_id,created_at'],['support_tickets','idx_support_student','student_id,status'],['exam_revisions','idx_exam_revisions','exam_id,id'],['announcement_revisions','idx_notice_revisions','announcement_id,id']] as [$t,$idx,$cols]) {
        $exists=$mysql?one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$t,$idx]):one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$idx]);
        if(!$exists)db()->exec("CREATE INDEX $idx ON $t ($cols)");
    }
    unset($GLOBALS['services_ready']);
}
function servicesReady(): bool {
    if(isset($GLOBALS['services_ready']))return $GLOBALS['services_ready'];
    try {foreach(['exams','exam_results','exam_revisions','announcements','announcement_revisions','support_tickets','support_messages'] as $t)query("SELECT id FROM $t WHERE 1=0");query('SELECT job_key FROM runtime_jobs WHERE 1=0');return $GLOBALS['services_ready']=true;}
    catch(PDOException $e){return $GLOBALS['services_ready']=false;}
}
function servicesGuard(): array {$u=requireRole(['owner','admin']);if(!servicesReady())fail('The owner must run the student services upgrade first.');return $u;}
function serviceRecord(string $table,int $id): array {
    servicesGuard();if(!in_array($table,['exams','announcements','support_tickets'],true))fail('Invalid record.');
    $r=one("SELECT * FROM $table WHERE id=?".lockSuffix(),[$id]);if(!$r)fail('Record not found.');instituteAccess((int)$r['institute_id']);return $r;
}
function scoreMinor(string $s): int {
    if(!preg_match('/^(\d{1,4})(?:\.(\d{1,2}))?$/D',$s,$m))fail('Marks must be between 0 and 1000, with at most two decimal places.');
    $n=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($n>100000)fail('Marks cannot exceed 1000.');return $n;
}
function createExam(): string {
    servicesGuard();$bid=(int)input('batch_id');opsRecord('batches',$bid);$b=one('SELECT * FROM batches WHERE id=?'.lockSuffix(),[$bid]);
    $date=validDate('held_on');if($date>date('Y-m-d')||$date<$b['starts_on']||$date>$b['ends_on'])fail('Exam date must be within batch dates and no later than today.');
    $max=scoreMinor(input('maximum'));$pass=scoreMinor(input('pass_mark'));if(!$max || $pass>$max)fail('Maximum must be positive and pass marks cannot exceed it.');
    $roster=rows('SELECT student_id FROM enrollments WHERE batch_id=? AND starts_on<=? AND (ends_on IS NULL OR ends_on>=?)'.lockSuffix(),[$bid,$date,$date]);
    if(!$roster||count($roster)>200)fail('An exam requires 1–200 eligible students. Split larger assessments into appropriate batches.');
    query('INSERT INTO exams (institute_id,batch_id,title,held_on,maximum_minor,pass_minor,created_at) VALUES (?,?,?,?,?,?,?)',[$b['institute_id'],$bid,input('title',150),$date,$max,$pass,date('Y-m-d H:i:s')]);
    $id=(int)db()->lastInsertId();foreach($roster as $r)query('INSERT INTO exam_results (exam_id,student_id) VALUES (?,?)',[$id,$r['student_id']]);
    audit('exam_created','exams',$id);return 'exams';
}
function examRevision(array $exam,string $action,string $reason,array $before,array $after): void {
    query('INSERT INTO exam_revisions (exam_id,actor_id,action,reason,snapshot_json,created_at) VALUES (?,?,?,?,?,?)',[$exam['id'],currentUser()['id'],$action,$reason,json_encode(['before'=>$before,'after'=>$after],JSON_THROW_ON_ERROR),date('Y-m-d H:i:s')]);
    audit('exam_'.$action,'exams',(int)$exam['id']);
}
function gradeExam(): string {
    $exam=serviceRecord('exams',(int)input('exam_id'));
    if((int)input('version')!==(int)$exam['version'])fail('Exam changed in another window. Reload before saving.');
    if($exam['state']!=='Draft')fail('Withdraw published results before making corrections.');
    $roster=rows('SELECT student_id,attendance,score_minor FROM exam_results WHERE exam_id=? ORDER BY student_id',[$exam['id']]);
    $marks=$_POST['marks']??null;$states=$_POST['result_status']??null;
    if(!is_array($marks)||!is_array($states)||count($marks)!==count($roster)||count($states)!==count($roster))fail('Submit exactly the exam roster.');
    $after=[];foreach($roster as $r){$sid=(int)$r['student_id'];$v=$marks[$sid]??null;$status=$states[$sid]??null;
        if(!is_string($v)||!in_array($status,['Present','Absent'],true))fail('Mark each student Present or Absent.');
        if($status==='Absent' && trim($v)!=='')fail('Leave marks blank for absent students.');
        $score=$status==='Absent'?null:scoreMinor(trim($v));if($score!==null && $score>(int)$exam['maximum_minor'])fail('A score exceeds this exam maximum.');
        $after[]=['student_id'=>$sid,'attendance'=>$status,'score_minor'=>$score];
    }
    $reason=input('reason',500);foreach($after as $r)query('UPDATE exam_results SET attendance=?,score_minor=? WHERE exam_id=? AND student_id=?',[$r['attendance'],$r['score_minor'],$exam['id'],$r['student_id']]);
    query('UPDATE exams SET version=version+1 WHERE id=?',[$exam['id']]);examRevision($exam,'graded',$reason,$roster,$after);return 'exams';
}
function publishExam(): string {
    $exam=serviceRecord('exams',(int)input('exam_id'));if((int)input('version')!==(int)$exam['version'])fail('Exam changed in another window. Reload before saving.');
    $state=choice('state',['Published','Draft']);$reason=input('reason',500);
    if($state===$exam['state'])fail('This exam already has that publication state.');
    if($state==='Published' && one("SELECT id FROM exam_results WHERE exam_id=? AND attendance='Pending'",[$exam['id']]))fail('Grade the complete roster before publishing.');
    query('UPDATE exams SET state=?,version=version+1 WHERE id=?',[$state,$exam['id']]);examRevision($exam,$state==='Published'?'published':'withdrawn',$reason,['state'=>$exam['state']],['state'=>$state]);return 'exams';
}
function saveAnnouncement(): string {
    $u=servicesGuard();$id=(int)($_POST['id']??0);$iid=(int)input('institute_id');instituteAccess($iid);$old=$id?serviceRecord('announcements',$id):null;
    if($old && ((int)$old['institute_id']!==$iid || (int)input('version')!==(int)$old['version']))fail('Announcement changed or institute does not match. Reload.');
    $bid=(int)input('batch_id',20,false);if($bid && (int)opsRecord('batches',$bid)['institute_id']!==$iid)fail('Choose a batch from this institute.');
    $start=validDate('starts_on');$end=validDate('ends_on');if($end<$start)fail('Expiry must not precede the start date.');
    $values=[$bid?:null,input('title',150),input('body',5000),$start,$end,choice('state',['Draft','Published','Archived']),date('Y-m-d H:i:s')];$reason=input('reason',500);
    if($old)query('UPDATE announcements SET batch_id=?,title=?,body=?,starts_on=?,ends_on=?,state=?,updated_at=?,version=version+1 WHERE id=?',[...$values,$id]);
    else {query('INSERT INTO announcements (batch_id,title,body,starts_on,ends_on,state,updated_at,institute_id) VALUES (?,?,?,?,?,?,?,?)',[...$values,$iid]);$id=(int)db()->lastInsertId();}
    query('INSERT INTO announcement_revisions (announcement_id,actor_id,reason,snapshot_json,created_at) VALUES (?,?,?,?,?)',[$id,$u['id'],$reason,json_encode(['before'=>$old,'after'=>$values],JSON_THROW_ON_ERROR),date('Y-m-d H:i:s')]);audit('announcement_saved','announcements',$id);return 'announcements';
}
function studentAnnouncements(array $student,int $offset=0): array {
    $today=date('Y-m-d');return rows("SELECT a.* FROM announcements a WHERE a.institute_id=? AND a.state='Published' AND a.starts_on<=? AND a.ends_on>=? AND (a.batch_id IS NULL OR EXISTS(SELECT e.id FROM enrollments e WHERE e.batch_id=a.batch_id AND e.student_id=? AND e.starts_on<=? AND (e.ends_on IS NULL OR e.ends_on>=?))) ORDER BY a.id DESC LIMIT 21 OFFSET ".max(0,$offset),[$student['institute_id'],$today,$today,$student['id'],$today,$today]);
}
function ticketForStudent(array $student,int $id): array {
    $t=one('SELECT * FROM support_tickets WHERE id=? AND student_id=? AND institute_id=?'.lockSuffix(),[$id,$student['id'],$student['institute_id']]);if(!$t)fail('Ticket not accessible.');return $t;
}
function ticketMessage(array $actor,bool $student,?array $ticket): int {
    $key=input('request_key',64);$existing=one('SELECT * FROM support_messages WHERE request_key=?',[$key]);
    $column=$student?'student_id':'staff_id';
    if($existing){if((int)$existing[$column]!==(int)$actor['id'] || ($ticket && (int)$existing['ticket_id']!==(int)$ticket['id']))fail('Invalid message request.');$_SESSION['flash']='That message request was already processed. No new text or status change was saved. Reload before sending another message.';return (int)$existing['ticket_id'];}
    if(!isset($_SESSION['support_nonce'])||!hash_equals($_SESSION['support_nonce'],$key))fail('Message form expired. Reload before sending.');
    $body=input('body',3000);$now=date('Y-m-d H:i:s');$status=$student?'Open':choice('status',['Open','Waiting','Resolved']);
    if($student){
        one('SELECT id FROM students WHERE id=?'.lockSuffix(),[$actor['id']]);
        $since=date('Y-m-d H:i:s',time()-3600);
        if((int)query('SELECT COUNT(*) FROM support_messages WHERE student_id=? AND created_at>=?',[$actor['id'],$since])->fetchColumn()>=30)fail('Message limit reached. Try again in an hour or contact the office.');
    }
    if(!$ticket){
        if(!$student)fail('Students must open their own support tickets.');
        if((int)query("SELECT COUNT(*) FROM support_tickets WHERE student_id=? AND status<>'Resolved'",[$actor['id']])->fetchColumn()>=5)fail('You already have five active tickets. Reply to an existing one.');
        query('INSERT INTO support_tickets (institute_id,student_id,subject,category,created_at,updated_at) VALUES (?,?,?,?,?,?)',[$actor['institute_id'],$actor['id'],input('subject',150),choice('category',['General','Academic','Fees','Documents']),$now,$now]);
        $ticket=['id'=>(int)db()->lastInsertId(),'status'=>'Open','version'=>1];
    } else {
        if((int)input('version')!==(int)$ticket['version'])fail('Ticket changed in another window. Reload before replying.');
        if($student && $ticket['status']==='Resolved')fail('This ticket is resolved. Open a new ticket if you need further help.');
        if((int)query('SELECT COUNT(*) FROM support_messages WHERE ticket_id=?',[$ticket['id']])->fetchColumn()>=200 && ($student || $status!=='Resolved' || $ticket['status']==='Resolved'))fail('Conversation limit reached. Staff can add one final resolution note, then a new ticket is needed.');
    }
    query('INSERT INTO support_messages (ticket_id,student_id,staff_id,body,request_key,created_at) VALUES (?,?,?,?,?,?)',[$ticket['id'],$student?$actor['id']:null,$student?null:$actor['id'],$body,$key,$now]);
    query('UPDATE support_tickets SET status=?,version=version+1,updated_at=? WHERE id=?',[$status,$now,$ticket['id']]);
    if($student)portalEvent((int)$actor['id'],'support_message');else audit('support_replied','support_tickets',(int)$ticket['id']);
    $_SESSION['support_nonce']=bin2hex(random_bytes(24));return (int)$ticket['id'];
}
function staffSupportReply(): string {
    $u=servicesGuard();unset($_SESSION['flash']);$id=(int)input('ticket_id');$t=one('SELECT * FROM support_tickets WHERE id=?',[$id]);if(!$t)fail('Ticket not found.');instituteAccess((int)$t['institute_id']);
    one('SELECT id FROM students WHERE id=?'.lockSuffix(),[$t['student_id']]);$t=serviceRecord('support_tickets',$id);ticketMessage($u,false,$t);return 'support';
}
function studentSupportAction(string $action): string {
    $s=portalStudent();if(!$s)fail('Sign in to send a support request.');if(!servicesReady())fail('Student services upgrade required.');
    unset($_SESSION['flash']);writeTransaction();try {
        // Same ordering for concurrent student requests: student row, then ticket row.
        one('SELECT id FROM students WHERE id=?'.lockSuffix(),[$s['id']]);
        $t=$action==='support_reply'?ticketForStudent($s,(int)input('ticket_id')):null;
        ticketMessage($s,true,$t);db()->commit();$_SESSION['flash']??='Your message was saved. Replies appear here; no email notification is sent.';return 'support';
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function jobHeartbeat(string $outcome,string $details,bool $start=false): void {
    if(!servicesReady())return;$now=date('Y-m-d H:i:s');
    if($start){
        $sql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?"INSERT INTO runtime_jobs (job_key,started_at,finished_at,outcome,details) VALUES ('notifications',?,NULL,?,?) ON DUPLICATE KEY UPDATE started_at=VALUES(started_at),finished_at=NULL,outcome=VALUES(outcome),details=VALUES(details)":"INSERT INTO runtime_jobs (job_key,started_at,finished_at,outcome,details) VALUES ('notifications',?,NULL,?,?) ON CONFLICT(job_key) DO UPDATE SET started_at=excluded.started_at,finished_at=NULL,outcome=excluded.outcome,details=excluded.details";
        query($sql,[$now,$outcome,$details]);
    }else query("UPDATE runtime_jobs SET finished_at=?,outcome=?,details=? WHERE job_key='notifications'",[$now,$outcome,$details]);
}
