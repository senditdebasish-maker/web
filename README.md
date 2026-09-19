# Northstar — multi-institute educational CRM

A PHP/MySQL CRM for pharma, medical, and other educational institutes. Includes enquiries, admissions, student records, staff roles, **email OTP login**, **recorded payments**, **PDF admission letters/receipts**, and a retryable student-email queue.

> Functional development foundation, not a fully audited production ERP. Before using real student data, validate the application on your XAMPP/MySQL stack, configure HTTPS, protect secrets, and test backup/restore. No real Gmail delivery has been performed in the development environment.

## What's working

- Multiple institutes, courses, staff, institute filters, dashboard, search and pagination.
- Owner, institute-admin and counsellor authorization, with server-side institute isolation.
- Enquiries, counsellor assignments, status changes, follow-up scheduling/completion.
- Transactional admission conversion into a student record with a course-fee snapshot.
- **Passwordless staff login:** enter the registered email → receive a six-digit code → verify in the same browser.
- **Gmail SMTP:** sends login codes immediately; supports App Passwords and verified TLS.
- **Admission confirmation PDF:** generated from a saved snapshot; queued for email on admission.
- **Payment recording:** cash/UPI/bank/card/cheque, date/reference, integer-paise amounts, balance validation, duplicate-form protection.
- **Numbered PDF receipts:** `RCPT-0000001`, with amount, payment method, date, and balance when recorded.
- **Documents:** authenticated PDF downloads; counsellors cannot download financial PDFs.
- **Email notifications:** pending/sending/sent/blocked/failed/spooled states, five-attempt automatic retry, admin requeue.
- Student email maintenance and generation of admission letters for pre-existing students.
- Activity log, CSRF protection, escaped HTML, prepared SQL, login throttling, session idle timeout.

**Not Google Sign-In:** the CRM uses email OTP delivered through Gmail SMTP, not Google's OAuth “Sign in with Google” button. Staff must already have an active CRM account. Recipients can use Gmail or any other valid email provider.

## Upgrading the existing milestone-one installation

1. Back up the database and private `config.php`.
2. Install Composer and required PHP extensions (below).
3. Run `composer install --no-dev --prefer-dist` in the repository root.
4. Run `php bin/migrate.php`. This adds tables without deleting existing records; it is safe to rerun.
5. Add `auth_mode`, `environment`, and `mail` settings from `config.example.php` into your existing private `config.php`. **Do not replace your database credentials.** OTP is the default; configure mail before switching an existing service over.
6. Follow [Gmail setup](docs/GMAIL-SETUP.md), run `php bin/test-mail.php --to=your-address@example.com`, and check the actual inbox.
7. Schedule the notification worker and test a new admission/payment with a test student email.
8. Existing student records are preserved. In **Students → Manage email / letter**, add their email and click **Create admission letter** if needed. Old admissions are not bulk-emailed automatically.

An explicit server configuration of `'auth_mode' => 'password'` enables the old password-only login for controlled migration/recovery. It is **not** a second login option in OTP mode; the old password endpoint is rejected when OTP is enabled. Remove the fallback before launch if you require OTP-only access.

## New installation on Windows / XAMPP

### 1. Prerequisites

- XAMPP with **PHP 8.2+**, MySQL/MariaDB and Apache.
- PHP extensions: `pdo_mysql`, `dom`, `mbstring`, `openssl`, `ctype`, `filter`, `hash`, `iconv`. `pdo_sqlite` is needed for automated tests/local SQLite demos. `curl` and `zip` are recommended for Composer.
- **Composer 2** installed with `C:\xampp\php\php.exe` as its PHP executable.
- No Node.js or frontend build tools are needed to run the application.

Put the repository in `C:\xampp\htdocs\institute-crm`. Start Apache and MySQL from XAMPP Control Panel.

### 2. Install dependencies

In PowerShell:

```powershell
cd C:\xampp\htdocs\institute-crm
composer install --no-dev --prefer-dist
```

