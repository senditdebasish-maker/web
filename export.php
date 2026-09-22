<?php
declare(strict_types=1);
ini_set('display_errors','0');
require __DIR__.'/app/bootstrap.php';
if (!currentUser()) { header('Location: index.php?page=login'); exit; }
http_response_code(410);
echo 'CSV export is currently unavailable.';
