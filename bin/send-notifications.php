<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/notifications.php';
$options=getopt('',['limit:']);
foreach(processNotifications((int)($options['limit'] ?? 25)) as $id=>$status) echo "Notification #$id: $status\n";
echo "Notification batch finished.\n";
