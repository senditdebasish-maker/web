<?php
declare(strict_types=1);
require_once __DIR__.'/mail.php';
require_once __DIR__.'/documents.php';
require_once __DIR__.'/portal.php';
function migrateApplications(): void {
    $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';$json=$mysql?'MEDIUMTEXT':'TEXT';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach([
        'admission_listings'=>"id INTEGER PRIMARY KEY, description TEXT NOT NULL, eligibility TEXT NOT NULL, privacy_notice TEXT NOT NULL, opens_on VARCHAR(10) NOT NULL, closes_on VARCHAR(10) NOT NULL, accepting INTEGER NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(id) REFERENCES courses(id)",
        'applicant_accounts'=>"id $id, email VARCHAR(200) NOT NULL UNIQUE, active INTEGER NOT NULL DEFAULT 1, version INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(19) NOT NULL",
        'applicant_codes'=>"id VARCHAR(64) PRIMARY KEY, email_hash VARCHAR(64) NOT NULL, code_hash VARCHAR(64) NOT NULL, account_version INTEGER NOT NULL, expires_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, consumed INTEGER NOT NULL DEFAULT 0",
        'admission_applications'=>"id $id, applicant_id INTEGER NOT NULL, institute_id INTEGER NOT NULL, course_id INTEGER NOT NULL, reference VARCHAR(32) NOT NULL UNIQUE, request_key VARCHAR(64) NOT NULL UNIQUE, status VARCHAR(30) NOT NULL DEFAULT 'Submitted', version INTEGER NOT NULL DEFAULT 1, fee_minor INTEGER NOT NULL, data_json $json NOT NULL, consent_notice TEXT NOT NULL, consent_version VARCHAR(50) NOT NULL, submitted_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, student_id INTEGER NULL UNIQUE, FOREIGN KEY(applicant_id) REFERENCES applicant_accounts(id), FOREIGN KEY(institute_id) REFERENCES institutes(id), FOREIGN KEY(course_id) REFERENCES courses(id), FOREIGN KEY(student_id) REFERENCES students(id)",
        'application_events'=>"id $id, application_id INTEGER NOT NULL, actor VARCHAR(20) NOT NULL, staff_id INTEGER NULL, message TEXT NOT NULL, snapshot_json $json NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(application_id) REFERENCES admission_applications(id), FOREIGN KEY(staff_id) REFERENCES users(id)"
    ] as $t=>$def)db()->exec("CREATE TABLE IF NOT EXISTS $t ($def)$suffix");
    foreach([['applicant_codes','idx_applicant_code_email','email_hash'],['admission_applications','idx_application_account','applicant_id,status'],['admission_applications','idx_application_scope','institute_id,status'],['application_events','idx_application_history','application_id,id']] as [$t,$idx,$cols]){
        $found=$mysql?one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$t,$idx]):one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$idx]);if(!$found)db()->exec("CREATE INDEX $idx ON $t ($cols)");
    }unset($GLOBALS['applications_ready']);
}
function applicationsReady(): bool {
    if(isset($GLOBALS['applications_ready']))return $GLOBALS['applications_ready'];
    try{foreach(['admission_listings','applicant_accounts','applicant_codes','admission_applications','application_events'] as $t)query("SELECT id FROM $t WHERE 1=0");return $GLOBALS['applications_ready']=true;}catch(PDOException $e){return $GLOBALS['applications_ready']=false;}
}
function applicationStaff(): array {$u=requireRole(['owner','admin']);if(!applicationsReady())fail('Public admissions upgrade required.');return $u;}
function publicCourses(int $offset=0,?int $id=null): array {
    $today=date('Y-m-d');return rows('SELECT c.id,c.name,c.duration,c.fee_minor,c.institute_id,i.name institute_name,i.kind,i.city,i.phone,l.description,l.eligibility,l.privacy_notice,l.opens_on,l.closes_on,l.version listing_version FROM admission_listings l JOIN courses c ON c.id=l.id JOIN institutes i ON i.id=c.institute_id WHERE l.accepting=1 AND c.active=1 AND l.opens_on<=? AND l.closes_on>=?'.($id!==null?' AND c.id=?':'').' ORDER BY i.name,c.name,c.id LIMIT 21 OFFSET '.max(0,$offset),$id!==null?[$today,$today,$id]:[$today,$today]);
}
function saveAdmissionListing(): string {
    applicationStaff();$cid=(int)input('course_id');record('courses',$cid);one('SELECT id FROM courses WHERE id=?'.lockSuffix(),[$cid]);
    $old=one('SELECT * FROM admission_listings WHERE id=?'.lockSuffix(),[$cid]);if((int)input('version')!==(int)($old['version']??0))fail('Listing changed in another window. Reload.');
    $open=validDate('opens_on');$close=validDate('closes_on');if($close<$open)fail('Closing date cannot precede opening date.');
    $v=[input('description',3000),input('eligibility',3000),input('privacy_notice',3000),$open,$close,choice('accepting',['0','1'])];
    if($old)query('UPDATE admission_listings SET description=?,eligibility=?,privacy_notice=?,opens_on=?,closes_on=?,accepting=?,version=version+1 WHERE id=?',[...$v,$cid]);
    else query('INSERT INTO admission_listings (description,eligibility,privacy_notice,opens_on,closes_on,accepting,id) VALUES (?,?,?,?,?,?,?)',[...$v,$cid]);
    audit('admission_listing_saved','courses',$cid);return 'applications';
}
function currentApplicant(): ?array {
    global $config;
    if(!isset($_SESSION['applicant_id'],$_SESSION['applicant_version'],$_SESSION['applicant_email'],$_SESSION['applicant_site']) || !hash_equals(hash('sha256',__DIR__.$config['dsn']),$_SESSION['applicant_site']))return null;
    $a=one('SELECT * FROM applicant_accounts WHERE id=? AND active=1',[$_SESSION['applicant_id']]);return $a && $a['email']===$_SESSION['applicant_email'] && (int)$a['version']===(int)$_SESSION['applicant_version']?$a:null;
}
function requestApplicantCode(): void {
    $email=emailInput();if(input('website',200,false)!=='')fail('Unable to process this request.');
    try{mailSettings();dependencies();}catch(Throwable $e){fail('Email verification is not configured. Contact the institute.');}
    $now=time();$hash=hash('sha256','applicant:'.$email);$ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$code=(string)random_int(100000,999999);$id=bin2hex(random_bytes(32));$secret=bin2hex(random_bytes(32));
    writeTransaction();try{
        query('DELETE FROM auth_events WHERE attempted_at<?',[$now-86400]);query('DELETE FROM applicant_codes WHERE expires_at<?',[$now-86400]);
        if(one("SELECT id FROM auth_events WHERE kind='request' AND ((identity_hash=? AND attempted_at>?) OR (ip_hash=? AND attempted_at>?))",[$hash,$now-60,$ip,$now-3]) || (int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND identity_hash=? AND attempted_at>?",[$hash,$now-900])->fetchColumn()>=5 || (int)query("SELECT COUNT(*) FROM auth_events WHERE kind='request' AND ip_hash=? AND attempted_at>?",[$ip,$now-900])->fetchColumn()>=20)fail('Please wait 60 seconds before resending, or 15 minutes after reaching the limit.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'request',?)",[$hash,$ip,$now]);
        $a=one('SELECT * FROM applicant_accounts WHERE email=?',[$email]);
        $deliver=$a?(bool)$a['active']:(bool)publicCourses();
        query('UPDATE applicant_codes SET consumed=1 WHERE email_hash=?',[$hash]);
        query('INSERT INTO applicant_codes (id,email_hash,code_hash,account_version,expires_at,consumed) VALUES (?,?,?,?,?,?)',[$id,$hash,hash_hmac('sha256',$code,$secret),(int)($a['version']??0),$now+300,$deliver?0:1]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    unset($_SESSION['applicant_id'],$_SESSION['applicant_version']);$_SESSION['applicant_pending']=['id'=>$id,'email'=>$email,'secret'=>$secret];
    if($deliver)try{sendMail($email,'Your Northstar application sign-in code',"Your application sign-in code is: $code\n\nIt expires in five minutes and works only in the requesting browser. Never share it. This is not an admission approval. Ignore this message if you did not request it.");}catch(Throwable $e){query('UPDATE applicant_codes SET consumed=1 WHERE id=?',[$id]);error_log('Northstar applicant code delivery failed.');}
    $_SESSION['flash']='If applications are open or you have an enabled applicant account, a code has been sent. Check your inbox and spam folder. No admission is granted by verifying email.';
}
function verifyApplicantCode(): void {
    $pending=$_SESSION['applicant_pending']??null;if(!$pending)fail('Request an applicant code first.');$code=input('code',20);$ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$account=null;
    writeTransaction();try{
        $row=one('SELECT * FROM applicant_codes WHERE id=?'.lockSuffix(),[$pending['id']]);
        if((int)query("SELECT COUNT(*) FROM auth_events WHERE kind='verify' AND ip_hash=? AND attempted_at>?",[$ip,time()-900])->fetchColumn()>=30)fail('Too many attempts. Try again in 15 minutes.');
        query("INSERT INTO auth_events (identity_hash,ip_hash,kind,attempted_at) VALUES (?,?,'verify',?)",[hash('sha256','applicant:'.$pending['email']),$ip,time()]);
        if($row && !$row['consumed'] && (int)$row['expires_at']>time() && (int)$row['attempts']<5){
            query('UPDATE applicant_codes SET attempts=attempts+1 WHERE id=?',[$row['id']]);
            if(preg_match('/^\d{6}$/D',$code) && hash_equals($row['code_hash'],hash_hmac('sha256',$code,$pending['secret']))){
                $account=one('SELECT * FROM applicant_accounts WHERE email=?'.lockSuffix(),[$pending['email']]);
                if(!$account && (int)$row['account_version']===0){query('INSERT INTO applicant_accounts (email,created_at) VALUES (?,?)',[$pending['email'],date('Y-m-d H:i:s')]);$account=one('SELECT * FROM applicant_accounts WHERE id=?',[(int)db()->lastInsertId()]);}
                elseif(!$account || !$account['active'] || (int)$account['version']!==(int)$row['account_version'])$account=null;
                if($account)query('UPDATE applicant_codes SET consumed=1 WHERE id=?',[$row['id']]);
            }
        }db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    if(!$account)fail('Invalid, expired or exhausted applicant code. Request a new code if needed.');
    global $config;session_regenerate_id(true);unset($_SESSION['applicant_pending']);$_SESSION['applicant_email']=$account['email'];$_SESSION['applicant_site']=hash('sha256',__DIR__.$config['dsn']);$_SESSION['applicant_id']=(int)$account['id'];$_SESSION['applicant_version']=(int)$account['version'];$_SESSION['csrf']=bin2hex(random_bytes(32));
}
function ownApplication(int $id,array $actor): array {$r=one('SELECT * FROM admission_applications WHERE id=? AND applicant_id=?',[$id,$actor['id']]);if(!$r)fail('Application not accessible.');return $r;}
function staffApplication(int $id): array {applicationStaff();$r=one('SELECT * FROM admission_applications WHERE id=?',[$id]);if(!$r)fail('Application not found.');instituteAccess((int)$r['institute_id']);return $r;}
function applicationEvent(int $id,string $actor,?int $uid,string $message,array $snapshot): void {query('INSERT INTO application_events (application_id,actor,staff_id,message,snapshot_json,created_at) VALUES (?,?,?,?,?,?)',[$id,$actor,$uid,$message,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),date('Y-m-d H:i:s')]);if(function_exists('queueApplicationMail'))queueApplicationMail((int)db()->lastInsertId(),$id);}
function applicationFields(): array {
    $year=input('completion_year',4);if(!ctype_digit($year)||(int)$year<1950||(int)$year>(int)date('Y'))fail('Enter a valid completed qualification year.');
    $phone=input('phone',30);if(!preg_match('/^[+0-9 ()\-]{7,30}$/D',$phone))fail('Enter a valid contact phone number.');
    if(input('consent',3,false)!=='yes')fail('Confirm the application declaration and privacy notice.');
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
    $father=input('father_name',120,false);if($father==='')fail("Enter your father's name.");
    $mother=input('mother_name',120,false);if($mother==='')fail("Enter your mother's name.");
    $dob=input('date_of_birth',10,false);if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$dob)||$dob>date('Y-m-d'))fail('Enter a valid date of birth.');
    $gender=input('gender',30,false);if(!in_array($gender,['Male','Female','Other'],true))fail('Select your gender.');
    $category=input('category',40,false);if(!in_array($category,['General','EWS','OBC-A','OBC-B','SC','ST','Other'],true))fail('Select your category.');
    $nationality=input('nationality',60,false);if(!in_array($nationality,['Indian','Other'],true))fail('Select your nationality.');
    $aadhaar=input('aadhaar',12,false);if($aadhaar!==''&&!preg_match('/^\\d{12}$/D',$aadhaar))fail('Enter a valid 12-digit Aadhaar number, or leave it blank.');
    $religion=input('religion',30,false);if(!in_array($religion,['','Hinduism','Islam','Christianity','Sikhism','Buddhism','Jainism','Other'],true))fail('Select a valid religion.');
    $blood=input('blood_group',10,false);if(!in_array($blood,['','A+','A-','B+','B-','AB+','AB-','O+','O-'],true))fail('Select a valid blood group.');
    $academic=[];
    foreach(['10','12'] as $level){
        $label=$level==='10'?'Class 10':'Class 12';
        $board=input('board_'.$level,120,false);if($board==='')fail('Enter your '.$label.' board.');
        $y=input('year_'.$level,4,false);if(!ctype_digit($y)||(int)$y<1950||(int)$y>(int)date('Y'))fail('Enter a valid '.$label.' passing year.');
        $roll=input('roll_'.$level,60,false);if($roll==='')fail('Enter your '.$label.' roll number.');
        $total=input('total_'.$level,7,false);$obtained=input('obtained_'.$level,7,false);
        if(!ctype_digit($total)||(int)$total<=0)fail('Enter valid '.$label.' total marks.');
        if(!preg_match('/^\\d{1,7}$/D',$obtained)||(int)$obtained>(int)$total)fail('Enter valid '.$label.' obtained marks (not above the total).');
        $academic+=['board_'.$level=>$board,'year_'.$level=>$y,'roll_'.$level=>$roll,'total_'.$level=>(string)(int)$total,'obtained_'.$level=>(string)(int)$obtained,'percentage_'.$level=>number_format(((int)$obtained/(int)$total)*100,2,'.','')];
        if($level==='12'){$stream=input('stream_12',60,false);if($stream==='')fail('Enter your Class 12 stream.');$academic['stream_12']=$stream;}
    }
=======
>>>>>>> parent of 35cbb61 (Application form becomes a 4-step wizard (personal, academic, documents, preview))
    $optional=[];
    foreach(['gender'=>30,'date_of_birth'=>10,'nationality'=>60,'category'=>40,'father_name'=>120,'mother_name'=>120,'guardian_name'=>120,'address'=>300,'state'=>100,'pincode'=>10,'board_university'=>160,'percentage'=>20,'entrance_exam'=>100,'entrance_rank'=>30] as $key=>$max) $optional[$key]=input($key,$max,false);
    if($optional['date_of_birth']!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$optional['date_of_birth']) || $optional['date_of_birth']>date('Y-m-d'))) fail('Enter a valid date of birth.');
    if($optional['pincode']!=='' && !preg_match('/^\d{6}$/D',$optional['pincode'])) fail('Enter a valid six-digit PIN code.');
    return ['name'=>input('name',120),'phone'=>$phone,'city'=>input('city',100),'qualification'=>input('qualification',300),'completion_year'=>$year,...$optional,'note'=>input('note',1500,false)];
}
function applicationDraftFields(): array {
    $allowed=['name','phone','city','qualification','completion_year','gender','date_of_birth','nationality','category','father_name','mother_name','guardian_name','address','state','pincode','board_university','percentage','entrance_exam','entrance_rank','note'];
    $data=[];foreach($allowed as $key){$value=$_POST[$key]??'';if(!is_string($value))fail('Invalid application field.');$data[$key]=trim(substr($value,0,match($key){'name','father_name','mother_name','guardian_name'=>120,'phone'=>30,'city'=>100,'qualification'=>300,'note'=>1500,'address'=>300,default=>200}));}return $data;
}
function applicantMutation(string $action): int {
    $actor=currentApplicant();if(!$actor)fail('Verify your applicant email first.');
    if($action==='submit_application' && ($_POST['submit_action'] ?? 'submit_final')==='save_draft') $action='save_application_draft';
=======
    return ['name'=>input('name',120),'phone'=>$phone,'city'=>input('city',100),'qualification'=>input('qualification',300),'completion_year'=>$year,'note'=>input('note',1500,false)];
}
function applicantMutation(string $action): int {
    $actor=currentApplicant();if(!$actor)fail('Verify your applicant email first.');
>>>>>>> parent of 549483e (new)
=======
    return ['name'=>input('name',120),'phone'=>$phone,'city'=>input('city',100),'qualification'=>input('qualification',300),'completion_year'=>$year,'note'=>input('note',1500,false)];
}
function applicantMutation(string $action): int {
    $actor=currentApplicant();if(!$actor)fail('Verify your applicant email first.');
>>>>>>> parent of 549483e (new)
=======
    return ['name'=>input('name',120),'phone'=>$phone,'city'=>input('city',100),'qualification'=>input('qualification',300),'completion_year'=>$year,'note'=>input('note',1500,false)];
}
function applicantMutation(string $action): int {
    $actor=currentApplicant();if(!$actor)fail('Verify your applicant email first.');
>>>>>>> parent of ba104b3 (new)
=======
    return ['name'=>input('name',120),'phone'=>$phone,'city'=>input('city',100),'qualification'=>input('qualification',300),'completion_year'=>$year,'note'=>input('note',1500,false)];
}
function applicantMutation(string $action): int {
    $actor=currentApplicant();if(!$actor)fail('Verify your applicant email first.');
>>>>>>> parent of ba104b3 (new)
=======
    return ['name'=>input('name',120),'phone'=>$phone,'city'=>input('city',100),'qualification'=>input('qualification',300),'completion_year'=>$year,'note'=>input('note',1500,false)];
}
function applicantMutation(string $action): int {
    $actor=currentApplicant();if(!$actor)fail('Verify your applicant email first.');
>>>>>>> parent of ba104b3 (new)
    writeTransaction();try{
        $locked=one('SELECT * FROM applicant_accounts WHERE id=?'.lockSuffix(),[$actor['id']]);if(!$locked['active']||(int)$locked['version']!==(int)$_SESSION['applicant_version'])fail('Applicant access is no longer active.');
        if($action==='submit_application'){
            $key=input('request_key',64);$existing=one('SELECT * FROM admission_applications WHERE request_key=?',[$key]);
            if($existing){if((int)$existing['applicant_id']!==(int)$actor['id'])fail('Invalid submission key.');db()->commit();$_SESSION['flash']='This submission was already received. No new application was created.';return (int)$existing['id'];}
            if(!isset($_SESSION['application_nonce'])||!hash_equals($_SESSION['application_nonce'],$key))fail('Application form expired. Reload before submitting.');
            if(input('website',200,false)!=='')fail('Unable to submit application.');
            if(one("SELECT id FROM admission_applications WHERE applicant_id=? AND status IN ('Submitted','Under review','Changes requested','Admitted')",[$actor['id']]))fail('You already have an active or admitted application. Open My applications instead.');
            if(one('SELECT id FROM students WHERE LOWER(email)=?',[$actor['email']]))fail('This email is already linked to a student record. Use the student portal or contact the office.');
            if((int)query('SELECT COUNT(*) FROM admission_applications WHERE applicant_id=? AND submitted_at>=?',[$actor['id'],date('Y-m-d H:i:s',time()-86400)])->fetchColumn()>=5)fail('Application submission limit reached. Try again tomorrow.');
            $cid=(int)input('course_id');one('SELECT id FROM courses WHERE id=?'.lockSuffix(),[$cid]);one('SELECT id FROM admission_listings WHERE id=?'.lockSuffix(),[$cid]);$course=publicCourses(0,$cid)[0]??null;if(!$course)fail('This course is not currently accepting applications.');
            if(!hash_equals(hash('sha256',json_encode($course,JSON_THROW_ON_ERROR)),input('offer_token',64)))fail('Course details or fee changed. Reload and review the offer before applying.');
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
            $data=($action==='save_application_draft'?applicationDraftFields():applicationFields())+['course_name'=>$course['name'],'institute_name'=>$course['institute_name'],'duration'=>$course['duration']];$now=date('Y-m-d H:i:s');$status=$action==='save_application_draft'?'Draft':'Pending Review';
            if($existing){$id=(int)$existing['id'];query('UPDATE admission_applications SET status=?,data_json=?,consent_notice=?,updated_at=?,revision_notes=\'\' WHERE id=?',[$status,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$course['privacy_notice'],$now,$id]);}
            else {query('INSERT INTO admission_applications (applicant_id,institute_id,course_id,reference,request_key,status,fee_minor,data_json,consent_notice,consent_version,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$actor['id'],$course['institute_id'],$cid,'APP-'.strtoupper(bin2hex(random_bytes(8))),$key,$status,$course['fee_minor'],json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$course['privacy_notice'],'admission-application-v1',$now,$now]);$id=(int)db()->lastInsertId();}
<<<<<<< HEAD
            applicationEvent($id,'Applicant',null,$action==='save_application_draft'?'Application draft saved.':'Application submitted for review.',['data'=>$data,'status'=>$status,'fee_minor'=>(int)$course['fee_minor'],$action=>$action],$action!=='save_application_draft');$wizardStored=attachWizardFiles($id);foreach($wizardStored as $stored)$wizardPaths[]=$stored['path'];if($wizardStored)applicationEvent($id,'Applicant',null,count($wizardStored).' document(s) attached with the application.',['files'=>array_column($wizardStored,'label')],false);if($action==='submit_application')$_SESSION['application_nonce']=bin2hex(random_bytes(24));
=======
            $data=applicationFields()+['course_name'=>$course['name'],'institute_name'=>$course['institute_name'],'duration'=>$course['duration']];$now=date('Y-m-d H:i:s');
            query('INSERT INTO admission_applications (applicant_id,institute_id,course_id,reference,request_key,fee_minor,data_json,consent_notice,consent_version,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$actor['id'],$course['institute_id'],$cid,'APP-'.strtoupper(bin2hex(random_bytes(8))),$key,$course['fee_minor'],json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$course['privacy_notice'],'admission-application-v1',$now,$now]);$id=(int)db()->lastInsertId();
            applicationEvent($id,'Applicant',null,'Application submitted.',['data'=>$data,'fee_minor'=>(int)$course['fee_minor']]);$_SESSION['application_nonce']=bin2hex(random_bytes(24));
>>>>>>> parent of 549483e (new)
=======
            $data=applicationFields()+['course_name'=>$course['name'],'institute_name'=>$course['institute_name'],'duration'=>$course['duration']];$now=date('Y-m-d H:i:s');
            query('INSERT INTO admission_applications (applicant_id,institute_id,course_id,reference,request_key,fee_minor,data_json,consent_notice,consent_version,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$actor['id'],$course['institute_id'],$cid,'APP-'.strtoupper(bin2hex(random_bytes(8))),$key,$course['fee_minor'],json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$course['privacy_notice'],'admission-application-v1',$now,$now]);$id=(int)db()->lastInsertId();
            applicationEvent($id,'Applicant',null,'Application submitted.',['data'=>$data,'fee_minor'=>(int)$course['fee_minor']]);$_SESSION['application_nonce']=bin2hex(random_bytes(24));
>>>>>>> parent of 549483e (new)
=======
            applicationEvent($id,'Applicant',null,$action==='save_application_draft'?'Application draft saved.':'Application submitted for review.',['data'=>$data,'status'=>$status,'fee_minor'=>(int)$course['fee_minor'],$action=>$action],$action!=='save_application_draft');if($action==='submit_application')$_SESSION['application_nonce']=bin2hex(random_bytes(24));
>>>>>>> parent of 35cbb61 (Application form becomes a 4-step wizard (personal, academic, documents, preview))
=======
            $data=applicationFields()+['course_name'=>$course['name'],'institute_name'=>$course['institute_name'],'duration'=>$course['duration']];$now=date('Y-m-d H:i:s');
            query('INSERT INTO admission_applications (applicant_id,institute_id,course_id,reference,request_key,fee_minor,data_json,consent_notice,consent_version,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$actor['id'],$course['institute_id'],$cid,'APP-'.strtoupper(bin2hex(random_bytes(8))),$key,$course['fee_minor'],json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$course['privacy_notice'],'admission-application-v1',$now,$now]);$id=(int)db()->lastInsertId();
            applicationEvent($id,'Applicant',null,'Application submitted.',['data'=>$data,'fee_minor'=>(int)$course['fee_minor']]);$_SESSION['application_nonce']=bin2hex(random_bytes(24));
>>>>>>> parent of ba104b3 (new)
=======
            $data=applicationFields()+['course_name'=>$course['name'],'institute_name'=>$course['institute_name'],'duration'=>$course['duration']];$now=date('Y-m-d H:i:s');
            query('INSERT INTO admission_applications (applicant_id,institute_id,course_id,reference,request_key,fee_minor,data_json,consent_notice,consent_version,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$actor['id'],$course['institute_id'],$cid,'APP-'.strtoupper(bin2hex(random_bytes(8))),$key,$course['fee_minor'],json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$course['privacy_notice'],'admission-application-v1',$now,$now]);$id=(int)db()->lastInsertId();
            applicationEvent($id,'Applicant',null,'Application submitted.',['data'=>$data,'fee_minor'=>(int)$course['fee_minor']]);$_SESSION['application_nonce']=bin2hex(random_bytes(24));
>>>>>>> parent of ba104b3 (new)
=======
            $data=applicationFields()+['course_name'=>$course['name'],'institute_name'=>$course['institute_name'],'duration'=>$course['duration']];$now=date('Y-m-d H:i:s');
            query('INSERT INTO admission_applications (applicant_id,institute_id,course_id,reference,request_key,fee_minor,data_json,consent_notice,consent_version,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$actor['id'],$course['institute_id'],$cid,'APP-'.strtoupper(bin2hex(random_bytes(8))),$key,$course['fee_minor'],json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$course['privacy_notice'],'admission-application-v1',$now,$now]);$id=(int)db()->lastInsertId();
            applicationEvent($id,'Applicant',null,'Application submitted.',['data'=>$data,'fee_minor'=>(int)$course['fee_minor']]);$_SESSION['application_nonce']=bin2hex(random_bytes(24));
>>>>>>> parent of ba104b3 (new)
        }else{
            $r=ownApplication((int)input('application_id'),$actor);$r=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[$r['id']]);$id=(int)$r['id'];
            if((int)input('version')!==(int)$r['version'])fail('Application changed in another window. Reload.');
            if(in_array($r['status'],['Admitted','Rejected','Withdrawn'],true))fail('This application is closed to applicant changes.');
            if($action==='withdraw_application'){$status='Withdrawn';$data=json_decode($r['data_json'],true,512,JSON_THROW_ON_ERROR);$message='Application withdrawn by applicant. '.input('reason',500);}
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
            else{if(!in_array($r['status'],['Changes requested','Revision'],true))fail('The office must request corrections before you edit a submitted application.');$data=array_replace(json_decode($r['data_json'],true,512,JSON_THROW_ON_ERROR),applicationFields());$status='Pending Review';$message='Corrected application resubmitted.';}
<<<<<<< HEAD
            query('UPDATE admission_applications SET status=?,data_json=?,version=version+1,updated_at=? WHERE id=?',[$status,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),date('Y-m-d H:i:s'),$id]);applicationEvent($id,'Applicant',null,$message,['before'=>json_decode($r['data_json'],true),'after'=>$data,'status'=>$status]);if($action!=='withdraw_application'){$wizardStored=attachWizardFiles($id);foreach($wizardStored as $stored)$wizardPaths[]=$stored['path'];if($wizardStored)applicationEvent($id,'Applicant',null,count($wizardStored).' document(s) attached with the corrected application.',['files'=>array_column($wizardStored,'label')],false);}
=======
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
            else{if($r['status']!=='Changes requested')fail('The office must request corrections before you edit a submitted application.');$data=array_replace(json_decode($r['data_json'],true,512,JSON_THROW_ON_ERROR),applicationFields());$status='Submitted';$message='Corrected application resubmitted.';}
            query('UPDATE admission_applications SET status=?,data_json=?,version=version+1,updated_at=? WHERE id=?',[$status,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),date('Y-m-d H:i:s'),$id]);applicationEvent($id,'Applicant',null,$message,['before'=>json_decode($r['data_json'],true),'after'=>$data,'status'=>$status]);
>>>>>>> parent of 549483e (new)
=======
            query('UPDATE admission_applications SET status=?,data_json=?,version=version+1,updated_at=? WHERE id=?',[$status,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),date('Y-m-d H:i:s'),$id]);applicationEvent($id,'Applicant',null,$message,['before'=>json_decode($r['data_json'],true),'after'=>$data,'status'=>$status]);
>>>>>>> parent of 35cbb61 (Application form becomes a 4-step wizard (personal, academic, documents, preview))
        }
        db()->commit();$_SESSION['flash']='Application saved. Updates are queued for email; check this page for the authoritative status.';return $id;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function reviewApplication(bool $admit=false): string {
    $u=applicationStaff();$r=staffApplication((int)input('application_id'));
    // Shared applicant mutex serializes admission, withdrawal and revision across sessions.
    $account=one('SELECT * FROM applicant_accounts WHERE id=?'.lockSuffix(),[$r['applicant_id']]);$r=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[$r['id']]);
    if((int)input('version')!==(int)$r['version'])fail('Application changed in another window. Reload.');
    if(in_array($r['status'],['Admitted','Rejected','Withdrawn'],true))fail('This application is already closed.');
    $message=input('message',1500);$studentId=null;
    if($admit){
        if($r['status']==='Changes requested')fail('Wait for the applicant to resubmit corrections before admission.');
        if(!$account['active'])fail('Applicant account is suspended. Ask the owner to review it.');
        if(input('approval',3,false)!=='yes')fail('Confirm eligibility, quoted fee and student portal access before admission.');
        courseAccess((int)$r['course_id'],(int)$r['institute_id']);
        if(one('SELECT id FROM students WHERE LOWER(email)=?',[$account['email']])||one('SELECT id FROM portal_accounts WHERE email=?',[$account['email']]))fail('An existing student or reserved portal email requires office review. Do not create a duplicate admission.');
        $studentId=createApprovedApplicantStudent($r,$account,(int)$u['id']);$status='Admitted';
    }else $status=choice('status',['Under review','Changes requested','Rejected']);
    query('UPDATE admission_applications SET status=?,student_id=?,version=version+1,updated_at=? WHERE id=?',[$status,$studentId,date('Y-m-d H:i:s'),$r['id']]);
    applicationEvent((int)$r['id'],'Office',(int)$u['id'],$message,['before_status'=>$r['status'],'after_status'=>$status,'student_id'=>$studentId]);audit('application_reviewed','admission_applications',(int)$r['id']);return 'applications';
}
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
function approveApplicationAction(): string {
    $u=applicationStaff();$r=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[(int)input('application_id')]);if(!$r)fail('Application not found.');instituteAccess((int)$r['institute_id']);
    if((int)input('version')!==(int)$r['version'])fail('Application changed in another window. Reload.');
    if(in_array($r['status'],['Approved','Admitted','Rejected','Withdrawn'],true))fail('This application is already closed.');
    $account=one('SELECT * FROM applicant_accounts WHERE id=?'.lockSuffix(),[$r['applicant_id']]);if(!$account||!$account['active'])fail('Applicant access is suspended.');
    if(input('approval',3,false)!=='yes')fail('Confirm eligibility, documents, fee terms and required consent before approval.');
    courseAccess((int)$r['course_id'],(int)$r['institute_id']);
    if(one('SELECT id FROM students WHERE LOWER(email)=?',[$account['email']])||one('SELECT id FROM portal_accounts WHERE email=?',[$account['email']]))fail('This email is already linked to a student record.');
    $studentId=createApprovedApplicantStudent($r,$account,(int)$u['id']);
    query("UPDATE admission_applications SET status='Approved',student_id=?,version=version+1,updated_at=? WHERE id=?",[$studentId,date('Y-m-d H:i:s'),$r['id']]);
    applicationEvent((int)$r['id'],'Office',(int)$u['id'],'Application approved and student access created.',['before_status'=>$r['status'],'after_status'=>'Approved','student_id'=>$studentId]);audit('application_approved','admission_applications',(int)$r['id']);return 'applications';
}
function rejectApplicationAction(): string {
    $u=applicationStaff();$r=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[(int)input('application_id')]);if(!$r)fail('Application not found.');instituteAccess((int)$r['institute_id']);
    if((int)input('version')!==(int)$r['version'])fail('Application changed in another window. Reload.');if(in_array($r['status'],['Approved','Admitted','Rejected','Withdrawn'],true))fail('This application is already closed.');
    $message=input('message',1500);query("UPDATE admission_applications SET status='Rejected',version=version+1,updated_at=? WHERE id=?",[date('Y-m-d H:i:s'),$r['id']]);applicationEvent((int)$r['id'],'Office',(int)$u['id'],$message,['before_status'=>$r['status'],'after_status'=>'Rejected']);audit('application_rejected','admission_applications',(int)$r['id']);return 'applications';
}
function requestApplicationRevisionAction(): string {
    $u=applicationStaff();$r=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[(int)input('application_id')]);if(!$r)fail('Application not found.');instituteAccess((int)$r['institute_id']);
    if((int)input('version')!==(int)$r['version'])fail('Application changed in another window. Reload.');if(in_array($r['status'],['Approved','Admitted','Rejected','Withdrawn'],true))fail('This application is already closed.');
    $message=input('message',1500);query("UPDATE admission_applications SET status='Revision',revision_notes=?,version=version+1,updated_at=? WHERE id=?",[$message,date('Y-m-d H:i:s'),$r['id']]);applicationEvent((int)$r['id'],'Office',(int)$u['id'],'Revision requested: '.$message,['before_status'=>$r['status'],'after_status'=>'Revision','revision_notes'=>$message]);audit('application_revision_requested','admission_applications',(int)$r['id']);return 'applications';
}
function cancelApplicationAction(): string {
    $u=applicationStaff();$r=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[(int)input('application_id')]);if(!$r)fail('Application not found.');instituteAccess((int)$r['institute_id']);
    if((int)input('version')!==(int)$r['version'])fail('Application changed in another window. Reload.');if(in_array($r['status'],['Approved','Admitted','Rejected','Withdrawn'],true))fail('This application is already closed.');
    $message=input('message',1500);query("UPDATE admission_applications SET status='Withdrawn',version=version+1,updated_at=? WHERE id=?",[date('Y-m-d H:i:s'),$r['id']]);applicationEvent((int)$r['id'],'Office',(int)$u['id'],'Application cancelled by the office: '.$message,['before_status'=>$r['status'],'after_status'=>'Withdrawn']);audit('application_cancelled','admission_applications',(int)$r['id']);return 'applications';
}
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
function toggleApplicant(): string {
    requireRole(['owner']);$r=staffApplication((int)input('application_id'));$a=one('SELECT * FROM applicant_accounts WHERE id=?'.lockSuffix(),[$r['applicant_id']]);
    query('UPDATE applicant_accounts SET active=?,version=version+1 WHERE id=?',[$a['active']?0:1,$a['id']]);query('UPDATE applicant_codes SET consumed=1 WHERE email_hash=?',[hash('sha256','applicant:'.$a['email'])]);audit('applicant_access_changed','applicant_accounts',(int)$a['id']);return 'applications';
}

function createApprovedApplicantStudent(array $r,array $account,int $actorId): int {
        $data=json_decode($r['data_json'],true,512,JSON_THROW_ON_ERROR);$now=date('Y-m-d H:i:s');
        query("INSERT INTO enquiries (institute_id,course_id,assigned_to,name,phone,email,source,status,notes,created_at) VALUES (?,?,?,?,?,?,'Website','Admitted',?,?)",[$r['institute_id'],$r['course_id'],$actorId,$data['name'],$data['phone'],$account['email'],'Online application '.$r['reference'],$now]);$eid=(int)db()->lastInsertId();
        query('INSERT INTO students (institute_id,enquiry_id,course_id,name,phone,email,admission_date,fee_minor,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$r['institute_id'],$eid,$r['course_id'],$data['name'],$data['phone'],$account['email'],date('Y-m-d'),$r['fee_minor'],$now]);$studentId=(int)db()->lastInsertId();
        createDocument($studentId);
        query('INSERT INTO portal_accounts (student_id,email,created_at) VALUES (?,?,?)',[$studentId,$account['email'],$now]);audit('admitted','students',$studentId);
    return $studentId;
}

require_once __DIR__.'/automation.php';
