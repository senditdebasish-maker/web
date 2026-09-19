<?php
declare(strict_types=1);
require_once __DIR__.'/services.php';
/** No private paths, DSNs, credentials, student records or exception messages in output. */
function operationalHealth(): array {
    global $config;$checks=[];
    $add=static function(string $name,string $status,string $detail)use(&$checks):void{$checks[]=['check'=>$name,'status'=>$status,'detail'=>$detail];};
    $prod=($config['environment']??'')==='production';
    $add('PHP',PHP_VERSION_ID>=80200?'ok':'fail','Runtime '.PHP_VERSION.'; independently verify patch support and updates.');
    $add('Environment',$prod?'ok':'warn',$prod?'Production configuration selected.':'Not configured for production.');
    $add('HTTPS cookies',!empty($config['secure_cookies'])?'ok':($prod?'fail':'warn'),!empty($config['secure_cookies'])?'Secure flag configured; verify actual HTTPS/proxy behavior.':'Secure cookies are disabled.');
    $smtp=($config['mail']['transport']??'')==='smtp';$add('Email transport',$smtp?'ok':($prod?'fail':'warn'),$smtp?'SMTP configured; inbox delivery is not verified here.':'No real SMTP transport configured.');
    try {
        query('SELECT 1');$add('Database','ok','Connection responded. Driver: '.db()->getAttribute(PDO::ATTR_DRIVER_NAME).'.');
        if(!servicesReady())$add('Modules','warn','Student services upgrade required.');
        else {
            $job=one("SELECT * FROM runtime_jobs WHERE job_key='notifications'");
            if(!$job || !$job['finished_at'])$add('Notification worker','warn','No completed worker heartbeat. Schedule the CLI worker.');
            else {$age=max(0,time()-(int)strtotime($job['finished_at']));$add('Notification worker',$age>300||$job['outcome']!=='ok'?'warn':'ok','Last completion '.$age.' seconds ago; outcome '.$job['outcome'].'.');}
        }
        $failed=(int)query("SELECT COUNT(*) FROM notifications WHERE status='failed'")->fetchColumn();
        $pending=(int)query("SELECT COUNT(*) FROM notifications WHERE status IN ('pending','sending')")->fetchColumn();
        $add('Email queue',$failed?'warn':'ok',"$pending queued; $failed failed. Monitor oldest pending messages and provider bounces separately.");
    }catch(Throwable $e){$add('Database / modules','fail','Database or required schema unavailable. Review private server logs and migration status.');}
    $add('Backups and independent review','info','Verify off-server encrypted backups, a successful isolated restore, uptime alerts and security/load testing externally.');
    return $checks;
}
