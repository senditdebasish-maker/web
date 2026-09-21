# Admissions automation: alerts, uploads, eligibility and online payments

This release adds four opt-in automation modules on top of public admissions. **All stay disabled until explicitly configured.** Upgrade first, then enable each institute/course separately and test with fictional data.

- **Application-status email alerts:** queued for every applicant-visible update; portal remains authoritative.
- **Certificate uploads:** quarantined PDF/JPEG/PNG with malware scanning and staff verification.
- **Automatic eligibility approval:** owner-authorized per-course rules; Verified + Clean evidence may auto-admit, all else stays manual.
- **Online fee collection (Razorpay):** test/live per-institute checkout for enrolled students; only exact captured payments credit the ledger.

## Upgrade

1. Back up database, `config.php`, `storage/` and code. Pause use and the worker.
2. Follow `UPGRADE.html` (replace program files, preserve config/database/storage/locks). **Do not reinstall.**
3. As group owner run **`upgrade.php`** (or `php bin/migrate.php`). Verify **Automation: ready**.
4. Configure below. Test end-to-end on staging before inviting real applicants.

## Application-status emails

Every `application_events` row queues one `application_mail` row (submission, upload, scan, review, auto-decision, withdrawal, etc.). The body is intentionally generic (reference + status + “sign in via your trusted address”); office messages are read in the portal, not via email. This limits phishing and privacy exposure.

- Worker: `php bin/send-notifications.php --limit=25` every minute handles both student PDFs and applicant alerts. “Sent” = SMTP accepted, not inbox guaranteed. “Spooled” = local `.eml` capture only.
- **Blocked** means applicant access/email changed after queueing. Verify the current mailbox; messages are never redirected. Requeue only after verification.
- Staff must still check **Online applications** regularly. There are no staff new-application push alerts.
- Applicant detail now says updates are queued for email; the portal is authoritative if mail is delayed.

## Certificate uploads

### Configuration

In private `config.php`:

```php
'certificates' => [
  'enabled' => true,
  'directory' => 'C:\\crm-certificates', // or /var/private/crm-certificates
  'scanner' => 'manual', // or 'clamav'
  'clamav_host' => '127.0.0.1',
  'clamav_port' => 3310,
],
```

- Directory must exist, be writable, and live **outside the app and all web roots** (`htdocs`, `www`, `public_html`, etc.). Owner-only permissions (700/600). The app refuses inside-project paths.
- XAMPP: create `C:\crm-certificates` (not in `htdocs`). Linux: `/var/private/crm-certificates`, `chown www-data`, `chmod 700`.
- `manual` = staff downloads on an isolated machine, scans with updated AV, then confirms Clean/Infected. `clamav` = local `clamd` INSTREAM scan; failure fails closed.
- Requires PHP `fileinfo`. Health page reports status without leaking paths.

### Rules

- Applicant uploads from own application page only while status is Submitted/Under review/Changes requested. Max 5 per application, 8 bytes–5 MB each.
- Only matching PDF/JPEG/PNG (finfo + extension + PDF header + image dimensions). Stored as random hex with no executable extension, `0600`, hash-verified on every access.
- Uploads start `Pending/Pending` (quarantine). Staff must **Scan → Verify**. Infected blocks review. Verification with mismatched/stale evidence stays manual.
- Downloads force `attachment` with `nosniff`; no inline rendering. Applicants see only own; staff only own institute (owner/admin). Cross-institute returns 403.
- No deletion; rejected files stay for audit. Do not request government IDs/medical records unless legally required and explicitly explained.

## Automatic eligibility approval

Owner-only per course: **Online applications → Eligibility automation → Review policy**.

- Fields: enabled flag, required qualification code (exact uppercase match, e.g. `12TH-SCI`), minimum percentage, min/max age, age cutoff date, authorization note + confirm checkbox. Versioned; changes invalidate prior verifications.
- Flow: applicant uploads → staff scans Clean → staff verifies (qualification code + percentage + birth date + authenticity + capacity/consent confirmation) → system checks policy (enabled, owner still active, course active, account active, no duplicate student/portal email, evidence versions fresh, qualification/percentage/age match).
- **Approved** creates enquiry + student at quoted fee + admission PDF/queue + portal account atomically (same as manual). **Manual review** leaves status unchanged for staff decision.
- History: **Eligibility automation history** on each application shows policy/app versions, result and reason. Policy list shows enabled/disabled + version.
- This is not blanket auto-admission, seat reservation, document-authenticity AI, guardian-consent verification or capacity planning. Staff verification remains the gate.

