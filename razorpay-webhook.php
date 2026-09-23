<?php
declare(strict_types=1);
ini_set('display_errors','0');
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/online-payments.php';
header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');exit('Method not allowed.');}
try{
    $iid=(int)($_GET['institute']??0);if($iid<=0){http_response_code(404);exit('Not found.');}
    $body=(string)file_get_contents('php://input');
    $sig=(string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??'');
    $eventId=(string)($_SERVER['HTTP_X_RAZORPAY_EVENT_ID']??'');
    if($body===''||strlen($body)>1024*1024)fail('Invalid webhook payload.');
    $result=handleRazorpayWebhook($iid,$body,$sig,$eventId);
    http_response_code(200);echo $result;exit;
}catch(DomainException $e){http_response_code(400);error_log('Razorpay webhook rejected.');exit('Webhook rejected.');}
catch(Throwable $e){http_response_code(500);error_log('Razorpay webhook processing failed.');exit('Webhook processing failed.');}
