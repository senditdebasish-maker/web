<?php
declare(strict_types=1);
// Opt-in native-engine gate. Refuses an existing/nonempty database; never drops tables.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(getenv('CRM_MYSQL_TEST_ALLOW')!=='1' || !getenv('CRM_CONFIG_FILE')) {
    fwrite(STDERR,"Use an EXPLICIT PRIVATE TEST configuration and CRM_MYSQL_TEST_ALLOW=1. See docs/OPERATIONS.md. Never point this at production.\n");exit(2);
}
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/schema.php';
require dirname(__DIR__).'/app/actions.php';
function verify(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function mutate(string $fn,array $post): void {
    $_POST=$post;writeTransaction();try{$fn();db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
try {
    if(db()->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('A dedicated MySQL/MariaDB TEST database is required.');
    if((int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0)throw new RuntimeException('Refusing a nonempty database. Use a new dedicated test database.');
    initializeSchema();initializeSchema();verify(operationsReady(),'native schema migration is repeatable');
    $today=date('Y-m-d');$now=date('Y-m-d H:i:s');
    query("INSERT INTO users (name,email,password_hash,role) VALUES ('Test Owner','owner@example.test',?,'owner')",[password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);$uid=(int)db()->lastInsertId();
    $_SESSION=['uid'=>$uid,'staff_stamp'=>staffStamp(one('SELECT * FROM users WHERE id=?',[$uid]))];
    query("INSERT INTO institutes (name,kind,city,phone,created_at) VALUES ('Test Institute','Pharma','Test City','000',?)",[$now]);$iid=(int)db()->lastInsertId();
    query("INSERT INTO courses (institute_id,name,duration,fee_minor) VALUES (?,'Test Course','One year',100000)",[$iid]);$cid=(int)db()->lastInsertId();
    query("INSERT INTO enquiries (institute_id,course_id,assigned_to,name,phone,email,source,status,notes,created_at) VALUES (?,?,?,'Test Student','000','','Website','Admitted','Fixture',?)",[$iid,$cid,$uid,$now]);$eid=(int)db()->lastInsertId();
    query("INSERT INTO students (institute_id,enquiry_id,course_id,name,phone,email,admission_date,fee_minor,created_at) VALUES (?,?,?,'Test Student','000','',?,100000,?)",[$iid,$eid,$cid,$today,$now]);$sid=(int)db()->lastInsertId();
    mutate('saveTeacher',['institute_id'=>(string)$iid,'name'=>'Test Teacher','phone'=>'000','email'=>'','qualification'=>'Test','active'=>'1']);$tid=(int)query('SELECT id FROM teachers')->fetchColumn();
    mutate('saveBatch',['institute_id'=>(string)$iid,'course_id'=>(string)$cid,'teacher_id'=>(string)$tid,'name'=>'Test Batch','room'=>'Lab','starts_on'=>$today,'ends_on'=>date('Y-m-d',strtotime('+1 year')),'capacity'=>'30','active'=>'1']);$bid=(int)query('SELECT id FROM batches')->fetchColumn();
    mutate('enrollStudent',['student_id'=>(string)$sid,'batch_id'=>(string)$bid,'starts_on'=>$today]);
    mutate('openClassDay',['batch_id'=>(string)$bid,'held_on'=>$today,'topic'=>'Test class']);$day=(int)query('SELECT id FROM class_days')->fetchColumn();
    mutate('saveAttendance',['class_id'=>(string)$day,'version'=>'1','reason'=>'','attendance'=>[$sid=>'Present']]);
    verify((int)query('SELECT COUNT(*) FROM attendance_revisions')->fetchColumn()===1,'native allocation and finalized attendance persist');
    mutate('createExam',['batch_id'=>(string)$bid,'title'=>'Native assessment','held_on'=>$today,'maximum'=>'100','pass_mark'=>'40']);$exam=(int)query('SELECT id FROM exams')->fetchColumn();
    mutate('gradeExam',['exam_id'=>(string)$exam,'version'=>'1','reason'=>'Native grade','marks'=>[$sid=>'75.25'],'result_status'=>[$sid=>'Present']]);
    mutate('publishExam',['exam_id'=>(string)$exam,'version'=>'2','state'=>'Published','reason'=>'Native publication']);
    verify((int)query('SELECT score_minor FROM exam_results')->fetchColumn()===7525,'native assessment publication retains exact marks');
    mutate('saveAnnouncement',['institute_id'=>(string)$iid,'batch_id'=>'0','title'=>'Native notice','body'=>'Fictional test notice','starts_on'=>$today,'ends_on'=>$today,'state'=>'Published','reason'=>'Native test']);
    verify(count(studentAnnouncements(['id'=>$sid,'institute_id'=>$iid]))===1,'native student notice targeting works');
    jobHeartbeat('running','Native smoke gate',true);jobHeartbeat('ok','Native smoke gate');
    verify(query("SELECT outcome FROM runtime_jobs WHERE job_key='notifications'")->fetchColumn()==='ok','native worker heartbeat upsert works');
    $plan=['student_id'=>(string)$sid,'version'=>'0','reason'=>'Test agreement','due_on'=>[$today],'installment_amount'=>['1000.00']];
    mutate('saveFeePlan',$plan);verify((int)query('SELECT SUM(amount_minor) FROM fee_installments')->fetchColumn()===100000,'native fee plan totals remain integer paise');
    try{mutate('saveFeePlan',$plan);throw new RuntimeException('Stale version accepted.');}catch(DomainException $e){verify(str_contains($e->getMessage(),'another window'),'native stale fee plan rejected');}
    // Reproduce a stale-snapshot hazard without requiring pcntl or a parallel HTTP server:
    // connection A reads before B commits; A must see B's payment when validating its own.
    $b=new PDO($config['dsn'],$config['user']??'',$config['password']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    writeTransaction();query('SELECT COUNT(*) FROM payments WHERE student_id=?',[$sid])->fetchColumn();
    $st=$b->prepare("INSERT INTO payments (institute_id,student_id,amount_minor,paid_on,method,reference,request_key,recorded_by,created_at) VALUES (?,?,80000,?,'Cash','Test concurrent commit',?,?,?)");
    $st->execute([$iid,$sid,$today,bin2hex(random_bytes(24)),$uid,$now]);
    $_SESSION['payment_nonce']=bin2hex(random_bytes(24));$_POST=['student_id'=>(string)$sid,'request_key'=>$_SESSION['payment_nonce'],'amount'=>'300','paid_on'=>$today,'method'=>'Cash','reference'=>''];
    try{recordPayment();throw new RuntimeException('Overpayment from stale snapshot was accepted.');}
    catch(DomainException $e){verify(str_contains($e->getMessage(),'exceeds'),'payment validation sees another connection commit, preventing overpayment');}
    finally{if(db()->inTransaction())db()->rollBack();}
    $_POST['amount']='200';mutate('recordPayment',$_POST);
    $payload=json_decode((string)query("SELECT payload_json FROM documents WHERE kind='payment'")->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
    verify($payload['payment']['total_paid_minor']===100000 && $payload['payment']['balance_minor']===0,'native receipt snapshot includes all committed payments');
    verify(studentInstallments($sid)[0]['status']==='Paid','native installment allocation matches paid balance');
    verify((int)feeReportRows($iid)[0]['paid_minor']===100000,'native report subqueries match ledger');
    mutate('saveAdmissionListing',['course_id'=>(string)$cid,'version'=>'0','description'=>'Native public course','eligibility'=>'Office verifies originals','privacy_notice'=>'Fictional native-test privacy notice','opens_on'=>$today,'closes_on'=>$today,'accepting'=>'1']);
    verify(count(publicCourses())===1,'native public course listing is explicitly published');
    query('INSERT INTO applicant_accounts (email,created_at) VALUES (?,?)',['online@example.test',$now]);$applicant=(int)db()->lastInsertId();
    $data=['name'=>'Native Applicant','phone'=>'9000000000','city'=>'Test City','qualification'=>'Test qualification','completion_year'=>date('Y'),'note'=>'','course_name'=>'Test Course','institute_name'=>'Test Institute','duration'=>'One year'];
    query('INSERT INTO admission_applications (applicant_id,institute_id,course_id,reference,request_key,fee_minor,data_json,consent_notice,consent_version,submitted_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$applicant,$iid,$cid,'APP-NATIVE',bin2hex(random_bytes(24)),100000,json_encode($data),'Native test notice','admission-application-v1',$now,$now]);$application=(int)db()->lastInsertId();
    $_POST=['application_id'=>(string)$application,'version'=>'1','message'=>'Native approval','approval'=>'yes'];writeTransaction();reviewApplication(true);db()->commit();
    verify(query('SELECT status FROM admission_applications WHERE id=?',[$application])->fetchColumn()==='Admitted','native online application approval commits');
    verify((int)query("SELECT COUNT(*) FROM portal_accounts WHERE email='online@example.test' AND active=1")->fetchColumn()===1,'native approved applicant gains exactly one student portal account');
    verify(automationReady(),'native automation tables are ready');
    verify((int)query('SELECT COUNT(*) FROM application_mail WHERE application_id=?',[$application])->fetchColumn()>=1,'native approval queues applicant status email');
    mutate('saveEligibilityPolicy',['course_id'=>(string)$cid,'version'=>'0','enabled'=>'0','qualification_code'=>'NATIVE-TEST','minimum_percentage'=>'60','minimum_age'=>'17','maximum_age'=>'30','cutoff_on'=>$today,'description'=>'Native test policy','confirm'=>'yes']);
    verify((int)query('SELECT enabled FROM eligibility_policies WHERE id=?',[$cid])->fetchColumn()===0,'native eligibility policy stays disabled by default');
    query("INSERT INTO online_orders (student_id,institute_id,actor_id,amount_minor,mode,key_id,receipt,state,created_epoch) VALUES (?,?,?,1000,'test','rzp_test_native',?, 'Pending',UNIX_TIMESTAMP())",[$sid,$iid,$uid,'ns_native'.bin2hex(random_bytes(8))]);
    $_SESSION['payment_nonce']=bin2hex(random_bytes(24));$_POST=['student_id'=>(string)$sid,'request_key'=>$_SESSION['payment_nonce'],'amount'=>'10','paid_on'=>$today,'method'=>'Cash','reference'=>''];
    try{writeTransaction();recordPayment();db()->commit();throw new RuntimeException('Manual payment bypassed online hold.');}catch(DomainException $e){if(db()->inTransaction())db()->rollBack();verify(str_contains($e->getMessage(),'pending or needs reconciliation'),'native manual payment blocked during online hold');}
    verify(verifyWebhookSignature('test-body',hash_hmac('sha256','test-body','native-secret'),'native-secret'),'native webhook signature verification works');
    verify(!verifyWebhookSignature('test-body','invalid','native-secret'),'native webhook rejects invalid signature');
    echo "Native smoke gate passed. Fictional fixture data remains in the dedicated TEST database. This is not a full concurrency, load or security audit.\n";
} catch(Throwable $e) {
    if(isset($config) && isset($e) && function_exists('db')){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable $ignored){}}
    fwrite(STDERR,"Native gate failed: ".$e->getMessage()."\nUse only an empty private TEST database; investigate locally without sharing credentials.\n");exit(1);
}
