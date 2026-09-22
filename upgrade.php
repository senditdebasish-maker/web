<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
if (!currentUser()) { header('Location: index.php?page=login'); exit; }
header('Location: office.php?page=health'); exit;
