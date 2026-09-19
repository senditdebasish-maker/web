<?php
declare(strict_types=1);
// Included only after the parent view has resolved user and institute scope.
if($page==='payments'):
    requireRole(['owner','admin']);
    [$sw,$sa]=scoped('s');
    $studentList=rows("SELECT s.*,i.name institute_name,(SELECT COALESCE(SUM(p.amount_minor),0) FROM payments p WHERE p.student_id=s.id) paid_minor FROM students s JOIN institutes i ON i.id=s.institute_id WHERE $sw ORDER BY s.name",$sa);
    $_SESSION['payment_nonce'] ??= bin2hex(random_bytes(32));
    $choices=[];
    foreach($studentList as $student) $choices[$student['id']]=$student['name'].' · ST-'.str_pad((string)$student['id'],5,'0',STR_PAD_LEFT).' · '.$student['institute_name'].' · Due '.money((int)$student['fee_minor']-(int)$student['paid_minor']);
?>
<div class="alert success">Record payments already received by your institute. This is not an online payment gateway. Each saved payment creates an immutable PDF receipt and queues a student email.</div>
<section class="panel editor"><div class="panel-heading"><div><h2>Record received payment</h2><p>Verify the student, amount and transaction reference before saving. Payments cannot be edited or deleted here.</p></div></div>
<?php formStart('payment'); ?><input type="hidden" name="request_key" value="<?=e($_SESSION['payment_nonce'])?>"><div class="form-grid">
<?php selectField('Student and remaining balance','student_id',$choices); field('Amount received (INR)','amount'); field('Payment date','paid_on',date('Y-m-d'),'date'); selectField('Payment method','method',['Cash'=>'Cash','UPI'=>'UPI','Bank transfer'=>'Bank transfer','Card'=>'Card','Cheque'=>'Cheque'],'Cash'); field('Transaction reference (required except cash)','reference','','text',false); ?></div><?php formEnd('Record payment & queue receipt'); ?></section>
<?php
    [$pw,$pa]=scoped('p');
    $q=is_string($_GET['q'] ?? null)?substr(trim($_GET['q']),0,120):'';
    if($q!=='') { $pw.=' AND (s.name LIKE ? OR p.reference LIKE ?)'; array_push($pa,'%'.$q.'%','%'.$q.'%'); }
    $pn=max(1,(int)($_GET['p'] ?? 1));
    $payments=rows("SELECT p.*,s.name,i.name institute_name,d.id document_id FROM payments p JOIN students s ON s.id=p.student_id JOIN institutes i ON i.id=p.institute_id LEFT JOIN documents d ON d.student_id=s.id AND d.event_key=".(db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?"CONCAT('payment:',p.id)":"('payment:' || p.id)")." WHERE $pw ORDER BY p.id DESC LIMIT 21 OFFSET ".(($pn-1)*20),$pa);
    $more=count($payments)>20; $payments=array_slice($payments,0,20);
?>
<section class="panel"><div class="panel-heading"><h2>Payment ledger</h2><span class="subtle-tag">INR · Recorded payments</span></div><form class="search-form list-toolbar" method="get"><input type="hidden" name="page" value="payments"><label>Search student or reference<input name="q" value="<?=e($q)?>" type="search"></label><button class="button secondary">Search</button></form>
<?php table(['Receipt','Student / institute','Paid on','Method / reference','Amount','Document'],$payments,fn($p)=>['<strong>RCPT-'.str_pad((string)$p['id'],7,'0',STR_PAD_LEFT).'</strong>',person($p['name'],$p['institute_name']),e($p['paid_on']),e($p['method']).'<small>'.e($p['reference']).'</small>',money((int)$p['amount_minor']),$p['document_id']?'<a class="text-link" href="document.php?id='.$p['document_id'].'">Download PDF ↓</a>':'']); ?>
<?php elseif($page==='documents'):
    [$dw,$da]=scoped('d');
    if(!$admin) $dw.=" AND d.kind='admission'";
    $pn=max(1,(int)($_GET['p'] ?? 1));
    $documents=rows("SELECT d.*,s.name,n.status FROM documents d JOIN students s ON s.id=d.student_id LEFT JOIN notifications n ON n.document_id=d.id WHERE $dw ORDER BY d.id DESC LIMIT 21 OFFSET ".(($pn-1)*20),$da);
    $more=count($documents)>20; $documents=array_slice($documents,0,20);
?>
<section class="panel"><div class="panel-heading"><div><h2>Generated documents</h2><p>PDFs use a saved snapshot of the admission or payment. Downloading a PDF does not send an email.</p></div></div>
<?php table(['Document','Student','Created','Email status',''],$documents,fn($d)=>['<strong>'.($d['kind']==='payment'?'Payment receipt':'Admission letter').' #'.$d['id'].'</strong>',e($d['name']),e($d['created_at']),badge($d['status'] ?? 'Not queued'),'<a class="text-link" href="document.php?id='.$d['id'].'">Download PDF ↓</a>']); ?>
<?php elseif($page==='notifications'):
    requireRole(['owner','admin']);
    [$nw,$na]=scoped('n');
    $pn=max(1,(int)($_GET['p'] ?? 1));
    $notifications=rows("SELECT n.*,d.kind,s.name FROM notifications n JOIN documents d ON d.id=n.document_id JOIN students s ON s.id=d.student_id WHERE $nw ORDER BY n.id DESC LIMIT 21 OFFSET ".(($pn-1)*20),$na);
    $more=count($notifications)>20; $notifications=array_slice($notifications,0,20);
?>
<div class="alert success"><strong>Delivery happens in the background.</strong> Schedule <code>php bin/send-notifications.php</code> every minute. “Sent” means accepted by the SMTP server, not guaranteed inbox delivery. “Spooled” means a local test file only.</div>
<section class="panel"><div class="panel-heading"><div><h2>Student email notifications</h2><p>Failed messages retry automatically up to five attempts. For a missing address, update the student email first.</p></div></div>
<?php table(['Student / recipient','Attachment','Status','Attempts / last update',''],$notifications,function($n){
    $action=in_array($n['status'],['failed','spooled'],true) ? '<form method="post">'.csrf().'<input type="hidden" name="action" value="retry_notification"><input type="hidden" name="id" value="'.$n['id'].'"><button class="text-button">Requeue email</button></form>' : '';
    return [person($n['name'],$n['recipient'] ?: 'Missing email'),'<a class="text-link" href="document.php?id='.$n['document_id'].'">'.e(ucfirst($n['kind'])).' PDF ↗</a>',badge(ucfirst($n['status'])).'<small class="notes">'.e($n['last_error']).'</small>',(int)$n['attempts'].' / 5<small>'.e($n['sent_at'] ?: $n['created_at']).'</small>',$action];
}); endif; ?>
<div class="pagination"><span>Page <?=$pn?></span><div><?php if($pn>1): ?><a class="button secondary" href="<?=e(url(['p'=>$pn-1]))?>">← Previous</a><?php endif; ?><?php if($more): ?><a class="button secondary" href="<?=e(url(['p'=>$pn+1]))?>">Next →</a><?php endif; ?></div></div></section>
