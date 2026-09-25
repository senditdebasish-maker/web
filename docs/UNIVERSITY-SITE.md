# University website (root `index.php`)

A single public front page for the whole institution, served from the project folder
(`http://localhost/institute-crm/index.php` on XAMPP). Every fact on it is read live
from the CRM: edit an institute, course listing or teacher in the office workspace and
the website changes immediately — no file editing.

## Pages (`?page=`)

Home (hero, live stats, featured programs, notices), About (campuses), Programs
(open listings with fee and closing date), Program detail, Admissions (process,
eligibility, dates), Notices (derived from live admission dates, with search and
Open/Closing-soon filters), Faculty (active teachers: name, qualification and
institute only — personal email/phone are never shown), Campus Life (laboratories,
library, hostel, transport, student activities), Contact (campus addresses and
phones) and Sign in.

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

The public site wears the future design system (`assets/future.css` +
`assets/future.js`, scoped under `body.future` so portal styles never
collide): a navy-to-royal utility bar (helpline, city, Student/Staff login
links, dark-mode toggle, working **EN | हिन्दी** toggle), a sticky glass
header with the college brand, icon dropdown nav (About, Academics,
Admissions, Campus Life, Student Corner, Placement, Research, Notices,
Contact), Enquire + Apply Online buttons, a slide-in mobile drawer and a
mobile bottom nav (Home, Programs, Apply, Notices, Account). A scrolling
LATEST UPDATES ticker (built live from CRM admission dates, closing dates
and the helpline — it hides itself when there is nothing to announce) sits
under the header, followed by a full-width campus photo hero ("Shaping
Healthcare Leaders for a Healthier Tomorrow" over
`assets/college-hero.jpg` — replace this file with a photo of your own
campus to rebrand instantly) with a live academic-year admissions badge and
animated CRM counters. Below come trust badges, "why choose us" cards, an
about preview with the Principal's message, photo program cards
(`assets/program-dpharm.jpg`, `program-bpharm.jpg`, `program-mpharm.jpg`,
`program-phd.jpg`, matched from the live course name, with degree short
codes via `collegeDegreeShort()`), a live-stats band, notices with date
badges, a campus gallery and an admission call-to-action band, closed by a
full footer (quick links, academics, admissions, student corner, contact —
no fake social icons). All icons are inline SVG; headings use Inter/Playfair
Display from Google Fonts (allowed in the page CSP; system fonts take over
offline). Inner pages share the same header/ticker/footer/drawer/bottom-nav
chrome; the sign-in page keeps its existing OTP forms and popups styled by
the theme/university stylesheets. The chrome is genuinely bilingual via a
sessionless `?lang=` + cookie switch, while CRM records stay in their
entered language.

Almost everything is live CRM data — counts, programs, dates, campuses,
contacts and notices all come from the database, and obsolete hardcoded
years were removed. The only starter template pieces are the hero headline,
the feature cards, the quote and the welcome text. Dropping
`assets/principal-photo.jpg` into place shows a real portrait in the
principal card automatically. (`collegeHighlights()`, `collegeTrustBadges()`,
`collegeSpotStats()`, `siteAccreditations()`, `siteShowcasePrograms()`,
`siteRecruiters()` and `siteVcMessage()` are kept for backwards
compatibility but no longer render.) A future Website Settings page in the
office workspace will make these editable without touching code. The
**?page=brochure** page and its PDF download are generated from live brand
and program records; **?page=courses** filters by typed text without
changing the default listing; **?page=notices** adds live search plus
Open/Closing-soon filters.

## One theme, no dead ends

The root site wears a college theme (`app/college-chrome.php` +
`assets/future.css` on top of `assets/university.css` for the sign-in
forms and popups); every portal page keeps the shared theme
(`app/site-chrome.php` + `assets/university.css`). Buttons,
form controls and the dark-mode toggle stay consistent everywhere. On every
page the brand links back to the university front page,
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
