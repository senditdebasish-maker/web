<?php
declare(strict_types=1);
ini_set('display_errors','0');
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/online-payments.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
try {
    $iid=(int)($_GET['institute']??0); $body=file_get_contents('php://input') ?: '';
    $signature=(string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??''); $event=(string)($_SERVER['HTTP_X_RAZORPAY_EVENT_ID']??'');
    if($iid<1 || $body==='' || $signature==='' || $event==='') throw new RuntimeException('Invalid webhook request.');
    $result=handleRazorpayWebhook($iid,$body,$signature,$event);
    echo json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR);
} catch(Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Webhook rejected.'],JSON_THROW_ON_ERROR); }