PHPMailer handles SMTP/MIME; Dompdf generates actual PDF files with embedded fonts. Dependencies live in ignored `vendor/`, not in Git. `composer.json` pins the currently used packages, including PDF transitive dependencies. Keep Composer's generated lockfile when managing a deployment and run `composer audit` before going live.

### 3. Create the database and configuration

Open `http://localhost/phpmyadmin` → SQL:

```sql
CREATE DATABASE institute_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Copy `config.example.php` to `config.php`. Fill in the local database username/password. Standard local XAMPP uses `root` and an empty password; **never use that configuration on the public internet**.

Configure your sender using the private file or environment variables, as described in [Gmail setup](docs/GMAIL-SETUP.md). Do not upload `config.php` to Git or send credentials in chat.

### 4. Create the owner

```powershell
$env:CRM_ADMIN_PASSWORD = 'Choose-A-Unique-Strong-Recovery-Password!'
C:\xampp\php\php.exe bin\install.php --name="Your Name" --email="your-real-email@gmail.com"
Remove-Item Env:CRM_ADMIN_PASSWORD
```

This initializes the schema and owner account. The owner's **email must be accessible** to receive OTPs. The password is a legacy recovery credential and does **not** enable password login while `auth_mode` is `otp`. It must be 12–72 bytes. There are no universal default credentials.

Optional: add `--demo` on the first install for fictional sample institutes/students. The demo staff addresses are `example.test`; change/use real staff accounts for real email testing. Demo students have no email until you add one. Re-running the installer never overwrites accounts or inserts the demo twice.

### 5. Open and sign in

Visit **`http://localhost/institute-crm/public/`**.

Enter the registered staff email, click **Send sign-in code**, then enter the six-digit code in the same browser. Check spam if necessary. Codes expire after **5 minutes**, allow **5 verification attempts**, and can be used only once. Wait **60 seconds** before resending. A new code invalidates older ones for that address. Login does not create a new account.

The email-address request response is intentionally generic for active, unknown and disabled addresses. If email sending fails, login does not bypass verification. Administrators should verify SMTP with the CLI test command.

### 6. Schedule student notifications

Login codes are sent immediately. Student admission/payment emails are processed by a separate worker so SMTP failures cannot undo business records.

Run a batch manually:

```powershell
C:\xampp\php\php.exe bin\send-notifications.php --limit=25
```

For Windows Task Scheduler, configure a task repeating every minute:

- **Program:** `C:\xampp\php\php.exe`
- **Arguments:** `C:\xampp\htdocs\institute-crm\bin\send-notifications.php --limit=25`
- **Start in:** `C:\xampp\htdocs\institute-crm`

Configure it for the correct service account/environment. The machine and MySQL must be running. Check **Email notifications** for delivery state and failures. No task is automatically installed on your computer by this repository.

## Daily workflows

### Admissions

1. Add institutes, courses and active staff.
2. Add an enquiry with course, counsellor, phone and **student email**.
3. Schedule/complete follow-ups.
4. Administrator/owner confirms admission.
5. Student + admission document + notification are saved together in a transaction.
6. Download the PDF in **Documents**; run/schedule the worker to send the email.

If no student email was provided, the admission is still recorded and the notification is **blocked**, not falsely marked sent. Add the address in **Students** to release those blocked notifications.

### Payments

1. Open **Payments** as owner/admin.
2. Choose the student; the option shows the current unpaid balance.
3. Enter the amount actually received, payment date, method, and reference (required except cash).
4. Confirm once. The saved receipt uses a stable number and immutable snapshot.
5. Download its PDF or check its email delivery in **Email notifications**.

This records payments already received; it does **not** charge a bank/card or verify that a UPI transfer settled. Non-cash references are recorded, not verified. Double-submitting the same form is idempotent, but independently submitting two new forms with the same real-world payment reference is not automatically detected. Check the ledger before entering a payment again.

Payments cannot exceed the course balance or be backdated before admission. Amounts and fees use INR only. Receipts are **not tax invoices**. Refunds, reversals, discounts, installments, financial exports, accounting reconciliation, and gateway integrations need a subsequent finance milestone. Payments have no delete/edit buttons; resolve entry mistakes through an audited reversal feature before relying on this ledger operationally.

