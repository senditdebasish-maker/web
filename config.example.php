<?php
return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=institute_crm;charset=utf8mb4',
    'user' => 'root',
    'password' => '',
    'timezone' => 'Asia/Kolkata',
    'environment' => 'production', // Use 'local' only for private development.
    'secure_cookies' => false, // Set TRUE on a live HTTPS server.
    'auth_mode' => 'otp', // Password login is disabled. 'password' is an explicit legacy fallback.
    'mail' => [
        'transport' => 'smtp', // 'log' captures private .eml files, only in environment=local.
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => getenv('CRM_SMTP_USERNAME') ?: '',
        'password' => getenv('CRM_SMTP_PASSWORD') ?: '', // Google App Password, NOT your Gmail password.
        'from_email' => getenv('CRM_SMTP_USERNAME') ?: '',
        'from_name' => 'My Institute',
        // 'log_path' => __DIR__ . '/storage/mail', // Private local test capture; never web-accessible.
    ],
];
