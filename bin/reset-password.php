<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$options=getopt('', ['email:']);
$email=strtolower(trim($options['email'] ?? ''));
$password=getenv('CRM_ADMIN_PASSWORD') ?: '';
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || (strlen($password)<12 || strlen($password)>72)) exit("Provide --email and set CRM_ADMIN_PASSWORD to a new password of 12–72 bytes.\n");
$user=one('SELECT id FROM users WHERE email=?',[$email]);
if (!$user) exit("Account not found.\n");
query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);
audit('password_reset_cli','users',(int)$user['id']);
echo "Password reset. The account's active/disabled status has not changed.\n";
