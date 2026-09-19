# Academic operations, fee schedules and deployment readiness

## This is a working operations release, not a production certification

The CRM now has teacher records, course batches, capacity-controlled allocation, transfers, daily attendance, installment schedules, overdue reporting, CSV export, student academic views, and stronger staff-session revocation. A public launch still needs a tested MySQL deployment, backups and restore drills, monitoring, privacy controls and a security review. Do not equate an installation screen or green automated tests with a production audit.

## Upgrade your current installation

1. Pause staff activity and the notification worker during the file update. Export the database and privately back up the working code, `config.php`, and `storage/`.
2. Extract the updated `northstar-setup.zip` into a temporary folder. Replace application files (`app/`, `public/`, `bin/`, `docs/`, `vendor/`, `composer.json`, and help HTML files) in the existing installation. **Keep your existing configuration, database, storage and installer locks. Do not reinstall or nest a second project folder.**
3. Restart Apache and sign in as the group owner. This release deliberately requires existing staff sessions to sign in again because their session security format changes.
4. Open **`public/upgrade.php`** or **Account settings → Upgrade installed modules**, acknowledge your backup, and run the additive upgrade. It creates operations tables and indexes; it does not recreate the owner, alter student fees, or delete admissions/payments.
5. A server operator may instead run `php bin/migrate.php`. DDL privileges are needed during migration. MySQL DDL is not fully transactional; correct any privilege errors and rerun rather than deleting data. Normal operation should use least-privilege database permissions.
6. Test with fictional data before enabling normal activity. Confirm SMTP and resume your worker schedule. For a clean installation, the ordinary browser setup includes all modules automatically.

## Teaching and batches

### Teacher directory

Owners/admins maintain name, contact information, qualification and active/inactive state within their institute. Teacher entries are **directory records, not login accounts**. A separate teacher-role portal, payroll, timetabling and subject assignment are not implemented. A teacher email does not automatically create CRM access.

### Batch creation

Create a batch for an institute's active course and an active teacher, with a name, room, start/end dates and capacity **1–500**. Names are unique within the institute. Course, institute and date range are immutable after creation; create a new batch instead of rewriting its academic period. Name, teacher, room, capacity and active/archive status can be maintained. Capacity cannot be reduced below the current open-ended allocation count. Archived courses/teachers can be retained on an archived batch for historical reference; active batches must have an active teacher when saving/opening a class.

### Allocation and transfer

- Select the working institute, student, destination batch and allocation date. A student's admission course/institute must match the batch.
- One current open-ended batch allocation is allowed per student. Current allocation capacity is enforced server-side; no automatic waitlist exists.
- Dates must be on/after admission, within the new batch period, and no later than today. Transfers must occur after the previous allocation start date.
- A transfer closes the previous allocation on the day before the new one and appends a new record. It does not delete earlier attendance.
- You cannot backdate an allocation/transfer across a saved student attendance roster, or into a destination batch with a recorded class on/after the proposed allocation date. Use a later date. Even a draft class freezes its roster to prevent invisible historical membership changes.
- **View allocation history** shows up to the latest 500 allocation periods for a batch. Active capacity is a limit on current allocations, not a retroactive audit of every historical date.
- Standalone withdrawal/completion, waitlists, cross-course transfers and rejoining the same batch without an intervening transfer are not implemented. Archive finished batches and use the transfer workflow for a new eligible batch; do not manually edit history on a live database.

## Attendance

1. Open **Attendance**, choose an active batch, a past/today class date within its date range, and a topic.
2. **Create class roster** snapshots members eligible for that date. One daily class record per batch is supported—not multiple subject periods or time slots. Empty rosters and future classes are rejected.
3. Open the class and mark every student **Present**, **Absent**, **Late**, or **Excused**. Finalize the register. Until then it is a draft and does not count in student attendance statistics.
4. Corrections require a reason, retain full before/after snapshots in `attendance_revisions`, and increment a version. A stale browser form cannot overwrite someone else's newer register.
5. The history screen lists the actor, time and reason for the latest 20 revisions. Full before/after snapshots are retained in the database for an administrator audit; there is not yet a full visual diff/revision-export UI.
6. Download that class's CSV if needed. The CSV labels whether the register is finalized and includes unmarked statuses for drafts.

