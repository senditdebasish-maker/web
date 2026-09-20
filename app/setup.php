<?php
declare(strict_types=1);
require_once __DIR__.'/core.php';
require_once __DIR__.'/mail.php';
require_once __DIR__.'/schema.php';

function setupRoot(): string { return rtrim(getenv('CRM_SETUP_ROOT') ?: dirname(__DIR__),'/\\'); }
function setupConfigPath(): string { return getenv('CRM_CONFIG_FILE') ?: setupRoot().'/config.php'; }
function setupStorage(): string { return setupRoot().'/storage'; }
function setupLocked(): bool {
    return file_exists(setupConfigPath()) || file_exists(setupStorage().'/installed.lock') || file_exists(setupStorage().'/setup-recovery.php');
}
function setupHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ||
        (getenv('CRM_TRUST_HTTPS_PROXY')==='1' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')==='https');
}
function setupLocalRequest(): bool { return in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true); }
function setupAllowedTransport(): bool { return setupHttps() || setupLocalRequest(); }
/** The key is never returned to the browser. Reading the server filesystem proves ownership. */
function setupKey(): string {
    $dir=setupStorage();
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Setup storage is not writable.');
    $guard=fopen($dir.'/setup.guard','c');
    if (!$guard || !flock($guard,LOCK_EX)) throw new RuntimeException('Setup lock unavailable.');
    try {
        $file=$dir.'/setup-key.txt';
        if (!is_file($file)) {
            $key=bin2hex(random_bytes(32));
            if (file_put_contents($file,$key."\n",LOCK_EX)===false) throw new RuntimeException('Cannot save setup key.');
            chmod($file,0600);
        }
        $key=trim((string)file_get_contents($file));
        if (!preg_match('/^[a-f0-9]{64}$/D',$key)) throw new RuntimeException('Setup key file is invalid.');
        return $key;
    } finally { flock($guard,LOCK_UN); fclose($guard); }
}
function setupAuthorized(): bool {
    return isset($_SESSION['setup_authorized']) && hash_equals(hash('sha256',setupKey()),$_SESSION['setup_authorized']);
}
function setupChecks(): array {
    $extensions=['pdo','session','openssl','dom','mbstring','ctype','filter','hash','iconv','fileinfo','curl'];
    $checks=['PHP 8.2 or newer'=>PHP_VERSION_ID>=80200];
    foreach($extensions as $ext) $checks['PHP extension: '.$ext]=extension_loaded($ext);
    $checks['MySQL or SQLite database driver']=extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite');
    try { dependencies(); $deps=class_exists(\PHPMailer\PHPMailer\PHPMailer::class) && class_exists(\Dompdf\Dompdf::class); } catch(Throwable $e) { $deps=false; }
    $checks['PHPMailer and PDF libraries installed']=$deps;
    $checks['Private storage is writable']=is_dir(setupStorage()) && is_writable(setupStorage());
    $checks['Configuration directory is writable']=is_writable(dirname(setupConfigPath()));
    return $checks;
}
function setupRequireChecks(): void {
    if (in_array(false,setupChecks(),true)) fail('Fix the failed server checks before continuing. Use the ready-to-install package or run composer install for missing libraries.');
}
function setupMaxStep(): int {
    if (!setupAuthorized()) return 0;
    $d=$_SESSION['setup_draft'] ?? [];
    if (empty($d['requirements_ok'])) return 1;
    if (empty($d['database_ok'])) return 2;
    if (empty($d['owner_email'])) return 3;
    if (empty($d['mail_verified'])) return 4;
    return 5;
}
function setupMailFingerprint(array $d): string {
    return hash('sha256',json_encode([$d['owner_email'] ?? '',$d['mail'] ?? [],$d['environment'] ?? ''],JSON_THROW_ON_ERROR));
}
function setupConfiguration(array $d): array {
    return ['dsn'=>$d['dsn'],'user'=>$d['db_user'],'password'=>$d['db_password'],'timezone'=>$d['timezone'],
        'environment'=>$d['environment'],'secure_cookies'=>$d['environment']==='production' || setupHttps(),
        'auth_mode'=>'otp','mail'=>$d['mail'],'certificates'=>['enabled'=>false,'directory'=>'','scanner'=>'manual','clamav_host'=>'127.0.0.1','clamav_port'=>3310],'razorpay'=>['accounts'=>[]]];
}
function setupDatabase(array $d,bool $create): PDO {
    $opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5];
    if ($d['engine']==='mysql') {
        $server=new PDO('mysql:host='.$d['db_host'].';port='.$d['db_port'].';charset=utf8mb4',$d['db_user'],$d['db_password'],$opts);
        if ($create) $server->exec('CREATE DATABASE IF NOT EXISTS `'.$d['db_name'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    $pdo=new PDO($d['dsn'],$d['db_user'],$d['db_password'],$opts);
    $tables=$d['engine']==='mysql' ? $pdo->query('SHOW TABLES')->fetchAll() : $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll();
    if ($tables) fail('This database is not empty. Choose a new, empty database. Existing installations must use the upgrade instructions, not this installer.');
    return $pdo;
}
function setupHandle(): int {
    global $config;
    if (!setupAllowedTransport()) fail('Use HTTPS for remote setup, or open localhost on your XAMPP computer.');
    if (!hash_equals($_SESSION['setup_csrf'],input('csrf',128))) fail('Setup form expired. Refresh and try again.');
    if (setupLocked()) fail('Setup is locked. Existing configuration and accounts cannot be overwritten.');
    $action=input('action',40);
    if ($action==='unlock') {
        if ((int)($_SESSION['unlock_until'] ?? 0)>time()) fail('Too many incorrect keys. Wait 15 minutes.');
        $provided=input('setup_key',64);
        if (!hash_equals(setupKey(),$provided)) {
            $_SESSION['unlock_attempts']=($_SESSION['unlock_attempts'] ?? 0)+1;
            if ($_SESSION['unlock_attempts']>=10) { $_SESSION['unlock_until']=time()+900; $_SESSION['unlock_attempts']=0; }
            fail('The setup key does not match. Open storage/setup-key.txt on your server and copy the complete key.');
        }
        session_regenerate_id(true);
        $_SESSION['setup_authorized']=hash('sha256',$provided);
        $_SESSION['setup_csrf']=bin2hex(random_bytes(32));
        return 1;
    }
    if (!setupAuthorized()) fail('Unlock setup with the server setup key first.');
    if ($action==='cancel') {
        $_SESSION=[]; session_regenerate_id(true); return 0;
    }
    setupRequireChecks();
    $d=$_SESSION['setup_draft'] ?? [];
    if ($action==='requirements') {
        $d['environment']=choice('environment',['local','production']);
        if ($d['environment']==='production' && !setupHttps()) fail('Live-server mode requires HTTPS. Use Local XAMPP mode for localhost HTTP.');
        $tz=input('timezone',80);
        if (!in_array($tz,DateTimeZone::listIdentifiers(),true)) fail('Choose a valid timezone.');
        $d['timezone']=$tz; $d['requirements_ok']=true;
        // Changing deployment mode invalidates all dependent choices and mail proof.
        unset($d['database_ok'],$d['mail_verified'],$d['mail_proof']);
        $_SESSION['setup_draft']=$d; return 2;
    }
    if (empty($d['requirements_ok'])) fail('Complete server checks first.');
    if ($action==='database') {
        $d['engine']=choice('engine',['mysql','sqlite']);
        if ($d['engine']==='sqlite') {
            if ($d['environment']!=='local') fail('SQLite is only available for a local demonstration. Choose MySQL for a live server.');
            $d['dsn']='sqlite:'.setupStorage().'/crm.sqlite';
            $d['db_user']=''; $d['db_password']='';
            if (!extension_loaded('pdo_sqlite')) fail('Enable the pdo_sqlite extension first.');
        } else {
            if (!extension_loaded('pdo_mysql')) fail('Enable the pdo_mysql extension first.');
            $d['db_host']=input('db_host',200);
            $d['db_name']=input('db_name',64);
            if (!preg_match('/^[a-zA-Z0-9_.:-]+$/D',$d['db_host']) || !preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$d['db_name'])) fail('Use a valid database host and a database name containing only letters, digits or underscores.');
            $port=input('db_port',5);
            if (!ctype_digit($port) || (int)$port<1 || (int)$port>65535) fail('Enter a valid database port.');
            $d['db_port']=(int)$port;
            $d['db_user']=input('db_user',100);
            $d['db_password']=input('db_password',512,false);
            if ($d['environment']==='production' && $d['db_password']==='') fail('Use a database account with a password on a live server.');
            $d['dsn']='mysql:host='.$d['db_host'].';port='.$d['db_port'].';dbname='.$d['db_name'].';charset=utf8mb4';
        }
        try { setupDatabase($d,($_POST['create_database'] ?? '')==='1'); }
        catch(DomainException $e) { throw $e; }
        catch(Throwable $e) { fail('Database connection failed. Check the host, port, credentials, database name and CREATE DATABASE permission if selected. No CRM records were created.'); }
        $d['database_ok']=true;
        $_SESSION['setup_draft']=$d; return 3;
    }
    if (empty($d['database_ok'])) fail('Test the database connection first.');
    if ($action==='owner') {
        $d['owner_name']=input('owner_name',120); $email=strtolower(input('owner_email',200));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Enter a valid owner email address.');
        $d['owner_email']=$email;
        $d['institute_name']=input('institute_name',120,false);
        $d['institute_kind']=input('institute_kind',60,false);
        $d['institute_city']=input('institute_city',100,$d['institute_name']!=='');
        $d['institute_phone']=input('institute_phone',30,$d['institute_name']!=='');
        unset($d['mail_verified'],$d['mail_proof']);
        $_SESSION['setup_draft']=$d; return 4;
    }
    if (empty($d['owner_email'])) fail('Enter the owner account details first.');
    if ($action==='test_mail') {
        if ((int)($d['last_mail_at'] ?? 0)>time()-60) fail('Wait 60 seconds before sending another test code.');
        unset($d['mail_verified'],$d['mail_proof']);
        $_SESSION['setup_draft']=$d;
        $transport=choice('mail_transport',['smtp','log']);
        if ($transport==='log' && $d['environment']!=='local') fail('Local capture cannot be used on a live server.');
        $from=strtolower(input('from_email',200));
        if (!filter_var($from,FILTER_VALIDATE_EMAIL)) fail('Enter a valid sender email address.');
        $m=['transport'=>$transport,'from_email'=>$from,'from_name'=>input('from_name',120)];
        if ($transport==='smtp') {
            $m['host']=strtolower(input('smtp_host',200));
            if (!preg_match('/^[a-zA-Z0-9.-]+$/D',$m['host'])) fail('Enter a valid SMTP hostname.');
            $m['encryption']=choice('smtp_encryption',['tls','smtps']);
            $m['port']=(int)choice('smtp_port',['587','465']);
            if (($m['encryption']==='tls' && $m['port']!==587) || ($m['encryption']==='smtps' && $m['port']!==465)) fail('Choose STARTTLS with port 587, or SMTPS with port 465.');
            $m['username']=input('smtp_username',200);
            $password=input('smtp_password',512,false);
            // A blank password retains an already-entered credential only for the same endpoint/user.
            if ($password==='') {
                $old=$d['mail'] ?? [];
                if (($old['host'] ?? '')!==$m['host'] || ($old['username'] ?? '')!==$m['username']) fail('Enter the SMTP App Password.');
                $password=$old['password'] ?? '';
            }
            if ($m['host']==='smtp.gmail.com') $password=str_replace(' ','',$password);
            if ($password==='') fail('Enter the SMTP App Password.');
            $m['password']=$password;
        } else $m['log_path']=setupStorage().'/mail';
        $d['mail']=$m;
        $d['last_mail_at']=time(); $_SESSION['setup_draft']=$d;
        $config=setupConfiguration($d);
        $code=(string)random_int(100000,999999);
        try {
            $status=sendMail($d['owner_email'],'Confirm your Northstar setup',"Your setup verification code is: $code\n\nEnter this code in the browser setup wizard. It expires in 5 minutes. This confirms that your owner mailbox can receive login codes through the configured PHPMailer transport.\n\nDo not share this code.");
        } catch(Throwable $e) {
            fail('PHPMailer could not send the test code. Check your Gmail App Password, sender address, TLS certificates and outbound SMTP access. No account was created; no credentials were logged.');
        }
        $d['mail_proof']=['hash'=>password_hash($code,PASSWORD_DEFAULT),'expires'=>time()+300,'attempts'=>0,'fingerprint'=>setupMailFingerprint($d)];
        $_SESSION['setup_draft']=$d;
        $_SESSION['setup_notice']=$status==='spooled' ? 'Test captured locally in storage/mail/. No email was sent. Read the six-digit code from the newest private .eml file.' : 'PHPMailer sent a verification code to your owner email. Check the inbox or spam folder, then enter the code below.';
        return 4;
    }
    if ($action==='verify_mail') {
        $p=$d['mail_proof'] ?? null;
        if (!$p || $p['expires']<=time() || $p['attempts']>=5) fail('The setup code expired or has no attempts left. Send another test code.');
        $d['mail_proof']['attempts']++;
        $_SESSION['setup_draft']=$d;
        $code=input('verification_code',20);
        if (!preg_match('/^\d{6}$/D',$code) || !password_verify($code,$p['hash']) || !hash_equals($p['fingerprint'],setupMailFingerprint($d))) fail('That verification code is not correct.');
        $d['mail_verified']=setupMailFingerprint($d); unset($d['mail_proof']);
        $_SESSION['setup_draft']=$d; return 5;
    }
    if ($action==='install') {
        if (empty($d['mail_verified']) || !hash_equals($d['mail_verified'],setupMailFingerprint($d))) fail('Verify your owner email using the current SMTP settings before installing.');
        if (input('confirm_install',5)!=='yes') fail('Confirm that this is a new installation.');
        setupFinish($d);
        $_SESSION=[]; session_regenerate_id(true);
        $_SESSION['setup_complete']=true;
        return 6;
    }
    fail('Unknown setup action.');
}
function setupFinish(array $d): void {
    global $config;
    $guard=fopen(setupStorage().'/setup.guard','c');
    if (!$guard || !flock($guard,LOCK_EX)) fail('Another installation is running. Try again shortly.');
    $recovery=setupStorage().'/setup-recovery.php';
    $committed=false; $database=null;
    try {
        if (setupLocked()) fail('Setup is locked. No existing configuration was changed.');
        setupRequireChecks();
        setupDatabase($d,false); // Recheck emptiness immediately before creating any tables.
        $config=setupConfiguration($d);
        $text="<?php\n// Created by the protected setup wizard. Keep this file private.\nreturn ".var_export($config,true).";\n";
        $file=fopen($recovery,'x');
        if (!$file) fail('Cannot create the private recovery configuration. Check storage permissions.');
        chmod($recovery,0600);
        $written=fwrite($file,$text); fflush($file); fclose($file);
        if ($written!==strlen($text)) fail('Could not write complete configuration. Check available disk space.');
        $database=db();
        initializeSchema();
        db()->beginTransaction();
        // No usable password is created: the verified email is the owner's login method.
        query("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'owner')",[$d['owner_name'],$d['owner_email'],password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
        $id=(int)db()->lastInsertId();
        if ($d['institute_name']!=='') query('INSERT INTO institutes (name,kind,city,phone,created_at) VALUES (?,?,?,?,?)',[$d['institute_name'],$d['institute_kind'] ?: 'Training',$d['institute_city'],$d['institute_phone'],date('Y-m-d H:i:s')]);
        query("INSERT INTO audit_log (user_id,action,entity,entity_id,created_at) VALUES (?,'browser_install','users',?,?)",[$id,$id,date('Y-m-d H:i:s')]);
        db()->commit(); $committed=true;
        if (file_exists(setupConfigPath()) || !rename($recovery,setupConfigPath())) fail('The database was initialized, but configuration could not be published. Setup is locked for safety. Ask the server operator to move storage/setup-recovery.php to config.php after checking permissions. Do not reinstall.');
        chmod(setupConfigPath(),0600);
        if (file_put_contents(setupStorage().'/installed.lock',date(DATE_ATOM)."\n",LOCK_EX)!==false) chmod(setupStorage().'/installed.lock',0600);
        @unlink(setupStorage().'/setup-key.txt');
    } catch(Throwable $e) {
        if ($database instanceof PDO && $database->inTransaction()) $database->rollBack();
        if (!$committed && is_file($recovery)) @unlink($recovery);
        if ($e instanceof DomainException) throw $e;
        fail('Installation could not finish. No owner transaction was committed. MySQL may retain empty tables after a schema failure; use a new empty database after fixing permissions/extensions. Existing data was not erased.');
    } finally { flock($guard,LOCK_UN); fclose($guard); }
}
