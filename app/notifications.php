<?php
declare(strict_types=1);
require_once __DIR__.'/mail.php';
require_once __DIR__.'/documents.php';
/** Claims a bounded batch. Delivery is at-least-once; see the operations guide. */
function processNotifications(int $limit=25): array {
    $now=time();
    $candidates=rows("SELECT id FROM notifications WHERE ((status='pending' AND next_attempt_at<=?) OR (status='sending' AND lease_until<?)) AND attempts<5 ORDER BY id LIMIT ".max(1,min(100,$limit)),[$now,$now]);
    $results=[];
    foreach($candidates as $candidate) {
        $id=(int)$candidate['id']; $token=bin2hex(random_bytes(32));
        $claimed=query("UPDATE notifications SET status='sending',lease_until=?,claim_token=?,attempts=attempts+1 WHERE id=? AND ((status='pending' AND next_attempt_at<=?) OR (status='sending' AND lease_until<?)) AND attempts<5",[$now+300,$token,$id,$now,$now])->rowCount();
        if (!$claimed) continue;
        $n=one('SELECT * FROM notifications WHERE id=?',[$id]);
        try {
            $doc=one('SELECT * FROM documents WHERE id=?',[$n['document_id']]);
            if (!$doc) throw new RuntimeException('Missing document.');
            $p=json_decode($doc['payload_json'],true,512,JSON_THROW_ON_ERROR);
            $subject=$doc['kind']==='payment' ? 'Payment receipt — '.$p['payment']['receipt'] : 'Admission confirmation — '.$p['student_number'];
            $body='Dear '.$p['name'].",\n\n".($doc['kind']==='payment'?'Your payment has been recorded. Please find your receipt attached.':'Your admission has been confirmed. Please find your admission letter attached.')."\n\nInstitute: ".$p['institute']."\nCourse: ".$p['course']."\n\nFor corrections, contact your institute at ".$p['institute_phone'].".\n\nThank you,\n".$p['institute'];
            $status=sendMail($n['recipient'],$subject,$body,renderDocument($doc),documentFilename($doc),'northstar-document-'.$doc['event_key'].'-'.$n['created_at']);
            query('UPDATE notifications SET status=?,sent_at=?,lease_until=0,last_error=? WHERE id=? AND claim_token=?',[$status,$status==='sent'?date('Y-m-d H:i:s'):null,$status==='spooled'?'Captured locally; no real email was sent.':'',$id,$token]);
            $results[$id]=$status;
        } catch (Throwable $e) {
            $status=(int)$n['attempts']>=5 ? 'failed':'pending';
            $delay=min(3600,60*(2 ** (int)$n['attempts']));
            query('UPDATE notifications SET status=?,next_attempt_at=?,lease_until=0,last_error=? WHERE id=? AND claim_token=?',[$status,time()+$delay,'Delivery/PDF generation failed. Check dependencies, SMTP settings, and server connectivity.',$id,$token]);
            error_log('Northstar notification #'.$id.' failed; no transport details logged.');
            $results[$id]=$status;
        }
    }
    // A worker may have died on its final attempt; make that recoverable in the UI.
    query("UPDATE notifications SET status='failed',lease_until=0,last_error='Worker interrupted on final attempt. Check delivery before retrying.' WHERE status='sending' AND lease_until<? AND attempts>=5",[time()]);
    return $results;
}
