<?php
declare(strict_types=1);
function db(): PDO {
    static $pdo;
    global $config;
    if (!$pdo) {
        $pdo = new PDO($config['dsn'], $config['user'] ?? '', $config['password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}
function query(string $sql, array $args = []): PDOStatement { $s = db()->prepare($sql); $s->execute($args); return $s; }
function rows(string $sql, array $args = []): array { return query($sql, $args)->fetchAll(); }
function one(string $sql, array $args = []): ?array { return query($sql, $args)->fetch() ?: null; }
function e(mixed $s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function fail(string $message): never { throw new DomainException($message); }
function input(string $key, int $max = 200, bool $required = true): string {
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) fail('Invalid field: ' . $key);
    $v = trim($v);
    if (($required && $v === '') || strlen($v) > $max) fail('Please provide a valid ' . str_replace('_', ' ', $key) . ' (maximum ' . $max . ' characters).');
    return $v;
}
function choice(string $key, array $choices): string { $v = input($key); if (!in_array($v, $choices, true)) fail('Invalid ' . $key); return $v; }
function emailInput(): string { $v = strtolower(input('email')); if (!filter_var($v, FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.'); return $v; }
function validDate(string $key, bool $required = true): string {
    $v = input($key, 10, $required); if (!$v && !$required) return '';
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) fail('Enter a valid date.'); return $v;
}
function audit(string $action, string $entity, int $id): void {
    query('INSERT INTO audit_log (user_id, action, entity, entity_id, created_at) VALUES (?, ?, ?, ?, ?)', [$_SESSION['uid'] ?? null, $action, $entity, $id, date('Y-m-d H:i:s')]);
}
function currentUser(): ?array {
    if(!isset($_SESSION['uid'],$_SESSION['staff_stamp'])) return null;
    $u=one('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['uid']]);
    return $u && hash_equals(staffStamp($u),$_SESSION['staff_stamp']) ? $u : null;
}
function requireRole(array $roles): array { $u = currentUser(); if (!$u || !in_array($u['role'], $roles, true)) fail('You do not have permission for this action.'); return $u; }
function instituteAccess(int $id): void {
    $u = currentUser();
    if (!$u || !one('SELECT id FROM institutes WHERE id = ?', [$id]) || ($u['role'] !== 'owner' && (int)$u['institute_id'] !== $id)) fail('Institute not accessible.');
}
function record(string $table, int $id): array {
    if (!in_array($table, ['courses', 'enquiries', 'students', 'followups', 'payments', 'documents', 'notifications'], true)) fail('Invalid record type.');
    $r = one("SELECT * FROM $table WHERE id = ?", [$id]); if (!$r) fail('Record not found.'); instituteAccess((int)$r['institute_id']); return $r;
}
function courseAccess(int $id, int $institute): array {
    $r = record('courses', $id); if ((int)$r['institute_id'] !== $institute || !(int)$r['active']) fail('Select an active course from the same institute.'); return $r;
}
function counsellorAccess(int $id, int $institute): void {
    if (!one("SELECT id FROM users WHERE id = ? AND institute_id = ? AND active = 1 AND role IN ('admin','counsellor')", [$id, $institute])) fail('Select an active counsellor or administrator from this institute.');
}
function csrf(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function redirect(string $page): never { header('Location: ?page=' . urlencode($page)); exit; }

function dependencies(): void {
    $file=dirname(__DIR__).'/vendor/autoload.php';
    if (!is_file($file)) throw new RuntimeException('Run composer install to enable email and PDF support.');
    require_once $file;
}
function writeTransaction(): void {
    // SQLite must acquire its write lock before reading balances/challenge counters.
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
        db()->beginTransaction();
        db()->exec('UPDATE users SET active=active WHERE id=-1');
    } else {
        // Do not retain a pre-lock REPEATABLE READ snapshot after waiting for another writer.
        // Student/batch/challenge row locks remain the serialization points.
        db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        db()->beginTransaction();
    }
}
function lockSuffix(): string { return db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : ''; }
function otpEnabled(): bool { global $config; return ($config['auth_mode'] ?? 'otp') === 'otp'; }

function staffStamp(array $user): string {
    try { $version=(int)(one('SELECT version FROM staff_security WHERE id=?',[$user['id']])['version']??0); }
    catch(PDOException $e) {
        // Only a genuinely absent legacy table may fall back; permission/connection failures fail closed.
        if ($e->getCode()!=='42S02' && !str_contains($e->getMessage(),'no such table: staff_security')) throw $e;
        $version=0;
    }
    return hash('sha256',$user['id'].'|'.$user['password_hash'].'|'.$version.'|'.$user['role'].'|'.($user['institute_id']??''));
}
