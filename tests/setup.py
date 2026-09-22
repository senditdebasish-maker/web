#!/usr/bin/env python3
"""Protected browser installer integration checks. No real emails or credentials used.
Requires the PHP dependencies plus pdo_sqlite. Uses a temporary private setup root.
"""
import email
from email import policy
import hashlib
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

ROOT=Path(__file__).resolve().parents[1]
PHP=shlex.split(os.environ.get('PHP_BIN','php'))
checks=0

def check(condition,message):
    global checks
    assert condition,message
    checks+=1
    print('PASS',message)

class Browser:
    def __init__(self,base):
        self.base=base
        self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self,path='setup.php',data=None):
        try:
            r=self.client.open(self.base+'/'+path,urllib.parse.urlencode(data).encode() if data is not None else None,timeout=30)
        except urllib.error.HTTPError as ex:r=ex
        self.status=r.status;self.url=r.url;self.html=r.read().decode(errors='replace')
        return self.html
    def post(self,action,step=0,**fields):
        self.get('setup.php?step='+str(step))
        token=re.search(r'name="csrf" value="([^"]+)"',self.html).group(1)
        return self.get('setup.php?step='+str(step),dict(action=action,csrf=token,**fields))

with tempfile.TemporaryDirectory(prefix='northstar-setup-') as temp:
    temp=Path(temp); storage=temp/'storage'; storage.mkdir(); cfg=temp/'config.php'
    env=dict(os.environ,CRM_SETUP_ROOT=str(temp),CRM_CONFIG_FILE=str(cfg))
    with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    log=open(temp/'server.log','w+')
    server=subprocess.Popen(PHP+['-d','opcache.enable=0','-S',f'0.0.0.0:{port}','-t',str(ROOT)],cwd=ROOT,env=env,stdout=log,stderr=log)
    try:
        base=f'http://127.0.0.1:{port}'
        for _ in range(100):
            try:urllib.request.urlopen(base+'/setup.php',timeout=.5);break
            except OSError:time.sleep(.1)
        a=Browser(base); b=Browser(base)
        check('confirm that you control' in a.get('office.php'),'unconfigured app redirects to protected browser setup')
        key=(storage/'setup-key.txt').read_text().strip()
        check(len(key)==64 and key not in a.html,'setup key generated privately and never exposed in HTML')
        check('confirm that you control' in a.get('setup.php?step=5'),'review step cannot bypass authorization')
        check('expired' in a.get('setup.php',dict(action='unlock',csrf='invalid',setup_key=key)),'unlock action requires CSRF')
        check('does not match' in a.post('unlock',setup_key='0'*64),'incorrect setup key rejected')
        check(not cfg.exists(),'unlock attempts do not write a configuration')
        check('Server checks' in a.post('unlock',setup_key=key),'correct server key unlocks wizard')
        check('verified email' not in b.get('setup.php?step=4') and 'confirm that you control' in b.html,'other browser is not authorized automatically')
        check('PHPMailer and PDF libraries installed' in a.get('setup.php?step=1') and 'Needs attention' not in a.html,'requirements include real mail and PDF classes')
        check('requires HTTPS' in a.post('requirements',1,environment='production',timezone='Asia/Kolkata'),'production installation rejects plain HTTP')
        check('valid timezone' in a.post('requirements',1,environment='local',timezone='Invalid/Zone'),'invalid timezone rejected')
        check('A home for your institute data' in a.post('requirements',1,environment='local',timezone='Asia/Kolkata'),'local settings advance to database')
        check('Test the database' in a.post('owner',3,owner_name='Owner',owner_email='owner@example.test'),'owner step cannot bypass database test')
        check('valid database host' in a.post('database',2,engine='mysql',db_host='localhost;dbname=other',db_name='crm',db_port='3306',db_user='root',db_password=''),'DSN injection rejected before connecting')
        check('Make this workspace yours' in a.post('database',2,engine='sqlite'),'SQLite local-demo connection tested in browser')
        check(not cfg.exists(),'database test alone does not publish a configuration')
        database=storage/'crm.sqlite';con=sqlite3.connect(database)
        check(con.execute("SELECT COUNT(*) FROM sqlite_master WHERE type='table'").fetchone()[0]==0,'connection test creates no CRM tables')
        con.execute('CREATE TABLE existing_data (value TEXT)');con.execute("INSERT INTO existing_data VALUES ('keep me')");con.commit()
        check('not empty' in a.post('database',2,engine='sqlite'),'non-empty database refused')
        check(con.execute('SELECT value FROM existing_data').fetchone()[0]=='keep me','existing database data untouched')
        con.execute('DROP TABLE existing_data');con.commit()
        check('valid owner email' in a.post('owner',3,owner_name='Test Owner',owner_email='invalid'),'owner email validated')
        owner_fields=dict(owner_name='Test Owner',owner_email='owner@example.test',institute_name='Setup Pharma Institute',institute_kind='Pharma',institute_city='Kolkata',institute_phone='9000000000')
        check('Real emails' in a.post('owner',3,**owner_fields),'owner and initial institute draft saved')
        check('Verify your owner email' in a.post('install',5,confirm_install='yes'),'installation cannot bypass email verification')
        mail_fields=dict(mail_transport='log',from_email='sender@example.test',from_name='Setup Institute')
        check('not correct' not in a.post('verify_mail',4,verification_code='123456') and 'expired' in a.html,'verification requires an issued code')
        check('Test captured locally' in a.post('test_mail',4,**mail_fields),'PHPMailer creates a local setup-verification email')
        def code_for(address):
            for file in sorted((storage/'mail').glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
                msg=email.message_from_bytes(file.read_bytes(),policy=policy.default)
                if address in msg['To']:
                    return re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1)
            raise AssertionError('Mail not found')
        code=code_for('owner@example.test')
        check(code not in a.html,'test OTP is not exposed in HTML')
        check('Wait 60 seconds' in a.post('test_mail',4,**mail_fields),'setup test-mail resend is throttled')
        check('not correct' in a.post('verify_mail',4,verification_code='000000'),'wrong setup OTP rejected')
        check('Everything in its right place' in a.post('verify_mail',4,verification_code=code),'correct setup code advances to review')
        check('Local capture' not in a.html or 'no real delivery' in a.html,'review does not misrepresent local test mail')
        check('MySQL' not in a.html or 'Private local SQLite' in a.html,'review reflects chosen database')
        check('Setup Pharma Institute' in a.html and 'owner@example.test' in a.html,'review shows owner and institute')
        check('not correct' not in a.post('verify_mail',4,verification_code=code) and 'expired' in a.html,'verified setup code is single-use')
        # A second authorized installer can prepare a competing draft but cannot overwrite the first completion.
        b.post('unlock',setup_key=key);b.post('requirements',1,environment='local',timezone='Asia/Kolkata');b.post('database',2,engine='sqlite')
        b.post('owner',3,**dict(owner_fields,owner_name='Other Owner',owner_email='other@example.test'))
        b.post('test_mail',4,**mail_fields);b.post('verify_mail',4,verification_code=code_for('other@example.test'))
        check('Everything in its right place' in b.html,'second session reaches review before either installs')
        # Clear the first session verification by changing owner details, then confirm installation is blocked.
        a.post('owner',3,**dict(owner_fields,owner_name='Final Owner'))
        check('Verify your owner email' in a.post('install',5,confirm_install='yes'),'changing owner details invalidates previous verification')
        # Complete the second (verified) draft; first session must be denied afterwards.
        check('Your workspace is ready' in b.post('install',5,confirm_install='yes'),'wizard installs schema, owner, institute and private configuration')
        check(cfg.exists() and (storage/'installed.lock').exists(),'configuration and permanent installer lock saved')
        check(not (storage/'setup-key.txt').exists(),'setup key removed on successful completion')
        check(not (storage/'setup-recovery.php').exists(),'private pending configuration published successfully')
        check(con.execute('SELECT COUNT(*) FROM users').fetchone()[0]==1,'exactly one owner created')
        check(con.execute('SELECT name FROM users').fetchone()[0]=='Other Owner','only the confirmed draft is installed')
        check(con.execute('SELECT name FROM institutes').fetchone()[0]=='Setup Pharma Institute','first institute created')
        check(con.execute("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('payments','notifications','otp_challenges','documents')").fetchone()[0]==4,'full CRM communication/payment schema created')
        check(con.execute("SELECT COUNT(*) FROM audit_log WHERE action='browser_install'").fetchone()[0]==1,'browser installation is audited')
        before=hashlib.sha256(cfg.read_bytes()).hexdigest()
        a.get('setup.php?step=5',dict(action='install',csrf='invalid',confirm_install='yes'))
        check(a.status==403 and 'Setup is locked' in a.html,'competing old session cannot reinstall after completion')
        check(hashlib.sha256(cfg.read_bytes()).hexdigest()==before and con.execute('SELECT COUNT(*) FROM users').fetchone()[0]==1,'reinstall attempt changes neither configuration nor owner count')
        fresh=Browser(base);check('Setup is locked' in fresh.get('setup.php') and fresh.status==403,'fresh browser sees locked installer')
        check(MASTER_MARK in fresh.get('office.php') and 'Send sign-in code' in fresh.html,'installed CRM opens on the master OTP login')
        fresh_master=Master(fresh);fresh_step=fresh_master.otp_start('other@example.test')
        login_code=code_for('other@example.test')
        fresh_master.otp_verify(fresh_step,login_code)
        check('Hello, Other' in fresh.html,'owner created by wizard can authenticate through PHPMailer OTP')
        check('password' not in b.html.lower() or 'App Password' not in b.html,'success page does not echo SMTP credentials')
        con.close()
        # Existing config by itself is also a lock; an installation cannot be reopened by deleting one marker.
        (storage/'installed.lock').unlink()
        check('Setup is locked' in Browser(base).get('setup.php'),'configuration presence locks setup even without marker')
        cfg.rename(temp/'saved-config.php');(storage/'installed.lock').write_text('installed')
        check('Setup is locked' in Browser(base).get('setup.php'),'marker locks setup even if configuration is accidentally missing')
        (storage/'installed.lock').unlink();(storage/'setup-recovery.php').write_text('<?php return [];')
        check('Setup is locked' in Browser(base).get('setup.php'),'recovery configuration blocks destructive retry')
        print(f'\n{checks} setup checks passed. No real email sent.')
    finally:
        server.terminate()
        try:server.wait(timeout=10)
        except subprocess.TimeoutExpired:server.kill();server.wait()
        log.seek(0);out=log.read();log.close()
        if 'Fatal error' in out or 'Warning:' in out:print(out);raise AssertionError('PHP runtime warnings/errors')
