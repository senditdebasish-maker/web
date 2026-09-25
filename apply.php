<?php
declare(strict_types=1);
<<<<<<< HEAD
ini_set('display_errors','0'); ob_start();
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/applications.php'; require_once __DIR__.'/app/site-accounts.php'; require_once __DIR__.'/app/automation.php';
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
=======
ini_set('display_errors','0');ob_start();
require __DIR__.'/app/bootstrap.php';require_once __DIR__.'/app/applications.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
>>>>>>> parent of 549483e (new)
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
ini_set('session.use_strict_mode','1');session_name('northstar_applicant');session_set_cookie_params(sessionCookieParams());session_start();
if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>1800){$_SESSION=[];session_regenerate_id(true);}
$_SESSION['last_seen']=time();$_SESSION['csrf']??=bin2hex(random_bytes(32));
$page=is_string($_GET['page']??null)?$_GET['page']:'courses';$error=null;$actor=null;$application=null;$course=null;
try{
    if(!applicationsReady()){http_response_code(503);$page='unavailable';}
    else{
        $actor=currentApplicant();
        if(isset($_GET['certificate'])){
            if(!$actor||!automationReady()){http_response_code(401);exit('Please sign in to access your uploads.');}
            $c=one('SELECT c.* FROM certificates c JOIN admission_applications a ON a.id=c.application_id WHERE c.id=? AND a.applicant_id=?',[(int)$_GET['certificate'],$actor['id']]);
            if(!$c){http_response_code(404);exit('Upload not found.');}
            ob_clean();serveCertificateFile($c);
        }
        if($_SERVER['REQUEST_METHOD']==='POST'){
            requireCookies();
<<<<<<< HEAD
            if(in_array(input('action',40),['request_code','verify_code'],true)){header('Location: index.php?page=login&show=applicant');exit;}
            if(!hash_equals($_SESSION['csrf'],input('csrf',128)))fail('Your form expired. Refresh and try again.');
            $action=input('action',40);$next='courses';
            if($action==='logout'){$_SESSION=[];session_regenerate_id(true);$next='courses';}
            elseif(in_array($action,['submit_application','save_application_draft','revise_application','withdraw_application'],true)){$id=applicantMutation($action);$next='application&id='.$id;}
=======
            if(!hash_equals($_SESSION['csrf'],input('csrf',128)))fail('Your form expired. Refresh and try again.');
            $action=input('action',40);$next='login';
            if($action==='request_code')requestApplicantCode();
            elseif($action==='verify_code'){verifyApplicantCode();$selected=(int)($_SESSION['apply_course']??0);$next=$selected && publicCourses(0,$selected)?'apply&course='.$selected:'dashboard';unset($_SESSION['apply_course']);}
            elseif($action==='logout'){$_SESSION=[];session_regenerate_id(true);$next='courses';}
            elseif(in_array($action,['submit_application','revise_application','withdraw_application'],true)){$id=applicantMutation($action);$next='application&id='.$id;}
>>>>>>> parent of 549483e (new)
            elseif($action==='upload_certificate'){$id=uploadCertificate();$next='application&id='.$id;}
            else fail('Applicants cannot perform this action.');
            header('Location: apply.php?page='.$next);exit;
        }
        if($page==='login'){header('Location: index.php?page=login&show=applicant');exit;}
        if(!in_array($page,['courses','course','dashboard','apply','application','help'],true)){http_response_code(404);$page='notfound';}
    }
}catch(DomainException $e){$error=$e->getMessage();}
catch(Throwable $e){http_response_code(503);$error='Admissions are temporarily unavailable. Contact the institute.';$page='unavailable';error_log('Northstar public admission operation failed.');}
// Resolve all protected view data inside the authorization/error boundary, including failed POSTs.
try{
    if(!in_array($page,['unavailable','notfound'],true)){
        $actor=currentApplicant();
<<<<<<< HEAD
        if($page==='apply' && !$actor && publicCourses(0,(int)($_GET['course']??0))){$_SESSION['apply_course']=(int)$_GET['course'];header('Location: index.php?page=login&show=applicant&course='.(int)$_GET['course']);exit;}
        if(in_array($page,['dashboard','apply','application'],true)&&!$actor){header('Location: index.php?page=login&show=applicant');exit;}
=======
        if($page==='apply' && !$actor && publicCourses(0,(int)($_GET['course']??0)))$_SESSION['apply_course']=(int)$_GET['course'];
        if(in_array($page,['dashboard','apply','application'],true)&&!$actor)$page='login';
>>>>>>> parent of 549483e (new)
        if(in_array($page,['course','apply'],true)){$course=publicCourses(0,(int)($_GET['course']??0))[0]??null;if(!$course){http_response_code(404);$page='notfound';}}
        if($page==='application')$application=ownApplication((int)($_GET['id']??0),$actor);
    }
}catch(DomainException $e){http_response_code(403);$error=$e->getMessage();$page='notfound';}
catch(Throwable $e){http_response_code(503);$page='unavailable';$error='Admissions are temporarily unavailable. Contact the institute.';}
<<<<<<< HEAD
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
require __DIR__.'/app/applicant-views.php';
=======
$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
try{require __DIR__.'/app/applicant-views.php';}catch(Throwable $e){ob_clean();http_response_code(503);echo 'Admissions are temporarily unavailable. Contact the institute.';error_log('Northstar public admissions view failed.');}
>>>>>>> parent of 549483e (new)
