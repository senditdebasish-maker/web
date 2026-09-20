<?php
declare(strict_types=1);
require_once __DIR__.'/services.php';
require_once __DIR__.'/applications.php';
/** No private paths, DSNs, credentials, student records or exception messages in output. */
function operationalHealth(): array {
    global $config;$checks=[];
    $add=static function(string $name,string $status,string $detail)use(&$checks):void{$checks[]=['check'=>$name,'status'=>$status,'detail'=>$detail];};
    $prod=($config['environment']??'')==='production';
    $add('PHP',PHP_VERSION_ID>=80200?'ok':'fail','Runtime '.PHP_VERSION.'; independently verify patch support and updates.');
    $add('Environment',$prod?'ok':'warn',$prod?'Production configuration selected.':'Not configured for production.');
    $add('HTTPS cookies',!empty($config['secure_cookies'])?'ok':($prod?'fail':'warn'),!empty($config['secure_cookies'])?'Secure flag configured; verify actual HTTPS/proxy behavior.':'Secure cookies are disabled.');
    $smtp=($config['mail']['transport']??'')==='smtp';$add('Email transport',$smtp?'ok':($prod?'fail':'warn'),$smtp?'SMTP configured; inbox delivery is not verified here.':'No real SMTP transport configured.');
    $add('Upload scanning',extension_loaded('fileinfo')?'ok':'fail',extension_loaded('fileinfo')?'File type verification available.':'Enable PHP fileinfo for certificate uploads.');
    $add('Payment gateway client',function_exists('curl_init')?'ok':'warn',function_exists('curl_init')?'HTTPS gateway client available.':'Enable PHP cURL before configuring online payments.');
    try {
        query('SELECT 1');$add('Database','ok','Connection responded. Driver: '.db()->getAttribute(PDO::ATTR_DRIVER_NAME).'.');
        if(!servicesReady())$add('Modules','warn','Student services upgrade required.');
        elseif(!applicationsReady())$add('Modules','warn','Public admissions upgrade required.');
        elseif(!automationReady())$add('Modules','warn','Admissions automation upgrade required. Run upgrade.php.');
        else {
            $job=one("SELECT * FROM runtime_jobs WHERE job_key='notifications'");
            if(!$job || !$job['finished_at'])$add('Notification worker','warn','No completed worker heartbeat. Schedule the CLI worker.');
            else {$age=max(0,time()-(int)strtotime($job['finished_at']));$add('Notification worker',$age>300||$job['outcome']!=='ok'?'warn':'ok','Last completion '.$age.' seconds ago; outcome '.$job['outcome'].'.');}
            $cert=!empty($config['certificates']['enabled'])&&!empty($config['certificates']['directory']);
            $add('Certificate uploads',$cert?'ok':'info',$cert?'Upload location configured; verify it stays outside web roots with owner-only permissions.':'Disabled until explicitly configured outside web roots.');
            $pay=0;foreach(($config['razorpay']['accounts']??[]) as $a)if(!empty($a['enabled']))$pay++;
            $live=false;foreach(($config['razorpay']['accounts']??[]) as $a)if(!empty($a['enabled'])&&($a['mode']??'')==='live')$live=true;
            $add('Online payments',$pay?'ok':'info',$pay?($live?'Live gateway configured; verify HTTPS, webhook secret and reconciliation separately.':'Test gateway configured; test payments never credit the ledger.'):'Disabled until each institute is explicitly configured.');
        }
        $failed=(int)query("SELECT COUNT(*) FROM notifications WHERE status='failed'")->fetchColumn();
        $pending=(int)query("SELECT COUNT(*) FROM notifications WHERE status IN ('pending','sending')")->fetchColumn();
        $add('Email queue',$failed?'warn':'ok',"$pending queued; $failed failed. Monitor oldest pending messages and provider bounces separately.");
        if(automationReady()){
            $af=(int)query("SELECT COUNT(*) FROM application_mail WHERE status='failed'")->fetchColumn();
            $ap=(int)query("SELECT COUNT(*) FROM application_mail WHERE status IN ('pending','sending')")->fetchColumn();
            $add('Application alerts',$af?'warn':'ok',"$ap queued; $af failed. The worker sends status updates; the portal remains authoritative.");
            $review=(int)query("SELECT COUNT(*) FROM online_orders WHERE state IN ('Uncertain','Review')")->fetchColumn();
            if($review)$add('Payment reconciliation','warn',"$review online order(s) need office reconciliation before retrying.");
        }
    }catch(Throwable $e){$add('Database / modules','fail','Database or required schema unavailable. Review private server logs and migration status.');}
    $add('Backups and independent review','info','Verify off-server encrypted backups, a successful isolated restore, uptime alerts and security/load testing externally.');
    return $checks;
}
