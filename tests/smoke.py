#!/usr/bin/env python3
"""HTTP integration tests. Requires PHP 8.2+ with pdo_sqlite and Python 3.
Uses a disposable SQLite database; never reads or changes your normal CRM data.
Run: python3 tests/smoke.py
Optional: PHP_BIN='path/to/php' python3 tests/smoke.py
"""
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
    def __init__(self, base):
        self.base = base
        self.client = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.html = ''
        self.status = 0

    def request(self, page='dashboard', data=None):
        url = self.base + '/office.php?page=' + page
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.client.open(url, body, timeout=20)
        except urllib.error.HTTPError as e:
            response = e
        self.status = response.status
        self.html = response.read().decode()
        return self.html

    def post(self, action, page='dashboard', **fields):
        self.request(page)
        token = re.search(r'name="csrf" value="([^"]+)"', self.html).group(1)
        return self.request(page, dict(action=action, csrf=token, **fields))

    def get(self, path, data=None):
        url = self.base + '/' + path
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = self.client.open(url, body, timeout=20)
        except urllib.error.HTTPError as e:
            response = e
        self.status = response.status
        self.html = response.read().decode()
        return self.html

    def login(self, email, password):
        master = Master(self)
        return master.password_login(master.otp_start(email), email, password)


with tempfile.TemporaryDirectory(prefix='northstar-test-') as temp:
    temp = Path(temp)
    db = temp / 'test.sqlite'
    cfg = temp / 'config.php'
    cfg.write_text("<?php return ['dsn'=>'sqlite:" + str(db) + "', 'timezone'=>'Asia/Kolkata', 'auth_mode'=>'password'];")
    env = dict(os.environ, CRM_CONFIG_FILE=str(cfg), CRM_ADMIN_PASSWORD='Test-Owner-Password!')
    install = subprocess.run(PHP + ['bin/install.php', '--name=Test Owner', '--email=owner@example.test'],
                             cwd=ROOT, env=env, text=True, capture_output=True, check=True)
    check('Installed successfully' in install.stdout, 'fresh install')
    repeated = subprocess.run(PHP + ['bin/install.php', '--email=owner@example.test'],
                              cwd=ROOT, env=env, text=True, capture_output=True, check=True)
    check('Already installed' in repeated.stdout, 'reinstall leaves data untouched')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    log = open(temp / 'server.log', 'w+')
    server = subprocess.Popen(PHP + ['-S', f'0.0.0.0:{port}', '-t', str(ROOT)],
                              cwd=ROOT, env=env, stdout=log, stderr=log)
    try:
        base = f'http://127.0.0.1:{port}'
        # Wait for this short-lived test server, not a persistent preview process.
        for _ in range(100):
            try:
                urllib.request.urlopen(base, timeout=.5)
                break
            except (OSError, urllib.error.URLError):
                time.sleep(.1)
        owner = Browser(base)
        check(MASTER_MARK in owner.request(), 'unauthenticated requests redirect to the master login')
        check('incorrect' in owner.login('owner@example.test', 'wrong-password'), 'bad credentials rejected')
        check('Hello, Test' in owner.login('owner@example.test', 'Test-Owner-Password!'), 'owner can sign in')
        check('expired' in owner.request(data=dict(action='institute', csrf='invalid', name='Bad')),
              'CSRF validation blocks forged writes')
        for name in ('Alpha Pharmacy', 'Beta Medical'):
            check('Changes saved' in owner.post('institute', page='institutes', name=name,
                  kind='Training', city='Kolkata', phone='9000000000'), f'create {name}')
        conn = sqlite3.connect(db)
        def first(sql, args=()):
            return conn.execute(sql, args).fetchone()[0]
        a = first("SELECT id FROM institutes WHERE name='Alpha Pharmacy'")
        b = first("SELECT id FROM institutes WHERE name='Beta Medical'")
        for iid, email, role in [(a,'admin@example.test','admin'), (a,'counsellor@example.test','counsellor'),
                                  (b,'beta@example.test','counsellor')]:
            check('Changes saved' in owner.post('staff', page='staff', institute_id=iid,
                  name=email.split('@')[0], email=email, role=role, password='Staff-Test-Password!'), f'create {role} {email}')
        ac = first("SELECT id FROM users WHERE email='counsellor@example.test'")
        bc = first("SELECT id FROM users WHERE email='beta@example.test'")
        for iid, name in [(a,'Pharmacy diploma'),(b,'Medical lab course')]:
            check('Changes saved' in owner.post('course',page='courses',institute_id=iid,name=name,
                  duration='2 years',fee='85000.25',active='1'), f'create course {name}')
        ca=first('SELECT id FROM courses WHERE institute_id=?',(a,))
        cb=first('SELECT id FROM courses WHERE institute_id=?',(b,))
        check(first('SELECT fee_minor FROM courses WHERE id=?',(ca,)) == 8500025, 'fees stored as integer paise')
        check('valid fee' in owner.post('course',page='courses',institute_id=a,name='Negative',
              duration='1 year',fee='-1',active='1'), 'negative course fees rejected')
        def enquiry(iid, cid, uid, name='Applicant One', **changes):
            fields=dict(institute_id=iid,course_id=cid,assigned_to=uid,name=name,phone='9000000001',
                        email='',source='Website',status='Interested',notes='Ready for a campus visit')
            fields.update(changes)
            return fields
        check('same institute' in owner.post('enquiry',page='enquiries',**enquiry(a,cb,ac)),
              'cross-institute course assignment rejected')
        check('from this institute' in owner.post('enquiry',page='enquiries',**enquiry(a,ca,bc)),
              'cross-institute counsellor assignment rejected')
        escaped='<script>alert(1)</script>'
        check('Changes saved' in owner.post('enquiry',page='enquiries',**enquiry(a,ca,ac,name=escaped)), 'create enquiry')
        check(escaped not in owner.html and '&lt;script&gt;' in owner.html, 'student-supplied HTML is escaped')
        ea=first('SELECT id FROM enquiries WHERE institute_id=?',(a,))
        check('Changes saved' in owner.post('enquiry',page='enquiries',**enquiry(b,cb,bc,name='Beta Secret Student')), 'create second-institute enquiry')
        eb=first('SELECT id FROM enquiries WHERE institute_id=?',(b,))
        admin=Browser(base); admin.login('admin@example.test','Staff-Test-Password!')
        check('Beta Secret Student' not in admin.request('enquiries'), 'admin cannot list another institute records')
        check('Medical lab course' not in admin.request('courses'), 'course list is institute scoped')
        check('not accessible' in admin.request(f'enquiries&edit={eb}'), 'direct record ID access denied')
        check('not accessible' in admin.request(f'dashboard&institute={b}') or 'Beta Medical' not in admin.html,
              'staff cannot override institute scope')
        check('not accessible' in admin.post('enquiry',page='enquiries',**enquiry(b,cb,bc)), 'cross-institute write denied')
        counsellor=Browser(base); counsellor.login('counsellor@example.test','Staff-Test-Password!')
        check('permission' in counsellor.post('institute',page='enquiries',name='Unauthorized',kind='X',city='Y',phone='9000000000'),
              'counsellor cannot create institutes')
        counsellor.request('staff')
        check(counsellor.status == 403 and 'admin@example.test' not in counsellor.html, 'counsellor cannot view staff management')
        check('permission' in counsellor.post('admit',page='admissions',enquiry_id=ea,admission_date='2026-01-01'), 'counsellor cannot admit students')
        check('valid date' in owner.post('followup',page='followups',enquiry_id=ea,assigned_to=ac,
              due_date='2026-02-30',notes='Invalid'), 'invalid follow-up dates rejected')
        check('Changes saved' in owner.post('followup',page='followups',enquiry_id=ea,assigned_to=ac,
              due_date='2026-01-01',notes='Call applicant'), 'schedule follow-up')
        fid=first('SELECT id FROM followups WHERE enquiry_id=?',(ea,))
        check('Changes saved' in counsellor.post('complete_followup',page='followups',id=fid,outcome='Interested in joining'), 'complete follow-up with outcome')
        check('already been completed' in counsellor.post('complete_followup',page='followups',id=fid,outcome='Changed'), 'completed follow-up cannot be overwritten')
        owner.post('followup',page='followups',enquiry_id=ea,assigned_to=ac,due_date='2026-01-02',notes='Admission reminder')
        check('Student admitted' in admin.post('admit',page='admissions',enquiry_id=ea,admission_date='2026-01-01'), 'admin converts enquiry to student')
        check(first('SELECT COUNT(*) FROM students WHERE enquiry_id=?',(ea,))==1, 'exactly one student created')
        check(first('SELECT fee_minor FROM students WHERE enquiry_id=?',(ea,))==8500025, 'admission snapshots the course fee')
        check(first('SELECT status FROM enquiries WHERE id=?',(ea,))=='Admitted', 'enquiry status updated atomically')
        check(first('SELECT COUNT(*) FROM followups WHERE enquiry_id=? AND completed_at IS NULL',(ea,))==0, 'admission closes outstanding follow-ups')
        check('Only an open enquiry' in admin.post('admit',page='admissions',enquiry_id=ea,admission_date='2026-01-01'), 'duplicate admission rejected')
        check('locked' in owner.post('enquiry',page='enquiries',id=ea,**enquiry(a,ca,ac)), 'admitted enquiry cannot be edited')
        check('ST-00001' in admin.request('students'), 'student directory has stable student IDs')
        check('Nothing here yet' in owner.request('enquiries&q=nonexistent-xyz'), 'search empty state')
        check('Beta Secret Student' not in owner.request(f'enquiries&institute={a}'), 'owner institute filter works')
        owner.request('dashboard&institute=0')
        for page in ['dashboard','institutes','courses','staff','enquiries','followups','admissions','students','audit','settings']:
            owner.request(page)
            check(owner.status == 200 and 'temporarily unavailable' not in owner.html, f'{page} renders')
        for page in ['institutes','courses','staff','enquiries','followups']:
            owner.request(page+'&edit=new')
            check(owner.status == 200 and ('Save record' in owner.html or 'Schedule follow-up' in owner.html), f'{page} create form renders')
        check('Changes saved' in owner.post('staff_toggle',page='staff',id=ac), 'owner disables staff account')
        check(MASTER_MARK in counsellor.request(), 'disabled staff session loses access immediately')
        check('current password' in admin.post('password',page='settings',current_password='bad',password='New-Test-Password!',confirm_password='New-Test-Password!').lower(), 'password change requires current password')
        check('Changes saved' in admin.post('password',page='settings',current_password='Staff-Test-Password!',password='New-Test-Password!',confirm_password='New-Test-Password!'), 'password change succeeds')
        check(first("SELECT COUNT(*) FROM audit_log WHERE action='admitted'") == 1, 'admission is audited once')
        check(first("SELECT password_hash FROM users WHERE email='admin@example.test'") != 'New-Test-Password!', 'passwords are stored hashed')
        check(MASTER_MARK in owner.post('logout'), 'logout removes authenticated access')
        print(f'\n{checks} checks passed.')
        conn.close()
    finally:
        server.terminate()
        try:
            server.wait(timeout=10)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait()
        log.seek(0)
        output=log.read()
        if 'Fatal error' in output or 'Warning:' in output:
            print(output)
            raise AssertionError('PHP runtime warnings/errors found')
        log.close()
