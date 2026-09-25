# Public admissions and applicant workspace

## Entry points

<<<<<<< HEAD
<<<<<<< HEAD
- **New applicants:** `apply.php` — course catalogue, course detail/contact, application form, private tracking, correction/resubmission, withdrawal and application help. Email verification happens on the master sign-in (`index.php?page=login`), which returns here afterwards.
- **Student-portal applicants:** `student.php?page=admissions` — the same journey inside the student portal for verified student accounts: secure course catalogue, course details, application form, private tracking, certificate uploads, withdrawal and application help.
=======
- **New applicants:** `apply.php` — course catalogue, course detail/contact, email verification, application form, private tracking, correction/resubmission, withdrawal and application help.
>>>>>>> parent of 549483e (new)
=======
- **New applicants:** `apply.php` — course catalogue, course detail/contact, email verification, application form, private tracking, correction/resubmission, withdrawal and application help.
>>>>>>> parent of 549483e (new)
- **Enrolled students:** `student.php` — admission/profile, fees/installments, PDFs, attendance, published results, notices and support.
- **Staff:** `office.php?page=applications` — public listing controls and institute-scoped review queue. Each application opens an organized review page showing every entered form field with four decision buttons (Approve, Reject, Revert back, Cancel). Approved applications collect under the **Accepted applications** subtab.

On XAMPP, open **http://localhost/institute-crm/apply.php**. A `localhost` link is usable only on that computer. Share your deployed **HTTPS** address for applicants on other devices. All in-app links are relative; there are no hardcoded localhost API calls.

## Upgrade and open admissions

1. Pause use and the notification worker; back up the existing database, private configuration/storage and working code.
2. Follow `UPGRADE.html` to replace program files from the new dependency-included ZIP. Preserve `config.php`, the database, `storage/` and installer locks. **Do not reinstall.**
3. Restart Apache, sign in as group owner, and run **`upgrade.php`**. CLI operators may use `php bin/migrate.php` instead. MySQL DDL can partially commit; fix permissions and rerun after a failure rather than deleting records.
4. Open **Online applications → Manage public course listings → Configure public listing** for an active course.
5. Enter a public description, eligibility/original-document requirements, and your actual institute privacy notice. The notice should explain purpose, office contact, retention, correction and any guardian-consent requirements. The CRM does not create a legally approved privacy policy for you or automate legal retention/erasure.
6. Set opening/closing dates and **Open within date window**. Dates are inclusive in the configured server timezone. **All courses are closed to public applications by default**, including after migration. Archived courses never appear publicly.
7. Preview the public catalogue, then test the entire journey with a fictional applicant and a controlled inbox before inviting real applicants.

Opening a listing publishes its current course name, fee, duration and the institute's name/type/city/phone, plus the listing text. Do not place private/internal information there. Closing a listing hides it and prevents new submissions; existing applicants can still track their records and respond to requested corrections.

## Applicant journey

1. Browse an open course and read its eligibility, fee, dates, contact details and privacy notice.
2. Choose Apply and verify a **personal email** with a six-digit code. Verification returns the applicant to the selected course if it is still open. Returning applicants use the same sign-in page.
3. Enter legal name, contact phone, city, highest completed qualification, completion year and an optional note. Accept the application declaration and institute privacy notice.
4. Submit and receive a private **APP-… reference**. A submission is not a seat guarantee, admission confirmation or payment receipt. The reference is not an access password: viewing requires the verified applicant session.
5. Visit **My applications** to track **Submitted → Under review / Changes requested → Admitted / Rejected**. Review messages are visible here; status updates are also queued for email (see `AUTOMATION.md`), but this page is authoritative. Staff must still check the queue; there are no staff push alerts.
6. When corrections are requested, edit contact/qualification details and resubmit. Email, course, institute and quoted fee cannot be changed in this workflow. Every revision remains in the audit history.
7. Applicants may withdraw a nonterminal application with a reason. Withdrawn/rejected applications cannot be edited or reopened. A new application is permitted if the account is not already active/admitted and the daily limit permits it.
8. After approval, use **Open my student portal**, sign in with a separate student OTP, and access the enrolled-student features. The applicant login alone never grants staff or enrolled-student access.

The application detail page serves as an acknowledgement and is printable through the browser; print styling hides navigation/forms. This is **not** a newly generated official PDF. The official admission-letter PDF becomes available in the student portal after approval.

### Submission rules

- One active or admitted application per applicant account across all institutes. The supported model is a unique personal email, not shared family email or multiple portal enrolments under one email.
- At most five new submissions per account in 24 hours. A one-use request key prevents duplicate submissions. Replaying a used key returns the original application with an explicit notice, not a second application.
- Server-derived course/institute/fee values override forged form values. The displayed course offer is fingerprinted: if its fee, listing terms or other public details change between form load and submission, the applicant must reload/review rather than unknowingly accepting changed terms.
- The application snapshots course/institute labels, duration, quoted fee, submitted details and the accepted privacy notice/version. Later catalogue price changes do not change that application's fee quote.
- Existing student emails are directed to the student portal or office, not automatically converted into duplicate student records.
- Text limits are byte limits (multibyte names use more than one byte). Qualification completion years run from 1950 through the current year. Phone validation is basic formatting, **not verification of phone ownership**.
- Do not put government ID numbers, medical records, card details, passwords or OTPs in notes. Certificate uploads (when enabled) happen after submission from your application page; see `AUTOMATION.md`. No payment is collected here; enrolled students pay separately after admission. The office must verify originals and any legally required guardian consent.

