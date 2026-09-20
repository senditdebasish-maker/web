<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/notifications.php';
require dirname(__DIR__).'/app/services.php';
require_once dirname(__DIR__).'/app/automation.php';
$options=getopt('',['limit:']);
$lock=fopen(dirname(__DIR__).'/storage/notification-worker.lock','c');
if(!$lock){fwrite(STDERR,"Worker lock unavailable. Check private storage permissions.\n");exit(1);}
if(!flock($lock,LOCK_EX|LOCK_NB)){echo "A notification worker is already running on this host.\n";fclose($lock);exit;}
try {
    jobHeartbeat('running','Worker started.',true);
    $results=processNotifications((int)($options['limit'] ?? 25));$errors=0;$total=count($results);
    foreach($results as $id=>$status){echo "Notification #$id: $status\n";if(in_array($status,['pending','failed'],true))$errors++;}
    $appResults=automationReady()?processApplicationMail((int)($options['limit']??25)):[];$total+=count($appResults);
    foreach($appResults as $id=>$status){echo "Application notification #$id: $status\n";if(in_array($status,['pending','failed'],true))$errors++;}
    jobHeartbeat($errors?'degraded':'ok',$total.' attempted; '.$errors.' pending/failed attempts.');
    echo "Notification batch finished.\n";
}catch(Throwable $e){try{jobHeartbeat('failed','Worker failed; inspect private server logs.');}catch(Throwable $ignored){}fwrite(STDERR,"Worker failed. Inspect database, permissions and private server logs.\n");exit(1);}
finally{flock($lock,LOCK_UN);fclose($lock);}
