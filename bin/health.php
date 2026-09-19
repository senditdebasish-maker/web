<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/health.php';
$checks=operationalHealth();$statuses=array_column($checks,'status');
$exit=in_array('fail',$statuses,true)?2:(in_array('warn',$statuses,true)?1:0);
echo json_encode(['checked_at'=>date(DATE_ATOM),'status'=>$exit===2?'fail':($exit===1?'warn':'ok'),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";exit($exit);
