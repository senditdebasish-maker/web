<?php
declare(strict_types=1);
/** Shared by the CLI installer and the browser setup wizard. */
function initializeSchema(): void {
$mysql = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
$id = $mysql ? 'INTEGER PRIMARY KEY AUTO_INCREMENT' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
$tables = [
'institutes' => "id $id, name VARCHAR(120) NOT NULL, kind VARCHAR(60) NOT NULL, city VARCHAR(100) NOT NULL, phone VARCHAR(30) NOT NULL, created_at VARCHAR(19) NOT NULL",
'users' => "id $id, institute_id INTEGER NULL, name VARCHAR(120) NOT NULL, email VARCHAR(200) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY (institute_id) REFERENCES institutes(id)",
'courses' => "id $id, institute_id INTEGER NOT NULL, name VARCHAR(120) NOT NULL, duration VARCHAR(80) NOT NULL, fee_minor INTEGER NOT NULL, active INTEGER NOT NULL DEFAULT 1, FOREIGN KEY (institute_id) REFERENCES institutes(id), UNIQUE (institute_id, name)",
'enquiries' => "id $id, institute_id INTEGER NOT NULL, course_id INTEGER NOT NULL, assigned_to INTEGER NOT NULL, name VARCHAR(120) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(200) NOT NULL, source VARCHAR(30) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'New', notes TEXT NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (course_id) REFERENCES courses(id), FOREIGN KEY (assigned_to) REFERENCES users(id)",
'followups' => "id $id, institute_id INTEGER NOT NULL, enquiry_id INTEGER NOT NULL, assigned_to INTEGER NOT NULL, due_date VARCHAR(10) NOT NULL, notes TEXT NOT NULL, outcome TEXT NOT NULL, completed_at VARCHAR(19) NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (enquiry_id) REFERENCES enquiries(id), FOREIGN KEY (assigned_to) REFERENCES users(id)",
'students' => "id $id, institute_id INTEGER NOT NULL, enquiry_id INTEGER NOT NULL UNIQUE, course_id INTEGER NOT NULL, name VARCHAR(120) NOT NULL, phone VARCHAR(30) NOT NULL, email VARCHAR(200) NOT NULL, admission_date VARCHAR(10) NOT NULL, fee_minor INTEGER NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (institute_id) REFERENCES institutes(id), FOREIGN KEY (enquiry_id) REFERENCES enquiries(id), FOREIGN KEY (course_id) REFERENCES courses(id)",
'audit_log' => "id $id, user_id INTEGER NULL, action VARCHAR(80) NOT NULL, entity VARCHAR(40) NOT NULL, entity_id INTEGER NOT NULL, created_at VARCHAR(19) NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id)",
'login_attempts' => "id $id, identity_hash VARCHAR(64) NOT NULL, attempted_at INTEGER NOT NULL"
];
foreach ($tables as $table => $definition) db()->exec("CREATE TABLE IF NOT EXISTS $table ($definition)$suffix");
require_once __DIR__ . '/migrations.php';
migrateCommunications();
foreach (['enquiries'=>['idx_enquiry_scope','institute_id, status'], 'followups'=>['idx_followup_due','institute_id, due_date'], 'login_attempts'=>['idx_login_identity','identity_hash, attempted_at'], 'students'=>['idx_student_scope','institute_id']] as $table=>[$index,$columns]) {
    $exists=$mysql ? one('SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$index]) : one("SELECT name FROM sqlite_master WHERE type='index' AND name=?",[$index]);
    if (!$exists) db()->exec("CREATE INDEX $index ON $table ($columns)");
}
}
