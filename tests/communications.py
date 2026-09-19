#!/usr/bin/env python3
"""OTP, payment, PDF and email-queue integration tests. Uses only local .eml capture.
Install Composer dependencies first. Run: python3 tests/communications.py
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
    def get(self,path='?page=dashboard',data=None):
        try:
            r=self.client.open(self.base+'/'+path,urllib.parse.urlencode(data).encode() if data is not None else None,timeout=40)
        except urllib.error.HTTPError as ex: r=ex
        self.status=r.status; self.headers=r.headers; self.raw=r.read(); self.html=self.raw.decode('utf-8',errors='replace')
        return self.html
    def post(self,action,page='dashboard',**fields):
        self.get('?page='+page)
        token=re.search(r'name="csrf" value="([^"]+)"',self.html).group(1)
        return self.get('?page='+page,dict(action=action,csrf=token,**fields))

with tempfile.TemporaryDirectory(prefix='northstar-mail-') as tmp:
    tmp=Path(tmp); database=tmp/'data.sqlite'; capture=tmp/'mail'; cfg=tmp/'config.php'
    def write_config(transport='log',environment='local'):
        cfg.write_text("<?php return ['dsn'=>'sqlite:"+str(database)+"','timezone'=>'Asia/Kolkata','auth_mode'=>'otp','environment'=>'"+environment+"','mail'=>['transport'=>'"+transport+"','host'=>'127.0.0.1','port'=>1,'username'=>'sender@example.test','password'=>'not-real','from_email'=>'sender@example.test','from_name'=>'Northstar','log_path'=>'"+str(capture)+"']];")
    write_config()
    env=dict(os.environ,CRM_CONFIG_FILE=str(cfg),CRM_ADMIN_PASSWORD='Test-Owner-Password!')
    def cli(script,*args):
        result=subprocess.run(PHP+[script,*args],cwd=ROOT,env=env,text=True,capture_output=True,timeout=90)
        assert result.returncode==0 and 'Fatal error' not in result.stdout,result.stdout+result.stderr
        return result.stdout
    check('Installed successfully' in cli('bin/install.php','--name=Test Owner','--email=owner@example.test','--demo'),'OTP-enabled fresh installation')
    check('ready' in cli('bin/migrate.php') and 'ready' in cli('bin/migrate.php'),'additive migration can run repeatedly')
    con=sqlite3.connect(database)
    def scalar(sql,args=()): return con.execute(sql,args).fetchone()[0]
    def execute(sql,args=()): con.execute(sql,args); con.commit()
    def unthrottle(): execute('DELETE FROM auth_events')
    def messages():
        return [email.message_from_bytes(p.read_bytes(),policy=policy.default) for p in sorted(capture.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns)]
    def last_code(address='owner@example.test'):
        for msg in reversed(messages()):
            if address in msg['To'] and 'sign-in code' in msg['Subject']:
                return re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1)
        raise AssertionError('OTP email not captured')
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    log=open(tmp/'server.log','w+')
    server=subprocess.Popen(PHP+['-d','opcache.enable=0','-S',f'0.0.0.0:{port}','-t','public'],cwd=ROOT,env=env,stdout=log,stderr=log)
    try:
        base=f'http://127.0.0.1:{port}'
        for _ in range(100):
            try: urllib.request.urlopen(base,timeout=.5); break
            except OSError: time.sleep(.1)
        owner=Browser(base); stranger=Browser(base)
        check('Secure email-code sign in' in owner.get(),'email OTP login UI replaces password form')
        check('Password login is disabled' in owner.post('login',email='owner@example.test',password='Test-Owner-Password!'),'password endpoint cannot bypass OTP')
        check('expired' in owner.get(data=dict(action='request_otp',csrf='bad',email='owner@example.test')),'OTP requests require CSRF')
        check('active staff account' in owner.post('request_otp',email='owner@example.test'),'registered account receives generic success response')
        code=last_code(); check(code not in owner.html,'OTP is never exposed in browser HTML')
        check(scalar('SELECT COUNT(*) FROM otp_challenges WHERE code_hash=?',(code,))==0,'OTP is not stored in plaintext')
        check('60 seconds' in owner.post('request_otp',email='owner@example.test'),'resend cooldown is enforced')
        check('Request a new' in stranger.post('verify_otp',code=code),'code cannot authenticate a different browser session')
        check('Invalid, expired' in owner.post('verify_otp',code='000000'),'wrong code is rejected')
        check('Hello, Test' in owner.post('verify_otp',code=code),'correct code signs in existing owner')
        check(scalar('SELECT consumed FROM otp_challenges ORDER BY expires_at DESC LIMIT 1')==1,'successful OTP is consumed')
        check('Request a new' in owner.post('verify_otp',code=code),'used code cannot be replayed')
        owner.post('logout'); unthrottle()
        before=len(messages())
        check('active staff account' in stranger.post('request_otp',email='unknown@example.test'),'unknown email has same generic success message')
        check(len(messages())==before,'unknown email sends no message and creates no account')
        check('Invalid, expired' in stranger.post('verify_otp',code='000000'),'unknown email challenge cannot sign in')
        unthrottle(); owner.post('request_otp',email='owner@example.test'); expired=last_code()
        execute('UPDATE otp_challenges SET expires_at=1')
        check('Invalid, expired' in owner.post('verify_otp',code=expired),'expired code rejected')
        unthrottle(); owner.post('request_otp',email='owner@example.test'); exhausted=last_code()
        for _ in range(5): owner.post('verify_otp',code='000000')
        check('Invalid, expired' in owner.post('verify_otp',code=exhausted),'challenge locks after five wrong attempts')
        unthrottle(); owner.post('request_otp',email='owner@example.test'); old_id=scalar('SELECT id FROM otp_challenges WHERE consumed=0 ORDER BY expires_at DESC LIMIT 1')
        unthrottle(); owner.post('request_otp',email='owner@example.test')
        check(scalar('SELECT consumed FROM otp_challenges WHERE id=?',(old_id,))==1,'resending invalidates previous challenge')
        owner.post('verify_otp',code=last_code())
        ac=scalar("SELECT id FROM users WHERE email='counsellor1@example.test'")
        iid=scalar('SELECT institute_id FROM users WHERE id=?',(ac,)); cid=scalar('SELECT id FROM courses WHERE institute_id=?',(iid,))
        fields=dict(institute_id=iid,course_id=cid,assigned_to=ac,name='New Student <Test>',phone='9000000001',email='student@example.test',source='Website',status='Interested',notes='Test admission')
        check('Changes saved' in owner.post('enquiry',page='enquiries',**fields),'create student enquiry with email')
        eid=scalar("SELECT id FROM enquiries WHERE email='student@example.test'")
        check('Student admitted' in owner.post('admit',page='admissions',enquiry_id=eid,admission_date='2026-01-01'),'admission commits with PDF snapshot and email queue')
        sid=scalar('SELECT id FROM students WHERE enquiry_id=?',(eid,))
        doc=scalar('SELECT id FROM documents WHERE student_id=?',(sid,)); nid=scalar('SELECT id FROM notifications WHERE document_id=?',(doc,))
        check(scalar('SELECT status FROM notifications WHERE id=?',(nid,))=='pending','admission email pending until worker runs')
        check('student@example.test' in owner.get('?page=notifications'),'notification page shows intended recipient')
        owner.get(f'document.php?id={doc}')
        check(owner.status==200 and owner.raw.startswith(b'%PDF-') and b'%%EOF' in owner.raw,'admission download is a real PDF')
        check(owner.headers.get_content_type()=='application/pdf' and 'attachment;' in owner.headers['Content-Disposition'],'PDF download has correct headers')
        stranger.get(f'document.php?id={doc}'); check(stranger.status==401,'anonymous PDF access denied')
        check('spooled' in cli('bin/send-notifications.php'),'worker captures email with PDF attachment locally')
        check(scalar('SELECT status FROM notifications WHERE id=?',(nid,))=='spooled','local test never claims real email delivery')
        msg=[m for m in messages() if 'student@example.test' in m['To']][-1]
        attachments=list(msg.iter_attachments())
        check(len(attachments)==1 and attachments[0].get_content_type()=='application/pdf' and attachments[0].get_payload(decode=True).startswith(b'%PDF-'),'admission email contains generated PDF attachment')
        before=len(messages()); cli('bin/send-notifications.php'); check(len(messages())==before,'completed queue item is not sent again')
        owner.get('?page=payments'); nonce=re.search(r'name="request_key" value="([^"]+)"',owner.html).group(1)
        payment=dict(student_id=sid,request_key=nonce,amount='1000.25',paid_on='2026-01-01',method='UPI',reference='TEST-UPI-001')
        check('Payment recorded' in owner.post('payment',page='payments',**payment),'record payment and queue receipt')
        pid=scalar('SELECT id FROM payments WHERE student_id=?',(sid,)); pdoc=scalar("SELECT id FROM documents WHERE event_key=?",('payment:'+str(pid),))
        check(scalar('SELECT amount_minor FROM payments WHERE id=?',(pid,))==100025,'payment amount uses integer paise')
        check('already recorded' in owner.post('payment',page='payments',**payment),'duplicate form submission is idempotent')
        check(scalar('SELECT COUNT(*) FROM payments WHERE student_id=?',(sid,))==1,'duplicate submission creates no second payment')
        owner.get('?page=payments'); nonce=re.search(r'name="request_key" value="([^"]+)"',owner.html).group(1)
        payment['request_key']=nonce
        check('exceeds' in owner.post('payment',page='payments',**dict(payment,amount='9999999')),'overpayment rejected')
        check('positive amount' in owner.post('payment',page='payments',**dict(payment,amount='-1')),'negative payment rejected')
        check('greater than zero' in owner.post('payment',page='payments',**dict(payment,amount='0')),'zero payment rejected')
        check('reference' in owner.post('payment',page='payments',**dict(payment,reference='')),'non-cash payment requires reference')
        check('between admission and today' in owner.post('payment',page='payments',**dict(payment,paid_on='2025-01-01')),'pre-admission payment date rejected')
        check(scalar('SELECT COUNT(*) FROM payments WHERE student_id=?',(sid,))==1,'invalid payments leave no records')
        owner.get(f'document.php?id={pdoc}'); check(owner.raw.startswith(b'%PDF-'),'payment receipt PDF renders')
        cli('bin/send-notifications.php'); receipt=[m for m in messages() if 'Payment receipt' in m['Subject']][-1]
        check(list(receipt.iter_attachments())[0].get_payload(decode=True).startswith(b'%PDF-'),'payment email includes receipt PDF')
        payload=scalar('SELECT payload_json FROM documents WHERE id=?',(pdoc,))
        execute('UPDATE courses SET fee_minor=100 WHERE id=?',(cid,))
        check(scalar('SELECT payload_json FROM documents WHERE id=?',(pdoc,))==payload,'changing course fee cannot alter saved receipt snapshot')
        oldsid=scalar('SELECT id FROM students WHERE email=\'\' LIMIT 1')
        check('available in Documents' in owner.post('admission_letter',page='students',student_id=oldsid),'existing student can generate admission letter')
        olddoc=scalar('SELECT id FROM documents WHERE student_id=?',(oldsid,))
        check(scalar('SELECT status FROM notifications WHERE document_id=?',(olddoc,))=='blocked','missing student email explicitly blocks delivery')
        check('Student email saved' in owner.post('student_email',page='students',student_id=oldsid,email='older@example.test'),'admin can add missing student email')
        check(scalar('SELECT status FROM notifications WHERE document_id=?',(olddoc,))=='pending','adding email releases blocked notification')
        owner.post('admission_letter',page='students',student_id=oldsid)
        check(scalar('SELECT COUNT(*) FROM documents WHERE student_id=?',(oldsid,))==1,'old admission document generation is idempotent')
        write_config('invalid')
        check('pending' in cli('bin/send-notifications.php'),'mail configuration failure is queued for retry')
        check(scalar('SELECT attempts FROM notifications WHERE document_id=?',(olddoc,))==1,'failed delivery increments attempts without deleting admission')
        execute("UPDATE notifications SET attempts=4,next_attempt_at=0 WHERE document_id=?",(olddoc,))
        check('failed' in cli('bin/send-notifications.php'),'fifth failed delivery becomes failed status')
        failid=scalar('SELECT id FROM notifications WHERE document_id=?',(olddoc,))
        check('Changes saved' in owner.post('retry_notification',page='notifications',id=failid),'administrator can requeue failed email')
        write_config(); cli('bin/send-notifications.php')
        check(scalar('SELECT status FROM notifications WHERE id=?',(failid,))=='spooled','requeued email can recover')
        beta_iid=scalar("SELECT institute_id FROM users WHERE email='counsellor2@example.test'")
        check('Changes saved' in owner.post('staff',page='staff',institute_id=beta_iid,name='Beta Admin',email='beta-admin@example.test',role='admin'),'create OTP-only staff without asking for a password')
        unthrottle(); beta=Browser(base); beta.post('request_otp',email='beta-admin@example.test'); beta.post('verify_otp',code=last_code('beta-admin@example.test'))
        check('not accessible' in beta.post('payment',page='payments',**payment),'admin cannot record another institute payment')
        check('not accessible' in beta.post('student_email',page='students',student_id=sid,email='stolen@example.test'),'admin cannot redirect another institute student email')
        check('not accessible' in beta.post('retry_notification',page='notifications',id=failid),'admin cannot requeue another institute notification')
        before_payments=scalar('SELECT COUNT(*) FROM payments')
        before_documents=scalar('SELECT COUNT(*) FROM documents')
        execute("CREATE TRIGGER fail_notification BEFORE INSERT ON notifications BEGIN SELECT RAISE(ABORT,'test queue failure'); END")
        check('could not be saved' in owner.post('payment',page='payments',**payment),'queue insert failure returns safe payment error')
        check(scalar('SELECT COUNT(*) FROM payments')==before_payments and scalar('SELECT COUNT(*) FROM documents')==before_documents,'payment and document roll back if queue insertion fails')
        execute('DROP TRIGGER fail_notification')
        unthrottle(); other=Browser(base); other.post('request_otp',email='counsellor2@example.test'); other.post('verify_otp',code=last_code('counsellor2@example.test'))
        other.get(f'document.php?id={doc}'); check(other.status==403,'cross-institute admission PDF denied')
        other.get('?page=notifications'); check(other.status==403 and 'student@example.test' not in other.html,'counsellor cannot view notification management')
        check('permission' in other.post('payment',**payment),'counsellor cannot record payments')
        other.get('?page=documents'); check('New Student' not in other.html,'documents list enforces institute scope')
        unthrottle(); ownCounsellor=Browser(base); ownCounsellor.post('request_otp',email='counsellor1@example.test'); ownCounsellor.post('verify_otp',code=last_code('counsellor1@example.test'))
        ownCounsellor.get(f'document.php?id={pdoc}'); check(ownCounsellor.status==403,'counsellor cannot download own-institute financial PDFs')
        unthrottle(); disabled=Browser(base); disabled.post('request_otp',email='counsellor1@example.test'); pending_code=last_code('counsellor1@example.test')
        owner.post('staff_toggle',page='staff',id=ac)
        check('Invalid, expired' in disabled.post('verify_otp',code=pending_code),'disabled user cannot redeem previously issued OTP')
        write_config(environment='production'); unthrottle()
        check('not configured' in stranger.post('request_otp',email='owner@example.test'),'mail capture cannot enable OTP login in production')
        write_config()
        for page in ['payments','documents','notifications','settings']:
            owner.get('?page='+page); check(owner.status==200 and 'temporarily unavailable' not in owner.html,page+' page renders')
        print(f'\n{checks} communications checks passed. No real emails sent.')
        con.close()
    finally:
        server.terminate()
        try: server.wait(timeout=10)
        except subprocess.TimeoutExpired: server.kill(); server.wait()
        log.seek(0); text=log.read(); log.close()
        if 'Fatal error' in text or 'Warning:' in text: print(text); raise AssertionError('PHP warnings/errors')
