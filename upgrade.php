<?php
declare(strict_types=1);
ini_set('display_errors','0');
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/portal.php';
require_once __DIR__.'/app/operations.php';
require_once __DIR__.'/app/services.php';
require_once __DIR__.'/app/applications.php';
require_once __DIR__.'/app/automation.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');session_name('northstar_session');
session_set_cookie_params(sessionCookieParams());session_start();
if(isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>1800){$_SESSION=[];session_regenerate_id(true);}
$_SESSION['last_seen']=time();$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$user=currentUser();$error=null;$success=false;
if(!$user || $user['role']!=='owner') { http_response_code(403);exit('Only the signed-in group owner can upgrade this installation. Sign in at office.php, then open upgrade.php again.'); }
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!hash_equals($_SESSION['csrf'],input('csrf',128))) fail('The form expired. Refresh and try again.');
        if(input('backup',3,false)!=='yes') fail('Confirm that you have backed up your database and config.php.');
        require_once __DIR__.'/app/migrations.php';
        migrateCommunications();
        audit('portal_schema_upgraded','users',(int)$user['id']);$success=true;
    } catch(DomainException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Upgrade could not complete. Check database CREATE/INDEX permissions. This migration is additive and can be retried; no student records are deleted.';}
}
require_once __DIR__.'/app/site-chrome.php';
[$ugBrand,$ugKind,$ugCity,$ugAddr,$ugPhone]=siteBrand();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CRM operations upgrade</title><link rel="stylesheet" href="assets/university.css"><link rel="stylesheet" href="assets/theme.css"></head><body>
<?=siteHeader($ugBrand,$ugKind,'','<a class="u-btn ghost" href="office.php?page=dashboard">Office workspace</a>'.siteBackLink())?>
<main class="u-page u-page-narrow"><div class="u-page-head"><div class="u-eyebrow">SAFE MODULE UPGRADE</div><h1>CRM operations upgrade</h1><p>Add academic and fee-management modules while preserving your existing institute, admission and payment records.</p></div>
<section class="u-card"><?php if($error): ?><div class="u-alert error"><?=e($error)?></div><?php endif; ?><?php if($success): ?><div class="u-alert">Upgrade complete. Go to Students, check each personal email, and enable portal access. No student is enabled automatically. Teaching operations, exams, announcements, support, online applications and admissions automation are now available. Public course listings remain closed until staff opens them. Certificate uploads, eligibility automation and online payments stay disabled until explicitly configured.</div><?php endif; ?><p>Signed in as <?=e($user['name'])?>. This adds portal, academic, exam, announcement and support tables. It does not recreate your owner account or replace SMTP settings.</p><p class="u-small">Portal tables: <?=portalReady()?'ready':'upgrade required'?> · Operations: <?=operationsReady()?'ready':'upgrade required'?> · Student services: <?=servicesReady()?'ready':'upgrade required'?> · Public admissions: <?=applicationsReady()?'ready':'upgrade required'?> · Automation: <?=automationReady()?'ready':'upgrade required'?>. Running the upgrade again is safe. Back up first; MySQL schema changes cannot be fully rolled back.</p><form method="post"><div class="u-form"><?=csrf()?><label class="u-check"><input type="checkbox" name="backup" value="yes" required> I have backed up my database and config.php.</label><button class="u-btn solid">Run safe CRM upgrade →</button></div></form><p class="u-small"><a href="office.php?page=students">Return to Students →</a> · <a href="student.php">Open student portal ↗</a></p></section></main>
<?=siteFooter($ugBrand,$ugKind,$ugCity,$ugAddr,$ugPhone)?></body></html>
