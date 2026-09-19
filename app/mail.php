<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;
function mailSettings(): array {
    global $config;
    $m=$config['mail'] ?? [];
    if (($m['transport'] ?? '')==='log') {
        if (($config['environment'] ?? 'production')!=='local') throw new RuntimeException('Local mail capture is disabled outside the local environment.');
    } elseif (($m['transport'] ?? '')!=='smtp' || empty($m['username']) || empty($m['password'])) {
        throw new RuntimeException('Configure Gmail SMTP in config.php before using email login.');
    }
    if (!filter_var($m['from_email'] ?? '',FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Configure a valid sender email address.');
    return $m;
}
/** Returns "sent" only when SMTP accepted the message, never an inbox-delivery guarantee. */
function sendMail(string $to, string $subject, string $body, ?string $pdf=null, string $filename='document.pdf', ?string $messageKey=null): string {
    $settings=mailSettings(); dependencies();
    if (!filter_var($to,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid recipient email address.');
    $mail=new PHPMailer(true);
    $mail->CharSet='UTF-8';
    $mail->setFrom($settings['from_email'],$settings['from_name'] ?? 'Northstar Institute CRM');
    $mail->addAddress($to);
    $mail->Subject=$subject;
    $mail->Body=$body;
    $mail->isHTML(false);
    if ($messageKey) $mail->MessageID='<'.hash('sha256',$messageKey).'@'.substr(strrchr($settings['from_email'],'@'),1).'>';
    if ($pdf!==null) $mail->addStringAttachment($pdf,$filename,'base64','application/pdf');
    if ($settings['transport']==='log') {
        $dir=$settings['log_path'] ?? dirname(__DIR__).'/storage/mail';
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Cannot create local mail capture directory.');
        $mail->preSend();
        $file=$dir.'/'.date('Ymd-His').'-'.bin2hex(random_bytes(8)).'.eml';
        if (file_put_contents($file,$mail->getSentMIMEMessage(),LOCK_EX)===false) throw new RuntimeException('Cannot write local mail capture.');
        chmod($file,0600);
        return 'spooled';
    }
    $mail->isSMTP();
    $mail->Host=$settings['host'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth=true;
    $mail->Username=$settings['username'];
    $mail->Password=$settings['password'];
    $mail->SMTPSecure=($settings['encryption'] ?? 'tls')==='smtps' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port=(int)($settings['port'] ?? 587);
    $mail->Timeout=15;
    $mail->Timelimit=30;
    $mail->SMTPDebug=0;
    // Default certificate and hostname verification intentionally stay enabled.
    $mail->send();
    return 'sent';
}
