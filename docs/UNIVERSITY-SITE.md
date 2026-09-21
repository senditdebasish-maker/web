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

`?page=login` accepts any email and detects the account type: staff addresses go to
the office dashboard (`public/index.php`), student addresses (portal account,
registered student user or student record) go to the student dashboard
(`public/student.php`), with the email prefilled. Unknown addresses get registration
and application links instead of an error dead-end.

## Campus name and address

The site brand, footer and contact details use the institutes table. Owners edit them
in **Institutes** (name, type, city, phone, campus address). The first institute is
the site brand; every institute appears under About and Contact. The `address` column
is added automatically by `public/upgrade.php` / `bin/migrate.php`; fresh installs
include it.

## Homepage design and starter showcase content

The homepage follows a classic university layout: navy crest header, scrolling
announcement ticker (built live from CRM admission dates and closing dates), a
campus hero banner (`public/assets/campus-hero.jpg` — replace this file with a
photo of your own campus to rebrand instantly), accreditation badges, a stats
band, featured programs, notices, and a sidebar with the Vice-Chancellor's
message, program links, recruiters and contact card.

Almost everything is live CRM data. The only starter template pieces are the
accreditation badges, the recruiter tiles and the welcome message — edit
`siteAccreditations()`, `siteRecruiters()` and `siteVcMessage()` in
`app/site-chrome.php` to put your institute's real approvals, recruiters and
message. A future Website Settings page in the office workspace will make these
editable without touching code.

## One theme, no dead ends

The root site and every portal page share one theme (`app/site-chrome.php` +
`public/assets/university.css`): the same header, navigation, footer, buttons and
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
- Installations served with document root `public/` only should keep using the
  portal homepage; the university page needs the project folder itself served.
