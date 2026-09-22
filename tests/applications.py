#!/usr/bin/env python3
"""Public applicant and admission workflow checks using private SQLite fixtures."""
import email
import csv as csvlib
import io
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
from master import Master, LOGIN, MASTER_MARK
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

with tempfile.TemporaryDirectory(prefix='northstar-applications-') as temp:
    temp=Path(temp);database=temp/'db.sqlite';cfg=temp/'config.php';mail=temp/'mail'
    cfg.write_text("<?php return ['dsn'=>'sqlite:"+str(database)+"','timezone'=>'Asia/Kolkata','auth_mode'=>'password','environment'=>'local','mail'=>['transport'=>'log','from_email'=>'sender@example.test','log_path'=>'"+str(mail)+"']];")
    env=dict(os.environ,CRM_CONFIG_FILE=str(cfg),CRM_ADMIN_PASSWORD='Test-Owner-Password!')
    result=subprocess.run(PHP+['bin/install.php','--name=Owner Test','--email=owner@example.test','--demo'],cwd=ROOT,env=env,capture_output=True,text=True,check=True)
    check('Installed successfully' in result.stdout,'fresh installation creates operations schema')
    con=sqlite3.connect(database)
    def scalar(sql,args=()):return con.execute(sql,args).fetchone()[0]
    def execute(sql,args=()):con.execute(sql,args);con.commit()
    sid,iid,cid=con.execute('SELECT id,institute_id,course_id FROM students ORDER BY id LIMIT 1').fetchone()
    other_sid,other_iid,other_cid=con.execute('SELECT id,institute_id,course_id FROM students ORDER BY id DESC LIMIT 1').fetchone()
    execute("UPDATE students SET admission_date='2026-01-01',name='=2+3',email='student@example.test' WHERE id=?",(sid,))
    execute("UPDATE students SET admission_date='2026-01-01',name='Beta Secret Student' WHERE id=?",(other_sid,))
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    log=open(temp/'server.log','w+')
    server=subprocess.Popen(PHP+['-d','opcache.enable=0','-S',f'0.0.0.0:{port}','-t',str(ROOT)],cwd=ROOT,env=env,stdout=log,stderr=log)
    try:
        base=f'http://127.0.0.1:{port}'
        for _ in range(100):
            try:urllib.request.urlopen(base+'/office.php',timeout=.5);break
            except OSError:time.sleep(.1)
        owner=Browser(base);other_owner=Browser(base);owner.login('owner@example.test');other_owner.login('owner@example.test')
        visitor=Browser(base,'apply.php')
        check('No courses currently accepting' in visitor.get(),'course catalogue closed by default')
        check('Beta Secret Student' not in visitor.html and 'admin@example' not in visitor.html,'public catalogue exposes no private records')
        visitor.get('apply.php?page=course&course='+str(cid));check(visitor.status==404,'unlisted course cannot be fetched by ID')
        for table in ['application_events','admission_applications','applicant_codes','applicant_accounts','admission_listings']:execute('DROP TABLE '+table)
        visitor.get();check(visitor.status==503 and 'not ready' in visitor.html,'pre-upgrade public admissions fails safely')
        owner.get('upgrade.php');token=re.search(r'name="csrf" value="([^"]+)"',owner.html).group(1)
        check('Upgrade complete' in owner.get('upgrade.php',dict(csrf=token,backup='yes')),'owner can add public admission tables')
        check('Upgrade complete' in owner.get('upgrade.php',dict(csrf=token,backup='yes')),'public admission migration is repeatable')
        check(scalar('SELECT COUNT(*) FROM students')==2,'migration preserves existing student records')
        listing=dict(course_id=cid,version='0',description='Study pharmacy <script>',eligibility='Completed qualifying education; office checks originals.',privacy_notice='We use your application to review admission. Contact the institute office for retention, correction and guardian consent information.',opens_on='2026-01-01',closes_on='2030-12-31',accepting='1')
        check('Closing date cannot precede' in owner.post('admission_listing',page='applications',**dict(listing,closes_on='2025-01-01')),'invalid listing dates rejected')
        check('Changes saved' in owner.post('admission_listing',page='applications',**listing),'staff explicitly publishes a course listing')
        check('changed in another window' in owner.post('admission_listing',page='applications',**listing),'stale listing edit rejected')
        check('Diploma in Pharmacy' in visitor.get() and 'Medical Lab Technology' not in visitor.html,'only explicitly open courses are public')
        check('Study pharmacy &lt;script&gt;' in visitor.get('apply.php?page=course&course='+str(cid)),'course description is escaped')
        master_visitor=Master(visitor);step_v=master_visitor.applicant_start('applicant@example.test')
        check('If applications are open' in step_v,'applicant email code request accepts eligible new account')
        check(scalar('SELECT COUNT(*) FROM applicant_accounts')==0,'requesting OTP does not create applicant account')
        check('Please wait 60 seconds' in master_visitor.applicant_start('applicant@example.test'),'applicant resend cooldown enforced')
        def code_for(address):
            for f in sorted(mail.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
                msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
                if address in msg['To']:
                    return re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1)
            raise AssertionError('No test email for '+address)
        code=code_for('applicant@example.test')
        check(code not in visitor.html,'OTP not exposed in applicant browser response')
        check(scalar('SELECT code_hash FROM applicant_codes ORDER BY expires_at DESC LIMIT 1')!=code,'applicant OTP not stored as plaintext')
        stranger=Browser(base,'apply.php');check('Your session expired' in Master(stranger).applicant_verify(Master(stranger).page(),code),'code cannot authenticate another browser')
        wrong_v=master_visitor.applicant_verify(visitor.html,'000000')
        check('Invalid, expired' in wrong_v,'incorrect applicant code rejected')
        check('My applications' in master_visitor.applicant_verify(wrong_v,code) and 'Your email is verified' in visitor.html,'verified email creates isolated applicant account')
        check(scalar('SELECT COUNT(*) FROM applicant_accounts')==1,'one applicant account created after verification')
        check('Your session expired' in master_visitor.applicant_verify(master_visitor.page(),code),'used applicant code cannot be replayed')
        visitor.get('office.php');check(MASTER_MARK in visitor.html,'applicant session cannot authenticate to staff CRM')
        visitor.get('student.php');check(MASTER_MARK in visitor.html,'applicant session cannot authenticate to student portal')
        for forbidden in ['application_admit','payment','portal_access']:
            check('cannot perform' in visitor.post(forbidden,page='dashboard',student_id=sid),'applicant cannot invoke '+forbidden)
        fields=dict(name='Online Applicant <script>',phone='9000000000',city='Baharampur',qualification='Higher secondary science',completion_year='2025',note='Please explain the next steps.',consent='yes')
        def submit(browser,course=cid,**overrides):
            browser.get('apply.php?page=apply&course='+str(course));token=re.search(r'name="csrf" value="([^"]+)"',browser.html).group(1);nonce=re.search(r'name="request_key" value="([^"]+)"',browser.html).group(1)
            offer=re.search(r'name="offer_token" value="([^"]+)"',browser.html).group(1)
            return browser.get('apply.php?page=apply&course='+str(course),dict(action='submit_application',csrf=token,request_key=nonce,offer_token=offer,course_id=course,**(fields|overrides)))
        check('declaration and privacy' in submit(visitor,consent=''),'application requires explicit declaration and consent')
        check('valid completed qualification year' in submit(visitor,completion_year='2099'),'future completed qualification rejected')
        check('valid contact phone' in submit(visitor,phone='bad'),'invalid phone rejected')
        visitor.get('apply.php?page=apply&course='+str(cid));old_offer=re.search(r'name="offer_token" value="([^"]+)"',visitor.html).group(1);old_nonce=re.search(r'name="request_key" value="([^"]+)"',visitor.html).group(1)
        execute('UPDATE courses SET fee_minor=8600000 WHERE id=?',(cid,))
        check('Course details or fee changed' in visitor.post('submit_application',page='dashboard',course_id=cid,request_key=old_nonce,offer_token=old_offer,**fields),'fee change between form load and submission requires re-review')
        execute('UPDATE courses SET fee_minor=8500000 WHERE id=?',(cid,))
        check('Application saved' in submit(visitor,student_id=other_sid,fee_minor='1',institute_id=other_iid),'verified applicant submits application')
        appid=scalar('SELECT id FROM admission_applications');aid=scalar('SELECT applicant_id FROM admission_applications')
        check(scalar('SELECT institute_id FROM admission_applications')==iid and scalar('SELECT fee_minor FROM admission_applications')==8500000,'server chooses institute and fee rather than submitted values')
        check(scalar('SELECT COUNT(*) FROM students')==2 and scalar('SELECT COUNT(*) FROM portal_accounts')==0,'submission does not auto-admit or grant student access')
        check('Online Applicant &lt;script&gt;' in visitor.html and 'APP-' in visitor.html,'private acknowledgement shows escaped details and reference')
        key=scalar('SELECT request_key FROM admission_applications')
        check('already received' in visitor.post('submit_application',page='dashboard',request_key=key,course_id=cid,**fields),'duplicate submission returns existing acknowledgement')
        check(scalar('SELECT COUNT(*) FROM admission_applications')==1,'duplicate submission creates no second application')
        check('already have an active' in submit(visitor),'one active application limit enforced')
        check('must request corrections' in visitor.post('revise_application',page='dashboard',application_id=appid,version='1',**fields),'applicant cannot edit a submitted record without correction request')
        execute('DELETE FROM auth_events')
        other=Browser(base,'apply.php');master_other=Master(other);master_other.applicant_verify(master_other.applicant_start('other-applicant@example.test'),code_for('other-applicant@example.test'))
        other.get('apply.php?page=application&id='+str(appid));check(other.status==403 and 'Online Applicant' not in other.html,'other applicant cannot read application by ID')
        check('Application not accessible' in other.post('withdraw_application',page='dashboard',application_id=appid,version='1',reason='Attack'),'other applicant cannot withdraw application')
        owner.post('staff',page='staff',institute_id=other_iid,name='Beta Admin',email='beta@example.test',role='admin',password='Staff-Test-Password!')
        admin=Browser(base);admin.login('beta@example.test','Staff-Test-Password!')
        admin.get('office.php?page=applications&application='+str(appid));check(admin.status==403,'other-institute admin cannot view application')
        check('not accessible' in admin.post('application_review',page='applications',application_id=appid,version='1',status='Rejected',message='Attack'),'cross-institute application review rejected')
        check('not accessible' in admin.post('admission_listing',page='applications',**dict(listing,version='1')),'cross-institute listing edit rejected')
        owner.post('staff',page='staff',institute_id=iid,name='Counsellor',email='counsellor@example.test',role='counsellor',password='Staff-Test-Password!')
        counsellor=Browser(base);counsellor.login('counsellor@example.test','Staff-Test-Password!');counsellor.get('office.php?page=applications');check(counsellor.status==403,'counsellor cannot access online admission decisions')
        check('Changes saved' in owner.post('application_review',page='applications',application_id=appid,version='1',status='Changes requested',message='Please provide full qualification details.'),'office requests applicant corrections')
        check('Please provide full qualification' in visitor.get('apply.php?page=application&id='+str(appid)),'applicant sees office message and correction form')
        check('Wait for the applicant' in owner.post('application_admit',page='applications',application_id=appid,version='2',message='Approval',approval='yes'),'cannot approve while corrections are outstanding')
        check('changed in another window' in visitor.post('revise_application',page='dashboard',application_id=appid,version='1',**fields),'stale applicant correction rejected')
        check('Application saved' in visitor.post('revise_application',page='dashboard',application_id=appid,version='2',**(fields|{'qualification':'Higher secondary science with biology'})),'applicant resubmits corrections')
        check(scalar('SELECT status FROM admission_applications')=='Submitted','resubmission returns application to submitted queue')
        check('changed in another window' in owner.post('application_review',page='applications',application_id=appid,version='2',status='Rejected',message='Stale'),'stale staff decision rejected')
        check('Confirm eligibility' in owner.post('application_admit',page='applications',application_id=appid,version='3',message='Approved',approval=''),'admission requires explicit eligibility and access confirmation')
        # Force queue insertion failure to prove all admission artifacts roll back together.
        execute("CREATE TRIGGER fail_application_mail BEFORE INSERT ON notifications BEGIN SELECT RAISE(ABORT,'test'); END")
        check('could not be saved' in owner.post('application_admit',page='applications',application_id=appid,version='3',message='Approved',approval='yes'),'admission failure returns safe error')
        check(scalar('SELECT COUNT(*) FROM students')==2 and scalar('SELECT status FROM admission_applications')=='Submitted' and scalar('SELECT COUNT(*) FROM portal_accounts')==0,'failed approval rolls back student, state and portal account')
        execute('DROP TRIGGER fail_application_mail')
        execute('UPDATE courses SET fee_minor=9000000 WHERE id=?',(cid,))
        check('Changes saved' in owner.post('application_admit',page='applications',application_id=appid,version='3',message='Your eligibility has been confirmed. Welcome.',approval='yes'),'office approves admission transactionally')
        new_sid=scalar('SELECT student_id FROM admission_applications')
        check(scalar('SELECT fee_minor FROM students WHERE id=?',(new_sid,))==8500000,'admission honors quoted fee snapshot after course price change')
        check(scalar('SELECT COUNT(*) FROM documents WHERE student_id=?',(new_sid,))==1 and scalar('SELECT COUNT(*) FROM notifications')==1,'approval creates admission PDF snapshot and queued email')
        check(scalar('SELECT active FROM portal_accounts WHERE student_id=?',(new_sid,))==1,'approved student portal account explicitly enabled')
        check(scalar('SELECT COUNT(*) FROM payments WHERE student_id=?',(new_sid,))==0,'admission does not fabricate a payment')
        owner.post('application_admit',page='applications',application_id=appid,version='3',message='Again',approval='yes')
        check(scalar('SELECT COUNT(*) FROM students')==3,'repeated approval cannot create duplicate student')
        check('Your admission has been approved' in visitor.get('apply.php?page=application&id='+str(appid)),'applicant sees approved status and portal next step')
        check('closed to applicant changes' in visitor.post('withdraw_application',page='dashboard',application_id=appid,version='4',reason='Cancel'),'applicant cannot self-cancel an admitted student record')
        execute('DELETE FROM auth_events')
        student=Browser(base,'student.php');master_student=Master(student);master_student.otp_verify(master_student.otp_start('applicant@example.test'),code_for('applicant@example.test'))
        check('₹85,000.00' in student.get('student.php?page=payments'),'approved applicant can log in to own student portal')
        doc=scalar('SELECT id FROM documents WHERE student_id=?',(new_sid,));student.get('student.php?document='+str(doc));check(student.raw.startswith(b'%PDF'),'approved student can download actual admission PDF')
        for url in ['applications','applications&tab=listings','applications&listing='+str(cid),'applications&application='+str(appid)]:
            owner.get('office.php?page='+url);check(owner.status==200 and 'temporarily unavailable' not in owner.html,url+' staff page renders')
        for url in ['courses','course&course='+str(cid),'dashboard','application&id='+str(appid),'help']:
            visitor.get('apply.php?page='+url);check(visitor.status==200 and 'temporarily unavailable' not in visitor.html,url+' applicant page renders')
        # New applicant exercises withdrawal, closing a listing and terminal review states.
        check('Application saved' in submit(other,name='Second Applicant'),'second verified applicant may apply')
        second_id=scalar('SELECT MAX(id) FROM admission_applications')
        check('Application saved' in other.post('withdraw_application',page='dashboard',application_id=second_id,version='1',reason='Plans changed'),'applicant can withdraw pending application')
        check('already closed' in owner.post('application_review',page='applications',application_id=second_id,version='2',status='Under review',message='No'),'withdrawn application cannot be reopened by review')
        owner.post('admission_listing',page='applications',**dict(listing,version='1',accepting='0'))
        other.get('apply.php?page=apply&course='+str(cid));check(other.status==404,'closed listing cannot accept a new application')
        visitor.get('apply.php?page=application&id='+str(appid));check(visitor.status==200,'closing catalogue does not hide own existing application')
        owner.post('admission_listing',page='applications',**dict(listing,version='2',accepting='1'))
        submit(other,name='Second Applicant');third_id=scalar('SELECT MAX(id) FROM admission_applications')
        owner.post('application_review',page='applications',application_id=third_id,version='1',status='Rejected',message='Eligibility not met.')
        check('Eligibility not met.' in other.get('apply.php?page=application&id='+str(third_id)),'applicant can read rejection explanation')
        check('closed to applicant changes' in other.post('revise_application',page='dashboard',application_id=third_id,version='2',**fields),'rejected application cannot be edited')
        owner.post('applicant_toggle',page='applications',application_id=third_id)
        check(MASTER_MARK in other.get('apply.php?page=dashboard'),'owner suspension revokes applicant session')
        owner.post('applicant_toggle',page='applications',application_id=third_id)
        check(MASTER_MARK in other.get('apply.php?page=dashboard'),'restoring account does not revive old applicant session')
        # Applicant OTP protections and return-to-course flow for a third browser.
        execute('DELETE FROM auth_events')
        fresh=Browser(base,'apply.php');master_fresh=Master(fresh);fresh.get('apply.php?page=apply&course='+str(cid))
        check('id="modal-applicant" data-open="1"' in fresh.html,'new applicants must verify before seeing submission form')
        master_fresh.raw(LOGIN,dict(action='applicant_start',csrf='bad',email='fresh@example.test'))
        check('form expired' in fresh.html,'applicant OTP requests require CSRF')
        step_fresh=master_fresh.applicant_start('fresh@example.test');expired_code=code_for('fresh@example.test')
        execute('UPDATE applicant_codes SET expires_at=1 WHERE email_hash=?',(__import__('hashlib').sha256(b'applicant:fresh@example.test').hexdigest(),))
        check('Invalid, expired' in master_fresh.applicant_verify(step_fresh,expired_code),'expired applicant code rejected')
        execute('DELETE FROM auth_events');step_lock=master_fresh.applicant_start('fresh@example.test');exhausted_code=code_for('fresh@example.test')
        for _ in range(5):step_lock=master_fresh.applicant_verify(step_lock,'000000')
        check('Invalid, expired' in master_fresh.applicant_verify(step_lock,exhausted_code),'applicant code locks after five wrong attempts')
        execute('DELETE FROM auth_events');step_new=master_fresh.applicant_start('fresh@example.test');latest=code_for('fresh@example.test')
        stale_new=master_fresh.applicant_verify(step_new,exhausted_code)
        check('Invalid, expired' in stale_new,'resend invalidates previous applicant code')
        check('APPLICATION FORM' in master_fresh.applicant_verify(stale_new,latest),'email verification returns applicant to selected course')
        check('form expired' in fresh.get('apply.php?page=apply&course='+str(cid),dict(action='submit_application',csrf='bad')),'application submission requires CSRF')
        check('Unable to submit' in submit(fresh,website='bot'),'submission honeypot rejects obvious automated form filling')
        # Same browser can hold independent staff and applicant cookies without privilege sharing.
        execute('DELETE FROM auth_events');owner.endpoint='apply.php';master_owner2=Master(owner);master_owner2.applicant_verify(master_owner2.applicant_start('other-applicant@example.test'),code_for('other-applicant@example.test'))
        check('My applications' in owner.get('apply.php?page=dashboard'),'existing enabled applicant can sign in again')
        owner.post('logout',page='dashboard');owner.endpoint='office.php';check('Hello, Owner' in owner.get(),'applicant logout preserves separate staff session')
        # Suspend pending codes, not only authenticated sessions.
        execute('DELETE FROM auth_events');pending=Browser(base,'apply.php');master_pending=Master(pending);step_pending=master_pending.applicant_start('other-applicant@example.test');pending_code=code_for('other-applicant@example.test')
        owner.post('applicant_toggle',page='applications',application_id=third_id);owner.post('applicant_toggle',page='applications',application_id=third_id)
        check('Invalid, expired' in master_pending.applicant_verify(step_pending,pending_code),'disable and restore cannot revive previously issued applicant OTP')
        owner.post('student_email',page='students',student_id=new_sid,email='corrected-student@example.test')
        check(scalar('SELECT active FROM applicant_accounts WHERE id=?',(aid,))==0 and MASTER_MARK in visitor.get('apply.php?page=dashboard'),'student email correction suspends linked applicant access')
        visitor.post('logout',page='dashboard');visitor.get('apply.php?page=application&id='+str(appid));check(MASTER_MARK in visitor.html,'applicant logout removes access to private application')
        print(f'\n{checks} public admissions checks passed. No real emails sent.');con.close()
    finally:
        server.terminate()
        try:server.wait(timeout=10)
        except subprocess.TimeoutExpired:server.kill();server.wait()
        log.seek(0);text=log.read();log.close()
        if 'Fatal error' in text or 'Warning:' in text:print(text);raise AssertionError('PHP warnings/errors')
