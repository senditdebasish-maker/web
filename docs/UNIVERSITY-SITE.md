# University website (root `index.php`)

A single public front page for the whole institution, served from the project folder
(`http://localhost/institute-crm/index.php` on XAMPP). Every fact on it is read live
from the CRM: edit an institute, course listing or teacher in the office workspace and
the website changes immediately — no file editing.

## Pages (`?page=`)

Home (hero, live stats, featured programs, notices), About (campuses), Programs
(open listings with fee and closing date), Admissions (process, eligibility, dates),
Notices (derived from live admission dates), Faculty (active teachers: name,
qualification and institute only — personal email/phone are never shown), Contact
(campus addresses and phones) and Sign in.

## One sign-in for everyone

`?page=login` signs visitors in with an emailed OTP code and lands them straight in
the right dashboard: staff addresses open the office (`office.php`), student
addresses (portal account, registered student user or student record) open the
student portal (`student.php`). Staff on password-mode institutes get an inline
password step on the same page instead of a code. Unknown addresses choose between
creating a student account, sending an admission inquiry and tracking an
application — never an error dead-end. The same page hosts four popups (plain
links work without JavaScript):

- **Create Student Account** — name, home address, mobile and Gmail, verified by a
  Gmail OTP code before the account is created.
- **Admission Inquiry** — name, mobile, Gmail, address, program and message; saved
  into office **Enquiries** with the `website` source and an auto-assigned counsellor.
- **Forgot password?** — student-only Gmail OTP recovery that restores the portal
  session (student accounts are OTP-based, so verifying the Gmail is the recovery).
- **Track application** — applicant email verification that returns to the chosen
  course or the application workspace (`apply.php`).

All four reuse the CRM's OTP, mail, rate-limit and transaction systems; the
`address` column on `student_users` is added automatically by `upgrade.php` /
`bin/migrate.php`, and fresh installs include it.

## Campus name and address

The site brand, footer and contact details use the institutes table. Owners edit them
in **Institutes** (name, type, city, phone, campus address). The first institute is
the site brand; every institute appears under About and Contact. The `address` column
is added automatically by `upgrade.php` / `bin/migrate.php`; fresh installs
include it.

## Homepage design and starter showcase content

The homepage follows a classic university layout: navy crest header, scrolling
announcement ticker (built live from CRM admission dates and closing dates), a
campus hero banner (`assets/campus-hero.jpg` — replace this file with a
photo of your own campus to rebrand instantly), accreditation badges, a stats
band, a Vice-Chancellor feature, featured programs, notices, and a sidebar with
mini badges, the Vice-Chancellor's card, a program showcase grid, recruiters and
a contact card. The header carries a working **EN | हिन्दी** toggle: the site
chrome (navigation, buttons, ticker, headings, footer) is genuinely bilingual via
a sessionless `?lang=` + cookie switch, while CRM records stay in their
entered language.

Almost everything is live CRM data. The only starter template pieces are the
hero headline, the accreditation badges, the showcase program names, the
recruiter tiles and the welcome message — edit `siteAccreditations()`,
`siteShowcasePrograms()`, `siteRecruiters()` and `siteVcMessage()` in
`app/site-chrome.php` to put your institute's real approvals, programs,
recruiters and message. A future Website Settings page in the office workspace
will make these editable without touching code.

## One theme, no dead ends

The root site and every portal page share one theme (`app/site-chrome.php` +
`assets/university.css`): the same header, navigation, footer, buttons and
dark-mode toggle. On every page the brand links back to the university front page,
and every portal (admissions, student, office homepage and office workspace) shows
an explicit **← Back to website** link, so the Admissions button never strands the
visitor: click the brand or the back button to return. The one-time installer and
upgrade pages keep their own minimal styling.

## Safety notes

- The page is sessionless and read-only; it never exposes enquiries, payments,
  documents, applicant data or office announcements.
- All CRM text is HTML-escaped; unknown pages are 404.
- On Apache the root `.htaccess` blocks `app/`, `bin/`, `storage/`, `vendor/`,
  `config.php` and databases. Hosts that ignore `.htaccess` (Nginx) must deny those
  paths in server configuration when serving the project folder directly.
- The project folder itself is the web root: `index.php` (university site),
  `office.php` (staff), `student.php`, `apply.php` and `assets/` sit side by side,
  exactly as XAMPP serves them from `htdocs/institute-crm/`.