Students see **their own** published attendance, batch allocation history and current batch teacher/room in **Academic record**. Attendance percentage is explicitly `(Present + Late) / (Present + Late + Absent)`; excused and draft days are excluded. It is a daily-class-record statistic, not a subject/term-based attendance calculation. Exams/marks/results remain a future module.

## Installment plans and dues

1. Open **Fee schedules**, load a student, then enter up to 36 unique due dates and positive INR amounts. Six rows are available without JavaScript; **Add installment row** extends the form to 36.
2. The total must equal the **full fee snapshotted on admission**, including money already paid. It must not merely equal today's unpaid balance. A zero-fee admission does not need a plan.
3. Dates cannot precede admission. Past dates are allowed when migrating an existing fee agreement.
4. Every save needs a reason. Replacing the current plan is transactional, stores before/after data in `fee_plan_revisions`, and uses a version check to prevent lost edits. The history view lists up to 20 recent actors/reasons/times; all snapshots remain in the database.
5. Recorded payments are applied to earliest due dates first **for reporting**. This does not rewrite payments, receipts or any financial ledger allocation. Previously issued receipts remain immutable snapshots.

**Fee reports** distinguishes:

- **Total unpaid:** admission fee minus all recorded payments.
- **Overdue:** installments with dates before today minus all recorded payments, floored at zero.
- **Due today:** visible on the student's installment detail, but not considered overdue until tomorrow.
- **No schedule:** unpaid is still shown, but overdue is explicitly “Not scheduled”, not falsely assumed zero due.

Students see their own current schedule/remaining amounts in **My payments**. These reports are current-state views, not historical as-of-date accounting reports. There are no automatic overdue reminder emails, penalties, scholarships, installment-specific payment instructions, refunds, payment reversals, write-offs, tax invoices, accounting reconciliation, or online gateway settlement in this release. **Do not use an irreversible manual-payment ledger as your sole production accounting system until an audited correction/refund workflow is implemented.**

## Exports and access

- Teachers, batches, attendance, fee plans and reports are restricted to owners/admins. Institute admins see only their own institute; counsellors cannot mutate these modules or export finance data.
- Fee reports and individual class rosters can be exported through POST forms with CSRF validation. Exports are audited and do not depend on trusting a client-supplied institute ID.
- Fee export is limited to 5,000 students per request. It refuses a larger scope rather than silently truncating. Narrow the institute selection or arrange an administrator export for larger datasets.
- Spreadsheet formula prefixes (including common whitespace/invisible-character prefixes) are neutralized in CSV strings. Keep student CSV files private, restrict access, and delete temporary copies according to your retention policy.
- Paginated operational directories show 30 records per page. Form selection lists currently load the selected institute's records; very large institutions will need searchable/paginated selectors and load testing before rollout.

## Staff session hardening

Staff authentication now includes a server-side security version and a fingerprint of user ID, password hash, role and institute assignment.

- Password changes invalidate other staff sessions; the browser changing its own password remains signed in. CLI password resets invalidate existing sessions too.
- Disabling/restoring a staff account does not revive its prior sessions. Pending staff OTP challenges are invalidated.
- **Team & access → Revoke sessions** lets owners/admins revoke permitted staff sessions without disabling the account. Admins can only manage their own counsellors; they cannot revoke another administrator or owner.
- **Account settings → Sign out all my staff sessions** invalidates your own sessions, including the current one.
- Revocation is enforced on subsequent requests. A response already in progress cannot be retracted. Student sessions retain their separate account/version policy.
- This is not a device/session inventory or phishing-resistant MFA. Protect staff email accounts, restrict admin access, and consider passkeys or MFA for higher-risk deployments.

## Owner deployment checks

**Deployment checks** shows PHP version, environment mode, secure-cookie setting, mail transport, database driver, and pending/failed notification counts. It never displays SMTP/database passwords or private filesystem paths. These are informational observations, **not** an automated security scan, SMTP-deliverability guarantee, worker heartbeat, backup verification, or production certification.

### Minimum go-live gate

