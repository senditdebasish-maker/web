<?php
declare(strict_types=1);
ini_set('display_errors','0');
ob_start();
require dirname(__DIR__).'/app/setup.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'none'; img-src 'self' data:; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'");
ini_set('session.use_strict_mode','1');
session_name('northstar_setup');
session_set_cookie_params(['httponly'=>true,'secure'=>setupHttps(),'samesite'=>'Strict','path'=>'/']);
session_start();
if (isset($_SESSION['setup_seen']) && time()-$_SESSION['setup_seen']>1800) { $_SESSION=[]; session_regenerate_id(true); }
$_SESSION['setup_seen']=time();
$_SESSION['setup_csrf'] ??= bin2hex(random_bytes(32));
$error=null; $step=0; $locked=setupLocked();
$done=$locked && !empty($_SESSION['setup_complete']) && $_SERVER['REQUEST_METHOD']!=='POST';
try {
    if (!$locked) {
        setupKey();
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $next=setupHandle(); header('Location: setup.php?step='.$next); exit;
        }
        $step=min(max(0,(int)($_GET['step'] ?? setupMaxStep())),setupMaxStep());
        if (setupAuthorized() && $step===0) $step=1;
    } elseif (!$done) http_response_code(403);
} catch(DomainException $e) {
    $error=$e->getMessage();
    $step=min(max(0,(int)($_GET['step'] ?? 0)),setupMaxStep());
} catch(Throwable $e) {
    http_response_code(503);
    $error='Setup cannot continue. Check private storage permissions and PHP requirements. No credentials or database errors are shown here.';
}
$d=$_SESSION['setup_draft'] ?? [];
$m=$d['mail'] ?? [];
date_default_timezone_set($d['timezone'] ?? 'Asia/Kolkata');
$notice=$_SESSION['setup_notice'] ?? null; unset($_SESSION['setup_notice']);
function setupForm(string $action): void {
    echo '<form method="post" class="setup-form"><input type="hidden" name="csrf" value="'.e($_SESSION['setup_csrf']).'"><input type="hidden" name="action" value="'.e($action).'">';
}
function setupField(string $label,string $name,string $value='',string $type='text',bool $required=true,string $help=''): void {
    // Secrets are never repopulated, not even after a failed submission.
    if ($type!=='password' && isset($_POST[$name]) && is_string($_POST[$name])) $value=$_POST[$name];
    if ($type==='password') $value='';
    echo '<label>'.e($label).'<input name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" '.($required?'required':'').' maxlength="512" autocomplete="'.($type==='password'?'new-password':'off').'">';
    if ($help!=='') echo '<small>'.e($help).'</small>';
    echo '</label>';
}
function setupSelect(string $label,string $name,array $choices,string $value): void {
    if (isset($_POST[$name]) && is_string($_POST[$name])) $value=$_POST[$name];
    echo '<label>'.e($label).'<select name="'.e($name).'">';
    foreach($choices as $key=>$text) echo '<option value="'.e($key).'" '.((string)$key===$value?'selected':'').'>'.e($text).'</option>';
    echo '</select></label>';
}
function setupButton(string $label): void { echo '<button class="setup-button" type="submit">'.e($label).' <span>→</span></button></form>'; }
$steps=['Secure access','Server checks','Database','Your institute','Email & OTP','Review & install'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Set up Northstar · Institute CRM</title><link rel="stylesheet" href="assets/setup.css"></head><body>
<div class="setup-shell"><aside class="setup-sidebar"><a class="setup-brand" href="setup.php"><span>N</span> northstar.</a><div class="setup-sidebar-body"><p class="eyebrow">A BETTER BEGINNING</p><h1>Your institutes.<br>Your workspace.</h1><p>Let's get everything ready.<br>One simple step at a time.</p><ol class="setup-steps"><?php foreach($steps as $i=>$label): ?><li class="<?=$done || (!$locked && $i<$step)?'complete':(!$locked && $i===$step?'current':'')?>"><span><?=$done || (!$locked && $i<$step)?'✓':$i+1?></span><div><?=e($label)?><?php if(!$locked && $i===$step): ?><small>YOU ARE HERE</small><?php endif; ?></div></li><?php endforeach; ?></ol></div><div class="setup-security">◈ &nbsp; Credentials stay on your server.<br><small>No secrets are sent to Northstar.</small></div></aside>
<main class="setup-main"><header><span>INSTITUTE CRM / INSTALLATION</span><span class="setup-chip"><?=$done?'Ready to go':($locked?'Installer locked':'PHPMailer + email OTP')?></span></header><div class="setup-content">
<?php if($error): ?><div class="setup-alert error" role="alert"><?=e($error)?></div><?php endif; ?>
<?php if($notice): ?><div class="setup-alert" role="status"><?=e($notice)?></div><?php endif; ?>
<?php if($done): ?>
<div class="setup-success-icon">✓</div><p class="eyebrow">ALL SET</p><h2>Your workspace is ready.</h2><p class="setup-intro">The database, owner account and PHPMailer settings are saved. Setup is now locked. Sign in using a new code sent to your verified owner email.</p>
<a class="setup-button" href="index.php">Open CRM login <span>→</span></a>
<div class="setup-card"><h3>One last thing: student email delivery</h3><p>Login codes send immediately. Admission letters, payment receipts and applicant status alerts use a background worker.</p><code>php bin/send-notifications.php --limit=25</code><p>Schedule this command every minute in Windows Task Scheduler or your hosting control panel's Cron Jobs. The website cannot create operating-system tasks for you.</p><p>Windows program: <code>C:\xampp\php\php.exe</code><br>Arguments: your project path + <code>\bin\send-notifications.php --limit=25</code></p></div>
<?php elseif($locked): ?>
<p class="eyebrow">PROTECTED WORKSPACE</p><h2>Setup is locked.</h2><p class="setup-intro">A configuration, completed installation, or recovery file already exists. This page cannot change credentials, overwrite your database, or create another owner.</p><a class="setup-button" href="index.php">Go to CRM login <span>→</span></a><div class="setup-card"><h3>Updating an existing installation?</h3><p>Use the upgrade instructions in README.md. Do not remove configuration or installer locks on a live website. A server operator must review recovery files and backups before making any changes.</p></div>
<?php elseif(!setupAllowedTransport()): ?>
<p class="eyebrow">SECURE CONNECTION REQUIRED</p><h2>Let's protect your settings.</h2><p class="setup-intro">Open this page over HTTPS before entering credentials. For XAMPP on your own computer, use <code>http://localhost/institute-crm/public/setup.php</code>.</p><div class="setup-card"><p>If your host terminates HTTPS at a trusted proxy, ask the server administrator to configure <code>CRM_TRUST_HTTPS_PROXY=1</code> only when that proxy is trusted. Arbitrary forwarded headers are not trusted automatically.</p></div>
<?php elseif($step===0): ?>
<p class="eyebrow">STEP 01 / SECURE ACCESS</p><h2>Welcome to your new workspace.</h2><p class="setup-intro">Before we begin, confirm that you control this server. We've created a private setup key for you. No terminal commands or PHP editing needed.</p>
<div class="setup-card"><h3>Find your setup key</h3><ol><li>Open this project's <strong>storage</strong> folder in File Explorer or your hosting file manager.</li><li>Open <code>setup-key.txt</code> with a text editor.</li><li>Copy its complete key and paste it below.</li></ol><p>This key is not displayed on the website. Do not share it or put it in a public URL.</p></div>
<?php setupForm('unlock'); setupField('Server setup key','setup_key','','password'); setupButton('Unlock setup'); ?>
<?php elseif($step===1): $checks=setupChecks(); ?>
<p class="eyebrow">STEP 02 / SERVER CHECKS</p><h2>A quick check before we start.</h2><p class="setup-intro">We'll check your PHP installation and prepare the right settings for your computer or live server.</p>
<div class="setup-checks"><?php foreach($checks as $label=>$ok): ?><div><span><?=e($label)?></span><b class="<?=$ok?'ok':'not-ok'?>"><?=$ok?'✓ Ready':'Needs attention'?></b></div><?php endforeach; ?></div>
<?php if(in_array(false,$checks,true)): ?><div class="setup-alert error"><strong>Missing libraries or extensions?</strong><br>Use a ready-to-install package containing <code>vendor/</code>, or run <code>composer install --no-dev --prefer-dist</code> once in the project folder. Enable missing extensions in XAMPP's php.ini and restart Apache. The browser never downloads or executes dependency-install commands.</div><?php endif; ?>
<?php setupForm('requirements'); ?><div class="setup-grid"><?php setupSelect('Installation type','environment',['local'=>'Local XAMPP / private demo','production'=>'Live server (HTTPS required)'],$d['environment'] ?? (setupLocalRequest()?'local':'production')); setupSelect('Timezone','timezone',array_combine(DateTimeZone::listIdentifiers(),DateTimeZone::listIdentifiers()),$d['timezone'] ?? 'Asia/Kolkata'); ?></div><p class="setup-hint">Live mode requires HTTPS, a database password and real SMTP delivery. Local mode also allows SQLite and private test-email capture.</p><?php setupButton('Continue to database'); ?>
<?php elseif($step===2): ?>
<p class="eyebrow">STEP 03 / DATABASE</p><h2>A home for your institute data.</h2><p class="setup-intro">Choose a new, empty database. Existing data is never erased. For standard XAMPP, the defaults below usually work.</p>
<?php setupForm('database'); setupSelect('Database engine','engine',$d['environment']==='local'?['mysql'=>'MySQL / MariaDB — recommended','sqlite'=>'SQLite — private local demo only']:['mysql'=>'MySQL / MariaDB'],$d['engine'] ?? 'mysql'); ?><div class="setup-grid"><?php setupField('Database host','db_host',$d['db_host'] ?? '127.0.0.1','text',false); setupField('Port','db_port',(string)($d['db_port'] ?? 3306),'text',false); setupField('Database name','db_name',$d['db_name'] ?? 'institute_crm','text',false); setupField('Username','db_user',$d['db_user'] ?? 'root','text',false); setupField('Database password','db_password','','password',false,'Default local XAMPP may use a blank password. Re-enter if you retest the connection.'); ?></div><label class="setup-checkbox"><input type="checkbox" name="create_database" value="1"> Create this database if it does not exist (requires CREATE permission).</label><p class="setup-hint">For SQLite, MySQL fields are ignored; the database is stored privately in storage/crm.sqlite. On shared hosting, create a MySQL database in your hosting panel first.</p><?php setupButton('Test connection & continue'); ?>
<?php elseif($step===3): ?>
<p class="eyebrow">STEP 04 / YOUR INSTITUTE</p><h2>Make this workspace yours.</h2><p class="setup-intro">Create the group owner account. Use an email inbox you can access—we'll verify it before installation.</p>
<?php setupForm('owner'); ?><div class="setup-grid"><?php setupField('Owner full name','owner_name',$d['owner_name'] ?? ''); setupField('Owner email / OTP login','owner_email',$d['owner_email'] ?? '','email'); ?></div><div class="setup-divider"></div><h3>Your first institute <span class="optional">Optional</span></h3><p class="setup-hint">You can add more pharma, medical or other institutes after signing in.</p><div class="setup-grid"><?php setupField('Institute name','institute_name',$d['institute_name'] ?? '','text',false); setupField('Type / specialization','institute_kind',$d['institute_kind'] ?? '','text',false); setupField('City','institute_city',$d['institute_city'] ?? '','text',false,'Required when adding an institute.'); setupField('Institute contact number','institute_phone',$d['institute_phone'] ?? '','tel',false,'Required when adding an institute.'); ?></div><?php setupButton('Continue to email setup'); ?>
<?php elseif($step===4): ?>
<p class="eyebrow">STEP 05 / EMAIL & OTP</p><h2>Real emails. A verified inbox.</h2><p class="setup-intro">PHPMailer will send login codes and student PDFs through your SMTP account. Send a test code to <strong><?=e($d['owner_email'])?></strong> to confirm delivery.</p>
<?php if(!empty($d['mail_verified'])): ?><div class="setup-alert">This configuration is verified. <a href="setup.php?step=5">Continue to review →</a>. Sending another test invalidates the previous verification.</div><?php endif; ?>
<?php if(isset($d['mail_proof'])): ?><section class="setup-card verification"><h3>Check your owner inbox</h3><p>Enter the newest six-digit code within five minutes. Five attempts allowed.</p><?php setupForm('verify_mail'); ?><label>Verification code<input name="verification_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="setup-code"></label><?php setupButton('Verify email & continue'); ?></section><?php endif; ?>
<?php setupForm('test_mail'); setupSelect('Email delivery','mail_transport',$d['environment']==='local'?['smtp'=>'PHPMailer SMTP — Gmail or your mail provider','log'=>'Private local capture — no real emails']:['smtp'=>'PHPMailer SMTP — Gmail or your mail provider'],$m['transport'] ?? 'smtp'); ?><div class="setup-grid"><?php setupField('Sender name','from_name',$m['from_name'] ?? ($d['institute_name'] ?: 'My Institute')); setupField('Sender email','from_email',$m['from_email'] ?? $d['owner_email'],'email'); setupField('SMTP host','smtp_host',$m['host'] ?? 'smtp.gmail.com','text',false); setupField('SMTP username','smtp_username',$m['username'] ?? $d['owner_email'],'text',false); setupSelect('Encryption','smtp_encryption',['tls'=>'STARTTLS (port 587)','smtps'=>'SMTPS (port 465)'],$m['encryption'] ?? 'tls'); setupSelect('SMTP port','smtp_port',['587'=>'587','465'=>'465'],(string)($m['port'] ?? 587)); setupField('Gmail App Password / SMTP password','smtp_password','','password',false,isset($m['password'])?'Password saved privately for this session. Leave blank to keep it for this same SMTP account.':'Use a Google App Password, not your normal Gmail password.'); ?></div><div class="setup-card"><h3>Using Gmail?</h3><p>Enable two-step verification on your Google account, then create an App Password. Use your Gmail address for the username and sender. Paste the App Password above. If your organization blocks App Passwords, ask its administrator for an approved SMTP account.</p><p>For local capture, SMTP fields are ignored. Read the code from storage/mail/ on your server; it is never shown publicly.</p></div><?php setupButton('Send verification code with PHPMailer'); ?><p class="setup-hint">Please wait 60 seconds between sends. Sending a test does not create your owner account yet.</p>
<?php elseif($step===5): ?>
<p class="eyebrow">STEP 06 / REVIEW & INSTALL</p><h2>Everything in its right place.</h2><p class="setup-intro">Check these settings, then create your workspace. Secrets are never shown in this review.</p>
<dl class="setup-review"><?php foreach(['Mode'=>ucfirst($d['environment']),'Timezone'=>$d['timezone'],'Database'=>$d['engine']==='mysql'?$d['db_name'].' @ '.$d['db_host'].':'.$d['db_port']:'Private local SQLite','Owner'=>$d['owner_name'].' · '.$d['owner_email'],'First institute'=>$d['institute_name'] ?: 'Add later','Email transport'=>$m['transport']==='smtp'?'PHPMailer · '.$m['host'].':'.$m['port']:'Private local capture (no real delivery)','Sender'=>$m['from_name'].' <'.$m['from_email'].'>','Owner mailbox'=>'Verified','Login method'=>'Email OTP only'] as $label=>$value): ?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach; ?></dl>
<?php if($m['transport']==='log'): ?><div class="setup-alert error">This is a local test installation. Emails will be captured in private files, not delivered to Gmail. Do not publish it as a live service.</div><?php endif; ?>
<?php setupForm('install'); ?><label class="setup-checkbox"><input type="checkbox" name="confirm_install" value="yes" required> I confirm this is a new installation using an empty database. I understand setup will lock after completion.</label><?php setupButton('Install my institute CRM'); ?>
<?php endif; ?>
<?php if(!$locked && $step>0 && setupAllowedTransport()): ?><div class="setup-navigation"><?php if($step>1): ?><a href="setup.php?step=<?=$step-1?>">← Previous step</a><?php endif; ?><?php setupForm('cancel'); ?><button class="setup-text-button">Cancel & clear private session</button></form></div><?php endif; ?>
</div><footer>Northstar Institute CRM <span>Secure setup · No credentials in Git</span></footer></main></div></body></html>
