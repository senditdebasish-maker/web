<?php
declare(strict_types=1);
ini_set('display_errors','0'); ob_start();
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/otp.php'; require_once __DIR__.'/app/documents.php'; require_once __DIR__.'/app/operations.php'; require_once __DIR__.'/app/services.php'; require_once __DIR__.'/app/online-payments.php'; require_once __DIR__.'/app/applications.php';
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
ini_set('session.use_strict_mode','1'); session_name('northstar_site'); session_set_cookie_params(sessionCookieParams()); session_start();
if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>1800){$_SESSION=[];session_regenerate_id(true);} $_SESSION['last_seen']=time(); $_SESSION['csrf']??=bin2hex(random_bytes(32));
$error=null; $student=null; $portalUser=null; $applicant=null; $ready=portalReady(); $page=is_string($_GET['page']??null)?$_GET['page']:'dashboard';
try {
    if(!$ready){http_response_code(503);$page='unavailable';}
    else {
        $student=portalStudent(); $portalUser=currentStudentUser(); $applicant=currentApplicant();
        if($portalUser&&!$student){linkStudentUser($portalUser);$student=portalStudent();}
        if($portalUser)syncStudentApplicant($portalUser);
        if(!$student&&!$portalUser&&!$applicant){header('Location: index.php?page=login');exit;}
        if($_SERVER['REQUEST_METHOD']==='POST'){
            requireCookies(); if(!hash_equals($_SESSION['csrf'],input('csrf',128)))fail('Your form expired. Refresh the page and try again.');
            $action=input('action',40);
            if($action==='request_otp')$next=requestOtp('student');
            elseif($action==='verify_otp')$next=verifyOtp('student');
            elseif(in_array($action,['register_request','register_verify'],true)){header('Location: index.php?page=create-account');exit;}
            elseif(in_array($action,['support_create','support_reply'],true))$next=studentSupportAction($action);
            elseif($action==='profile_update')$next=updateStudentProfile();
            elseif(in_array($action,['submit_application','save_application_draft','revise_application','withdraw_application'],true)){$next='applications';applicantMutation($action);}
            elseif($action==='upload_certificate'){$applicationId=uploadCertificate();header('Location: student.php?page=application&id='.$applicationId);exit;}
            elseif($action==='logout'){if(function_exists('clearSiteIdentity'))clearSiteIdentity();$_SESSION=[];session_regenerate_id(true);$next='login';}
            else fail('Students cannot perform this action.');
            header('Location: student.php?page='.$next);exit;
        }
        if(isset($_GET['certificate']) && $applicant){
            if(!automationReady())fail('Certificate downloads are not enabled.');
            $certificate=one('SELECT c.* FROM certificates c JOIN admission_applications a ON a.id=c.application_id WHERE c.id=? AND a.applicant_id=?',[(int)$_GET['certificate'],$applicant['id']]);
            if(!$certificate)fail('Certificate not found.');
            serveCertificateFile($certificate);
        }
        if(!$student&&!$portalUser&&!$applicant && $page!=='login'){$page='login';}
        elseif(!$student&&($portalUser||$applicant) && !in_array($page,['dashboard','courses','admissions','course','apply','applications','application','profile','inquiries'],true))$page='dashboard';
        elseif($page==='login'||$page==='register'){header('Location: student.php');exit;}
        elseif(!in_array($page,['dashboard','courses','admissions','course','apply','applications','application','payments','documents','profile','academics','results','announcements','support'],true)){http_response_code(404);$page='notfound';}
    }
} catch(DomainException $e){$error=$e->getMessage();$student=$ready?portalStudent():null;$portalUser=$ready?currentStudentUser():null;$applicant=$ready?currentApplicant():null;if(!$student)$page=($portalUser||$applicant)?'dashboard':'login';}
catch(Throwable $e){http_response_code(503);$error='Your portal is temporarily unavailable. Please contact your institute.';$page='unavailable';error_log('Student portal entry point failed.');}
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']); require __DIR__.'/app/student-views.php';
