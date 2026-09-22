<?php
declare(strict_types=1);
ini_set('display_errors','0'); ob_start();
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/otp.php';
require_once __DIR__.'/app/documents.php';
require_once __DIR__.'/app/operations.php';
require_once __DIR__.'/app/services.php';
require_once __DIR__.'/app/online-payments.php';
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://checkout.razorpay.com; style-src 'self'; img-src 'self' data: https://*.razorpay.com; frame-src https://api.razorpay.com https://*.razorpay.com; connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com; form-action 'self'; base-uri 'none'; object-src 'none'");
ini_set('session.use_strict_mode','1'); session_name('northstar_student');
session_set_cookie_params(sessionCookieParams()); session_start();
if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>1800) { $_SESSION=[]; session_regenerate_id(true); }
$_SESSION['last_seen']=time(); $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error=null; $student=null; $portalUser=null; $ready=portalReady();
$page=is_string($_GET['page'] ?? null)?$_GET['page']:'dashboard';
try {
    if (!$ready) { http_response_code(503); $page='unavailable'; }
    else {
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            requireCookies();
            if (in_array(input('action',40),['request_otp','verify_otp','register_request','register_verify'],true)) { header('Location: index.php?page=login'); exit; }
            if (!hash_equals($_SESSION['csrf'],input('csrf',128))) fail('Your form expired. Refresh and try again.');
            $action=input('action',40);
            if (in_array($action,['support_create','support_reply'],true)) $next=studentSupportAction($action);
            elseif ($action==='online_order') {
                $s=portalStudent();if(!$s)fail('Please sign in to pay online.');
                $oid=beginOnlineOrder($s,input('amount',12));
                $o=one('SELECT * FROM online_orders WHERE id=?',[$oid]);
                $_SESSION['flash']=$o['state']==='Uncertain'?'Order saved but gateway confirmation failed. Do not pay again; ask the office to reconcile receipt '.$o['receipt'].'.':'Online order prepared. Complete payment, then use Check payment status. Do not create another order while one is pending.';
                header('Location: student.php?page=payments&order='.$oid);exit;
            }
            elseif ($action==='online_sync') {
                $s=portalStudent();if(!$s)fail('Please sign in to verify payment.');
                $oid=(int)input('order_id');$o=one('SELECT * FROM online_orders WHERE id=? AND student_id=?',[$oid,$s['id']]);if(!$o)fail('Order not accessible.');
                $result=synchronizeOnlineOrder($oid,null,false);
                $_SESSION['flash']='Payment verification result: '.$result.'. Only Credited/Test results are final; Pending means no captured payment was found yet.';
                header('Location: student.php?page=payments&order='.$oid);exit;
            }
            elseif ($action==='logout') {
                $s=portalStudent(); if($s) portalEvent((int)$s['id'],'logout');
                $_SESSION=[];session_regenerate_id(true);header('Location: index.php?page=login');exit;
            } else fail('Students cannot perform this action.');
            header('Location: student.php?page='.$next);exit;
        }
        $student=portalStudent();
        $portalUser=currentStudentUser();
        if($portalUser && !$student){ linkStudentUser($portalUser); $student=portalStudent(); }
        if (isset($_GET['document'])) {
            if (!$student) { http_response_code(401); exit('Please sign in to your student portal.'); }
            $document=one('SELECT * FROM documents WHERE id=? AND student_id=? AND institute_id=?',[(int)$_GET['document'],$student['id'],$student['institute_id']]);
            if (!$document) { http_response_code(404); exit('Document not found.'); }
            $pdf=renderDocument($document); portalEvent((int)$student['id'],'document_download',(int)$document['id']);
            ob_clean();header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.documentFilename($document).'"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
        }
        if (!$student && !$portalUser) { header('Location: index.php?page=login'.($page==='register'?'&show=create':'')); exit; }
        elseif ($portalUser && !$student) $page='account';
        elseif ($page==='login'||$page==='register') { header('Location: student.php');exit; }
        elseif (!in_array($page,['dashboard','payments','documents','profile','academics','results','announcements','support'],true)) { http_response_code(404);$page='notfound'; }
        if($student && $page==='support' && isset($_GET['ticket']) && servicesReady()) ticketForStudent($student,(int)$_GET['ticket']);
    }
} catch(DomainException $e) { $error=$e->getMessage();if($page==='support' && isset($_GET['ticket'])){unset($_GET['ticket']);if($_SERVER['REQUEST_METHOD']==='GET')http_response_code(403);}$student=$ready?portalStudent():null;$portalUser=$ready?currentStudentUser():null;if(!$student && !$portalUser){ header('Location: index.php?page=login'); exit; } if(!$student)$page='account'; }
catch(Throwable $e) { http_response_code(503);$error='Your portal is temporarily unavailable. Please contact your institute.';$page='unavailable';error_log('Northstar student portal operation failed.'); }
$flash=$_SESSION['flash'] ?? null;unset($_SESSION['flash']);
require __DIR__.'/app/student-views.php';
