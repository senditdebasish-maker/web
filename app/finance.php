<?php
declare(strict_types=1);
require_once __DIR__.'/documents.php';
function recordPayment(): string {
    $user=requireRole(['owner','admin']);
    $studentId=(int)input('student_id');
    $student=record('students',$studentId);
    // Serialize payments for a student before reading the balance (InnoDB row lock).
    $student=one('SELECT * FROM students WHERE id=?'.lockSuffix(),[$studentId]);
    $key=input('request_key',64);
    $existing=one('SELECT * FROM payments WHERE request_key=?',[$key]);
    if ($existing) {
        if ((int)$existing['student_id']!==$studentId || (int)$existing['recorded_by']!==(int)$user['id']) fail('Invalid payment request.');
        $_SESSION['flash']='This payment was already recorded. No duplicate receipt was created.';
        return 'payments';
    }
    if (!isset($_SESSION['payment_nonce']) || !hash_equals($_SESSION['payment_nonce'],$key)) fail('Payment form expired. Reload the Payments page.');
    $amount=input('amount',12);
    if (!preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/D',$amount,$m)) fail('Enter a positive amount with up to two decimal places.');
    $minor=(int)$m[1]*100+(int)str_pad($m[2] ?? '',2,'0');
    if ($minor<=0) fail('Payment amount must be greater than zero.');
    $paid=(int)query('SELECT COALESCE(SUM(amount_minor),0) FROM payments WHERE student_id=?',[$studentId])->fetchColumn();
    if ($minor>(int)$student['fee_minor']-$paid) fail('Payment exceeds the remaining course balance.');
    $date=validDate('paid_on');
    if ($date>date('Y-m-d') || $date<$student['admission_date']) fail('Payment date must be between admission and today.');
    $method=choice('method',['Cash','UPI','Bank transfer','Card','Cheque']);
    $reference=input('reference',120,$method!=='Cash');
    query('INSERT INTO payments (institute_id,student_id,amount_minor,paid_on,method,reference,request_key,recorded_by,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$student['institute_id'],$studentId,$minor,$date,$method,$reference,$key,$user['id'],date('Y-m-d H:i:s')]);
    $id=(int)db()->lastInsertId();
    createDocument($studentId,one('SELECT * FROM payments WHERE id=?',[$id]));
    audit('payment_recorded','payments',$id);
    $_SESSION['payment_nonce']=bin2hex(random_bytes(32));
    $_SESSION['flash']='Payment recorded. The receipt is available in Documents; email delivery is queued (or blocked if the student has no email).';
    return 'payments';
}
function studentEmail(): string {
    requireRole(['owner','admin']); $id=(int)input('student_id'); record('students',$id);
    $email=emailInput();
    query('UPDATE students SET email=? WHERE id=?',[$email,$id]);
    // Only previously blocked, never-sent notifications are released automatically.
    $documents=rows('SELECT id FROM documents WHERE student_id=?',[$id]);
    foreach($documents as $doc) query("UPDATE notifications SET recipient=?,status='pending',last_error='',next_attempt_at=0 WHERE document_id=? AND status='blocked'",[$email,$doc['id']]);
    audit('email_updated','students',$id);
    $_SESSION['flash']='Student email saved. Notifications previously blocked by a missing email are now queued. Existing recipients on other notifications were not changed.';
    return 'students';
}
function retryNotification(): string {
    requireRole(['owner','admin']); $id=(int)input('id'); $n=record('notifications',$id);
    if (!in_array($n['status'],['failed','spooled'],true)) fail('Only failed or locally captured messages can be requeued.');
    query("UPDATE notifications SET status='pending',attempts=0,next_attempt_at=0,last_error='' WHERE id=? AND status IN ('failed','spooled')",[$id]);
    audit('notification_requeued','notifications',$id); return 'notifications';
}
function generateAdmissionLetter(): string {
    requireRole(['owner','admin']); $id=(int)input('student_id'); record('students',$id);
    $doc=createDocument($id);
    audit('admission_document_requested','documents',$doc);
    $_SESSION['flash']='Admission document is available in Documents. Its email notification is queued, unless already created or missing a student email.';
    return 'documents';
}
