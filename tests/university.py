#!/usr/bin/env python3
"""University front-page checks: CRM-driven content, OTP sign-in, signup, recovery, enquiries."""
import http.cookiejar
import os
from pathlib import Path
import re
import shlex
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from master import Master, MASTER_MARK
ROOT = Path(__file__).resolve().parents[1]
PHP = shlex.split(os.environ.get('PHP_BIN', 'php'))
checks = 0


def check(condition, message):
    global checks
    assert condition, message
    checks += 1
    print('PASS', message)


class Browser:
    def __init__(self, base, endpoint='index.php'):
        self.base = base
        self.endpoint = endpoint
        self.client = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(self, path=None, data=None):
        try:
            response = self.client.open(
                self.base + '/' + (path or self.endpoint),
                urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None,
                timeout=30)
        except urllib.error.HTTPError as e:
            response = e
        self.status = response.status
        self.url = response.geturl()
        self.html = response.read().decode(errors='replace')
        return self.html

    def token(self, page='dashboard'):
        self.get(self.endpoint + '?page=' + page)
        return re.search(r'name="csrf" value="([^"]+)"', self.html).group(1)

    def post(self, action, page='dashboard', **fields):
        return self.get(self.endpoint + '?page=' + page,
                         dict(action=action, csrf=self.token(page), **fields))

    def login(self, address, password='Test-Owner-Password!'):
        master = Master(self)
        return master.password_login(master.otp_start(address), address, password)


with tempfile.TemporaryDirectory(prefix='northstar-university-') as temp:
    temp = Path(temp)
    database = temp / 'db.sqlite'
    cfg = temp / 'config.php'
    mail = temp / 'mail'
    cfg.write_text("<?php return ['dsn'=>'sqlite:" + str(database) + "','timezone'=>'Asia/Kolkata','auth_mode'=>'password','environment'=>'local','mail'=>['transport'=>'log','from_email'=>'sender@example.test','log_path'=>'" + str(mail) + "']];")
    env = dict(os.environ, CRM_CONFIG_FILE=str(cfg), CRM_ADMIN_PASSWORD='Test-Owner-Password!')
    result = subprocess.run(
        PHP + ['bin/install.php', '--name=Owner Test', '--email=owner@example.test', '--demo'],
        cwd=ROOT, env=env, capture_output=True, text=True, check=True)
    check('Installed successfully' in result.stdout, 'fresh installation succeeds')
    con = sqlite3.connect(database)

    def scalar(sql, args=()):
        return con.execute(sql, args).fetchone()[0]

    def execute(sql, args=()):
        con.execute(sql, args)
        con.commit()

    check(con.execute("SELECT name FROM pragma_table_info('institutes') WHERE name='address'").fetchone(),
          'fresh install includes institute address column')
    check('Park Street' in scalar('SELECT address FROM institutes WHERE id=1'),
          'demo institutes carry campus addresses')
    check((ROOT / 'assets' / 'campus-hero.jpg').is_file(),
          'homepage hero image is packaged with the site')
    iid = scalar('SELECT id FROM institutes ORDER BY id LIMIT 1')
    cid = scalar('SELECT id FROM courses WHERE institute_id=?', (iid,))
    sid = scalar('SELECT id FROM students ORDER BY id LIMIT 1')
    execute('INSERT INTO admission_listings (id,description,eligibility,privacy_notice,opens_on,closes_on,accepting,version) VALUES (?,?,?,?,?,?,?,1)',
            (cid, 'D.Pharm with practical labs.', '10+2 with Science.', 'Privacy notice.', '2026-01-01', '2030-12-31', 1))
    # Serve the repository root like XAMPP htdocs (router/Apache blocking is deployment-level).
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    log = open(temp / 'server.log', 'w+')
    server = subprocess.Popen(
        PHP + ['-d', 'opcache.enable=0', '-S', f'0.0.0.0:{port}', '-t', str(ROOT)],
        cwd=ROOT, env=env, stdout=log, stderr=log)
    try:
        base = f'http://127.0.0.1:{port}'
        for _ in range(100):
            try:
                urllib.request.urlopen(base + '/index.php', timeout=.5)
                break
            except OSError:
                time.sleep(.1)
        uni = Browser(base)
        owner = Browser(base, 'office.php')
        check('Northstar University' in uni.get() and '45 Park Street' in uni.html,
              'homepage brand and address come from the CRM')
        check('Diploma in Pharmacy' in uni.html and 'Apply Online' in uni.html,
              'homepage lists the open CRM program')
        check('data-theme-toggle' in uni.html and 'theme.js' in uni.html,
              'homepage offers the dark-mode toggle')
        check('u-ticker' in uni.html and 'Admission helpline' in uni.html,
              'homepage ticker announces live admission dates')
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
        check('Principal&#039;s Message' in uni.html and 'id="principal"' in uni.html and 'f-gallery' in uni.html
              and 'PCI Approved' in uni.html,
              'homepage shows the principal message, campus life and badges')
