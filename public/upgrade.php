<?php
declare(strict_types=1);
ini_set('display_errors','0');
require dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/portal.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');session_name('northstar_session');
session_set_cookie_params(['httponly'=>true,'secure'=>$config['secure_cookies'] ?? false,'samesite'=>'Lax','path'=>'/']);session_start();
if(isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen']>1800){$_SESSION=[];session_regenerate_id(true);}
$_SESSION['last_seen']=time();$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$user=currentUser();$error=null;$success=false;
if(!$user || $user['role']!=='owner') { http_response_code(403);exit('Only the signed-in group owner can upgrade this installation. Sign in at index.php, then open upgrade.php again.'); }
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!hash_equals($_SESSION['csrf'],input('csrf',128))) fail('The form expired. Refresh and try again.');
        if(input('backup',3,false)!=='yes') fail('Confirm that you have backed up your database and config.php.');
        require_once dirname(__DIR__).'/app/migrations.php';
        migrateCommunications();
        audit('portal_schema_upgraded','users',(int)$user['id']);$success=true;
    } catch(DomainException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Upgrade could not complete. Check database CREATE/INDEX permissions. This migration is additive and can be retried; no student records are deleted.';}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student portal upgrade</title><link rel="stylesheet" href="assets/student.css"></head><body><main class="sp-login"><section class="sp-login-intro"><div class="sp-eyebrow">YOUR NEXT CHAPTER</div><h1>A student space.<br>Without starting over.</h1><p>Add student access while preserving your existing institute, admission and payment records.</p></section><section class="sp-login-card"><h2>Student portal upgrade</h2><?php if($error): ?><div class="sp-alert error"><?=e($error)?></div><?php endif; ?><?php if($success): ?><div class="sp-alert">Upgrade complete. Go to Students, check each personal email, and enable portal access. No student is enabled automatically.</div><?php endif; ?><p>Signed in as <?=e($user['name'])?>. This adds portal accounts, OTP storage and student activity logs. It does not recreate your owner account or replace SMTP settings.</p><p class="sp-small">Portal tables: <?=portalReady()?'ready':'upgrade required'?>. Running the upgrade again is safe. Back up first; MySQL schema changes cannot be fully rolled back.</p><form method="post"><?=csrf()?><label><span><input type="checkbox" name="backup" value="yes" required> I have backed up my database and config.php.</span></label><button class="sp-button">Run safe portal upgrade →</button></form><p class="sp-small"><a href="index.php?page=students">Return to Students →</a> · <a href="student.php">Open student portal ↗</a></p></section></main></body></html>
