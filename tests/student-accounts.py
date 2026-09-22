#!/usr/bin/env python3
"""Student self-registration and account-link checks using private SQLite fixtures."""
import email
from email import policy
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
ROOT=Path(__file__).resolve().parents[1];PHP=shlex.split(os.environ.get('PHP_BIN','php'));checks=0

def check(ok,msg):
    global checks
    assert ok,msg
    checks+=1;print('PASS',msg)

class Browser:
    def __init__(self,base,endpoint='office.php'):
        self.base=base;self.endpoint=endpoint
        self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self,path=None,data=None):
        try:r=self.client.open(self.base+'/'+(path or self.endpoint),urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None,timeout=30)
        except urllib.error.HTTPError as ex:r=ex
        self.status=r.status;self.headers=r.headers;self.raw=r.read();self.html=self.raw.decode(errors='replace');return self.html
    def token(self,page='dashboard'):
        self.get(self.endpoint+'?page='+page);return re.search(r'name="csrf" value="([^"]+)"',self.html).group(1)
    def post(self,action,page='dashboard',**fields):return self.get(self.endpoint+'?page='+page,dict(action=action,csrf=self.token(page),**fields))
    def login(self,address,password='Test-Owner-Password!'):master=Master(self);return master.password_login(master.otp_start(address),address,password)