## Staff review and approval

Owners/admins review applications within their permitted institute scope. Counsellors cannot manage listings or make admission decisions. Public and applicant sessions cannot invoke staff actions.

- Queue filters show each status, reference/email, course/institute and last update. The application detail contains applicant-provided data, the accepted privacy notice and the latest 100 events; full event history remains in the database.
- **All review messages are applicant-visible.** This is not an internal-notes field. Explain requested corrections or rejection respectfully without exposing private staff information.
- Draft/correction edits, withdrawals and decisions use version checks so an old browser form cannot overwrite newer changes.
- Admission is blocked while corrections are outstanding, when the course is inactive, for suspended applicant accounts, or when an existing student/reserved portal email needs manual resolution.
- Approval requires explicit confirmation of eligibility, original documents, required consent, the **quoted fee**, and granting student access. The admission date is today; backdating is not available here.
- Approval atomically creates an admitted enquiry, student at the quoted fee, immutable admission-document snapshot, email-queue record, enabled student portal account, application transition and audit events. If any step fails, the transaction rolls back. Retrying an old approval cannot create another student.
- The admission letter is queued using the existing PHPMailer/PDF worker. An enabled portal and queued email do not prove SMTP inbox delivery. **No payment is fabricated or collected.**
- Approval uses the application quote even if the course price later changes. There is no fee-negotiation/offer-revision screen; contact the applicant and use a properly reviewed new application if agreed terms need to change. Do not silently edit database fee records.

## Applicant access and email recovery

Applicant sessions use `northstar_applicant`, separate from staff and student cookies. They have a 30-minute idle timeout and are bound to account ID, verified email, access version and installation/configuration identity.

- The group owner may **Suspend applicant sign-in** or restore it from an application detail. This is global across the applicant's historical institute applications, so institute admins cannot toggle it directly.
- Suspension invalidates applicant sessions and pending codes. Restoring access does not revive old sessions/codes.
- Applicant suspension does not withdraw records or disable an already enrolled student's separate portal. Conversely, disabling only the student portal does not suspend the applicant account.
- **Changing a linked admitted student's email automatically suspends the old applicant account and invalidates its codes.** Staff should verify the new student email and re-enable the student portal separately. Original application email/history is not silently rewritten or merged. There is no self-service applicant email change or automatic account migration; the owner must follow a verified recovery process. Do not restore access to an old mailbox that the student no longer controls.

## Security and deployment gate

Applicant OTPs expire after five minutes, are hashed, bound to the requesting browser, single-use and limited to five guesses. Resends invalidate previous codes. The endpoint shares request/verification IP limits with existing email authentication: 60-second per-email resend cooldown, short IP spacing, five requests per email and twenty per IP per 15 minutes, and thirty verification attempts per IP per 15 minutes. Public forms require CSRF; registration/submission honeypots reject obvious bots. New applicant accounts are created only after successful verification. When no courses are open, only existing enabled applicants receive login codes.

These controls are **not** CAPTCHA, distributed bot protection, DDoS protection, identity/qualification verification or phishing-resistant MFA. Before exposing the public endpoint:

- Use HTTPS and secure cookies, keep the bundled `.htaccess` protections, patch dependencies and validate trusted proxy/client-IP handling on the real host. Never expose private application/storage/configuration directories.
- Configure provider quotas, perimeter rate limiting/bot protection, private monitoring/logging and incident ownership. Do not trust arbitrary `X-Forwarded-For` headers. Shared campus IPs may hit limits; review capacity through a tested security policy rather than removing throttling.
- Test real Gmail/inbox delivery, native MySQL concurrency, two institutes and multiple applicant/student sessions on staging.
- Schedule and monitor the admission/PDF notification worker. Staff must separately check the application queue regularly.
- Include applicant accounts, applications, accepted notices and application events in encrypted backup/restore and retention procedures. Document privacy/consent/guardian handling; this checkbox does not establish legal compliance.

## Automation modules

Status emails, certificate uploads, owner-authorized automatic eligibility checks and Razorpay fee collection are documented in **[AUTOMATION.md](AUTOMATION.md)**. All are disabled by default and require explicit configuration. Approval (manual or automatic) never collects money at decision time.

## Tests and known limits

`python3 tests/applications.py` runs isolated HTTP checks for migrations, default-private listings, offer changes, OTP expiry/replay/attempt limits, CSRF, audience separation, private tracking, review/correction/withdrawal, institute roles, fee tampering, atomic approval rollback, actual admission PDF access, account suspension and linked-email recovery. Run all existing suites too.

Workspace evidence is PHP WebAssembly + SQLite + private email capture. Native MySQL/MariaDB, actual Gmail delivery and live-browser rendering still need server validation (see `AUTOMATION.md` deployment checklist). The native gate now covers listings, approval, mail queue, policy save, online hold and webhook HMAC but has not run here. Self-service email recovery, multiple active enrolments, waitlists/seat reservation and legal guardian-consent verification are not implemented.
