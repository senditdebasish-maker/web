#!/usr/bin/env python3
"""University front-page checks: CRM-driven content, sync and sign-in detection."""
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
        return self.post('login', email=address, password=password)


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
        owner = Browser(base, 'public/index.php')
        check('Northstar Pharmacy Institute' in uni.get() and '45 Park Street' in uni.html,
              'homepage brand and address come from the CRM')
        check('Diploma in Pharmacy' in uni.html and 'Apply Now' in uni.html,
              'homepage lists the open CRM program')
        check('data-theme-toggle' in uni.html and 'theme.js' in uni.html,
              'homepage offers the dark-mode toggle')
        check('OUR CAMPUSES' in uni.get('index.php?page=about') and 'Howrah' in uni.html,
              'about page lists CRM campuses')
        check('1 program open' in uni.get('index.php?page=courses') and 'Apply by' in uni.html,
              'programs page reflects open listings')
        check('How to join' in uni.get('index.php?page=admissions') and '10+2 with Physics' in uni.html,
              'admissions page shows process and eligibility')
        check('Admission notices' in uni.get('index.php?page=notices'),
              'notices page renders from listing dates')
        check('faculty directory will appear' in uni.get('index.php?page=faculty'),
              'empty faculty roster fails soft')
        execute("INSERT INTO teachers (institute_id,name,email,phone,qualification,active) VALUES (?,?,?,?,?,1)",
                (iid, 'Dr. Public Teacher', 't@example.test', '9000000000', 'M.Pharm, PhD'))
        check('Dr. Public Teacher' in uni.get('index.php?page=faculty') and 'M.Pharm, PhD' in uni.html,
              'faculty page reflects the CRM teaching roster')
        check('t@example.test' not in uni.html and '9000000000' not in uni.html,
              'faculty page hides personal contact details')
        check('Visit or call us' in uni.get('index.php?page=contact') and '+91 90000 00000' in uni.html,
              'contact page shows CRM phone numbers')
        check('ONE SIGN-IN FOR EVERYONE' in uni.get('index.php?page=login'),
              'unified sign-in page renders')
        uni.get('index.php?page=login', {'action': 'detect', 'email': 'owner@example.test'})
        check('public/index.php' in uni.url and 'email=owner' in uni.url,
              'staff email redirects to the office dashboard')
        execute("UPDATE students SET email='linkme@example.test' WHERE id=?", (sid,))
        uni.get('index.php?page=login', {'action': 'detect', 'email': 'linkme@example.test'})
        check('public/student.php' in uni.url and 'email=linkme' in uni.url,
              'student email redirects to the student dashboard')
        check('No account found' in uni.get('index.php?page=login', {'action': 'detect', 'email': 'nobody@example.test'})
              and 'Create your account' in uni.html, 'unknown email offers registration choices')
        check('valid email address' in uni.get('index.php?page=login', {'action': 'detect', 'email': 'bad'}),
              'invalid email is rejected')
        check('value="owner@example.test"' in owner.get('public/index.php?email=owner@example.test'),
              'office sign-in prefills the detected email')
        portal = Browser(base, 'public/student.php')
        check('value="linkme@example.test"' in portal.get('public/student.php?email=linkme@example.test'),
              'student sign-in prefills the detected email')
        owner.login('owner@example.test')
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
