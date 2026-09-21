<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$options = getopt('', ['name:', 'email:', 'demo']);
$name = trim($options['name'] ?? 'Institute Owner');
$email = strtolower(trim($options['email'] ?? ''));
$password = getenv('CRM_ADMIN_PASSWORD') ?: '';
if (!$name || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || (strlen($password) < 12 || strlen($password) > 72)) exit("Provide --name and --email and set CRM_ADMIN_PASSWORD (12–72 bytes).\n");
require dirname(__DIR__).'/app/schema.php';
initializeSchema();
if (one('SELECT id FROM users LIMIT 1')) exit("Already installed. No data was changed.\n");
db()->beginTransaction();
try {
    query("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'owner')", [$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    if (isset($options['demo'])) {
        $now = date('Y-m-d H:i:s');
        foreach ([['Northstar Pharmacy Institute','Pharma','Kolkata'],['Northstar Medical Academy','Medical','Howrah']] as $n => $inst) {
            query('INSERT INTO institutes (name,kind,city,phone,address,created_at) VALUES (?,?,?,?,?,?)', [...$inst,'+91 90000 00000',$n ? '12 College Street, Howrah, West Bengal 711101' : '45 Park Street, Kolkata, West Bengal 700016',$now]);
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
