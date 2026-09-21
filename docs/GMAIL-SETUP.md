# Gmail SMTP setup for OTP and student PDF emails

For a **new installation**, open `setup.php` and use the [browser wizard](BROWSER-SETUP.md) instead of editing PHP. It configures PHPMailer and verifies the owner inbox before creating an account. The manual instructions below remain useful for existing installations.

## What you need

- A Gmail or Google Workspace sender account controlled by your institute.
- Two-step verification enabled on that Google account.
- A **Google App Password**, if available for that account. Use it instead of your normal Google password.
- Outbound access from your PHP server to `smtp.gmail.com:587` (STARTTLS), or port 465 for implicit TLS.
- PHP OpenSSL, up-to-date CA certificates, and the Composer dependencies.

App Passwords may be unavailable because of account policy, Advanced Protection, or organization restrictions. In that case ask your Workspace administrator about a supported SMTP relay/account, or plan an OAuth2 integration. This implementation supports SMTP username/App Password authentication; it does not provision Google OAuth credentials.

Create the App Password in your Google Account's security settings. Give it a label such as “Institute CRM”. **Do not send the password in chat, put it into student forms, commit it to Git, or use your normal Google account password.**

## Private configuration

Copy the `mail` section into the ignored `config.php` on your server:

```php
'auth_mode' => 'otp',
'environment' => 'production',
'mail' => [
    'transport' => 'smtp',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-institute@gmail.com',
    'password' => 'YOUR_PRIVATE_GOOGLE_APP_PASSWORD',
    'from_email' => 'your-institute@gmail.com',
    'from_name' => 'Your Institute Name',
],
```

The values above are placeholders, not working credentials. Use the authenticated Gmail address as sender unless Google has authorized a specific send-as alias. All institutes share this one server-configured sender in this version; the subject/body/PDF identifies the relevant institute. Per-institute Gmail accounts are not implemented.

Alternatively keep credentials in server environment variables:

```php
'username' => getenv('CRM_SMTP_USERNAME') ?: '',
'password' => getenv('CRM_SMTP_PASSWORD') ?: '',
'from_email' => getenv('CRM_SMTP_USERNAME') ?: '',
```

On Windows, setting an environment variable in one PowerShell session affects only child processes of that session. It **does not automatically configure already-running XAMPP Apache or Windows Task Scheduler**. Configure credentials for both the web runtime and the worker's service account, or use the private config file readable by both. Restart Apache after changes. Never place real passwords in scheduled-task command-line arguments.

For port 465, use `'port' => 465, 'encryption' => 'smtps'`. Do not disable certificate verification to fix TLS problems; update trusted CA certificates/PHP configuration instead.

## Test before enabling OTP for staff

```powershell
cd C:\xampp\htdocs\institute-crm
composer install --no-dev --prefer-dist
C:\xampp\php\php.exe bin\migrate.php
C:\xampp\php\php.exe bin\test-mail.php --to="your-test-inbox@gmail.com"
```

The command distinguishes **SMTP accepted** from **captured locally**. Check the recipient inbox/spam folder. “Accepted” is not proof of final delivery.

Next request an OTP for your active owner account. Test expired and wrong codes, resend cooldown, and staff disabling. Do not turn on a live mail configuration until you have removed/isolated fictional demo recipients.

## Send admission letters and payment receipts

1. Record a test enquiry with an inbox you control as its student email.
2. Confirm admission; inspect the generated PDF in **Documents**.
3. Run `php bin/send-notifications.php` and inspect **Email notifications**.
4. Verify the actual student email and PDF attachment in the inbox.
5. Record a test payment; repeat the worker and attachment check.
6. Schedule the worker every minute and monitor it.

Linux cron example (adjust paths and runtime):

```cron
* * * * * cd /var/www/institute-crm && /usr/bin/php bin/send-notifications.php --limit=25 >> /var/log/institute-crm-mail.log 2>&1
```

Keep logs private; rotate them. The worker does not log message bodies, student addresses, OTPs, or SMTP credentials. Set a task execution limit above the batch's expected duration. Batch size 25 can take several minutes when SMTP is slow; start smaller if necessary. Worker claims prevent normal concurrent double-processing, but a crash in the SMTP acknowledgement window can still repeat delivery.

Gmail has sending limits and anti-abuse controls. This project does not detect provider quotas or bounce events automatically. Start with a small batch and monitor failed messages; for high-volume transactional mail, use a suitable transactional SMTP provider and validate domain authentication/deliverability. Do not use this queue for unsolicited bulk messages.

## Local test capture, not real delivery

Set `environment=local` and `mail.transport=log` with a valid example sender. OTPs and messages are saved as private `.eml` files in `storage/mail/`. Open these **on the server filesystem**, never through a public URL. No credentials are needed and no email is sent.

The log transport refuses to run in `production`. Remove captured files after testing. OTP messages contain authentication secrets, and PDF attachments may contain personal data. The CRM deliberately does not expose a public test inbox. Do not rely on the preview to send Gmail messages until you configure a real sender on your own server.
