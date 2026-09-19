<?php
declare(strict_types=1);
function migrateCommunications(): void {
    $mysql = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    // Additive, repeatable migration: no existing student or account records are changed.
    $tables = [
        'otp_challenges' => 'id VARCHAR(64) PRIMARY KEY, user_id INTEGER NULL, email_hash VARCHAR(64) NOT NULL, code_hash VARCHAR(64) NOT NULL, expires_at INTEGER NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, consumed INTEGER NOT NULL DEFAULT 0, FOREIGN KEY (user_id) REFERENCES users(id)',
        'auth_events' => "id $id, identity_hash VARCHAR(64) NOT NULL, ip_hash VARCHAR(64) NOT NULL, kind VARCHAR(16) NOT NULL, attempted_at INTEGER NOT NULL",
        'payments' => "id $id, institute_id INTEGER NOT NULL, student_id INTEGER NOT NULL, amount_minor INTEGER NOT NULL, paid_on VARCHAR(10) NOT NULL, method VARCHAR(20) NOT NULL, reference VARCHAR(120) NOT NULL, request_key VARCHAR(64) NOT NULL UNIQUE, recorded_by INTEGER NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (student_id) REFERENCES students(id), FOREIGN KEY (recorded_by) REFERENCES users(id)",
        'documents' => "id $id, institute_id INTEGER NOT NULL, student_id INTEGER NOT NULL, kind VARCHAR(20) NOT NULL, event_key VARCHAR(80) NOT NULL UNIQUE, payload_json TEXT NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (student_id) REFERENCES students(id)",
        'notifications' => "id $id, institute_id INTEGER NOT NULL, document_id INTEGER NOT NULL UNIQUE, recipient VARCHAR(200) NOT NULL, status VARCHAR(20) NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, next_attempt_at INTEGER NOT NULL DEFAULT 0, lease_until INTEGER NOT NULL DEFAULT 0, claim_token VARCHAR(64) NOT NULL DEFAULT '', last_error VARCHAR(250) NOT NULL DEFAULT '', created_at VARCHAR(19) NOT NULL, sent_at VARCHAR(19) NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (document_id) REFERENCES documents(id)"
    ];
    foreach ($tables as $name => $definition) db()->exec("CREATE TABLE IF NOT EXISTS $name ($definition)$suffix");
    // Check metadata so a second migration run does not attempt duplicate indexes.
    foreach (['auth_events'=>['idx_auth_identity'=>'identity_hash,kind,attempted_at','idx_auth_ip'=>'ip_hash,kind,attempted_at'], 'otp_challenges'=>['idx_otp_email'=>'email_hash'], 'payments'=>['idx_payment_student'=>'student_id'], 'notifications'=>['idx_mail_due'=>'status,next_attempt_at'], 'documents'=>['idx_document_student'=>'student_id']] as $table=>$indexes) {
        foreach ($indexes as $name=>$columns) {
            $exists=$mysql ? one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$name]) : one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$name]);
            if (!$exists) db()->exec("CREATE INDEX $name ON $table ($columns)");
        }
    }
    migratePortal();
}

require_once __DIR__.'/portal.php';
