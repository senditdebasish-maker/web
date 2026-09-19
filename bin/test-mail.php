<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/mail.php';
$options=getopt('',['to:']);
$to=$options['to'] ?? '';
if (!filter_var($to,FILTER_VALIDATE_EMAIL)) { fwrite(STDERR,"Use --to=your-test-address@example.com\n"); exit(1); }
try {
    $status=sendMail($to,'Northstar SMTP configuration test',"This is a configuration test from your institute CRM. No student information is included.\n\nIf you received this, your server connected to the configured email transport.");
    echo $status==='sent' ? "SMTP accepted the test email. Check the recipient inbox/spam folder.\n" : "Test captured locally. No real email was sent.\n";
} catch (Throwable $e) {
    fwrite(STDERR,"Test failed. Check Composer dependencies, sender, SMTP credentials, TLS certificates and outbound SMTP access. No credentials or transport response were logged.\n"); exit(1);
}
