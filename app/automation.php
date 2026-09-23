<?php
declare(strict_types=1);
require_once __DIR__.'/applications.php';
function migrateAutomation(): void {
    $mysql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';$json=$mysql?'MEDIUMTEXT':'TEXT';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4':'';
    foreach([
        'application_mail'=>"id $id, event_id INTEGER NOT NULL UNIQUE, application_id INTEGER NOT NULL, recipient VARCHAR(200) NOT NULL, subject VARCHAR(200) NOT NULL, body TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, next_attempt_at INTEGER NOT NULL DEFAULT 0, lease_until INTEGER NOT NULL DEFAULT 0, claim_token VARCHAR(64) NOT NULL DEFAULT '', last_error VARCHAR(250) NOT NULL DEFAULT '', created_at VARCHAR(19) NOT NULL, FOREIGN KEY(event_id) REFERENCES application_events(id), FOREIGN KEY(application_id) REFERENCES admission_applications(id)",
        'certificates'=>"id $id, application_id INTEGER NOT NULL, storage_key VARCHAR(64) NOT NULL UNIQUE, original_name VARCHAR(150) NOT NULL, mime VARCHAR(80) NOT NULL, bytes INTEGER NOT NULL, sha256 VARCHAR(64) NOT NULL, scan_state VARCHAR(20) NOT NULL DEFAULT 'Pending', review_state VARCHAR(20) NOT NULL DEFAULT 'Pending', reviewed_by INTEGER NULL, application_version INTEGER NOT NULL DEFAULT 0, policy_version INTEGER NOT NULL DEFAULT 0, qualification_code VARCHAR(80) NOT NULL DEFAULT '', percentage_minor INTEGER NULL, birth_date VARCHAR(10) NULL, review_note VARCHAR(500) NOT NULL DEFAULT '', doc_type VARCHAR(30) NOT NULL DEFAULT 'certificate', created_at VARCHAR(19) NOT NULL, FOREIGN KEY(application_id) REFERENCES admission_applications(id), FOREIGN KEY(reviewed_by) REFERENCES users(id)",
        'eligibility_policies'=>"id INTEGER PRIMARY KEY, enabled INTEGER NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, qualification_code VARCHAR(80) NOT NULL, minimum_percentage INTEGER NOT NULL, minimum_age INTEGER NOT NULL, maximum_age INTEGER NOT NULL, cutoff_on VARCHAR(10) NOT NULL, authorized_by INTEGER NOT NULL, description TEXT NOT NULL, FOREIGN KEY(id) REFERENCES courses(id), FOREIGN KEY(authorized_by) REFERENCES users(id)",
        'eligibility_runs'=>"id $id, application_id INTEGER NOT NULL, policy_version INTEGER NOT NULL, application_version INTEGER NOT NULL, result VARCHAR(40) NOT NULL, details $json NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(application_id) REFERENCES admission_applications(id)",
        'online_orders'=>"id $id, student_id INTEGER NOT NULL, institute_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, amount_minor INTEGER NOT NULL, mode VARCHAR(10) NOT NULL, key_id VARCHAR(100) NOT NULL, receipt VARCHAR(40) NOT NULL UNIQUE, provider_order VARCHAR(100) NULL UNIQUE, state VARCHAR(20) NOT NULL DEFAULT 'Creating', created_epoch INTEGER NOT NULL, note VARCHAR(500) NOT NULL DEFAULT '', FOREIGN KEY(student_id) REFERENCES students(id), FOREIGN KEY(institute_id) REFERENCES institutes(id), FOREIGN KEY(actor_id) REFERENCES users(id)",
        'online_captures'=>"id $id, order_id INTEGER NOT NULL, provider_payment VARCHAR(100) NOT NULL UNIQUE, state VARCHAR(20) NOT NULL, amount_minor INTEGER NOT NULL, payment_id INTEGER NULL UNIQUE, reason VARCHAR(500) NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(order_id) REFERENCES online_orders(id), FOREIGN KEY(payment_id) REFERENCES payments(id)",
        'razorpay_webhook_events'=>"event_id VARCHAR(100) PRIMARY KEY, institute_id INTEGER NOT NULL, received_at VARCHAR(19) NOT NULL, FOREIGN KEY(institute_id) REFERENCES institutes(id)"
    ] as $t=>$def)db()->exec("CREATE TABLE IF NOT EXISTS $t ($def)$suffix");
    $ccols=$mysql?rows("SELECT COLUMN_NAME name FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certificates'"):rows("SELECT name FROM pragma_table_info('certificates')");
    if($ccols&&!in_array('doc_type',array_column($ccols,'name'),true))db()->exec("ALTER TABLE certificates ADD doc_type VARCHAR(30) NOT NULL DEFAULT 'certificate'");
    foreach([['application_mail','idx_appmail_due','status,next_attempt_at'],['certificates','idx_certificate_app','application_id,id'],['online_orders','idx_online_student','student_id,state'],['eligibility_runs','idx_eligibility_app','application_id,id']] as [$t,$index,$cols]){
        $found=$mysql?one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$t,$index]):one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$index]);if(!$found)db()->exec("CREATE INDEX $index ON $t ($cols)");
    }unset($GLOBALS['automation_ready']);
}
function automationReady(): bool {
    if(isset($GLOBALS['automation_ready']))return $GLOBALS['automation_ready'];
    try{foreach(['application_mail','certificates','eligibility_policies','eligibility_runs','online_orders','online_captures','razorpay_webhook_events'] as $t)query("SELECT * FROM $t WHERE 1=0");return $GLOBALS['automation_ready']=true;}catch(PDOException $e){return $GLOBALS['automation_ready']=false;}
}
function queueApplicationMail(int $event,int $app): void {
    if(!automationReady())return;
    $r=one('SELECT a.reference,a.status,p.email FROM admission_applications a JOIN applicant_accounts p ON p.id=a.applicant_id WHERE a.id=?',[$app]);
    if(!$r)return;
    query('INSERT INTO application_mail (event_id,application_id,recipient,subject,body,created_at) VALUES (?,?,?,?,?,?)',[$event,$app,$r['email'],'Application update — '.$r['reference'],"Your application ".$r['reference']." has an update.\nCurrent status: ".$r['status']."\n\nSign in to the institute's application portal using your usual trusted website address to read the details. Do not share your sign-in codes. This message does not confirm a payment.\n",date('Y-m-d H:i:s')]);
}
function processApplicationMail(int $limit=25): array {
    if(!automationReady())return [];$results=[];$now=time();
    foreach(rows("SELECT id FROM application_mail WHERE ((status='pending' AND next_attempt_at<=?) OR (status='sending' AND lease_until<?)) AND attempts<5 ORDER BY id LIMIT ".max(1,min(100,$limit)),[$now,$now]) as $candidate){
        $id=(int)$candidate['id'];$token=bin2hex(random_bytes(24));
        if(!query("UPDATE application_mail SET status='sending',claim_token=?,lease_until=?,attempts=attempts+1 WHERE id=? AND ((status='pending' AND next_attempt_at<=?) OR (status='sending' AND lease_until<?)) AND attempts<5",[$token,$now+300,$id,$now,$now])->rowCount())continue;
        $n=one('SELECT m.*,p.active,p.email current_email FROM application_mail m JOIN admission_applications a ON a.id=m.application_id JOIN applicant_accounts p ON p.id=a.applicant_id WHERE m.id=?',[$id]);
        try{
            if(!$n || !$n['active'] || $n['recipient']!==$n['current_email']){$status='blocked';$error='Applicant access/email changed. Verify recipient before retrying.';}
            else{$status=sendMail($n['recipient'],$n['subject'],$n['body'],null,'','application-event-'.$n['event_id']);$error=$status==='spooled'?'Captured locally; no inbox delivery.':'';}
        }catch(Throwable $e){$status=(int)$n['attempts']>=5?'failed':'pending';$error='Delivery failed. Check private SMTP configuration and server connectivity.';}
        query('UPDATE application_mail SET status=?,last_error=?,next_attempt_at=?,lease_until=0 WHERE id=? AND claim_token=?',[$status,$error,time()+min(3600,60*(2**(int)$n['attempts'])),$id,$token]);$results[$id]=$status;
    }
    query("UPDATE application_mail SET status='failed',last_error='Worker interrupted on final attempt.',lease_until=0 WHERE status='sending' AND lease_until<? AND attempts>=5",[time()]);return $results;
}
function retryApplicationMail(): string {
    applicationStaff();$n=one('SELECT * FROM application_mail WHERE id=?',[(int)input('id')]);if(!$n)fail('Notification not found.');$a=staffApplication((int)$n['application_id']);
    if(!in_array($n['status'],['failed','blocked','spooled'],true))fail('Only failed, blocked or captured messages can be retried.');
    $account=one('SELECT * FROM applicant_accounts WHERE id=?',[$a['applicant_id']]);if(!$account['active']||$account['email']!==$n['recipient'])fail('Recipient must be verified and enabled; messages cannot be redirected.');
    query("UPDATE application_mail SET status='pending',attempts=0,next_attempt_at=0,last_error='' WHERE id=?",[$n['id']]);audit('application_mail_retried','application_mail',(int)$n['id']);return 'applications';
}
function certificateRoot(): string {
    global $config;$root=realpath((string)($config['certificates']['directory']??''));$project=realpath(dirname(__DIR__));
    if(empty($config['certificates']['enabled'])||!$root||!is_dir($root)||!is_writable($root))fail('Certificate uploads are not configured. Contact the office.');
    $normal=static fn(string $s):string=>strtolower(str_replace('\\','/',rtrim($s,'/\\'))).'/';
    if(str_starts_with($normal($root),$normal($project)))fail('Certificate storage must be outside the application and all web roots.');
    for($p=dirname($project);$p!==dirname($p);$p=dirname($p))if(in_array(strtolower(basename($p)),['htdocs','www','wwwroot','public_html'],true)&&str_starts_with($normal($root),$normal($p)))fail('Certificate directory is under a web root.');
    return $root;
}
function certificatePath(array $c): string {
    $root=certificateRoot();if(!preg_match('/^[a-f0-9]{48}$/D',$c['storage_key']))fail('Invalid stored file.');$path=$root.DIRECTORY_SEPARATOR.$c['storage_key'];
    if(!is_file($path)||is_link($path)||filesize($path)!==(int)$c['bytes']||!hash_equals($c['sha256'],hash_file('sha256',$path)))fail('Certificate integrity check failed. Contact the office.');return $path;
}
function serveCertificateFile(array $c): void {
    $path=certificatePath($c);
    header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
    header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.$c['original_name'].'"');header('Content-Length: '.filesize($path));
    readfile($path);exit;
}
function uploadCertificate(): int {
    $actor=currentApplicant();if(!$actor)fail('Applicant sign-in required.');if(!automationReady())fail('Automation upgrade required.');$root=certificateRoot();$file=$_FILES['certificate']??null;
    if(!class_exists('finfo'))fail('Server file verification is unavailable. Contact the office.');
    if(!$file || is_array($file['error']) || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))fail('Choose a certificate file; check server upload limits.');
    $size=filesize($file['tmp_name']);if($size===false||$size<8||$size>5*1024*1024)fail('Certificate size must be between 8 bytes and 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));$allow=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if(!isset($allow[$mime])||!in_array($ext,$mime==='image/jpeg'?['jpg','jpeg']:[$allow[$mime]],true))fail('Only matching PDF, JPEG or PNG files are accepted.');
    if($mime==='application/pdf' && file_get_contents($file['tmp_name'],false,null,0,5)!=='%PDF-')fail('Invalid PDF signature.');
    if(str_starts_with($mime,'image/')){$dimensions=@getimagesize($file['tmp_name']);if(!$dimensions||$dimensions[0]>12000||$dimensions[1]>12000||$dimensions[0]*$dimensions[1]>40000000)fail('Invalid or oversized image dimensions.');}
    $destination=null;writeTransaction();try{
        one('SELECT id FROM applicant_accounts WHERE id=?'.lockSuffix(),[$actor['id']]);$a=ownApplication((int)input('application_id'),$actor);$a=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[$a['id']]);
        if((int)input('version')!==(int)$a['version'])fail('Application changed. Reload before uploading.');
        if(!in_array($a['status'],['Draft','Pending Review','Submitted','Under review','Changes requested','Revision'],true))fail('Uploads are closed for this application.');
        if((int)query('SELECT COUNT(*) FROM certificates WHERE application_id=?',[$a['id']])->fetchColumn()>=10)fail('Maximum ten document uploads per application. Ask the office for help.');
        $key=bin2hex(random_bytes(24));$destination=$root.DIRECTORY_SEPARATOR.$key;
        if(!move_uploaded_file($file['tmp_name'],$destination))fail('Private upload storage failed.');chmod($destination,0600);
        $name='certificate.'.$allow[$mime];query('INSERT INTO certificates (application_id,storage_key,original_name,mime,bytes,sha256,created_at) VALUES (?,?,?,?,?,?,?)',[$a['id'],$key,$name,$mime,$size,hash_file('sha256',$destination),date('Y-m-d H:i:s')]);
        query('UPDATE admission_applications SET version=version+1,updated_at=? WHERE id=?',[date('Y-m-d H:i:s'),$a['id']]);applicationEvent((int)$a['id'],'Applicant',null,'Certificate uploaded to quarantine.',['certificate_id'=>(int)db()->lastInsertId()]);db()->commit();$_SESSION['flash']='Certificate uploaded for office verification. It is quarantined until scanned and reviewed.';return (int)$a['id'];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($destination&&is_file($destination))unlink($destination);throw $e;}
}
function clamavClean(string $path): bool {
    global $config;$host=(string)($config['certificates']['clamav_host']??'127.0.0.1');$port=(int)($config['certificates']['clamav_port']??3310);
    if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$host)||$port<1||$port>65535)fail('Malware scanner is misconfigured. Contact the server operator.');
    $sock=@fsockopen($host,$port,$errno,$errstr,5);if(!$sock)fail('Malware scanner unavailable. Retry later or ask the operator to check ClamAV.');
    try{
        stream_set_timeout($sock,20);fwrite($sock,"nINSTREAM\n");
        $fh=fopen($path,'rb');if(!$fh)fail('Stored file unavailable for scanning.');
        try{while(!feof($fh)){$chunk=fread($fh,8192);if($chunk==='')break;fwrite($sock,pack('N',strlen($chunk)).$chunk);}}finally{fclose($fh);}
        fwrite($sock,pack('N',0));$response=(string)stream_get_contents($sock);
        if(str_contains($response,'FOUND'))return false;if(str_contains($response,'OK'))return true;
        fail('Malware scanner returned an unexpected result. Contact the operator.');
    }finally{fclose($sock);}
}
function scanCertificate(): string {
    $u=applicationStaff();$c=one('SELECT * FROM certificates WHERE id=?',[(int)input('certificate_id')]);if(!$c)fail('Certificate not found.');$a=staffApplication((int)$c['application_id']);
    one('SELECT id FROM applicant_accounts WHERE id=?'.lockSuffix(),[$a['applicant_id']]);$a=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[$a['id']]);$c=one('SELECT * FROM certificates WHERE id=?'.lockSuffix(),[$c['id']]);
    if((int)input('version')!==(int)$a['version'])fail('Application changed. Reload.');
    if(!in_array($a['status'],['Draft','Pending Review','Submitted','Under review','Changes requested','Revision'],true))fail('Application is closed.');
    if($c['scan_state']!=='Pending')fail('This certificate has already been scanned.');
    global $config;$mode=(string)($config['certificates']['scanner']??'manual');$path=certificatePath($c);
    if($mode==='clamav'){
        // Network scan runs inside the staff transaction; failure rolls back without changing state.
        $clean=clamavClean($path);$state=$clean?'Clean':'Infected';$note=$clean?'ClamAV scan passed.':'Malware detected by ClamAV. Do not open on other machines; follow incident procedure.';
    }else{
        $state=choice('scan_state',['Clean','Infected']);
        if(input('offline_scan',3,false)!=='yes')fail('Confirm you downloaded this file on an isolated machine and scanned it with updated antivirus software.');
        $note=input('scan_note',500);
    }
    query('UPDATE certificates SET scan_state=? WHERE id=?',[$state,$c['id']]);
    query('UPDATE admission_applications SET version=version+1 WHERE id=?',[$a['id']]);applicationEvent((int)$a['id'],'Office',(int)$u['id'],'Certificate scan '.$state.': '.$note,['certificate_id'=>$c['id'],'scan_state'=>$state]);audit('certificate_scanned','certificates',(int)$c['id']);return 'applications';
}
function percentageMinor(string $s): int {if(!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/D',$s,$m))fail('Enter a percentage between 0 and 100 with up to two decimal places.');$v=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($v>10000)fail('Percentage cannot exceed 100.');return $v;}
function saveEligibilityPolicy(): string {
    $u=requireRole(['owner']);if(!automationReady())fail('Automation upgrade required.');$cid=(int)input('course_id');record('courses',$cid);one('SELECT id FROM courses WHERE id=?'.lockSuffix(),[$cid]);$old=one('SELECT * FROM eligibility_policies WHERE id=?'.lockSuffix(),[$cid]);
    if((int)input('version')!==(int)($old['version']??0))fail('Policy changed. Reload.');
    $min=input('minimum_age',3);$max=input('maximum_age',3);if(!ctype_digit($min)||!ctype_digit($max)||(int)$min>(int)$max||(int)$max>100)fail('Set valid age bounds from 0 to 100.');
    if(input('confirm',3,false)!=='yes')fail('Confirm these are your approved course rules and fee/access authorization.');
    $v=[choice('enabled',['0','1']),strtoupper(input('qualification_code',80)),percentageMinor(input('minimum_percentage')), (int)$min,(int)$max,validDate('cutoff_on'),$u['id'],input('description',3000)];
    if($old)query('UPDATE eligibility_policies SET enabled=?,qualification_code=?,minimum_percentage=?,minimum_age=?,maximum_age=?,cutoff_on=?,authorized_by=?,description=?,version=version+1 WHERE id=?',[...$v,$cid]);
    else query('INSERT INTO eligibility_policies (enabled,qualification_code,minimum_percentage,minimum_age,maximum_age,cutoff_on,authorized_by,description,id) VALUES (?,?,?,?,?,?,?,?,?)',[...$v,$cid]);
    audit('eligibility_policy_changed','courses',$cid);return 'applications';
}
function reviewCertificate(): string {
    $u=applicationStaff();$c=one('SELECT * FROM certificates WHERE id=?',[(int)input('certificate_id')]);if(!$c)fail('Certificate not found.');$a=staffApplication((int)$c['application_id']);
    one('SELECT id FROM applicant_accounts WHERE id=?'.lockSuffix(),[$a['applicant_id']]);$a=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[$a['id']]);$c=one('SELECT * FROM certificates WHERE id=?'.lockSuffix(),[$c['id']]);
    if((int)input('version')!==(int)$a['version'])fail('Application changed. Reload.');if(!in_array($a['status'],['Draft','Pending Review','Submitted','Under review','Changes requested','Revision'],true))fail('Application is closed.');
    if($c['scan_state']!=='Clean')fail('Certificate must pass configured malware scanning first.');certificatePath($c);
    $state=choice('review_state',['Verified','Rejected']);$note=input('review_note',500);$code='';$percent=null;$dob=null;
    if($state==='Verified'){if(input('authenticity',3,false)!=='yes')fail('Confirm original authenticity, evidence accuracy, all other eligibility/consent requirements and available capacity.');$code=strtoupper(input('qualification_code',80));$percent=percentageMinor(input('percentage'));$dob=validDate('birth_date');if($dob>date('Y-m-d')||$dob<'1900-01-01')fail('Enter a valid verified date of birth.');}
    $p=one('SELECT * FROM eligibility_policies WHERE id=?'.lockSuffix(),[$a['course_id']]);
    query('UPDATE certificates SET review_state=?,reviewed_by=?,application_version=?,policy_version=?,qualification_code=?,percentage_minor=?,birth_date=?,review_note=? WHERE id=?',[$state,$u['id'],(int)$a['version']+1,(int)($p['version']??0),$code,$percent,$dob,$note,$c['id']]);
    query('UPDATE admission_applications SET version=version+1 WHERE id=?',[$a['id']]);applicationEvent((int)$a['id'],'Office',(int)$u['id'],'Certificate '.$state.': '.$note,['certificate_id'=>$c['id'],'review_state'=>$state]);
    if($state==='Verified')evaluateAutomaticAdmission((int)$a['id']);return 'applications';
}
function evaluateAutomaticAdmission(int $id): string {
    $a=one('SELECT * FROM admission_applications WHERE id=?'.lockSuffix(),[$id]);$p=one('SELECT * FROM eligibility_policies WHERE id=?'.lockSuffix(),[$a['course_id']]);
    if(!$p||!$p['enabled']||!in_array($a['status'],['Pending Review','Submitted','Under review'],true))return 'Not enabled or not ready';
    $c=one("SELECT * FROM certificates WHERE application_id=? AND review_state='Verified' AND scan_state='Clean' AND application_version=? AND policy_version=? ORDER BY id DESC LIMIT 1",[$id,$a['version'],$p['version']]);
    $owner=one("SELECT id FROM users WHERE id=? AND role='owner' AND active=1",[$p['authorized_by']]);$reason='Evidence is missing or stale';$passed=false;
    if($c && $owner){certificatePath($c);$birth=new DateTimeImmutable($c['birth_date']);$cutoff=new DateTimeImmutable($p['cutoff_on']);$age=$birth<=$cutoff?$birth->diff($cutoff)->y:-1;$passed=$c['qualification_code']===$p['qualification_code'] && (int)$c['percentage_minor']>=(int)$p['minimum_percentage'] && $age>=(int)$p['minimum_age'] && $age<=(int)$p['maximum_age'];$reason=$passed?'Verified evidence matches configured rules':'Qualification, percentage or age does not match configured rules';}
    $account=one('SELECT * FROM applicant_accounts WHERE id=?',[$a['applicant_id']]);$course=one('SELECT active FROM courses WHERE id=?',[$a['course_id']]);
    if(!$account||!$account['active']||!$course||!$course['active']||one('SELECT id FROM students WHERE LOWER(email)=?',[$account['email']])||one('SELECT id FROM portal_accounts WHERE email=?',[$account['email']])){$passed=false;$reason='Account, course or duplicate-email condition requires office review';}
    $result=$passed?'Approved':'Manual review';
    query('INSERT INTO eligibility_runs (application_id,policy_version,application_version,result,details,created_at) VALUES (?,?,?,?,?,?)',[$id,$p['version'],$a['version'],$result,json_encode(['reason'=>$reason,'policy'=>$p,'certificate_id'=>$c['id']??null],JSON_THROW_ON_ERROR),date('Y-m-d H:i:s')]);
    if($passed){$sid=createApprovedApplicantStudent($a,$account,(int)$p['authorized_by']);query("UPDATE admission_applications SET status='Admitted',student_id=?,version=version+1,updated_at=? WHERE id=?",[$sid,date('Y-m-d H:i:s'),$id]);applicationEvent($id,'System',(int)$p['authorized_by'],'Automatically admitted under owner-authorized policy after certificate verification.',['policy_version'=>$p['version'],'student_id'=>$sid,'certificate_id'=>$c['id']]);audit('automatically_admitted','students',$sid);}
    return $result;
}

function wizardDocumentSlots(): array {
    return ['doc_photo'=>'Photograph','doc_signature'=>'Signature','doc_marksheet10'=>'Class 10 marksheet','doc_marksheet12'=>'Class 12 marksheet','doc_caste'=>'Caste certificate','doc_pwd'=>'PwD certificate','doc_domicile'=>'Domicile certificate','doc_other'=>'Other document'];
}
function wizardDocLabel(string $type): string { return wizardDocumentSlots()[$type]??'Certificate'; }
function wizardUploadsReady(): bool {
    if(!automationReady())return false;
    try{certificateRoot();query('SELECT doc_type FROM certificates WHERE 1=0');return true;}catch(Throwable $e){return false;}
}
function validateWizardFile(array $file,string $label): array {
    if(!class_exists('finfo'))fail('Server file verification is unavailable. Contact the office.');
    if(!is_array($file)||is_array($file['error']??null)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))fail('Could not read the uploaded '.$label.'. Please re-attach it.');
    $size=filesize($file['tmp_name']);if($size===false||$size<8||$size>2*1024*1024)fail($label.' must be between 8 bytes and 2 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=strtolower(pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));$allow=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    if(!isset($allow[$mime])||!in_array($ext,$mime==='image/jpeg'?['jpg','jpeg']:[$allow[$mime]],true))fail($label.' must be a matching PDF, JPEG or PNG file.');
    if($mime==='application/pdf'&&file_get_contents($file['tmp_name'],false,null,0,5)!=='%PDF-')fail('Invalid PDF signature in '.$label.'.');
    if(str_starts_with($mime,'image/')){$d=@getimagesize($file['tmp_name']);if(!$d||$d[0]>12000||$d[1]>12000||$d[0]*$d[1]>40000000)fail('Invalid or oversized image dimensions in '.$label.'.');}
    return [$mime,$allow[$mime],$size];
}
function attachWizardFiles(int $appId): array {
    $pending=[];foreach(wizardDocumentSlots() as $field=>$label){$f=$_FILES[$field]??null;if(!$f||($f['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;$pending[$field]=[$f,$label];}
    if(!$pending)return [];
    if(!wizardUploadsReady())fail('Document uploads are not available right now. Submit without documents, or contact the office.');
    $root=certificateRoot();
    if((int)query('SELECT COUNT(*) FROM certificates WHERE application_id=?',[$appId])->fetchColumn()+count($pending)>10)fail('Maximum ten document uploads per application. Ask the office for help.');
    $hashes=array_column(rows('SELECT sha256 FROM certificates WHERE application_id=?',[$appId]),'sha256');$stored=[];
    foreach($pending as $field=>[$f,$label]){
        [$mime,$ext,$size]=validateWizardFile($f,$label);
        $sha=hash_file('sha256',$f['tmp_name']);if(in_array($sha,$hashes,true))continue;
        $key=bin2hex(random_bytes(24));$dest=$root.DIRECTORY_SEPARATOR.$key;
        if(!move_uploaded_file($f['tmp_name'],$dest))fail('Private upload storage failed.');
        chmod($dest,0600);
        $safe=substr(strtolower(preg_replace('/[^a-z0-9]+/','-',trim($label))).'.'.$ext,0,150);
        query('INSERT INTO certificates (application_id,storage_key,original_name,mime,bytes,sha256,doc_type,created_at) VALUES (?,?,?,?,?,?,?,?)',[$appId,$key,$safe,$mime,$size,$sha,$field,date('Y-m-d H:i:s')]);
        $hashes[]=$sha;$stored[]= ['path'=>$dest,'label'=>$label];
    }
    return $stored;
}
