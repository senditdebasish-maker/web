<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$options = getopt('', ['name:', 'email:', 'demo']);
$name = trim($options['name'] ?? 'Institute Owner');
$email = strtolower(trim($options['email'] ?? ''));
$password = getenv('CRM_ADMIN_PASSWORD') ?: '';
if (!$name || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || (strlen($password) < 12 || strlen($password) > 72)) exit("Provide --name and --email and set CRM_ADMIN_PASSWORD (12–72 bytes).\n");
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
if (one('SELECT id FROM users LIMIT 1')) exit("Already installed. No data was changed.\n");
foreach (['CREATE INDEX idx_enquiry_scope ON enquiries (institute_id, status)', 'CREATE INDEX idx_followup_due ON followups (institute_id, due_date)', 'CREATE INDEX idx_login_identity ON login_attempts (identity_hash, attempted_at)', 'CREATE INDEX idx_student_scope ON students (institute_id)'] as $sql) db()->exec($sql);
db()->beginTransaction();
try {
    query("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'owner')", [$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    if (isset($options['demo'])) {
        $now = date('Y-m-d H:i:s');
        foreach ([['Northstar Pharmacy Institute','Pharma','Kolkata'],['Northstar Medical Academy','Medical','Howrah']] as $n => $inst) {
            query('INSERT INTO institutes (name,kind,city,phone,created_at) VALUES (?,?,?,?,?)', [...$inst,'+91 90000 00000',$now]);
            $iid = (int)db()->lastInsertId();
            query("INSERT INTO users (institute_id,name,email,password_hash,role) VALUES (?,?,?,?,'counsellor')", [$iid,$n ? 'Riya Das' : 'Arjun Sen', 'counsellor'.($n+1).'@example.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
            $uid = (int)db()->lastInsertId();
            query('INSERT INTO courses (institute_id,name,duration,fee_minor) VALUES (?,?,?,?)', [$iid,$n ? 'Medical Lab Technology' : 'Diploma in Pharmacy','2 years',8500000]);
            $cid = (int)db()->lastInsertId();
            foreach (['Aarav Sharma','Ananya Roy','Ishita Bose','Rahul Das'] as $k => $student) {
                query('INSERT INTO enquiries (institute_id,course_id,assigned_to,name,phone,email,source,status,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)', [$iid,$cid,$uid,$student,'90000000'.($n+1).$k,'','Website',$k === 0 ? 'Admitted' : ($k === 1 ? 'Interested' : 'New'),'Fictional demo record.',$now]);
                $eid = (int)db()->lastInsertId();
                if ($k === 0) query('INSERT INTO students (institute_id,enquiry_id,course_id,name,phone,email,admission_date,fee_minor,created_at) VALUES (?,?,?,?,?,?,?,?,?)', [$iid,$eid,$cid,$student,'90000000'.($n+1).$k,'',date('Y-m-d'),8500000,$now]);
                else query("INSERT INTO followups (institute_id,enquiry_id,assigned_to,due_date,notes,outcome) VALUES (?,?,?,?,?,'')", [$iid,$eid,$uid,date('Y-m-d',strtotime(($k-2).' days')),'Discuss eligibility and arrange a campus visit.']);
            }
        }
    }
    db()->commit(); echo "Installed successfully. Open public/ and sign in with your owner account.\n";
} catch (Throwable $e) { db()->rollBack(); throw $e; }
