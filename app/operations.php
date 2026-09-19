<?php
declare(strict_types=1);
function migrateOperations(): void {
    $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    $tables=[
        'teachers'=>"id $id, institute_id INTEGER NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(200) NOT NULL, phone VARCHAR(30) NOT NULL, qualification VARCHAR(200) NOT NULL, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY (institute_id) REFERENCES institutes(id)",
        'batches'=>"id $id, institute_id INTEGER NOT NULL, course_id INTEGER NOT NULL, teacher_id INTEGER NOT NULL, name VARCHAR(120) NOT NULL, room VARCHAR(100) NOT NULL, starts_on VARCHAR(10) NOT NULL, ends_on VARCHAR(10) NOT NULL, capacity INTEGER NOT NULL, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (course_id) REFERENCES courses(id), FOREIGN KEY (teacher_id) REFERENCES teachers(id), UNIQUE(institute_id,name)",
        'enrollments'=>"id $id, institute_id INTEGER NOT NULL, student_id INTEGER NOT NULL, batch_id INTEGER NOT NULL, starts_on VARCHAR(10) NOT NULL, ends_on VARCHAR(10) NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (student_id) REFERENCES students(id), FOREIGN KEY (batch_id) REFERENCES batches(id)",
        'class_days'=>"id $id, institute_id INTEGER NOT NULL, batch_id INTEGER NOT NULL, held_on VARCHAR(10) NOT NULL, topic VARCHAR(200) NOT NULL, teacher_name VARCHAR(120) NOT NULL, finalized INTEGER NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, recorded_by INTEGER NOT NULL, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (batch_id) REFERENCES batches(id), FOREIGN KEY (recorded_by) REFERENCES users(id), UNIQUE(batch_id,held_on)",
        'attendance'=>"id $id, class_id INTEGER NOT NULL, student_id INTEGER NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Unmarked', FOREIGN KEY (class_id) REFERENCES class_days(id), FOREIGN KEY (student_id) REFERENCES students(id), UNIQUE(class_id,student_id)",
        'attendance_revisions'=>"id $id, class_id INTEGER NOT NULL, recorded_by INTEGER NOT NULL, reason VARCHAR(500) NOT NULL, snapshot_json TEXT NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (class_id) REFERENCES class_days(id), FOREIGN KEY (recorded_by) REFERENCES users(id)",
        'fee_plans'=>"id $id, institute_id INTEGER NOT NULL, student_id INTEGER NOT NULL UNIQUE, version INTEGER NOT NULL DEFAULT 1, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (student_id) REFERENCES students(id)",
        'fee_installments'=>"id $id, plan_id INTEGER NOT NULL, due_on VARCHAR(10) NOT NULL, amount_minor INTEGER NOT NULL, FOREIGN KEY (plan_id) REFERENCES fee_plans(id), UNIQUE(plan_id,due_on)",
        'fee_plan_revisions'=>"id $id, plan_id INTEGER NOT NULL, recorded_by INTEGER NOT NULL, reason VARCHAR(500) NOT NULL, snapshot_json TEXT NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (plan_id) REFERENCES fee_plans(id), FOREIGN KEY (recorded_by) REFERENCES users(id)",
        'staff_security'=>"id INTEGER PRIMARY KEY, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY (id) REFERENCES users(id)"
    ];
    foreach($tables as $table=>$definition) db()->exec("CREATE TABLE IF NOT EXISTS $table ($definition)$suffix");
    foreach(['enrollments'=>['idx_enrollment_student'=>'student_id,ends_on','idx_enrollment_batch'=>'batch_id,starts_on'], 'class_days'=>['idx_class_scope'=>'institute_id,held_on'], 'attendance'=>['idx_attendance_student'=>'student_id'], 'fee_installments'=>['idx_installment_due'=>'due_on']] as $table=>$indexes) foreach($indexes as $index=>$columns) {
        $exists=$mysql?one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$index]):one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$index]);
        if(!$exists) db()->exec("CREATE INDEX $index ON $table ($columns)");
    }
    unset($GLOBALS['ops_ready']);
}
function operationsReady(): bool {
    if(isset($GLOBALS['ops_ready']))return $GLOBALS['ops_ready'];
    try { foreach(['teachers','batches','enrollments','class_days','attendance','attendance_revisions','fee_plans','fee_installments','fee_plan_revisions','staff_security'] as $t) query("SELECT id FROM $t WHERE 1=0"); return $GLOBALS['ops_ready']=true; }
    catch(PDOException $e){return $GLOBALS['ops_ready']=false;}
}
function opsGuard(): array { $u=requireRole(['owner','admin']);if(!operationsReady())fail('The owner must run the operations upgrade first.');return $u; }
function opsRecord(string $table,int $id): array {
    if(!in_array($table,['teachers','batches','class_days','fee_plans'],true))fail('Invalid operations record.');
    $r=one("SELECT * FROM $table WHERE id=?",[$id]);if(!$r)fail('Record not found.');instituteAccess((int)$r['institute_id']);return $r;
}
function exactMinor(string $amount): int {
    if(!preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/D',$amount,$m))fail('Enter a positive amount with at most two decimal places.');
    $minor=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($minor<=0)fail('Amount must be greater than zero.');return $minor;
}
function dateString(string $v): string {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d || $d->format('Y-m-d')!==$v)fail('Enter a valid date.');return $v;
}
function saveTeacher(): string {
    opsGuard();$iid=(int)input('institute_id');instituteAccess($iid);$id=(int)($_POST['id']??0);
    if($id && (int)opsRecord('teachers',$id)['institute_id']!==$iid)fail('Cannot move teacher records between institutes.');
    $email=input('email',200,false);if($email && !filter_var($email,FILTER_VALIDATE_EMAIL))fail('Enter a valid teacher email.');
    $v=[input('name',120),strtolower($email),input('phone',30),input('qualification',200,false),choice('active',['1','0'])];
    if($id)query('UPDATE teachers SET name=?,email=?,phone=?,qualification=?,active=? WHERE id=?',[...$v,$id]);
    else {query('INSERT INTO teachers (name,email,phone,qualification,active,institute_id) VALUES (?,?,?,?,?,?)',[...$v,$iid]);$id=(int)db()->lastInsertId();}
    audit('teacher_saved','teachers',$id);return 'teachers';
}
function saveBatch(): string {
    opsGuard();$iid=(int)input('institute_id');instituteAccess($iid);$id=(int)($_POST['id']??0);
    $cid=(int)input('course_id');if(!$id)courseAccess($cid,$iid);else { $course=record('courses',$cid);if((int)$course['institute_id']!==$iid)fail('Course belongs to a different institute.'); } $active=choice('active',['1','0']);$tid=(int)input('teacher_id');$teacher=opsRecord('teachers',$tid);
    if((int)$teacher['institute_id']!==$iid || (!$teacher['active'] && $active==='1'))fail('Select an active teacher from this institute.');
    $start=validDate('starts_on');$end=validDate('ends_on');if($end<$start)fail('Batch end date must be after its start date.');
    $capacity=input('capacity',4);if(!ctype_digit($capacity) || (int)$capacity<1 || (int)$capacity>500)fail('Batch capacity must be between 1 and 500.');
    $v=[input('name',120),input('room',100,false),$tid,(int)$capacity,$active];
    if($id) {
        $old=opsRecord('batches',$id);one('SELECT id FROM batches WHERE id=?'.lockSuffix(),[$id]);
        if((int)$old['institute_id']!==$iid || (int)$old['course_id']!==$cid || $old['starts_on']!==$start || $old['ends_on']!==$end)fail('Course, institute and dates cannot change on an existing batch. Create a new batch instead.');
        $members=rows('SELECT id FROM enrollments WHERE batch_id=? AND ends_on IS NULL'.lockSuffix(),[$id]);
        if(count($members)>(int)$capacity)fail('Capacity cannot be below the allocated student count.');
        query('UPDATE batches SET name=?,room=?,teacher_id=?,capacity=?,active=? WHERE id=?',[...$v,$id]);
    } else {query('INSERT INTO batches (name,room,teacher_id,capacity,active,institute_id,course_id,starts_on,ends_on) VALUES (?,?,?,?,?,?,?,?,?)',[...$v,$iid,$cid,$start,$end]);$id=(int)db()->lastInsertId();}
    audit('batch_saved','batches',$id);return 'batches';
}
function enrollStudent(): string {
    opsGuard();$sid=(int)input('student_id');$student=record('students',$sid);
    $student=one('SELECT * FROM students WHERE id=?'.lockSuffix(),[$sid]);
    $bid=(int)input('batch_id');$batch=opsRecord('batches',$bid);$batch=one('SELECT * FROM batches WHERE id=?'.lockSuffix(),[$bid]);
    if(!$batch['active'] || (int)$batch['institute_id']!==(int)$student['institute_id'] || (int)$batch['course_id']!==(int)$student['course_id'])fail('Choose an active batch for the student’s own institute and course.');
    $start=validDate('starts_on');
    if($start<$student['admission_date'] || $start<$batch['starts_on'] || $start>$batch['ends_on'] || $start>date('Y-m-d'))fail('Allocation date must be within the batch dates, after admission and no later than today.');
    $current=one('SELECT * FROM enrollments WHERE student_id=? AND ends_on IS NULL'.lockSuffix(),[$sid]);
    if($current && (int)$current['batch_id']===$bid)fail('Student is already allocated to this batch.');
    if($current && $start<=$current['starts_on'])fail('A transfer must be later than the previous allocation date.');
    $latest=one('SELECT ends_on last_end FROM enrollments WHERE student_id=? AND ends_on IS NOT NULL ORDER BY ends_on DESC LIMIT 1'.lockSuffix(),[$sid]);
    if($latest && $latest['last_end'] && $start<=$latest['last_end'])fail('Allocation cannot overlap prior batch history.');
    if(function_exists('servicesReady') && servicesReady()) {
        if(one('SELECT r.id FROM exam_results r JOIN exams x ON x.id=r.exam_id WHERE r.student_id=? AND x.held_on>=?'.lockSuffix(),[$sid,$start]))fail('Cannot change allocation across a frozen exam roster. Choose a later date.');
        if(one('SELECT id FROM exams WHERE batch_id=? AND held_on>=?'.lockSuffix(),[$bid,$start]))fail('Destination batch already has a frozen exam roster. Choose a later date.');
    }
    if(one('SELECT a.id FROM attendance a JOIN class_days c ON c.id=a.class_id WHERE a.student_id=? AND c.held_on>=?'.lockSuffix(),[$sid,$start]))fail('Cannot transfer or backdate an allocation across an existing attendance roster. Use a later date.');
    if(one('SELECT id FROM class_days WHERE batch_id=? AND held_on>=?'.lockSuffix(),[$bid,$start]))fail('This batch already has a roster on or after that date. Choose a date after its latest recorded class.');
    $members=rows('SELECT id FROM enrollments WHERE batch_id=? AND ends_on IS NULL'.lockSuffix(),[$bid]);
    if(count($members)>=(int)$batch['capacity'])fail('This batch is at capacity.');
    if($current)query('UPDATE enrollments SET ends_on=? WHERE id=?',[(new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d'),$current['id']]);
    query('INSERT INTO enrollments (institute_id,student_id,batch_id,starts_on) VALUES (?,?,?,?)',[$student['institute_id'],$sid,$bid,$start]);
    audit($current?'batch_transferred':'batch_allocated','students',$sid);return 'batches';
}
function openClassDay(): string {
    $u=opsGuard();$bid=(int)input('batch_id');opsRecord('batches',$bid);$b=one('SELECT * FROM batches WHERE id=?'.lockSuffix(),[$bid]);
    if(!$b['active'])fail('Cannot open attendance for an archived batch.');
    $date=validDate('held_on');if($date<$b['starts_on'] || $date>$b['ends_on'] || $date>date('Y-m-d'))fail('Class date must be within the batch dates and not in the future.');
    if(one('SELECT id FROM class_days WHERE batch_id=? AND held_on=?',[$bid,$date]))fail('A class record already exists for this batch and day. Open it to edit attendance.');
    $members=rows('SELECT student_id FROM enrollments WHERE batch_id=? AND starts_on<=? AND (ends_on IS NULL OR ends_on>=?)'.lockSuffix(),[$bid,$date,$date]);
    if(!$members)fail('No eligible students are allocated for this class date.');
    if(count($members)>500)fail('This historical roster exceeds 500 students. Ask your administrator to review allocation dates before opening this class.');
    $teacher=one('SELECT name,active FROM teachers WHERE id=?',[$b['teacher_id']]);
    if(!$teacher || !$teacher['active'])fail('Assign an active teacher to the batch before opening a class.');
    query('INSERT INTO class_days (institute_id,batch_id,held_on,topic,teacher_name,recorded_by,updated_at) VALUES (?,?,?,?,?,?,?)',[$b['institute_id'],$bid,$date,input('topic',200),$teacher['name'],$u['id'],date('Y-m-d H:i:s')]);
    $id=(int)db()->lastInsertId();foreach($members as $m)query('INSERT INTO attendance (class_id,student_id) VALUES (?,?)',[$id,$m['student_id']]);
    audit('class_opened','class_days',$id);$_SESSION['flash']='Class roster created. Open the class below and mark every student before finalizing.';return 'attendance';
}
function saveAttendance(): string {
    $u=opsGuard();$id=(int)input('class_id');opsRecord('class_days',$id);$class=one('SELECT * FROM class_days WHERE id=?'.lockSuffix(),[$id]);
    if((int)input('version')!==(int)$class['version'])fail('Attendance changed in another window. Reload before saving.');
    $statuses=$_POST['attendance']??null;if(!is_array($statuses) || count($statuses)>500)fail('Invalid attendance list.');
    $roster=rows('SELECT student_id,status FROM attendance WHERE class_id=? ORDER BY student_id',[$id]);
    if(count($roster)!==count($statuses))fail('Mark exactly the students in this saved roster.');
    foreach($roster as $r) if(!isset($statuses[$r['student_id']]) || !is_string($statuses[$r['student_id']]) || !in_array($statuses[$r['student_id']],['Present','Absent','Late','Excused'],true))fail('Choose Present, Absent, Late or Excused for every student.');
    $reason=input('reason',500,(bool)$class['finalized']);
    $snapshot=['before'=>$roster,'after'=>$statuses,'previous_version'=>(int)$class['version']];
    query('INSERT INTO attendance_revisions (class_id,recorded_by,reason,snapshot_json,created_at) VALUES (?,?,?,?,?)',[$id,$u['id'],$reason?:'Initial attendance',json_encode($snapshot,JSON_THROW_ON_ERROR),date('Y-m-d H:i:s')]);
    foreach($roster as $r)query('UPDATE attendance SET status=? WHERE class_id=? AND student_id=?',[$statuses[$r['student_id']],$id,$r['student_id']]);
    query('UPDATE class_days SET finalized=1,version=version+1,recorded_by=?,updated_at=? WHERE id=?',[$u['id'],date('Y-m-d H:i:s'),$id]);
    audit($class['finalized']?'attendance_corrected':'attendance_finalized','class_days',$id);return 'attendance';
}
function saveFeePlan(): string {
    $u=opsGuard();$sid=(int)input('student_id');record('students',$sid);$s=one('SELECT * FROM students WHERE id=?'.lockSuffix(),[$sid]);
    $old=one('SELECT * FROM fee_plans WHERE student_id=?'.lockSuffix(),[$sid]);
    if((int)$s['fee_minor']<=0)fail('This admission has no payable course fee; an installment plan is not needed.');
    if((int)input('version')!==(int)($old['version']??0))fail('Fee plan changed in another window. Reload before saving.');
    $dates=$_POST['due_on']??[];$amounts=$_POST['installment_amount']??[];
    if(!is_array($dates)||!is_array($amounts)||count($dates)!==count($amounts)||count($dates)>36)fail('Use between 1 and 36 installment rows.');
    $items=[];$total=0;foreach($dates as $k=>$date){
        $amount=$amounts[$k]??null;if(!is_string($date)||!is_string($amount))fail('Invalid installment field.');$date=trim($date);$amount=trim($amount);
        if($date===''&&$amount==='')continue;
        dateString($date);if($date<$s['admission_date'])fail('Installment dates cannot be before admission.');
        if(isset($items[$date]))fail('Use a unique date for each installment.');
        $items[$date]=exactMinor($amount);$total+=$items[$date];
    }
    if(!$items || $total!==(int)$s['fee_minor'])fail('The installment total must exactly equal the agreed admission course fee, including amounts already paid.');
    ksort($items);$reason=input('reason',500,true);$now=date('Y-m-d H:i:s');
    $previous=$old?rows('SELECT due_on,amount_minor FROM fee_installments WHERE plan_id=? ORDER BY due_on'.lockSuffix(),[$old['id']]):[];
    if($old){$pid=(int)$old['id'];query('DELETE FROM fee_installments WHERE plan_id=?',[$pid]);query('UPDATE fee_plans SET version=version+1,updated_at=? WHERE id=?',[$now,$pid]);}
    else{query('INSERT INTO fee_plans (institute_id,student_id,updated_at) VALUES (?,?,?)',[$s['institute_id'],$sid,$now]);$pid=(int)db()->lastInsertId();}
    foreach($items as $date=>$minor)query('INSERT INTO fee_installments (plan_id,due_on,amount_minor) VALUES (?,?,?)',[$pid,$date,$minor]);
    query('INSERT INTO fee_plan_revisions (plan_id,recorded_by,reason,snapshot_json,created_at) VALUES (?,?,?,?,?)',[$pid,$u['id'],$reason,json_encode(['before'=>$previous,'after'=>$items],JSON_THROW_ON_ERROR),$now]);
    audit('fee_plan_saved','fee_plans',$pid);return 'fee-plans';
}
/** Allocate recorded payments to earliest installments; no ledger rows are rewritten. */
function studentInstallments(int $sid): array {
    $paid=(int)query('SELECT COALESCE(SUM(amount_minor),0) FROM payments WHERE student_id=?',[$sid])->fetchColumn();
    $items=rows('SELECT f.* FROM fee_installments f JOIN fee_plans p ON p.id=f.plan_id WHERE p.student_id=? ORDER BY f.due_on,f.id',[$sid]);
    foreach($items as &$item){$covered=min($paid,(int)$item['amount_minor']);$paid-=$covered;$item['paid_minor']=$covered;$item['balance_minor']=(int)$item['amount_minor']-$covered;$item['status']=$item['balance_minor']===0?'Paid':($item['due_on']<date('Y-m-d')?'Overdue':($item['due_on']===date('Y-m-d')?'Due today':'Upcoming'));}unset($item);return $items;
}
function feeReportRows(int $iid,int $limit=100,int $offset=0): array {
    $where=$iid?'s.institute_id=?':'1=1';$args=$iid?[$iid]:[];
    return rows("SELECT s.id,s.name,s.email,s.phone,s.institute_id,s.fee_minor,i.name institute_name,c.name course_name,
      COALESCE((SELECT SUM(amount_minor) FROM payments x WHERE x.student_id=s.id),0) paid_minor,
      COALESCE((SELECT SUM(f.amount_minor) FROM fee_installments f JOIN fee_plans p ON p.id=f.plan_id WHERE p.student_id=s.id AND f.due_on<?),0) scheduled_before_today,
      (SELECT id FROM fee_plans p WHERE p.student_id=s.id) plan_id
      FROM students s JOIN institutes i ON i.id=s.institute_id JOIN courses c ON c.id=s.course_id WHERE $where ORDER BY s.id DESC LIMIT ".min(5001,max(1,$limit)).' OFFSET '.max(0,$offset),[date('Y-m-d'),...$args]);
}
function bumpStaffVersion(int $id): void {
    one('SELECT id FROM users WHERE id=?'.lockSuffix(),[$id]);
    if(one('SELECT id FROM staff_security WHERE id=?'.lockSuffix(),[$id]))query('UPDATE staff_security SET version=version+1 WHERE id=?',[$id]);
    else query('INSERT INTO staff_security (id,version) VALUES (?,1)',[$id]);
}
function revokeStaffSessions(): string {
    $u=requireRole(['owner','admin','counsellor']);if(!operationsReady())fail('Upgrade required before revoking sessions.');
    $id=(int)input('user_id');$target=one('SELECT * FROM users WHERE id=?',[$id]);if(!$target)fail('Account not found.');
    if($id!==(int)$u['id']){
        requireRole(['owner','admin']);if($target['role']==='owner')fail('Cannot revoke another owner here.');instituteAccess((int)$target['institute_id']);
        if($u['role']==='admin'&&$target['role']!=='counsellor')fail('Only the owner can manage administrators.');
    }
    bumpStaffVersion($id);query('UPDATE otp_challenges SET consumed=1 WHERE user_id=?',[$id]);audit('sessions_revoked','users',$id);
    if($id===(int)$u['id']){unset($_SESSION['uid'],$_SESSION['staff_stamp']);session_regenerate_id(true);return 'login';}
    return 'staff';
}
