# Student services: exams, announcements and support

This release extends the operations CRM. It does **not** turn it into an accredited examination system, full accounting package, healthcare records system or production-certified ERP.

## Upgrade once, without reinstalling

Pause use and the email worker. Back up the database, configuration, private storage and working application. Replace program files from the updated dependency-included ZIP while preserving `config.php`, `storage/`, the database and installer locks. Restart Apache, sign in as group owner, and open **`upgrade.php`**. Confirm your backup and run the upgrade. The CLI alternative is `php bin/migrate.php`.

The additive migration creates exam/result/revision, announcement/revision, support conversation and worker-heartbeat tables and indexes. Existing accounts, fees, payments and student records are retained. It can be rerun after fixing permissions; MySQL DDL is not fully transactional. A clean browser installation includes these tables automatically.

## Exams and published results

Owners and institute admins use **Exams & results**. Counsellors and students cannot enter, publish or change marks.

1. Create a **completed assessment** for a batch: title/subject, exam date, maximum and pass marks. Dates must be within the batch period and no later than today. Marks support two decimal places, from 0 to 1000; maximum must be positive and pass marks cannot exceed maximum.
2. Creation freezes the students eligible on that date. Each exam supports **1–200 students**, safely below PHP's normal form-field limit with two inputs per student. The same title/batch/date cannot be created twice. The definition cannot be edited afterward; create a correctly named replacement assessment if it was wrong, and leave the incorrect one unpublished.
3. Complete every roster row: **Present + a numeric mark**, or **Absent + blank marks**. Add the reason and save. A save retains the full before/after mark snapshot and remains a **draft**.
4. Publish the completed assessment with a reason. Students see only **their own** results in **My results**. The outcome is Pass / Below pass mark / Absent. No email, SMS or notification is sent automatically.
5. To correct published marks, **withdraw** the exam with a reason, save corrected draft marks, then publish again. Withdrawal immediately hides all that exam's results on subsequent student requests. Students cannot continue to see the previous published version while it is withdrawn.
6. Version checks reject stale grading/publication forms. The latest 20 revision entries expose escaped before/after snapshots in staff detail view; all revisions remain in the database.

Frozen exam rosters also protect allocation history: retroactive transfers/allocations cannot cross an existing exam or attendance roster. Current batch changes do not remove a student's historical results.

**Limits:** each assessment is independent. There is no teacher login, subject catalogue, scheduled future exam, invigilation, weighted term aggregation, GPA, retake policy, certificate, official transcript/PDF or accreditation workflow. Publication by an admin is not a separate two-person approval process. This is not yet suitable as your sole statutory examination record without institutional policy and independent validation.

## Announcements

Use **Announcements** to create a plain-text notice for an institute or one of its batches. Supply a title, text, start/expiry dates, state and reason. Titles allow 150 bytes; plain-text bodies allow 5,000 bytes. Dates use the configured institute-server timezone.

- Only **Published** notices within the inclusive date range appear to students.
- Draft, archived, future and expired notices are hidden.
- Institute-wide notices reach enabled students of that institute. Batch notices use the student's allocation **on the current day**, so transferring changes which current batch notices they see. This is not a frozen mailing list.
- Admins cannot target another institute or its batches. Owner editing also cannot move an existing announcement between institutes.
- Edits use optimistic versions and retain before/after revision snapshots. The detail screen shows the latest 20 revisions.
- Content is escaped plain text; there is no HTML editor, attachment upload, email blast, read receipt, acknowledgement requirement or guaranteed delivery.

## Student support desk

Students now have a narrowly scoped write capability: **Support → Open support ticket / Send reply**. Their admissions, finances, marks and official documents remain read-only. Staff use **Student support** to filter Open / Waiting / Resolved tickets, read conversations and reply.

- The student identity and institute are derived from the authenticated portal session, never form fields or URL IDs.
- Every write requires CSRF and a one-use browser message key. Replaying the same successful key does not insert a second ticket/message.
- Tickets are restricted to the student owner and authorized institute admins/group owner. Counsellors cannot access them. Even a different student in the same institute cannot read or reply to a ticket.
- Each reply has a version check. A student reply returns the conversation to **Open**. Staff can mark a reply **Waiting** or **Resolved** and reopen resolved conversations by replying with Open status, unless the conversation limit has been exhausted.
- Messages are retained without edit/delete controls. Every staff message is visible to the student—**there are no internal/private notes**.
- Each student may have five active tickets and send up to 30 messages per hour. Conversations allow 200 ordinary messages; staff may add one final resolution note after reaching the cap so the student is not trapped with an uncloseable ticket. A full, closed conversation cannot be reopened; start a new ticket.
- Text is limited to 3,000 bytes per message and 150 bytes per subject. Non-Latin characters may consume more than one byte. No attachments or rich text are accepted.
- No automatic email/push notifications, staff assignment, SLA escalations or emergency monitoring are included. Staff must check the inbox; students must reopen their portal to see replies.

This is an **educational office help desk**, not an emergency/clinical service. Do not collect patient records, card numbers, passwords or OTPs. Publish your response hours, consent/privacy notice, retention policy and an alternate contact number before inviting students to use it. Data-retention/erasure workflows still require a verified administrator process; no automated legal-compliance claim is made.

## Operational tooling

- The scheduled email worker uses a per-host lock and records start/completion/outcome in `runtime_jobs`. Queue message claims remain the cross-process delivery guard. A heartbeat is not evidence of inbox delivery or an external uptime monitor.
- **Deployment checks** now shows the latest worker completion and queue observations.
- `php bin/health.php` outputs credential-free JSON. Native PHP exits **0** for healthy automated checks, **1** for warnings, **2** for failure. Informational items about backups/security review do not certify those tasks. The PHP-WASM runner used here does not propagate PHP exit codes reliably, so its tests validate JSON; verify exit handling on native PHP.
- `php bin/backup.php --directory=...` creates an unencrypted private database snapshot plus SHA-256 manifest. Read **[Recovery and backup guide](RECOVERY.md)** before running it. Scheduling, encryption, retention, off-server copying and restore verification remain operator responsibilities.

## Evidence and rollout gate

The services suite covers additive upgrades, frozen result rosters, grade bounds and hundredths, draft/publication/withdrawal, stale versions, transactional revision rollback, notice audience/date/state rules, private support access, nonce replay, limits, staff status changes, worker heartbeat, non-sensitive health output, backup cleanup, and an **actual SQLite snapshot opened as a separate database for integrity/data checks**.

```sh
python3 tests/services.py
```

Run this **alongside all five existing suites**. All Python suites use disposable SQLite fixtures. The optional native MySQL/MariaDB smoke gate now includes assessments, announcement targeting and heartbeat upserts, but it has not executed in this workspace. Native installation was attempted; Debian package hosts were unreachable. Real SMTP, native MySQL concurrency/dump/restore, browser rendering, dependency audit and production load/security review remain unverified here.
