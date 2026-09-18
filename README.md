# Northstar — multi-institute educational CRM

A working **milestone-one CRM** for managing a group of pharma, medical, and other educational institutes. Responsive, server-rendered PHP with PDO, normal HTML forms, and local CSS/JavaScript. No Composer, Node.js, paid services, or frontend build step is required to run it on XAMPP.

> **Status:** functional foundation, not a certified production-ready ERP. The preview uses fictional records and SQLite. Production/XAMPP should use MySQL or MariaDB. Read the deployment checklist before putting real student information into it.

## What is implemented

- Owner login; institute administrator and counsellor accounts.
- Owner-wide overview and persistent institute filter.
- Institute creation and editing (type, city, contact).
- Courses with duration, INR fee, editing, and archive/unarchive.
- Staff creation and access disable/restore, scoped by institute and role.
- Enquiries with source, contact information, notes, status, course, and counsellor assignment; search, filters, pagination, editing.
- Scheduled follow-ups with due date, assigned counsellor, conversation plan, completion outcome, and overdue/completed filters.
- Admission conversion: copies contact/course details, snapshots the course fee in integer paise, creates a unique student record, marks the enquiry admitted, and closes open follow-ups **in one transaction**.
- Student directory with stable IDs, admission date, contact, course, institute, and agreed course fee.
- Live dashboard counts, recent enquiries, due follow-ups, and status pipeline. No fabricated analytics or percentages.
- Activity log, password changes, CLI password recovery, session idle timeout, login throttling.
- Prepared SQL, output escaping, CSRF tokens, server-side institute authorization, and role checks.
- Mobile navigation and responsive layouts; core workflows work without JavaScript.

### Roles

| Capability | Owner | Institute admin | Counsellor |
| --- | --- | --- | --- |
| View records | All institutes | Own institute | Own institute |
| Create/edit institutes | Yes | No | No |
| Create/edit/archive courses | Yes | Own institute | No |
| Create/disable staff | Admins + counsellors | Own counsellors | No |
| Manage enquiries and follow-ups | Yes | Own institute | Own institute |
| Convert admissions | Yes | Own institute | No |
| View students | Yes | Own institute | Own institute |
| View activity log | All | Actions by own institute staff | No |

Counsellors share their institute's enquiries; assignment is an operational responsibility, **not** a per-counsellor privacy boundary. Staff are assigned to one institute in this version. Owner accounts are not created from the staff screen.

## First-time setup on Windows / XAMPP

### 1. Install prerequisites

- XAMPP with **PHP 8.2 or newer** and MySQL/MariaDB.
- Enable the PDO MySQL extension (normally included).
- Download/clone this repository into `C:\xampp\htdocs\institute-crm`.
- Open XAMPP Control Panel and start **Apache** and **MySQL**.

### 2. Create your database

Open `http://localhost/phpmyadmin`, choose **SQL**, and run:

```sql
CREATE DATABASE institute_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

The installer creates the tables. Do not manually invent table columns.

### 3. Configure the connection

Copy `config.example.php` to **`config.php`** in the project root. The defaults work with a standard local XAMPP installation:

```php
<?php
return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=institute_crm;charset=utf8mb4',
    'user' => 'root',
    'password' => '',
    'timezone' => 'Asia/Kolkata',
    'secure_cookies' => false, // local HTTP only; true on live HTTPS
];
```

Keep `config.php` private. It is excluded from Git. The blank root password is **only** for a local default XAMPP setup, never live hosting.

### 4. Create your owner account

Open **PowerShell** and run these commands, replacing the example email and password:

```powershell
cd C:\xampp\htdocs\institute-crm
$env:CRM_ADMIN_PASSWORD = 'Choose-A-Unique-Strong-Password!'
C:\xampp\php\php.exe bin\install.php --name="Your Name" --email="you@example.com"
Remove-Item Env:CRM_ADMIN_PASSWORD
```

Passwords must be at least 12 characters and no more than 72 bytes. Use a unique password, not the example. There are **no hard-coded default login credentials**.

For fictional sample institutes, students, and follow-ups, add `--demo` on the **first** installation:

```powershell
C:\xampp\php\php.exe bin\install.php --name="Your Name" --email="you@example.com" --demo
```

Set the password environment variable first as above. Installation does not overwrite existing accounts or seed a second time. Demo counsellors have random, undisclosed passwords; reset them with the CLI if you want to test those roles. Use a separate database for your real institute rather than retaining demo records.

### 5. Open the website

Visit **`http://localhost/institute-crm/public/`** and sign in using the account you just created.