=======
        check('Message from the Principal' in uni.html and 'PCI Approved' in uni.html and 'Campus Life' in uni.html,
              'homepage shows the principal message, badges and campus life')
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
        check('Shaping Healthcare Leaders' in uni.html and 'for a Healthier Tomorrow' in uni.html
              and 'ADMISSIONS 2024-25' not in uni.html and 'SCHOLARSHIPS 2024-25' not in uni.html
              and 'SEMESTER RESULTS DECLARED' not in uni.html,
              'homepage matches the college hero with a live-only ticker and no hardcoded years')
        check('f-prog' in uni.html and 'Latest notices' in uni.html
              and 'Quick Links' in uni.html and 'id="principal"' in uni.html and 'f-foot-bottom' in uni.html,
              'homepage shows programs, updates, principal feature and footer')
        check('f-topbar' in uni.html and 'Student Login' in uni.html and 'Staff Login' in uni.html,
              'homepage wears the college utility bar with student and staff login')
        check('future.css' in uni.html and 'future.js' in uni.html and 'Apply Online' in uni.html
              and 'nimita.css' not in uni.html and 'nimita.js' not in uni.html and 'font-awesome' not in uni.html,
              'homepage wears the future design system without legacy assets')
        check('Latest notices' in uni.html and 'f-gallery' in uni.html
=======
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
        check('Message from the Principal' in uni.html and 'PCI Approved' in uni.html and 'Campus Life' in uni.html,
              'homepage shows the principal message, badges and campus life')
        check('Shaping Healthcare Leaders' in uni.html and 'for a Healthier Tomorrow' in uni.html
=======
        check('Vice-Chancellor' in uni.html and 'Top recruiters' in uni.html and 'NAAC A++' in uni.html,
              'homepage shows the message, badges and recruiters showcase')
        check('TRADITION MEETS INNOVATION: EST. 1887' in uni.html and 'Nurturing global leaders.' in uni.html
>>>>>>> parent of 392c080 (Homepage matches the college reference photo: full-bleed hero, principal message, program cards, campus photos)
              and 'ADMISSIONS 2024-25 OPEN FOR UNDERGRADUATE PROGRAMS' in uni.html and 'SCHOLARSHIPS 2024-25' in uni.html
              and 'SEMESTER RESULTS DECLARED' in uni.html, 'homepage matches the replica hero and ticker texts')
        check('ENGINEERING &amp; TECHNOLOGY' in uni.html and 'MEDICINE &amp; HEALTH SCIENCES' in uni.html
              and 'MANAGEMENT STUDIES' in uni.html and 'ARTS &amp; HUMANITIES' in uni.html
              and 'International Companies' in uni.html and 'DHL' in uni.html
              and 'Quick Links' in uni.html and 'Follow Us' in uni.html and 'u-vc-full' in uni.html,
              'homepage shows the program grid, recruiters, VC feature and footer')
        check('c-topbar' in uni.html and 'Student Login' in uni.html and 'Faculty Login' in uni.html and 'Admin Login' in uni.html,
              'homepage wears the college utility bar with all three logins')
        check('Download Brochure' in uni.html and 'college.css' in uni.html and 'Our Programs' in uni.html,
              'homepage shows the college header, programs and stylesheet')
<<<<<<< HEAD
        check('Latest Updates' in uni.html and 'Campus Life' in uni.html
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
              and 'campus-learning.jpg' in uni.html and 'campus-sports.jpg' in uni.html,
              'homepage shows updates and campus life photos')
=======
        check('Latest Updates' in uni.html and 'Campus Life' in uni.html and 'id="placement"' in uni.html,
              'homepage shows updates, campus life and recruiter strip')
>>>>>>> parent of 392c080 (Homepage matches the college reference photo: full-bleed hero, principal message, program cards, campus photos)
        check('AICTE' in uni.get('index.php?page=brochure') and 'Download PDF' in uni.html,
              'brochure page renders live college data')
        uni.get('index.php?page=brochure&format=pdf')
        check(uni.html.startswith('%PDF'), 'brochure downloads as a real PDF')
        check('Diploma in Pharmacy' in uni.get('index.php?page=courses&q=pharmacy') and 'No programs match' not in uni.html,
              'program search finds matching courses')
        check('No programs match' in uni.get('index.php?page=courses&q=zzz-no-such-course'),
              'program search reports no matches')
        uni_hi = Browser(base)
<<<<<<< HEAD
        check('<html lang="hi">' in uni_hi.get('index.php?lang=hi') and 'छात्र लॉगिन' in uni_hi.html
              and 'प्राचार्य का संदेश' in uni_hi.html and '2024-25' not in uni_hi.html,
=======
        check('<html lang="hi">' in uni_hi.get('index.php?lang=hi') and 'अभी आवेदन करें' in uni_hi.html
              and 'कुलपति का संदेश' in uni_hi.html and 'छात्रवृत्ति 2024-25' in uni_hi.html,
>>>>>>> parent of 392c080 (Homepage matches the college reference photo: full-bleed hero, principal message, program cards, campus photos)
              'EN | हिन्दी toggle renders a real Hindi homepage')
        check('OUR CAMPUSES' in uni.get('index.php?page=about') and 'Howrah' in uni.html,
              'about page lists CRM campuses')
        check('2 Programs' in uni.get('index.php?page=courses') and 'Apply by' in uni.html,
              'programs page lists every active course with open-listing dates')
        check('How to join' in uni.get('index.php?page=admissions') and '10+2 with Physics' in uni.html,
              'admissions page shows process and eligibility')
        check('Admission notices' in uni.get('index.php?page=notices'),
              'notices page renders from listing dates')
        check('faculty directory will appear' in uni.get('index.php?page=faculty'),
              'empty faculty roster fails soft')
        execute("INSERT INTO teachers (institute_id,name,email,phone,qualification,active) VALUES (?,?,?,?,?,1)",
                (iid, 'Dr. Public Teacher', 't@example.test', '9111111111', 'M.Pharm, PhD'))
        check('Dr. Public Teacher' in uni.get('index.php?page=faculty') and 'M.Pharm, PhD' in uni.html,
              'faculty page reflects the CRM teaching roster')
        check('t@example.test' not in uni.html and '9111111111' not in uni.html,
              'faculty page hides personal contact details')
        check('Visit or call us' in uni.get('index.php?page=contact') and '+91 90000 00000' in uni.html,
              'contact page shows CRM phone numbers')
        check('Laboratories' in uni.get('index.php?page=facilities') and 'id="hostel"' in uni.html
              and 'id="transport"' in uni.html and 'id="activities"' in uni.html,
              'facilities page anchors every campus-life section')
        check('f-bottomnav' in uni.html and 'f-drawer' in uni.html and 'data-notice-search' in uni.get('index.php?page=notices'),
              'mobile drawer, bottom navigation and notice search are present')
        check('href="#"' not in uni.get() and 'href="#"' not in uni.get('index.php?page=about')
              and 'href="#"' not in uni.get('index.php?page=courses'),
              'public pages carry no dead placeholder links')
        login_html = uni.get('index.php?page=login')
        check('ONE SIGN-IN FOR EVERYONE' in login_html and 'id="modal-create"' in login_html
              and 'id="modal-inquiry"' in login_html and 'id="modal-recovery"' in login_html
              and 'id="modal-applicant"' in login_html
              and 'Create Student Account' in login_html and 'Admission Inquiry' in login_html
              and 'Forgot password?' in login_html and 'Track application' in login_html
              and 'u-login-links' not in login_html,
              'unified sign-in page offers OTP, signup, inquiry, recovery and applicant tracking with no side logins')
        check('data-open="1"' in uni.get('index.php?page=login&show=create'),
              'signup popup opens directly without JavaScript')

        def form_token(html):
            return re.search(r'name="csrf" value="([^"]+)"', html).group(1)

        def newest_code():
            files = sorted(mail.glob('*.eml'), key=lambda f: f.stat().st_mtime, reverse=True)
            for found in files:
                matched = re.search(r'code is: (\d{6})', found.read_text(errors='replace'))
                if matched:
                    return matched.group(1)
            raise AssertionError('no OTP mail found')

        staff_step = uni.post('otp_start', page='login', email='owner@example.test')
        check('Staff password sign-in' in staff_step and 'password_login' in uni.html
              and 'Sign in to office' in uni.html and 'office.php?email=' not in uni.html,
              'staff email in password mode leads to the inline password step')
        check('incorrect' in Master(uni).password_login(staff_step, 'owner@example.test', 'wrong-password'),
              'master password step rejects bad credentials')
        staff = Browser(base)
        master_staff = Master(staff)
        check('Hello, Owner' in master_staff.password_login(
            master_staff.otp_start('owner@example.test'), 'owner@example.test', 'Test-Owner-Password!'),
              'master password step signs staff into the office dashboard')
        check('valid email address' in uni.post('otp_start', page='login', email='bad'),
              'invalid email is rejected')
        check('No account found' in uni.post('otp_start', page='login', email='nobody@example.test')
              and 'Choose how to continue' in uni.html and 'I applied — track my application' in uni.html,
              'unknown email offers signup, inquiry or applicant tracking')

        execute("UPDATE students SET email='linkme@example.test' WHERE id=?", (sid,))
        execute("INSERT INTO portal_accounts (student_id,email,created_at) VALUES (?,?,'2026-01-01 00:00:00')",
                (sid, 'linkme@example.test'))
        otp = Browser(base)
        otp.get('index.php?page=login')
        otp.get('index.php?page=login', {'action': 'otp_start', 'csrf': form_token(otp.html),
                                         'email': 'linkme@example.test'})
        check('Enter the 6-digit code' in otp.html, 'student email triggers an OTP code step')
        otp.get('index.php?page=login', {'action': 'otp_start', 'csrf': form_token(otp.html),
                                         'email': 'linkme@example.test'})
        check('before resending' in otp.html and 'Enter the 6-digit code' in otp.html,
              'an immediate resend is throttled politely')
        otp.get('index.php?page=login', {'action': 'otp_verify', 'csrf': form_token(otp.html),
                                         'code': newest_code()})
        check('student.php' in otp.url, 'the OTP code signs the student in from the homepage')
        check('Sign out' in otp.get('student.php'),
              'single sign-on opens the student dashboard')

        time.sleep(4)
        signup = Browser(base)
        signup.get('index.php?page=login')
        signup.get('index.php?page=login', {'action': 'create_start', 'csrf': form_token(signup.html),
                                            'name': 'Mira Sen', 'address': '3 Park Street',
                                            'phone': '9000055555', 'email': 'mira@example.test',
                                            'website': ''})
        check('Your verification code' in signup.html, 'student signup sends a Gmail code')
        signup.get('index.php?page=login', {'action': 'create_verify', 'csrf': form_token(signup.html),
                                            'code': newest_code()})
        check('student.php' in signup.url, 'verified signup lands in the student portal')
        row = con.execute("SELECT name,phone,address FROM student_users WHERE email='mira@example.test'").fetchone()
        check(row == ('Mira Sen', '9000055555', '3 Park Street'),
              'signup stores the name, mobile and home address')
        check('Sign out' in signup.get('student.php'),
              'the new account session opens the dashboard')

        time.sleep(4)
        forgot = Browser(base)
        forgot.get('index.php?page=login')
        forgot.get('index.php?page=login', {'action': 'recovery_start', 'csrf': form_token(forgot.html),
                                            'email': 'mira@example.test'})
        check('Your verification code' in forgot.html, 'forgot password sends a recovery code')
        forgot.get('index.php?page=login', {'action': 'recovery_verify', 'csrf': form_token(forgot.html),
                                            'code': newest_code()})
        check('student.php' in forgot.url, 'the recovery code restores the student session')
        check('Sign out' in forgot.get('student.php'), 'recovered access opens the dashboard')

        enquiry = Browser(base)
        enquiry.get('index.php?page=login')
        enquiry.get('index.php?page=login', {'action': 'inquiry_save', 'csrf': form_token(enquiry.html),
                                             'name': 'Ravi Kumar', 'phone': '+919000011111',
                                             'email': 'ravi@example.test', 'address': '7 Lake Road',
                                             'course_id': str(cid), 'message': 'D.Pharm fees?',
                                             'website': ''})
        check('received' in enquiry.html, 'admission inquiry confirms receipt')
        row = con.execute('SELECT source,notes FROM enquiries WHERE email=?', ('ravi@example.test',)).fetchone()
        check(row and row[0] == 'website' and 'D.Pharm fees?' in row[1] and '7 Lake Road' in row[1],
              'the inquiry reaches the office with message and address')

        time.sleep(4)
        applicant = Browser(base)
        master_app = Master(applicant)
        step_app = master_app.applicant_start('uni-applicant@example.test')
        check('Your verification code' in step_app, 'applicant tracking sends a code from the master login')
        master_app.applicant_verify(step_app, newest_code())
        check('apply.php' in applicant.url and 'dashboard' in applicant.url,
              'verified applicant lands in the application workspace')
        check('My applications' in applicant.html, 'master applicant login opens the workspace directly')

        check(MASTER_MARK in owner.get('office.php?email=owner@example.test')
              and 'page=login' in owner.url,
              'office address redirects to the master sign-in')
        portal = Browser(base, 'student.php')
        check(MASTER_MARK in portal.get('student.php?email=linkme@example.test')
              and 'page=login' in portal.url,
              'student address redirects to the master sign-in')
        check('data-open="1"' in uni.get('index.php?page=login&show=applicant'),
              'applicant tracking opens directly without JavaScript')
        apply = uni.get('apply.php')
        check('<a class="u-brand" href="/index.php"' in apply and '← Back to website' in apply,
              'admissions brand and back button return to the university site')
        check('university.css' in apply and 'My applications' in apply,
              'admissions page uses the university theme and keeps its navigation')
        home = owner.get('office.php')
        check(MASTER_MARK in home and 'university.css' in home and 'page=login' in owner.url,
              'office address shares the themed master sign-in')
        spot = portal.get('student.php')
        check(MASTER_MARK in spot and 'f-brand' in spot,
              'student address lands on the branded master sign-in')
        owner.login('owner@example.test')
        dash = owner.get('office.php?page=dashboard')
        check('nav-link site-back' in dash and 'university.css' in dash,
              'office workspace carries the theme and a back-to-website link')
        check('Renamed Pharma University' in owner.post('institute', page='institutes', id=iid, name='Renamed Pharma University',
                                             kind='Pharma', city='Kolkata', phone='+91 91111 11111',
                                             address='99 New Campus Road'),
              'staff can edit institute identity')
        home = uni.get()
        check('Renamed Pharma University' in home and '99 New Campus Road' in home,
              'CRM identity change appears on the homepage immediately')
        execute('UPDATE admission_listings SET accepting=0 WHERE id=?', (cid,))
        check('Admissions are opening soon' in uni.get(), 'closing a listing updates the homepage')
        execute('UPDATE admission_listings SET accepting=1 WHERE id=?', (cid,))
        execute("INSERT INTO institutes (name,kind,city,phone,address,created_at) VALUES (?,?,?,?,?,?)",
                ('Evil <script>alert(1)</script>', 'Test', 'X', '1', 'Y', '2026-01-01 00:00:00'))
        check('<script>alert(1)</script>' not in uni.get('index.php?page=about')
              and '&lt;script&gt;' in uni.html, 'CRM text is escaped on public pages')
        uni.get('index.php?page=bogus')
        check(uni.status == 404 and 'Page not found' in uni.html, 'unknown university page is a 404')
    finally:
        server.terminate()
        try:
            server.wait(timeout=10)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait()
print(f'\n{checks} university checks passed. No real emails sent.')
