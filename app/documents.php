<?php
declare(strict_types=1);
/** Called inside the admission/payment transaction. Store immutable document data, not HTML. */
function createDocument(int $studentId, ?array $payment=null): int {
    $student=one('SELECT s.*,c.name course_name,i.name institute_name,i.city,i.phone institute_phone FROM students s JOIN courses c ON c.id=s.course_id JOIN institutes i ON i.id=s.institute_id WHERE s.id=?',[$studentId]);
    if (!$student) fail('Student not found.');
    $key=$payment ? 'payment:'.$payment['id'] : 'admission:'.$studentId;
    $existing=one('SELECT id FROM documents WHERE event_key=?',[$key]);
    if ($existing) return (int)$existing['id'];
    $payload=[
        'institute'=>$student['institute_name'], 'city'=>$student['city'], 'institute_phone'=>$student['institute_phone'],
        'name'=>$student['name'], 'student_number'=>'ST-'.str_pad((string)$studentId,5,'0',STR_PAD_LEFT),
        'course'=>$student['course_name'], 'admission_date'=>$student['admission_date'], 'fee_minor'=>(int)$student['fee_minor'],
    ];
    if ($payment) {
        $total=(int)query('SELECT COALESCE(SUM(amount_minor),0) FROM payments WHERE student_id=?',[$studentId])->fetchColumn();
        $payload['payment']=[
            'receipt'=>'RCPT-'.str_pad((string)$payment['id'],7,'0',STR_PAD_LEFT),
            'amount_minor'=>(int)$payment['amount_minor'], 'paid_on'=>$payment['paid_on'], 'method'=>$payment['method'],
            'reference'=>$payment['reference'], 'total_paid_minor'=>$total, 'balance_minor'=>(int)$student['fee_minor']-$total
        ];
    }
    $now=date('Y-m-d H:i:s');
    query('INSERT INTO documents (institute_id,student_id,kind,event_key,payload_json,created_at) VALUES (?,?,?,?,?,?)',[$student['institute_id'],$studentId,$payment?'payment':'admission',$key,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$now]);
    $id=(int)db()->lastInsertId();
    $email=$student['email'];
    $valid=(bool)filter_var($email,FILTER_VALIDATE_EMAIL);
    query('INSERT INTO notifications (institute_id,document_id,recipient,status,last_error,created_at) VALUES (?,?,?,?,?,?)',[$student['institute_id'],$id,$valid?$email:'',$valid?'pending':'blocked',$valid?'':'Student email is missing. Add it in Students.',$now]);
    return $id;
}
function documentFilename(array $document): string { return ($document['kind']==='payment'?'payment-receipt-':'admission-letter-').$document['id'].'.pdf'; }
function renderDocument(array $document): string {
    dependencies();
    $p=json_decode($document['payload_json'],true,512,JSON_THROW_ON_ERROR);
    $title=$document['kind']==='payment' ? 'Payment receipt' : 'Admission confirmation';
    $money=fn(int $amount)=>'INR '.number_format($amount/100,2);
    $details=['Student ID'=>$p['student_number'],'Student name'=>$p['name'],'Course'=>$p['course'],'Admission date'=>$p['admission_date'],'Agreed course fee'=>$money($p['fee_minor'])];
    if (isset($p['payment'])) {
        $pay=$p['payment'];
        $details=['Receipt number'=>$pay['receipt']]+$details+['Payment date'=>$pay['paid_on'],'Payment method'=>$pay['method'],'Transaction reference'=>$pay['reference'] ?: 'Not applicable','Amount received'=>$money($pay['amount_minor']),'Total paid at receipt creation'=>$money($pay['total_paid_minor']),'Balance at receipt creation'=>$money($pay['balance_minor'])];
    }
    $html='<!doctype html><html><head><meta charset="UTF-8"><style>body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#292738;margin:28px}h1{color:#6650af;font-size:27px}h2{font-size:18px}p{line-height:1.8}table{width:100%;border-collapse:collapse;margin-top:25px}td{padding:11px;border-bottom:1px solid #e9e5f0}td:first-child{color:#81768e;width:42%}.footer{margin-top:38px;font-size:9px;color:#857c91}</style></head><body><h1>'.e($p['institute']).'</h1><p>'.e($p['city']).' · '.e($p['institute_phone']).'</p><h2>'.e($title).'</h2><p>Dear '.e($p['name']).',<br>'.($document['kind']==='payment'?'Thank you. Your institute has recorded the payment below.':'Your admission has been recorded for the course below. Welcome to your next chapter.').'</p><table>';
    foreach($details as $label=>$value) $html.='<tr><td>'.e($label).'</td><td>'.e($value).'</td></tr>';
    $html.='</table><p class="footer">Document #'.(int)$document['id'].' · Created '.e($document['created_at']).'<br>This is a computer-generated record. Contact your institute if a detail is incorrect. '.($document['kind']==='payment'?'This receipt is not a tax invoice.':'This is an admission confirmation, not a payment receipt.').'</p></body></html>';
    $cache=dirname(__DIR__).'/storage/pdf-cache';
    if (!is_dir($cache) && !mkdir($cache,0700,true) && !is_dir($cache)) throw new RuntimeException('PDF cache is not writable.');
    $options=new \Dompdf\Options();
    $options->set('isRemoteEnabled',false);
    $options->set('isPhpEnabled',false);
    $options->set('isJavascriptEnabled',false);
    $options->set('chroot',dirname(__DIR__).'/vendor/dompdf/dompdf');
    $options->set('tempDir',$cache);
    $options->set('fontCache',$cache);
    $pdf=new \Dompdf\Dompdf($options);
    $pdf->loadHtml($html,'UTF-8');
    $pdf->setPaper('A4'); $pdf->render();
    return $pdf->output();
}
