<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/migrations.php';
migrateCommunications();
echo "Email OTP, payments, documents, notifications, student portal, operations and student services tables are ready. Existing data was preserved.\n";
