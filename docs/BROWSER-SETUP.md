# Easy browser installation

## Recommended: ready-to-install ZIP

The `northstar-setup.zip` package contains the CRM, PHPMailer, PDF libraries and their licenses. No Composer or PHP-file editing is needed on the destination computer.

1. Extract the **institute-crm** folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in XAMPP. PHP 8.2+ is required.
3. Open **http://localhost/institute-crm/public/setup.php**.
4. The page creates a private `storage/setup-key.txt`. Open it in Notepad (or the hosting file manager) and copy its key into the setup form. This proves you own the server; a public visitor cannot claim your owner account first.
5. Complete the guided steps below.

If you open the unconfigured CRM's `public/index.php`, it redirects to the setup page automatically. Do not double-click PHP files in File Explorer; Apache must serve them.

## Wizard steps

### 1. Secure access

The setup key is generated randomly and saved on the server with restrictive permissions. It is **never** displayed on the website or accepted in a URL. Keep it private. All forms require a session CSRF token. Setup uses a separate session from normal CRM login.

Remote setup requires HTTPS. Only actual loopback requests may use HTTP for local XAMPP. A trusted HTTPS-terminating proxy can be explicitly configured with the server environment variable `CRM_TRUST_HTTPS_PROXY=1`; only use this when that proxy overwrites incoming forwarded-protocol headers. Arbitrary forwarded headers are not trusted by default.

### 2. Server checks

Checks PHP version, PDO/session/OpenSSL/DOM/mbstring and other extensions, database drivers, PHPMailer/Dompdf availability, private storage and configuration-directory writability.

Choose **Local XAMPP / private demo** or **Live server**, plus a timezone. Live mode requires HTTPS, an authenticated SMTP account and a password-protected database account. Set the server's own timezone/clock accurately as well, since OTPs expire.

If a check fails, the installer stops before account creation. Enable missing PHP extensions in XAMPP's php.ini and restart Apache. The browser does not execute Composer, shell commands, or download arbitrary code.

### 3. Database

Standard local XAMPP defaults:

| Field | Value |
| --- | --- |
| Engine | MySQL / MariaDB |
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `institute_crm` |
| User | `root` |
| Password | Blank on a default local XAMPP install |

Select **Create this database if it does not exist** if the account has CREATE permission. Otherwise create a database in your hosting panel and enter its exact name and credentials. A shared-hosting database name may include your account prefix.

The installer tests the connection and rejects **any non-empty database**, not just a database with existing CRM users. It does not erase or migrate existing data. Live servers must use a dedicated, password-protected database account rather than local XAMPP's defaults.

Local mode also offers SQLite as a private demo option. MySQL form fields are ignored for this option; it uses `storage/crm.sqlite`. SQLite is not offered in live mode.

### 4. Owner and first institute

Enter the owner's full name and a working email address. There is no password prompt: this account will use email OTP. Optionally add your first institute with specialization, city and phone number. You can add more institutes after login.

### 5. PHPMailer / Gmail

Enter:

- Sender display name and email.
- SMTP host (Gmail default: `smtp.gmail.com`).
- SMTP username (usually the full Gmail address).
- **Google App Password**, not your normal account password.
- STARTTLS + port 587, or SMTPS + port 465.

Enable Google two-step verification and generate the App Password in your Google account's security settings. Organization policy may disable App Passwords; ask your Workspace administrator for an approved SMTP account instead. OAuth2 setup is not implemented by this wizard.

Click **Send verification code with PHPMailer**. A six-digit code goes to the owner email. Enter it in the wizard within five minutes; five attempts are allowed. Wait 60 seconds between sends. Verification is invalidated if you change owner details, send a new test, or change deployment mode.

The code is sent through the **same PHPMailer transport used for CRM login OTPs**. Checking the code proves inbox access, not merely that SMTP accepted a message. Installation cannot finish until verification succeeds.