- [ ] Run all tests on staging using the **actual PHP, Apache/Nginx and MySQL/MariaDB versions** you will deploy. Test two institutes, each staff role and two student accounts. Load-test expected class sizes and concurrent clerks; SQLite tests alone are insufficient.
- [ ] Serve only `public/`. Use HTTPS, secure cookies, patched dependencies, correct reverse-proxy trust, appropriate CSP/anti-framing headers and restricted private filesystem permissions. Never expose XAMPP itself publicly.
- [ ] Use unique strong owner credentials/mail security. Restrict the migration account's DDL permissions after upgrading; application accounts should not be database root.
- [ ] Run `composer audit` for each release. Scan the application and review authentication, data exports, financial integrity and privilege boundaries with a qualified reviewer.
- [ ] Configure actual SMTP, test inbox delivery, monitor bounces/provider quotas, and schedule the notification worker. Alert on failures/stuck messages; the displayed queue count is not monitoring.
- [ ] Automate **encrypted database backups**, keep an off-server copy, define retention and secure access. Use MySQL/MariaDB's supported dump tooling with credentials supplied privately, not command-line passwords. Never store backups under `public/` or commit them to Git.
- [ ] Restore a backup into a **separate test database**, verify student/payment/document/attendance counts and account isolation, and record the drill externally. A successful dump command is not proof of a working restore. Backup and restore automation are not bundled here.
- [ ] Set external uptime checks, private error-log monitoring/rotation, disk-space alerts and incident response ownership. Define recovery time and acceptable data-loss targets.
- [ ] Obtain appropriate student consent; document privacy notices, retention and account/email-verification processes. Email/PDF exports carry personal and financial information and are not end-to-end encryption.
- [ ] Reconcile financial entries against your real accounting process. Deploy a tested reversal/refund workflow before treating the CRM ledger as authoritative accounting.

## Verification

```sh
python3 tests/smoke.py
python3 tests/communications.py
python3 tests/setup.py
python3 tests/portal.py
python3 tests/operations.py
```

The operations suite covers institute/role separation, batch capacity, frozen rosters, date limits, stale-form conflicts, attendance revisions, atomic fee-plan replacement, FIFO installment coverage, protected/formula-neutralized CSV exports, portal-only student views, and staff-session revocation. The workspace tests use PHP WebAssembly, SQLite and private local email capture; no real Gmail delivery, native MySQL concurrency, live browser rendering or full production load/security audit has been performed here.

### Native MySQL/MariaDB smoke gate (not executed in this workspace)

`tests/mysql-smoke.php` is an additional **opt-in** native database gate. It exercises additive migrations, InnoDB-backed academic/fee actions, receipt totals, and a two-connection stale-payment-snapshot scenario. Write transactions use **READ COMMITTED plus student/batch/challenge row locks**, so a balance check after waiting does not retain an older REPEATABLE READ snapshot. Full parallel HTTP/load/deadlock testing is still needed. If binary logging is enabled, use an InnoDB-compatible ROW/MIXED logging configuration rather than statement-only logging with READ COMMITTED.

Ask your server administrator to create a **new empty, isolated TEST database**, not a copy connected to production. Put a private PHP configuration outside the web root with its DSN/user/password, `environment => local` and `timezone => Asia/Kolkata`. Do not paste credentials into a chat or commit that file. In PowerShell:

```powershell
$env:CRM_CONFIG_FILE = 'C:\private\crm-native-test.php'
$env:CRM_MYSQL_TEST_ALLOW = '1'
C:\xampp\php\php.exe tests\mysql-smoke.php
Remove-Item Env:CRM_MYSQL_TEST_ALLOW
Remove-Item Env:CRM_CONFIG_FILE
```

The script refuses a nonempty database and never drops tables; it leaves fictional fixture data behind for inspection. Use a newly created empty database for each run and remove the test database privately afterward. It does not send mail. The regular Python suites always use SQLite, even when MySQL is installed. Run those on the target PHP runtime too, then run this native gate and perform staging HTTP acceptance tests against your actual configured MySQL server. The optional CI template includes a disposable native-engine job, but that job has **not** run in this workspace.