## Online payments (Razorpay)

### Configuration (per institute)

```php
'razorpay' => ['accounts' => [
  1 => ['enabled'=>true,'mode'=>'test','key_id'=>'rzp_test_xxx',
        'key_secret'=>getenv('CRM_RAZORPAY_SECRET_1')?:'',
        'webhook_secret'=>getenv('CRM_RAZORPAY_WEBHOOK_1')?:'',
        'ledger_actor_id'=>1],
]],
```

- Start with `mode=test`. Use `live` only on production HTTPS with `secure_cookies=true`.
- `key_id` must match mode (`rzp_test_…` vs `rzp_live_…`). Secrets in env vars or private config; never commit.
- `ledger_actor_id` = active owner/admin responsible for postings (admin must belong to same institute).
- Webhook in Razorpay dashboard per institute: `https://your-host/institute-crm/razorpay-webhook.php?institute=<id>`. Copy the webhook secret. Enable auto-capture. localhost cannot receive webhooks; use staging with public URL.
- Requires PHP `curl`. No card data touches the CRM; checkout runs on Razorpay.

### Student flow

Enrolled student → **My payments → Pay online** (shows test banner in test mode):

1. Enter amount up to outstanding balance → server creates local receipt + Razorpay order.
2. Pay in secure Razorpay window → click **Check payment status** (server re-fetches from Razorpay; browser is never trusted).
3. **Credited** (live) creates payment + receipt PDF + email queue. **Test** never credits. **Pending** = no captured payment yet. **Uncertain/Review** = ask office; do not create another order.

One pending order at a time. Manual cash entry is blocked while a hold exists.

### Staff reconciliation

**Payments → Online payment orders** shows receipt, provider order, mode, state, captures and notes.

- `Pending/Uncertain/Review` need attention. Verify amounts in Razorpay dashboard, then **Reconcile** (supply Razorpay order ID for Uncertain). `allow_review` permits crediting a Review capture after manual verification (e.g. fee corrected).
- `Paid/TestPaid` are terminal. A second capture for a Paid order stays `Review` without overwriting (refund duplicate via dashboard).
- `Closed` = office abandoned; reopen only to reconcile.
- Only exact INR, unrefunded, `captured=true` payments credit. Refunds via Razorpay dashboard do **not** auto-adjust the ledger; handle accounting manually (never delete payments).
- Webhooks verify HMAC-SHA256 with the institute secret, deduplicate by `x-razorpay-event-id`, ignore non-capture events, and re-fetch payments before crediting. Invalid signatures return 400; transient gateway/DB failures return 500 for Razorpay retry.

## Live deployment verification (MySQL/Gmail)

Sandbox evidence is PHP-WASM + SQLite + private `.eml` capture. Before production:

- **MySQL:** run `php tests/mysql-smoke.php` against an empty private TEST database (`CRM_MYSQL_TEST_ALLOW=1`, never production). Verifies repeatable migration, hostel concurrency guard, receipt snapshots, public listing, approval, mail queue, policy save, online hold and webhook HMAC. Then load-test concurrency, backups and restore on your host.
- **Gmail:** configure App Password (not account password), valid sender, STARTTLS 587. Run `php bin/test-mail.php --to=controlled-inbox`, check inbox/spam, bounces and quotas. Verify OTP, admission PDFs, receipts and applicant alerts separately.
- **HTTPS/hosting:** keep the bundled `.htaccess` protections, enable secure cookies, patch PHP/extensions, configure trusted proxy handling, perimeter rate limits/bot protection, monitoring, encrypted off-server backups and isolated restore drills.
- **Browser:** test applicant, student and staff journeys in real browsers (uploads, checkout popup, PDFs, printing). `localhost` works only on that machine.

## Tests

```bash
python3 tests/automation.py   # 76 checks: mail, uploads, policy, auto-decision, payments, webhooks
python3 tests/smoke.py tests/communications.py tests/setup.py tests/portal.py tests/operations.py tests/services.py tests/applications.py
```

Native gate (MySQL only, empty TEST DB): `php tests/mysql-smoke.php`.

## Limits

- No applicant fee collection; payments only after admission via student portal.
- No upload for enrolled students; no government-ID OCR; no automatic seat guarantee.
- No refund ledger, payment links, EMI, or gateway-fee accounting.
- No CAPTCHA/DDoS/identity proofing; institute must arrange perimeter controls and legal privacy/guardian procedures.