### Email states

| State | Meaning |
| --- | --- |
| Pending | Waiting for the worker or its scheduled retry |
| Sending | Claimed by a worker with a five-minute lease |
| Sent | Accepted by SMTP; inbox delivery is not guaranteed |
| Blocked | Student email is missing; add it in Students |
| Failed | Five attempts failed; check setup and requeue as admin |
| Spooled | Local `.eml` test file only; **no real email sent** |

Failed delivery retries use increasing delays. Restarting a worker does not repeat completed items. A crash after SMTP accepts a message but before the database records success can cause a repeat after the lease expires: delivery is **at least once**, not exactly once. Stable message IDs and receipt numbers aid reconciliation but do not guarantee mail-provider deduplication. Review the inbox/provider before manually requeuing an uncertain delivery.

Updating a student email automatically releases **blocked** items only. Already pending, failed, or sent items retain their original recipient to avoid silently redirecting a financial document. Verify student email before admission/payment; recipient correction for an already queued item requires a controlled administrator data correction, not a blind requeue.

## Roles

| Capability | Owner | Institute admin | Counsellor |
| --- | --- | --- | --- |
| Institute records | All | Own | Own |
| Create/edit institutes | Yes | No | No |
| Courses | Manage | Manage own | View own |
| Staff access | Manage all staff | Manage own counsellors | No |
| Enquiries/follow-ups | Manage all | Manage own | Manage own |
| Confirm admission | Yes | Own institute | No |
| Record payments / financial PDFs | Yes | Own institute | No |
| Admission PDF downloads | All | Own | Own |
| Update student emails / retry mail | Yes | Own institute | No |
| Activity log | All | Actions by own institute staff | No |

Staff belong to one institute; counsellors share their institute's enquiries. Assignment is not a per-counsellor privacy boundary. Owner accounts are installed using the CLI, not the staff form. New staff accounts in OTP mode do not need a password.

## Local development without actual email

Use a private, ignored `config.php` with:

```php
<?php
return [
    'dsn' => 'sqlite:' . __DIR__ . '/storage/demo.sqlite',
    'timezone' => 'Asia/Kolkata',
    'environment' => 'local',
    'secure_cookies' => false,
    'auth_mode' => 'otp',
    'mail' => [
        'transport' => 'log',
        'from_email' => 'institute@example.test',
        'from_name' => 'Demo Institute',
        'log_path' => __DIR__ . '/storage/mail',
    ],
];
```

Run the installer, then `php -S 0.0.0.0:8080 -t public`. When requesting an OTP, open the newest private `.eml` file in `storage/mail/` with a text editor/mail client and read the code. Student emails are captured with actual PDF attachments after running the worker. There is deliberately **no public browser inbox or OTP-reveal endpoint**.

Log transport is refused unless `environment=local`. Keep the capture directory outside the public document root, restrict filesystem access and delete test captures promptly: they contain login codes and potentially personal documents. Do not deploy test captures or a local/test configuration to live hosting.

## Tests and validation

Install dependencies, PHP with `pdo_sqlite`, and Python 3:

```sh
python3 tests/smoke.py
python3 tests/communications.py
```

If PHP isn't on PATH (PowerShell):

```powershell
$env:PHP_BIN = 'C:/xampp/php/php.exe'
python tests/smoke.py
python tests/communications.py
```

Both suites use disposable SQLite databases and local test servers, not your normal database. The communication suite captures mail privately and sends no real emails. Coverage includes OTP expiry/replay/attempt limits, CSRF, staff disabling, permission checks, transactional admission/queue creation, real PDF downloads/attachments, payment amounts/idempotency/balance validation, blocked recipients, worker retries and recovery.

**Validation here:** 64 baseline checks + 71 communication checks passed with PHP 8.5 WebAssembly/PDO SQLite and the pinned PDF/mail dependencies. Native Apache/XAMPP, MySQL/MariaDB, real Gmail SMTP and mailbox delivery still need validation on your server. The sandbox had no Composer network access; dependencies were checked out at the pinned upstream tags for testing. Run the standard Composer installation/audit on your target server before launch.