Passwords are never repopulated in HTML or displayed in the review. SMTP secrets are held in the private PHP setup session until completion/cancellation/expiry; a blank SMTP password retains it only for the same host and username. Retesting a MySQL connection requires re-entering its password.

For a local demo, choose **Private local capture**. Messages become private `.eml` files in `storage/mail/`. Open the newest file on the filesystem and use its code. The wizard clearly marks that **no real email was sent**. Local capture is prohibited in live mode.

### 6. Review and install

Review the mode, timezone, database, owner, institute, sender and transport. Secrets are hidden. Confirm this is a new installation and click **Install my institute CRM**.

The installer:

- Serializes install requests with a filesystem lock.
- Rechecks database emptiness and requirements.
- Initializes the same schema as the CLI installer.
- Creates exactly one owner and optionally the first institute in a transaction.
- Writes the private configuration without overwriting an existing file.
- Records an installation audit entry.
- Locks setup and removes the temporary setup key.
- Clears the setup session's credentials/draft.

Then open the CRM login and request a **new** OTP. The setup code cannot be reused as a login code.

## Schedule student email delivery

Login OTPs send immediately. Student admission/payment emails use the background notification queue.

In Windows Task Scheduler, create a task repeating every minute:

- **Program:** `C:\xampp\php\php.exe`
- **Arguments:** `C:\xampp\htdocs\institute-crm\bin\send-notifications.php --limit=25`
- **Start in:** `C:\xampp\htdocs\institute-crm`

Run it as a user who can read `config.php` and access the database. For hosting, use its Cron Jobs interface with the equivalent full paths. Monitor **Email notifications** in the CRM.

A PHP website cannot safely create OS scheduled tasks on an arbitrary server. This remains a separate one-time step, explained on the completion page.

## Existing installations and recovery

**This is a first-install wizard, not a settings editor.** Any existing `config.php`, `storage/installed.lock`, or `storage/setup-recovery.php` disables it. Do not remove these safeguards to change an SMTP password. Back up the database and use the private configuration/upgrade instructions for an existing site.

Setup sessions expire after 30 minutes of inactivity. **Cancel & clear private session** removes credentials from the active setup session. Rotate the private setup key if it was exposed; existing authorized sessions check the key fingerprint again on every request.

MySQL schema operations are not fully transactional. If schema creation fails, empty tables may remain even though no owner was committed. Diagnose privileges/extensions and use a **new empty database**; the installer never drops tables for you. If the owner was committed but publishing `config.php` fails, `storage/setup-recovery.php` retains the configuration and blocks further installation. A server operator must verify the database/permissions and move that private recovery file to `config.php`. Do not repeat owner creation.

After setup, make the application code/configuration read-only to the web user where practical, while keeping required runtime storage writable. Serve only `public/`; private sessions, setup keys, config files, databases, captured mail and recovery files must not be web-accessible. Avoid setting directory permissions to 777.

## Source checkout / building a new package

A GitHub source checkout intentionally excludes `vendor/`. Install dependencies once:

```sh
composer install --no-dev --prefer-dist
```

Then open `public/setup.php`, or build a ready-to-install ZIP:

```sh
php bin/build-release.php
```

This CLI-only command requires the PHP zip extension and writes `releases/northstar-setup.zip`. It includes an explicit application-file allowlist plus installed libraries and licenses. It excludes Git metadata, private configuration, database files, captured mail, sessions, setup keys, locks and recovery data. Vendor libraries and arbitrary build outputs are not committed to Git. The single dependency-included `releases/northstar-setup.zip` is intentionally tracked to provide a reliable GitHub download; rebuild and review it explicitly when publishing an updated installer.

The downloadable package built in this environment uses the pinned upstream dependency tags and an equivalent local autoloader, because Composer network access was unavailable. The setup/CRM suites were run with those libraries. For your own production releases, run normal Composer installation and `composer audit`, test on native PHP/MySQL, and verify real SMTP delivery before launch.
