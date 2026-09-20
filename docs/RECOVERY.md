# Database backups, recovery and monitoring

**Do not experiment on the production database.** A snapshot file or SHA-256 checksum does not prove that your service can be restored. Establish a separate restore-test environment and have a responsible operator perform recovery drills before live deployment.

## What the helper does

```text
php bin/backup.php --directory=PRIVATE_BACKUP_DIRECTORY
```

The supplied directory must already exist, be writable and be **outside every web root**. The helper refuses the application tree and recognized ancestor web roots (`htdocs`, `www`, `wwwroot`, `public_html`). It cannot discover every custom virtual host or cloud mount; verify directory privacy yourself.

- **SQLite:** uses `VACUUM INTO` to produce a consistent database snapshot, not a blind copy of a possibly live WAL-mode file.
- **Local/socket MySQL or MariaDB:** invokes your installed, trusted `mysqldump` / `mariadb-dump` with a single-transaction InnoDB dump. It obtains connection details from the existing private app configuration. It does not invoke a shell or put the password in command arguments. Supply `--dump-binary` if the executable is not on PATH.
- **Remote MySQL:** deliberately refused by this helper. Use your hosting provider's backup service or native tools with verified TLS and the correct server identity. This helper does not infer a remote TLS policy.
- Temporary files receive restrictive POSIX modes; a finalized `.sqlite` or `.sql` file is accompanied by a `.json` manifest with driver, timestamp, size and SHA-256. Backup names have random suffixes so later runs do not overwrite old files.
- Output is **not encrypted**. Checksums detect accidental corruption; they are not authentication against an attacker who can alter both files.
- MySQL dumps cover database tables/data, not server accounts/grants, routines, events or server configuration. The CRM does not depend on stored routines/events. The helper passes no `--databases` flag, so a normal dump can be imported into a differently named **empty test database**. Confirm the generated file with your actual native client before relying on that behavior.
- Normal errors clean up temporary dump/client-option files. Abrupt termination/power loss can leave `.northstar-part-*` or `.northstar-client-*` files; remove abandoned files privately after verifying that no backup is running. Client-option files temporarily contain database credentials. **Never upload/copy temporary files or include them in an off-server backup upload.**

The SQLite path and a separate restore/integrity check were exercised here. **The native MySQL/MariaDB dump path has not executed here.** Validate its executable, privileges, quoting, client/server compatibility and restore result on staging first. Do not run schema changes while a dump is in progress. Single-transaction consistency assumes InnoDB tables; do not add nontransactional tables and assume the same guarantee.

## Example: Windows/XAMPP

1. Create `C:\private-crm-backups`, not a folder under `htdocs`.
2. Restrict its Windows ACL to your designated backup/server operator. POSIX `chmod` is not a Windows ACL security policy.
3. In PowerShell from the application directory:

```powershell
C:\xampp\php\php.exe bin\backup.php --directory=C:\private-crm-backups --dump-binary=C:\xampp\mysql\bin\mysqldump.exe
```

The MySQL executable switch is ignored for SQLite. Keep the app's `config.php` private; never put real passwords into task names, command-line arguments, Git or chat.

4. Confirm exit status on native PHP, output files and manifest. A backup with no manifest needs investigation, not blind upload.
5. Encrypt completed backups using your organization's trusted backup/encryption product, then copy them off-server. Store recovery keys separately and test access under incident conditions. No encryption-key management or remote upload is implemented here.
6. Schedule only after a successful manual **backup and restore drill**. Monitor job exit status, age/size/checksum of the latest completed backup, available disk space and off-server copy status. Do not blindly delete old backups on a timer before confirming a newer recoverable copy.

## What else to back up

Database snapshots do **not** include application code, private configuration, mail credentials, installer locks, scheduled-task definitions or storage contents.

- Keep a known-working version of the application/dependency package and its commit identifier.
- Securely preserve `config.php`, necessary `storage/` files/installer locks, private keys, server/PHP settings and task definitions. Separate secrets from shared diagnostic bundles.
- Do not publish email captures, sessions, student CSVs, PDFs or database exports. Decide which runtime files are actually required for recovery versus disposable cache under your retention policy.
- Record recovery-time and acceptable-data-loss targets. Define who can authorize restoration and who contacts students/staff after an incident.

## Isolated restore drill

1. Create a private staging environment with outbound email blocked or local capture enabled. Use a **different configuration file and database**. Never change production credentials while testing.
2. Verify the backup's SHA-256 against its stored manifest. On Windows use `Get-FileHash -Algorithm SHA256`. A checksum match is only the first check.
3. For SQLite, copy the snapshot to a **new file path**. Open that copy and run `PRAGMA integrity_check`; verify expected table/record counts, exam marks, support messages and financial totals. Never overwrite the running database file.
4. For MySQL/MariaDB, create a new empty test database and import the dump there with your native client/administrator tooling. Supply passwords through a private client configuration or prompt, never inline. Inspect the dump for database-selection statements and verify the import target before running it. No automatic restore command is included because a mistaken restore can destroy live data.
5. Match counts and totals for institutes, users, enquiries, students, payments, documents, fee plans, attendance, exams, notices, support conversations, applicant accounts, applications, accepted notices and review events against a record captured at backup time. Test at least two institutes and student access isolation.
6. Ensure restore testing cannot send old queued notifications or accidentally expose private records. Enable the worker only when the recipient/transport policy is understood; delivery is at-least-once and an interrupted send can result in a duplicate.
7. Test owner/staff/student login on the isolated environment. Stale restored OTP/session data must not be treated as valid production identity. Revoke staff/student sessions as part of a documented incident recovery process, verified by your operator.
8. Record the drill's date, backup identifier, duration, checks and problems **outside this CRM**. Remove the test environment securely when finished.

## Monitoring hooks

Run `php bin/health.php` privately via your scheduler or monitoring agent. The JSON reports configured environment/cookies/mail transport, database availability, worker heartbeat and queue counts without exposing private paths/passwords.

- Native PHP exit 0: automated observations are currently OK, **not production certification**.
- Exit 1: warnings, for example local mode, no/recently failed worker or failed messages.
- Exit 2: failed minimum runtime/security configuration or database/schema check.
- Database connection/network timeouts are controlled by the runtime/driver; set an external monitoring timeout.
- A worker heartbeat becomes stale after five minutes. This is the last shared database heartbeat, not a per-machine inventory. Multi-host delivery still relies on queue claims; run external host/process monitoring as well.
- The worker's normal exit does not guarantee all messages delivered: retryable individual failures are recorded in its `degraded` heartbeat and queue. Alert on those fields and queue age/failed counts, not only process exit 0.

There is no unauthenticated web health endpoint, automated backup scheduler, off-server transfer, disk-space alert service, certificate monitor, credential rotation or incident-response automation. Configure these through your actual hosting infrastructure and independently verify them.
