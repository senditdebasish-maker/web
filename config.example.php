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
    // Certificate uploads are disabled until explicitly configured outside all web roots.
    // Example XAMPP: create C:\crm-certificates (NOT inside htdocs), then set directory below.
    // Example Linux: /var/private/crm-certificates with owner-only permissions.
    'certificates' => [
        'enabled' => false,
        'directory' => '', // Absolute private path; must exist, be writable and outside the app.
        'scanner' => 'manual', // 'manual' = staff confirms offline AV scan; 'clamav' = local ClamAV daemon.
        'clamav_host' => '127.0.0.1',
        'clamav_port' => 3310,
    ],
    // Online payments are disabled until each institute is explicitly configured.
    // Keep secrets in environment variables or this private file; never commit real keys.
    // ledger_actor_id must be an active owner/admin who owns ledger responsibility.
    'razorpay' => [
        'accounts' => [
            // 1 => [
            //     'enabled' => true,
            //     'mode' => 'test', // 'test' first; 'live' only on production HTTPS.
            //     'key_id' => 'rzp_test_xxxxxxxx',
            //     'key_secret' => getenv('CRM_RAZORPAY_SECRET_1') ?: '',
            //     'webhook_secret' => getenv('CRM_RAZORPAY_WEBHOOK_1') ?: '',
            //     'ledger_actor_id' => 1,
            // ],
        ],
    ],
];
