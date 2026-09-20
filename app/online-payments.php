<?php
declare(strict_types=1);
require_once __DIR__.'/automation.php';
require_once __DIR__.'/operations.php';
function gatewayAccount(int $iid): array {
    global $config;$g=$config['razorpay']['accounts'][$iid]??[];
    if(empty($g['enabled'])||!in_array($g['mode']??'', ['test','live'],true)||empty($g['key_secret'])||empty($g['webhook_secret'])||!str_starts_with($g['key_id']??'','rzp_'.$g['mode'].'_'))fail('Online payments are not configured for this institute.');
    if($g['mode']==='live' && (($config['environment']??'')!=='production'||empty($config['secure_cookies'])))fail('Live payments require production configuration and secure HTTPS cookies.');
    return $g;
}
/** Fixed Razorpay endpoint, TLS verification, no redirects, no credential-bearing errors. */
function razorpayApi(array $g,string $method,string $path,?array $data=null): array {
    if(!preg_match('~^/(orders(?:/order_[A-Za-z0-9]+(?:/payments)?)?|payments/pay_[A-Za-z0-9]+)$~D',$path))throw new RuntimeException('Invalid gateway resource.');
    if(!function_exists('curl_init'))throw new RuntimeException('Enable PHP cURL for the payment gateway.');
    $c=curl_init('https://api.razorpay.com/v1'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>$g['key_id'].':'.$g['key_secret'],CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
    if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($data,JSON_THROW_ON_ERROR));
    $body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if($body===false||$status<200||$status>=300)throw new RuntimeException('Gateway request unavailable. No success has been assumed.');
    $r=json_decode($body,true,512,JSON_THROW_ON_ERROR);if(!is_array($r))throw new RuntimeException('Invalid gateway response.');return $r;
}
function onlineHold(int $sid): ?array {
    if(!automationReady())return null;
    return one("SELECT * FROM online_orders WHERE student_id=? AND state IN ('Creating','Pending','Uncertain','Review') ORDER BY id DESC LIMIT 1",[$sid]);
}
function onlineOrdersForStudent(int $sid): array {
    if(!automationReady())return [];
    return rows('SELECT * FROM online_orders WHERE student_id=? ORDER BY id DESC LIMIT 20',[$sid]);
}
function onlineCapturesForOrder(int $oid): array {
    if(!automationReady())return [];
    return rows('SELECT * FROM online_captures WHERE order_id=? ORDER BY id',[$oid]);
}
function beginOnlineOrder(array $student,string $amount): int {
    $g=gatewayAccount((int)$student['institute_id']);$minor=exactMinor($amount);$local=null;
    writeTransaction();try{
        $s=one('SELECT * FROM students WHERE id=?'.lockSuffix(),[$student['id']]);if(!$s)fail('Student not found.');$hold=onlineHold((int)$s['id']);if($hold){db()->commit();return (int)$hold['id'];}
        $actor=one('SELECT * FROM users WHERE id=?',[(int)($g['ledger_actor_id']??0)]);
        if(!$actor || !$actor['active'] || !in_array($actor['role'],['owner','admin'],true) || ($actor['role']==='admin' && (int)$actor['institute_id']!==(int)$s['institute_id']))fail('Gateway posting account needs owner configuration.');
        $paid=(int)query('SELECT COALESCE(SUM(amount_minor),0) FROM payments WHERE student_id=?',[$s['id']])->fetchColumn();if($minor<=0||$minor>(int)$s['fee_minor']-$paid)fail('Amount exceeds outstanding fees.');
        if((int)query('SELECT COUNT(*) FROM online_orders WHERE student_id=? AND created_epoch>?',[$s['id'],time()-3600])->fetchColumn()>=10)fail('Payment order limit reached. Contact the office.');
        query('INSERT INTO online_orders (student_id,institute_id,actor_id,amount_minor,mode,key_id,receipt,created_epoch) VALUES (?,?,?,?,?,?,?,?)',[$s['id'],$s['institute_id'],$actor['id'],$minor,$g['mode'],$g['key_id'],'ns_'.bin2hex(random_bytes(16)),time()]);$local=one('SELECT * FROM online_orders WHERE id=?',[(int)db()->lastInsertId()]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    try{$remote=razorpayApi($g,'POST','/orders',['amount'=>$minor,'currency'=>'INR','receipt'=>$local['receipt'],'partial_payment'=>false]);validateRemoteOrder($local,$remote);query("UPDATE online_orders SET provider_order=?,state='Pending' WHERE id=? AND state='Creating'",[$remote['id'],$local['id']]);}
    catch(Throwable $e){query("UPDATE online_orders SET state='Uncertain',note='Order creation could not be confirmed. Office must reconcile the receipt before retrying.' WHERE id=?",[$local['id']]);}
    return (int)$local['id'];
}
function validateRemoteOrder(array $o,array $r): void {
    if(!preg_match('/^order_[A-Za-z0-9]+$/D',(string)($r['id']??''))||($r['receipt']??'')!==$o['receipt']||($r['currency']??'')!=='INR'||(int)($r['amount']??-1)!==(int)$o['amount_minor'])fail('Gateway order does not match the local receipt, amount or currency.');
}
/** Called only with a signed webhook entity or a payment fetched with merchant credentials. */
function acceptCapturedPayment(int $localId,array $p,bool $allowReview=false): string {
    $initial=one('SELECT * FROM online_orders WHERE id=?',[$localId]);if(!$initial)fail('Order not found.');
    writeTransaction();try{
        $s=one('SELECT * FROM students WHERE id=?'.lockSuffix(),[$initial['student_id']]);$o=one('SELECT * FROM online_orders WHERE id=?'.lockSuffix(),[$localId]);
        if(!preg_match('/^pay_[A-Za-z0-9]+$/D',(string)($p['id']??'')) || ($p['order_id']??'')!==$o['provider_order'] || ($p['currency']??'')!=='INR' || (int)($p['amount']??-1)!==(int)$o['amount_minor'] || ($p['status']??'')!=='captured' || ($p['captured']??false)!==true || (int)($p['amount_refunded']??0)!==0)fail('Payment is not an exact, unrefunded captured payment for this order.');
        $old=one('SELECT * FROM online_captures WHERE provider_payment=?'.lockSuffix(),[$p['id']]);
        if($old && (int)$old['order_id']!==$localId)fail('Payment already belongs to another order.');
        if($old && ($old['state']!=='Review'||!$allowReview)){db()->commit();return $old['state'];}
        $paid=(int)query('SELECT COALESCE(SUM(amount_minor),0) FROM payments WHERE student_id=?',[$s['id']])->fetchColumn();
        $other=one("SELECT id FROM online_captures WHERE order_id=? AND state IN ('Credited','Test')",[$localId]);$state=$o['mode']==='test'?'Test':'Credited';$reason='';$payment=null;
        if($other || (int)$o['amount_minor']>(int)$s['fee_minor']-$paid || (!$allowReview && $o['state']==='Closed')){$state='Review';$reason='Captured funds require reconciliation: closed/duplicate order or insufficient remaining balance. Do not request another payment.';}
        if($state==='Credited'){
            query('INSERT INTO payments (institute_id,student_id,amount_minor,paid_on,method,reference,request_key,recorded_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$s['institute_id'],$s['id'],$o['amount_minor'],date('Y-m-d'),'Razorpay',$p['id'],'rzp:'.$p['id'],$o['actor_id'],date('Y-m-d H:i:s')]);$payment=(int)db()->lastInsertId();createDocument((int)$s['id'],one('SELECT * FROM payments WHERE id=?',[$payment]));
        }
        if($old)query('UPDATE online_captures SET state=?,payment_id=?,reason=? WHERE id=?',[$state,$payment,$reason,$old['id']]);
        else query('INSERT INTO online_captures (order_id,provider_payment,state,amount_minor,payment_id,reason,created_at) VALUES (?,?,?,?,?,?,?)',[$localId,$p['id'],$state,$o['amount_minor'],$payment,$reason,date('Y-m-d H:i:s')]);
        // Never overwrite an already credited order with a later duplicate review.
        $orderState=$state==='Credited'?'Paid':($state==='Test'?'TestPaid':'Review');
        if($orderState==='Review' && in_array($o['state'],['Paid','TestPaid'],true)){$orderState=$o['state'];}
        else query('UPDATE online_orders SET state=?,note=? WHERE id=?',[$orderState,$reason,$localId]);
        if($orderState!==$o['state']||$state==='Review')query('UPDATE online_orders SET note=? WHERE id=?',[$reason,$localId]);
        audit('razorpay_'.strtolower($state),'online_orders',$localId);db()->commit();return $state;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function synchronizeOnlineOrder(int $id,?string $supplied=null,bool $allowReview=false): string {
    $o=one('SELECT * FROM online_orders WHERE id=?',[$id]);if(!$o)fail('Order not found.');$g=gatewayAccount((int)$o['institute_id']);if($g['key_id']!==$o['key_id']||$g['mode']!==$o['mode'])fail('Restore the matching merchant key/mode before reconciling this order.');
    $provider=$o['provider_order']?:$supplied;if(!$provider)fail('Supply the Razorpay order ID matching this receipt from the merchant dashboard.');
    if(!preg_match('/^order_[A-Za-z0-9]+$/D',$provider))fail('Invalid Razorpay order ID format.');
    $remote=razorpayApi($g,'GET','/orders/'.$provider);validateRemoteOrder($o,$remote);
    if(!$o['provider_order'])query('UPDATE online_orders SET provider_order=? WHERE id=? AND provider_order IS NULL',[$provider,$id]);
    $payments=razorpayApi($g,'GET','/orders/'.$provider.'/payments');$result='Pending';
    foreach($payments['items']??[] as $p)if(($p['status']??'')==='captured')$result=acceptCapturedPayment($id,$p,$allowReview);
    if($result==='Pending')query("UPDATE online_orders SET state='Pending',note='' WHERE id=? AND state IN ('Uncertain','Creating')",[$id]);return $result;
}
function verifyWebhookSignature(string $body,string $signature,string $secret): bool {
    if($body===''||$signature===''||$secret==='')return false;
    return hash_equals(hash_hmac('sha256',$body,$secret),$signature);
}
function handleRazorpayWebhook(int $iid,string $body,string $signature,string $eventId): string {
    if(!automationReady())fail('Automation upgrade required.');
    if(!preg_match('/^[A-Za-z0-9_.-]{1,100}$/D',$eventId))fail('Invalid webhook event.');
    $g=gatewayAccount($iid);
    if(!verifyWebhookSignature($body,$signature,$g['webhook_secret']))fail('Invalid webhook signature.');
    $payload=json_decode($body,true,512,JSON_THROW_ON_ERROR);if(!is_array($payload))fail('Invalid webhook payload.');
    try{query('INSERT INTO razorpay_webhook_events (event_id,institute_id,received_at) VALUES (?,?,?)',[$eventId,$iid,date('Y-m-d H:i:s')]);}
    catch(PDOException $e){return 'duplicate';}
    try{
        $event=(string)($payload['event']??'');
        if(!in_array($event,['payment.captured','order.paid'],true))return 'ignored';
        $payment=$payload['payload']['payment']['entity']??null;
        if(!is_array($payment)||!isset($payment['id'],$payment['order_id']))return 'ignored';
        $local=one('SELECT * FROM online_orders WHERE provider_order=? AND institute_id=?',[$payment['order_id'],$iid]);
        if(!$local)return 'ignored';
        $fresh=razorpayApi($g,'GET','/payments/'.$payment['id']);
        if(($fresh['id']??'')!==$payment['id'])fail('Gateway payment mismatch.');
        return acceptCapturedPayment((int)$local['id'],$fresh,false);
    }catch(DomainException $e){throw $e;}
    catch(Throwable $e){try{query('DELETE FROM razorpay_webhook_events WHERE event_id=?',[$eventId]);}catch(Throwable $ignored){}throw $e;}
}
function staffReconcileOrder(): string {
    $u=requireRole(['owner','admin']);if(!automationReady())fail('Automation upgrade required.');
    $o=one('SELECT * FROM online_orders WHERE id=?',[(int)input('id')]);if(!$o)fail('Order not found.');instituteAccess((int)$o['institute_id']);
    if($o['state']==='Closed'&&input('reopen',3,false)!=='yes')fail('This order is closed. Confirm reopening to reconcile it.');
    $supplied=input('provider_order',100,false);$allow=input('allow_review',3,false)==='yes';
    // Network calls run outside any outer staff transaction; each DB mutation is atomic.
    $result=synchronizeOnlineOrder((int)$o['id'],$supplied!==''?$supplied:null,$allow);
    $_SESSION['flash']='Reconciliation result: '.$result.'. Verify amounts in the Razorpay dashboard before collecting another payment.';return 'payments';
}
function closeOnlineOrder(): string {
    requireRole(['owner','admin']);$o=one('SELECT * FROM online_orders WHERE id=?',[(int)input('id')]);if(!$o)fail('Order not found.');instituteAccess((int)$o['institute_id']);
    if(in_array($o['state'],['Paid','TestPaid'],true))fail('Credited orders cannot be closed.');
    query("UPDATE online_orders SET state='Closed',note=? WHERE id=?",['Closed by office: '.input('reason',300),(int)$o['id']]);audit('razorpay_closed','online_orders',(int)$o['id']);return 'payments';
}
