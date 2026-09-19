<?php
declare(strict_types=1);
// An environment-only override lets automated tests use an isolated database.
$configFile = getenv('CRM_CONFIG_FILE') ?: dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI !== 'cli') { header('Location: setup.php'); exit; }
    exit("Setup required: open public/setup.php in your browser, or follow the CLI instructions in README.md.\n");
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Kolkata');
require_once __DIR__.'/core.php';
