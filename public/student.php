<?php
declare(strict_types=1);
ini_set('display_errors','0'); ob_start();
require dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/otp.php';
require_once dirname(__DIR__).'/app/documents.php';
require_once dirname(__DIR__).'/app/operations.php';
require_once dirname(__DIR__).'/app/services.php';
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; img-src 'self' data:; form-action 'self'; base-uri 'none'; object-src 'none'");
ini_set('session.use_strict_mode','1'); session_name('northstar_student');
session_set_cookie_params(['httponly'=>true,'secure'=>$config['secure_cookies'] ?? false,'samesite'=>'Lax','path'=>'/']); session_start();
if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>1800) { $_SESSION=[]; session_regenerate_id(true); }
$_SESSION['last_seen']=time(); $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error=null; $student=null; $ready=portalReady();
$page=is_string($_GET['page'] ?? null)?$_GET['page']:'dashboard';
try {
    if (!$ready) { http_response_code(503); $page='unavailable'; }
    else {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            if (!hash_equals($_SESSION['csrf'],input('csrf',128))) fail('Your form expired. Refresh and try again.');
            $action=input('action',40);
            if ($action==='request_otp') $next=requestOtp('student');
            elseif ($action==='verify_otp') $next=verifyOtp('student');
            elseif (in_array($action,['support_create','support_reply'],true)) $next=studentSupportAction($action);
            elseif ($action==='logout') {
                $s=portalStudent(); if($s) portalEvent((int)$s['id'],'logout');
                $_SESSION=[];session_regenerate_id(true);$next='login';
            } else fail('Students cannot perform this action.');
            header('Location: student.php?page='.$next);exit;
        }
        $student=portalStudent();
        if (isset($_GET['document'])) {
            if (!$student) { http_response_code(401); exit('Please sign in to your student portal.'); }
            // The owner is always derived from the session, never from a URL/form student ID.
            $document=one('SELECT * FROM documents WHERE id=? AND student_id=? AND institute_id=?',[(int)$_GET['document'],$student['id'],$student['institute_id']]);
            if (!$document) { http_response_code(404); exit('Document not found.'); }
            $pdf=renderDocument($document); portalEvent((int)$student['id'],'document_download',(int)$document['id']);
            ob_clean();header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.documentFilename($document).'"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
        }
        if (!$student) $page='login';
        elseif ($page==='login') { header('Location: student.php');exit; }
        elseif (!in_array($page,['dashboard','payments','documents','profile','academics','results','announcements','support'],true)) { http_response_code(404);$page='notfound'; }
        if($student && $page==='support' && isset($_GET['ticket']) && servicesReady()) ticketForStudent($student,(int)$_GET['ticket']);
    }
} catch(DomainException $e) { $error=$e->getMessage();if($page==='support' && isset($_GET['ticket'])){unset($_GET['ticket']);if($_SERVER['REQUEST_METHOD']==='GET')http_response_code(403);}$student=$ready?portalStudent():null;if(!$student)$page='login'; }
catch(Throwable $e) { http_response_code(503);$error='Your portal is temporarily unavailable. Please contact your institute.';$page='unavailable';error_log('Northstar student portal operation failed.'); }
$flash=$_SESSION['flash'] ?? null;unset($_SESSION['flash']);
require dirname(__DIR__).'/app/student-views.php';