The optional GitHub Actions template in `docs/github-actions-tests.yml.example` includes both suites. An administrator with workflow permission can copy it to `.github/workflows/tests.yml`.

## Deployment checklist

- Serve **only `public/`** as the web document root; never expose the repository root, `storage/`, `vendor/`, configuration, backups, or logs. Nginx ignores `.htaccess`.
- Use HTTPS, `secure_cookies=true`, patched PHP/MySQL/server, server-level HSTS and appropriate anti-framing headers.
- Use a dedicated least-privilege database user. Installation/migration needs DDL privileges; normal operation does not.
- Configure Gmail credentials privately, test an inbox, and schedule/monitor the worker. Do not disable TLS certificate verification.
- Keep database backups encrypted; define retention and test restores, including payments/documents/notification states. No automated backup system is bundled.
- Re-test access with two institutes and all roles, including direct document URL changes.
- Run `composer audit`; maintain dependencies and server security updates.
- Add monitoring for failed logins, stuck pending mail, failed workers, bounced email and backup failures. These alerts are not automated here.
- Confirm student consent, contact accuracy, data-minimization/retention and applicable privacy requirements. PDFs contain personal/financial data; ordinary email is not end-to-end encryption.
- Verify PDF output for your institute names and languages. DejaVu supports common Unicode characters, but complex-script shaping/additional fonts require validation before using Bengali or other regional scripts in official documents.
- Email OTP is not phishing-resistant MFA; mailbox compromise can compromise CRM access. Use strong Google account security and consider passkeys/MFA for a later security milestone.

Sessions expire after 30 minutes of inactivity. Disabling an account removes its access on subsequent requests. Password changes alone do not revoke existing sessions; in OTP mode, password reset does not recover an inaccessible mailbox. Server operators should handle account-email recovery through a verified administrative process.

## Still planned

Attendance, batches, timetables, exams/results, student/parent portals, uploads, installment plans, refunds/reversals, accounting/gateway integrations, SMS/WhatsApp, bulk imports/exports, device/session management and production load/security auditing. Follow-ups are in-app tasks; they do not send automatic reminders in this version.

## Structure

```text
app/bootstrap.php           PDO, validation, authorization and transactions
app/actions.php             Core CRM form actions
app/otp.php                 Email-code request and verification
app/mail.php                Gmail SMTP / private local test mail transport
app/finance.php             Payment entry and communication actions
app/documents.php           Immutable document snapshots and PDF rendering
app/notifications.php       Claim/retry/deliver notification batches
app/migrations.php          Additive email/payment schema changes
app/views.php               CRM screens and OTP login
app/communication-views.php Payments, document and notification screens
public/index.php            HTTP entry point, session and security policy
public/document.php         Authorized PDF download endpoint
bin/install.php             First-time installation
bin/migrate.php             Upgrade existing databases
bin/test-mail.php           SMTP configuration test
bin/send-notifications.php  Scheduled notification worker
config.example.php          Safe configuration template
composer.json               Pinned mail/PDF dependencies
storage/                    Private, ignored runtime data and test mail
```

### Common problems

- **Email login not configured:** install Composer dependencies and check the private mail configuration; restart Apache after configuration/environment changes.
- **No code:** use an existing active staff email, wait for cooldown, check spam and run `bin/test-mail.php` from the server. The CLI and Apache may have different environment variables/configurations.
- **Invalid/expired code:** use the most recent code in the browser that requested it. Request another after the cooldown if expired/exhausted.
- **PDF unavailable:** confirm `vendor/`, `dom`, `mbstring`, and writable `storage/pdf-cache/`; check server dependency installation.
- **Pending notifications never move:** run/schedule `bin/send-notifications.php` and check its exit/log output.
- **Blocked message:** add the missing student email in Students.
- **Could not find driver:** enable the relevant PDO extension in the PHP runtime used by both Apache and CLI.
- **Missing tables after upgrade:** back up the database, then run `bin/migrate.php` using the same config as Apache.
- **Generic save error:** check duplicate staff emails/course names or server logs. Database errors are intentionally not exposed to students/staff.