The safest setup is an Apache virtual host whose `DocumentRoot` points directly at `public`, keeping private files outside the web root:

```apache
<VirtualHost *:80>
    ServerName institute-crm.test
    DocumentRoot "C:/xampp/htdocs/institute-crm/public"
    <Directory "C:/xampp/htdocs/institute-crm/public">
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

For this optional named local host, add `127.0.0.1 institute-crm.test` to the Windows hosts file, enable Apache's virtual-host config, and restart Apache. The normal localhost URL above requires neither of these extra steps.

### 6. Your first real workflow

1. **Institutes** → add your pharma institute and medical institute.
2. **Courses** → choose and **Load institute**, then add courses and fees.
3. **Team & access** → choose and **Load institute**, create staff; communicate initial passwords securely.
4. **Enquiries** → choose and **Load institute**, enter contact details, course, and counsellor.
5. **Follow-ups** → choose institute, schedule a conversation; later record the outcome.
6. **Admissions** → review an open enquiry, choose admission date, and confirm.
7. **Students** → see the automatically created student record.
8. Use the institute selector at the top to narrow lists and dashboard totals.

Before adding enquiries, the institute needs at least one active course and one active admin/counsellor. The owner oversees all institutes but is not an assignable counsellor. Admitted enquiries are locked to preserve the admission snapshot. No destructive delete buttons are provided.

## Other local environments

Linux/macOS with PHP installed:

```sh
cp config.example.php config.php
# Edit config.php with the local database connection, then:
CRM_ADMIN_PASSWORD='Choose-A-Unique-Strong-Password!' php bin/install.php --name='Your Name' --email='you@example.com'
php -S 0.0.0.0:8080 -t public
```

PHP's built-in server is for development only.

For an isolated demo without MySQL, configure PDO SQLite:

```php
<?php
return [
    'dsn' => 'sqlite:' . __DIR__ . '/storage/demo.sqlite',
    'user' => '',
    'password' => '',
    'timezone' => 'Asia/Kolkata',
    'secure_cookies' => false,
];
```

Enable `pdo_sqlite`, make `storage/` writable, and run the same installer. SQLite is a preview/testing option, not a substitute for validating your MySQL deployment. Moving an existing SQLite dataset to MySQL requires explicit data migration; merely changing the DSN does not transfer records.

## Password recovery

A server operator can reset an existing account using the CLI (no web-accessible reset endpoint):

```powershell
$env:CRM_ADMIN_PASSWORD = 'A-New-Unique-Strong-Password!'
C:\xampp\php\php.exe bin\reset-password.php --email="you@example.com"
Remove-Item Env:CRM_ADMIN_PASSWORD
```

This records a recovery event and does not reactivate disabled accounts. Disable compromised accounts immediately; changing a password alone does not invalidate other already authenticated sessions in this milestone. Sessions expire after 30 minutes of inactivity.

## Tests

Requires Python 3 and PHP with `pdo_sqlite`:

```sh
python3 tests/smoke.py
# If PHP is not on PATH (PowerShell):
$env:PHP_BIN = 'C:/xampp/php/php.exe'
python tests/smoke.py
```

The suite creates a temporary database, installs an owner, starts a short-lived test server, exercises actual HTTP requests, and removes the database afterward. It does not touch `config.php` or your normal database. `CRM_CONFIG_FILE` is an environment-only override used for test isolation.

Coverage includes authentication, institute/role isolation, CSRF, output escaping, fee/date validation, staff disabling, enquiries, follow-up completion, admission transactions, duplicate admission protection, fee snapshots, search, all screens and create forms, password changes, and audit entries.

**Validation in this workspace:** PHP syntax checks and 64 HTTP integration checks passed using PHP 8.5 WebAssembly + PDO SQLite. Native MySQL/MariaDB and Apache/XAMPP have not been available for runtime verification here. Browser automation could not be run: the standard browser download was blocked and an alternate binary lacked required system libraries. Validate on your own XAMPP stack and supported browsers before deployment.

## Going live: mandatory checklist

- [ ] Use supported PHP 8.2+ and MySQL 8+/MariaDB 10.4+, patched Apache/Nginx and PHP; avoid exposing a local XAMPP machine.
- [ ] Point the web document root **only** at `public/`; `.htaccess` is defense-in-depth, not a substitute. Nginx ignores `.htaccess`.
- [ ] Configure HTTPS and set `secure_cookies` to `true`. Configure server-level HSTS and anti-framing headers according to your hosting environment.
- [ ] Use a dedicated database user with least privilege, not root. Installation needs DDL permissions; routine use needs SELECT/INSERT/UPDATE/DELETE only.
- [ ] Store `config.php` and backups outside publicly served paths. Restrict filesystem access. Keep display_errors disabled and PHP logs private.
- [ ] Create real accounts and strong unique passwords; no demo data or preview credentials in the live database.
- [ ] Re-test every role with **two different institutes**, including direct URL/ID changes.
- [ ] Test actual Apache, PHP, MySQL/MariaDB, backups, and restore on a staging server.
- [ ] Automate encrypted daily database backups, define retention, and test restore regularly. On MySQL, use `mysqldump --single-transaction`/`mariadb-dump` with securely supplied credentials; restore a backup into a separate database and verify counts/workflows.
- [ ] Add external uptime/error monitoring and a backup-failure alert. This milestone does not automate monitoring or backups.
- [ ] Set privacy/retention/consent policies appropriate to student data and your jurisdiction; collect only needed information.
- [ ] Plan operational support, security review, recovery drills, dependency/server updates, and database migrations before a real launch.

## Not part of milestone one

Fees here mean a **course fee snapshot**, not a payment ledger. There is no payment collection or outstanding-balance calculation yet. The following require subsequent milestones and testing:

- Fee installments, payments, numbered receipts, refunds, reconciliation, financial reports.
- Batches, teacher roles, attendance, timetables, examinations, results.
- Student/parent portals and document uploads.
- Email/SMS/WhatsApp reminders and payment gateway integrations.
- MFA, self-service email password recovery, device/session management.
- Bulk imports/exports, multi-currency, detailed compliance controls, automated backups/monitoring.
- Versioned schema migrations and full production load/security testing.

No placeholder buttons claim these features work. Scheduled follow-ups are visible in the CRM; they do not send messages automatically.

## Project structure

```text
app/bootstrap.php       Configuration, PDO helpers, validation, authorization
app/actions.php         Transactional form actions and login handling
app/views.php           Server-rendered screens and forms
public/index.php        HTTP entry point and session/security headers
public/assets/          Local CSS and progressive-enhancement JavaScript
bin/install.php         CLI schema initialization and optional fictional demo seed
bin/reset-password.php  CLI account recovery
config.example.php      Safe configuration template
storage/                Ignored local SQLite files (no student data in Git)
tests/smoke.py           Isolated HTTP integration suite
```

### Common setup problems

- **“Setup required”**: create `config.php`, check filename extensions, then run the installer.
- **“Could not find driver”**: enable `pdo_mysql` or `pdo_sqlite` in the PHP configuration used by that runtime, then restart Apache.
- **“Access denied” / connection refused**: check database username, password, port, and that MySQL is started.
- **Empty course/counsellor list**: load the correct institute and create an active course and staff member first.
- **Too many login attempts**: wait 15 minutes. Do not disable throttling on a live system.
- **Setup differs between terminal and Apache**: both must use the expected PHP version/extensions; XAMPP's PHP executable path is shown above.
- **A generic save error**: check for a duplicate staff email or course name, then inspect private server logs. Detailed database errors are deliberately not exposed to users.

## Optional GitHub Actions automation

`docs/github-actions-tests.yml.example` contains a CI workflow for PHP 8.2 and 8.4. A repository administrator with workflow-write permission can copy it to `.github/workflows/tests.yml`. It is provided as a template because the current GitHub App connection does not have permission to create workflows. Local tests work without GitHub Actions.