with tempfile.TemporaryDirectory(prefix='northstar-student-accounts-') as temp:
    temp=Path(temp);database=temp/'db.sqlite';cfg=temp/'config.php';mail=temp/'mail'
    cfg.write_text("<?php return ['dsn'=>'sqlite:"+str(database)+"','timezone'=>'Asia/Kolkata','auth_mode'=>'password','environment'=>'local','mail'=>['transport'=>'log','from_email'=>'sender@example.test','log_path'=>'"+str(mail)+"']];")
    env=dict(os.environ,CRM_CONFIG_FILE=str(cfg),CRM_ADMIN_PASSWORD='Test-Owner-Password!')
    result=subprocess.run(PHP+['bin/install.php','--name=Owner Test','--email=owner@example.test','--demo'],cwd=ROOT,env=env,capture_output=True,text=True,check=True)
    check('Installed successfully' in result.stdout,'fresh installation includes student account schema')
    con=sqlite3.connect(database)
    def scalar(sql,args=()):return con.execute(sql,args).fetchone()[0]
    def execute(sql,args=()):con.execute(sql,args);con.commit()
    def clear_limits():execute('DELETE FROM auth_events')
    sid=con.execute('SELECT id FROM students ORDER BY id LIMIT 1').fetchone()[0]
    for table in ['student_user_codes','student_users']:execute('DROP TABLE '+table)
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    log=open(temp/'server.log','w+')
    server=subprocess.Popen(PHP+['-d','opcache.enable=0','-S',f'0.0.0.0:{port}','-t',str(ROOT)],cwd=ROOT,env=env,stdout=log,stderr=log)
    try:
        base=f'http://127.0.0.1:{port}'
        for _ in range(100):
            try:urllib.request.urlopen(base+'/office.php',timeout=.5);break
            except OSError:time.sleep(.1)
        owner=Browser(base);s=Browser(base,'student.php');master_s=Master(s)
        check('Registration is not ready yet' in s.get('student.php?page=register'),'pre-upgrade registration fails safely')
        owner.login('owner@example.test')
        owner.get('upgrade.php');token=re.search(r'name="csrf" value="([^"]+)"',owner.html).group(1)
        check('Upgrade complete' in owner.get('upgrade.php',dict(csrf=token,backup='yes')),'owner upgrade adds student account tables')
        check('Send verification code' in s.get('student.php?page=register'),'registration form appears after upgrade')
        check('Create Student Account' in s.get('student.php'),'master sign-in links student registration')
        newbie=dict(name='New Student',email='newstudent@example.test',phone='9000000001',address='Test Address',website='')
        check('valid contact phone' in master_s.create_start(**dict(newbie,phone='bad')),'invalid phone rejected')
        check('Unable to process' in master_s.create_start(**dict(newbie,website='bot')),'honeypot rejects automated signup')
        step_new=master_s.create_start(**newbie)
        check('verification code has been sent' in step_new,'registration code request accepted')
        check(scalar('SELECT COUNT(*) FROM student_users')==0,'requesting a code does not create the account')
        check('Please wait 60 seconds' in master_s.create_start(**newbie),'registration resend cooldown enforced')
        def code_for(address):
            for f in sorted(mail.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
                msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
                if address in msg['To']:
                    return re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1)
            raise AssertionError('No test email for '+address)
        code=code_for('newstudent@example.test')
        check(code not in s.html,'verification code not exposed in browser response')
        check(scalar('SELECT code_hash FROM student_user_codes ORDER BY expires_at DESC LIMIT 1')!=code,'verification code not stored as plaintext')
        check('Invalid, expired' in master_s.create_verify(step_new,'000000'),'incorrect verification code rejected')
        check('STUDENT ACCOUNT' in master_s.create_verify(step_new,code) and 'Apply for admission' in s.html,'verified email creates the student account')
        check(scalar('SELECT COUNT(*) FROM student_users')==1,'one student account created after verification')
        check('Your session expired' in master_s.create_verify(master_s.page(),code),'used verification code cannot be replayed')
        t=Browser(base,'student.php');master_t=Master(t);clear_limits()
        step_t=master_t.create_start(name='Other Name',email='newstudent@example.test',phone='9000000002',address='Other Address',website='')
        check('verification code has been sent' in step_t,'existing email can request a sign-in code')
        check('STUDENT ACCOUNT' in master_t.create_verify(step_t,code_for('newstudent@example.test')),'existing email signs in with a fresh code')
        check(scalar('SELECT COUNT(*) FROM student_users')==1,'sign-in creates no duplicate account')
        check('Student accounts' in owner.get('office.php?page=student-accounts') and 'newstudent@example.test' in owner.html,'staff can list self-registered accounts')
        check('Not admitted' in owner.html and 'student_user_toggle' in owner.html,'unlinked account shows status and access control')
        uid=scalar('SELECT id FROM student_users')
        check('Changes saved' in owner.post('student_user_toggle',page='student-accounts',user_id=uid),'staff can disable a student account')
        check('STUDENT ACCOUNT' not in s.get('student.php') and MASTER_MARK in s.html,'disabled account loses access immediately')
        check('Changes saved' in owner.post('student_user_toggle',page='student-accounts',user_id=uid),'staff can restore a student account')
        clear_limits()
        step_back=master_s.create_start(**newbie)
        check('verification code has been sent' in step_back,'restored account can sign in again')
        check('STUDENT ACCOUNT' in master_s.create_verify(step_back,code_for('newstudent@example.test')),'restored account verified')
        check('Student email saved' in owner.post('student_email',page='students',student_id=sid,email='newstudent@example.test'),'office records the admitted student email')
        check('access enabled' in owner.post('portal_access',page='students',student_id=sid,access='enable'),'office enables portal access for the verified email')
        check('Enrolled' in s.get('student.php') and 'STUDENT ACCOUNT' not in s.html,'verified account links automatically to the full student portal')
        check('Linked' in owner.get('office.php?page=student-accounts'),'staff list shows the portal link')
        s.get('student.php',dict(action='logout',csrf=re.search(r'name="csrf" value="([^"]+)"',s.get('student.php')).group(1)))
        check(MASTER_MARK in s.get('student.php'),'student logout returns to sign-in')
    finally:
        server.terminate()
        try:server.wait(timeout=10)
        except subprocess.TimeoutExpired:server.kill();server.wait()
print(f'\n{checks} student account checks passed. No real emails sent.')
