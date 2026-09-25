<?php
declare(strict_types=1);
ini_set('display_errors','0');
// Buffer rendering so authorization/error responses can still set the correct status.
ob_start();
require __DIR__ . '/app/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; object-src 'none'");
ini_set('session.use_strict_mode','1');
session_name('northstar_session');
session_set_cookie_params(sessionCookieParams());
session_start();
if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>1800) { $_SESSION=[]; session_regenerate_id(true); }
$_SESSION['last_seen']=time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
if (!currentUser() && !defined('CRM_DOCUMENT_REQUEST') && !isset($_GET['certificate'])) { header('Location: index.php?page=login'); exit; }
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
require __DIR__ . '/app/actions.php';
$page=is_string($_GET['page'] ?? null) ? $_GET['page'] : 'dashboard';
$error=null;
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        requireCookies();
        $next=handleAction(); if ($next==='login') { header('Location: index.php?page=login'); exit; } $_SESSION['flash'] ??='Changes saved successfully.'; redirect($next);
    }
    $user=currentUser();
    if (defined('CRM_DOCUMENT_REQUEST')) {
        if (!$user) { http_response_code(401); exit('Please sign in to download documents.'); }
        $document=record('documents',(int)($_GET['id'] ?? 0));
        if ($document['kind']==='payment') requireRole(['owner','admin']);
        $pdf=renderDocument($document);
        ob_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.documentFilename($document).'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf; exit;
    }
    if (isset($_GET['certificate'])) {
        if (!$user) { http_response_code(401); exit('Please sign in to download uploads.'); }
        requireRole(['owner','admin']);
        if(!automationReady()){http_response_code(503);exit('Automation upgrade required.');}
        $c=one('SELECT * FROM certificates WHERE id=?',[(int)$_GET['certificate']]);if(!$c){http_response_code(404);exit('Upload not found.');}
        staffApplication((int)$c['application_id']);
        ob_clean();serveCertificateFile($c);
    }
    if (!$user) $page='login';
    elseif ($page==='login') redirect('dashboard');
    $allowed=['dashboard','institutes','courses','staff','enquiries','followups','admissions','students','settings','audit','payments','documents','notifications','teachers','batches','attendance','fee-plans','fee-reports','health','exams','announcements','support','applications','student-accounts'];
    if ($user && !in_array($page,$allowed,true)) { http_response_code(404); $page='notfound'; }
    if ($user && in_array($page,['staff','audit','payments','notifications','teachers','batches','attendance','fee-plans','fee-reports','health','exams','announcements','support','applications','student-accounts'],true)) requireRole(['owner','admin']);
} catch (DomainException $ex) { if(defined('CRM_DOCUMENT_REQUEST')) { http_response_code(403); exit('Document not accessible.'); } if(isset($_GET['certificate'])){http_response_code(403);exit('Upload not accessible.');} $error=$ex->getMessage(); $user=currentUser(); if (!$user) { header('Location: index.php?page=login'); exit; } }
catch (Throwable $ex) { if(defined('CRM_DOCUMENT_REQUEST')) { http_response_code(503); exit('PDF unavailable. Ask your administrator to check Composer dependencies.'); } if(isset($_GET['certificate'])){http_response_code(503);exit('Upload unavailable.');} error_log((string)$ex); $error='The request could not be saved. Check for duplicate records or try again. If this continues, contact your administrator.'; $user=null; try { $user=currentUser(); } catch(Throwable $ignored) {} if (!$user) { header('Location: index.php?page=login'); exit; } }
$flash=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
require __DIR__ . '/app/views.php';
