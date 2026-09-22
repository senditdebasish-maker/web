<?php
declare(strict_types=1);
header('Location: student.php?page=admissions', true, 301);
exit;
ini_set('display_errors','0'); ob_start();
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/applications.php'; require_once __DIR__.'/app/site-accounts.php';
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
ini_set('session.use_strict_mode','1'); session_name('northstar_applicant'); session_set_cookie_params(sessionCookieParams()); session_start();
if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>1800){$_SESSION=[];session_regenerate_id(true);} $_SESSION['last_seen']=time(); $_SESSION['csrf']??=bin2hex(random_bytes(32));
$page=is_string($_GET['page']??null)?$_GET['page']:'courses'; $error=null; $actor=null; $application=null; $course=null;
try {
    if(!applicationsReady()){http_response_code(503);$page='unavailable';}
    else {
        $actor=currentApplicant();
        if($_SERVER['REQUEST_METHOD']==='POST'){
            requireCookies(); if(!hash_equals($_SESSION['csrf'],input('csrf',128)))fail('Your form expired. Refresh the page and try again.');
            $action=input('action',40); $next='login';
            if($action==='request_code') requestApplicantCode();
            elseif($action==='verify_code'){verifyApplicantCode();$next='dashboard';}
            elseif($action==='logout'){$_SESSION=[];session_regenerate_id(true);$next='courses';}
            elseif(in_array($action,['submit_application','save_application_draft','revise_application','withdraw_application','upload_certificate'],true)){ $next='dashboard'; if($action!=='upload_certificate') applicantMutation($action); }
            else fail('Applicants cannot perform this action.');
            header('Location: apply.php?page='.$next); exit;
        }
        if(!in_array($page,['courses','course','login','dashboard','apply','application','help'],true)){http_response_code(404);$page='notfound';}
    }
} catch(DomainException $e){$error=$e->getMessage();} catch(Throwable $e){http_response_code(503);$error='Admissions are temporarily unavailable. Contact the institute.';$page='unavailable';error_log('Admissions entry point failed.');}
try {
    if(!in_array($page,['unavailable','notfound'],true)){
        $actor=currentApplicant();
        if(in_array($page,['dashboard','apply','application'],true)&&!$actor)$page='login';
        if(in_array($page,['course','apply'],true)){$course=publicCourses(0,(int)($_GET['course']??0))[0]??null;if(!$course){http_response_code(404);$page='notfound';}}
        if($page==='application'&&$actor)$application=ownApplication((int)($_GET['id']??0),$actor);
    }
} catch(Throwable $e){http_response_code(503);$page='unavailable';$error='Admissions are temporarily unavailable. Contact the institute.';}
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
require __DIR__.'/app/applicant-views.php';
