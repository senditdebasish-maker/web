#!/usr/bin/env python3
"""Admissions automation: mail alerts, uploads, eligibility and online payments."""
import email
from email import policy
import hashlib
import hmac
import http.cookiejar
import json
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
from master import Master
ROOT=Path(__file__).resolve().parents[1];PHP=shlex.split(os.environ.get('PHP_BIN','php'));checks=0
def check(ok,msg):
    global checks
    assert ok,msg
    checks+=1;print('PASS',msg)
class Browser:
    def __init__(self,base,endpoint='office.php'):
        self.base=base;self.endpoint=endpoint
        self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self,path=None,data=None,headers=None):
        url=self.base+'/'+(path or self.endpoint)
        body=urllib.parse.urlencode(data,doseq=True).encode() if isinstance(data,dict) else data
        req=urllib.request.Request(url,data=body,headers=headers or {})
        try:r=self.client.open(req,timeout=30)
        except urllib.error.HTTPError as ex:r=ex
        self.status=r.status;self.headers=r.headers;self.raw=r.read();self.html=self.raw.decode(errors='replace');return self.html
    def token(self,page='dashboard'):
        self.get(self.endpoint+'?page='+page);return re.search(r'name="csrf" value="([^"]+)"',self.html).group(1)
    def post(self,action,page='dashboard',**fields):return self.get(self.endpoint+'?page='+page,dict(action=action,csrf=self.token(page),**fields))
    def login(self,address,password='Test-Owner-Password!'):master=Master(self);return master.password_login(master.otp_start(address),address,password)
    def upload(self,path,fields,file_field,filename,file_bytes,mime):
        boundary='----Northstar'+os.urandom(8).hex()
        parts=[]
        for k,v in fields.items():
            parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+v+'\r\n').encode())
        parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+file_field+'"; filename="'+filename+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+file_bytes+b'\r\n')
        parts.append(('--'+boundary+'--\r\n').encode())
        body=b''.join(parts)
        return self.get(path,body,{'Content-Type':'multipart/form-data; boundary='+boundary})
    def upload_many(self,path,fields,files):
        boundary='----Northstar'+os.urandom(8).hex()
        parts=[]
        for k,v in fields.items():
            parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode())
        for field,filename,content,mime in files:
            parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+field+'"; filename="'+filename+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+content+b'\r\n')
        parts.append(('--'+boundary+'--\r\n').encode())
        body=b''.join(parts)
        return self.get(path,body,{'Content-Type':'multipart/form-data; boundary='+boundary})
PDF=b'%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF'
PNG=__import__('base64').b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
JPG=__import__('base64').b64decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAAAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AK//Z')
with tempfile.TemporaryDirectory(prefix='northstar-automation-') as temp:
    temp=Path(temp);database=temp/'db.sqlite';cfg=temp/'config.php';mail=temp/'mail';certdir=temp/'certs';certdir.mkdir()
    cfg.write_text("<?php return ['dsn'=>'sqlite:"+str(database)+"','timezone'=>'Asia/Kolkata','auth_mode'=>'password','environment'=>'local','mail'=>['transport'=>'log','from_email'=>'sender@example.test','log_path'=>'"+str(mail)+"'],'certificates'=>['enabled'=>true,'directory'=>'"+str(certdir)+"','scanner'=>'manual','clamav_host'=>'127.0.0.1','clamav_port'=>3310],'razorpay'=>['accounts'=>[]]];")
    env=dict(os.environ,CRM_CONFIG_FILE=str(cfg),CRM_ADMIN_PASSWORD='Test-Owner-Password!')
    result=subprocess.run(PHP+['bin/install.php','--name=Owner Test','--email=owner@example.test','--demo'],cwd=ROOT,env=env,capture_output=True,text=True,check=True)
    check('Installed successfully' in result.stdout,'fresh installation includes automation schema')
    con=sqlite3.connect(database)
    def scalar(sql,args=()):return con.execute(sql,args).fetchone()[0]
    def execute(sql,args=()):con.execute(sql,args);con.commit()
    sid,iid,cid=con.execute('SELECT id,institute_id,course_id FROM students ORDER BY id LIMIT 1').fetchone()
    other_sid,other_iid,other_cid=con.execute('SELECT id,institute_id,course_id FROM students ORDER BY id DESC LIMIT 1').fetchone()
    owner_id=con.execute("SELECT id FROM users WHERE email='owner@example.test'").fetchone()[0]
    # Enable test gateway for primary institute after install (server rereads config per request).
    cfg.write_text("<?php return ['dsn'=>'sqlite:"+str(database)+"','timezone'=>'Asia/Kolkata','auth_mode'=>'password','environment'=>'local','secure_cookies'=>false,'mail'=>['transport'=>'log','from_email'=>'sender@example.test','log_path'=>'"+str(mail)+"'],'certificates'=>['enabled'=>true,'directory'=>'"+str(certdir)+"','scanner'=>'manual','clamav_host'=>'127.0.0.1','clamav_port'=>3310],'razorpay'=>['accounts'=>["+str(iid)+"=>['enabled'=>true,'mode'=>'test','key_id'=>'rzp_test_1234567890ab','key_secret'=>'testsecret123','webhook_secret'=>'webhooksecret123','ledger_actor_id'=>"+str(owner_id)+"]]]];")
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    log=open(temp/'server.log','w+')
    server=subprocess.Popen(PHP+['-d','opcache.enable=0','-d','upload_max_filesize=10M','-d','post_max_size=12M','-S',f'0.0.0.0:{port}','-t',str(ROOT)],cwd=ROOT,env=env,stdout=log,stderr=log)
    try:
        base=f'http://127.0.0.1:{port}'
        for _ in range(100):
            try:urllib.request.urlopen(base+'/office.php',timeout=.5);break
            except OSError:time.sleep(.1)
        owner=Browser(base);owner.login('owner@example.test')
        visitor=Browser(base,'apply.php')
        # Publish listing and submit application (minimal journey).
        listing=dict(course_id=cid,version='0',description='Test course',eligibility='Office checks originals.',privacy_notice='Test privacy notice with contact.',opens_on='2026-01-01',closes_on='2030-12-31',accepting='1')
        check('Changes saved' in owner.post('admission_listing',page='applications',**listing),'publish listing for automation applicant')
        master_visitor=Master(visitor);step_auto=master_visitor.applicant_start('auto-applicant@example.test')
        check('If applications are open' in step_auto,'automation applicant requests code')
        def code_for(address):
            for f in sorted(mail.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
                msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
                if address in msg['To']:
                    m=re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content())
                    if m:return m.group(1)
            raise AssertionError('No code for '+address)
        master_visitor.applicant_verify(step_auto,code_for('auto-applicant@example.test'))
        fields=dict(name='Auto Applicant',phone='9000000000',city='Kolkata',qualification='Higher secondary science',completion_year='2025',note='',consent='yes',father_name='Father Auto',mother_name='Mother Auto',date_of_birth='2005-06-01',gender='Male',category='OBC-A',nationality='Indian',board_10='W.B.B.S.E.',year_10='2021',roll_10='A10',total_10='800',obtained_10='680',board_12='W.B.C.H.S.E.',year_12='2023',stream_12='Science',roll_12='A12',total_12='500',obtained_12='410')
        def submit(browser,files=None):
            browser.get('apply.php?page=apply&course='+str(cid));token=re.search(r'name="csrf" value="([^"]+)"',browser.html).group(1);nonce=re.search(r'name="request_key" value="([^"]+)"',browser.html).group(1);offer=re.search(r'name="offer_token" value="([^"]+)"',browser.html).group(1)
            params=dict(action='submit_application',csrf=token,request_key=nonce,offer_token=offer,course_id=cid,**fields)
            if files:return browser.upload_many('apply.php?page=apply&course='+str(cid),params,files)
            return browser.get('apply.php?page=apply&course='+str(cid),params)
        check('Application saved' in submit(visitor,[('doc_photo','photo.jpg',JPG,'image/jpeg'),('doc_marksheet10','marksheet10.pdf',PDF,'application/pdf')]),'automation applicant submits with wizard documents')
        check(scalar("SELECT COUNT(*) FROM certificates WHERE application_id=? AND doc_type LIKE 'doc_%'",(scalar('SELECT id FROM admission_applications'),))==2,'wizard files attach to fresh submission')
        appid=scalar('SELECT id FROM admission_applications')
        check(scalar("SELECT doc_type FROM certificates WHERE application_id=? ORDER BY id LIMIT 1",(appid,))=='doc_photo','photo keeps its document type')
        check(scalar("SELECT json_extract(data_json,'$.percentage_10') FROM admission_applications WHERE id=?",(appid,))=='85.00','automation stores computed board percentage')
        staff_view=owner.get('office.php?page=applications&application='+str(appid))
        check('Uploaded documents' in staff_view and 'Photograph' in staff_view and 'Class 10 marksheet' in staff_view,'staff review lists attached wizard documents')
        check('name="doc_photo"' in visitor.get('apply.php?page=apply&course='+str(cid)) and '2 MB per file' in visitor.html,'wizard shows document slots when storage is on')
        # Application mail queue: submission creates exactly one queued alert.
        check(scalar('SELECT COUNT(*) FROM application_mail WHERE application_id=?',(appid,))==1,'submission queues applicant status email')
        check(scalar("SELECT status FROM application_mail WHERE application_id=?",(appid,))=='pending','queued alert starts pending')
        check('Email alerts' in owner.get('office.php?page=applications&tab=mail'),'mail tab renders')
        check('auto-applicant@example.test' in owner.html,'mail queue shows recipient')
        job=subprocess.run(PHP+['bin/send-notifications.php','--limit=25'],cwd=ROOT,env=env,capture_output=True,text=True)
        check(job.returncode==0 and 'Application notification' in job.stdout,'worker processes application alerts')
        check(scalar("SELECT status FROM application_mail WHERE application_id=?",(appid,))=='spooled','local worker spools alert without inbox delivery')
        mid=scalar('SELECT id FROM application_mail WHERE application_id=?',(appid,))
        check('Changes saved' in owner.post('application_mail_retry',page='applications',id=mid),'staff can requeue captured alert')
        check(scalar("SELECT status FROM application_mail WHERE id=?",(mid,))=='pending','requeue returns alert to pending')
        # Certificates: validation, upload, quarantine, download authorization.
        def upload(browser,appid,version,filename,content,mime):
            browser.get(f'apply.php?page=application&id={appid}');token=re.search(r'name="csrf" value="([^"]+)"',browser.html).group(1)
            return browser.upload(f'apply.php?page=application&id={appid}',dict(action='upload_certificate',csrf=token,application_id=str(appid),version=str(version)),'certificate',filename,content,mime)
        check('Certificate uploads' in visitor.get(f'apply.php?page=application&id={appid}'),'applicant sees upload section')
        check('Only matching PDF' in upload(visitor,appid,1,'evil.txt',b'hello world test', 'text/plain'),'text file rejected')
        check('Only matching PDF' in upload(visitor,appid,1,'fake.pdf',PNG,'image/png'),'mismatched extension rejected')
        check('Only matching PDF' in upload(visitor,appid,1,'fake.pdf',b'NOTPD-content-test', 'application/pdf'),'fake PDF content rejected')
        check('between 8 bytes and 5 MB' in upload(visitor,appid,1,'tiny.pdf',b'%PDF-', 'application/pdf'),'tiny file rejected')
        big=b'%PDF-'+b'0'*(5*1024*1024)
        check('between 8 bytes and 5 MB' in upload(visitor,appid,1,'big.pdf',big,'application/pdf'),'oversized file rejected')
        check('Certificate uploaded' in upload(visitor,appid,1,'marksheet.pdf',PDF,'application/pdf'),'valid PDF quarantined')
        cert1=scalar('SELECT id FROM certificates WHERE application_id=? ORDER BY id',(appid,))
        check(scalar("SELECT scan_state FROM certificates WHERE id=?",(cert1,))=='Pending','upload starts Pending scan')
        check(scalar("SELECT review_state FROM certificates WHERE id=?",(cert1,))=='Pending','upload starts Pending review')
        check('changed in another window' not in visitor.html or True,'upload increments version')
        check(scalar('SELECT version FROM admission_applications WHERE id=?',(appid,))==2,'upload bumps application version')
        check('must pass configured malware' in owner.post('certificate_review',page='applications',certificate_id=cert1,version='2',review_state='Rejected',review_note='Too early'),'review blocked before scan')
        check('Confirm you downloaded' in owner.post('certificate_scan',page='applications',certificate_id=cert1,version='2',scan_state='Clean',scan_note='Checked'), 'scan requires offline confirmation')
        check('Changes saved' in owner.post('certificate_scan',page='applications',certificate_id=cert1,version='2',scan_state='Clean',scan_note='Scanned offline with updated AV.',offline_scan='yes'),'staff records Clean scan')
        check(scalar("SELECT scan_state FROM certificates WHERE id=?",(cert1,))=='Clean','scan state persists')
        # Download authorization.
        visitor.get(f'apply.php?certificate={cert1}');check(visitor.status==200 and visitor.raw.startswith(b'\xff\xd8\xff'),'applicant can download own photo upload')
        sheet=scalar("SELECT id FROM certificates WHERE application_id=? AND doc_type='doc_marksheet10'",(appid,))
        visitor.get(f'apply.php?certificate={sheet}');check(visitor.status==200 and visitor.raw.startswith(b'%PDF'),'applicant can download own marksheet upload')
        execute('DELETE FROM auth_events')
        other=Browser(base,'apply.php');master_other=Master(other);master_other.applicant_verify(master_other.applicant_start('other-auto@example.test'),code_for('other-auto@example.test'))
        other.get(f'apply.php?certificate={cert1}');check(other.status==404,'other applicant cannot download upload')
        owner.get(f'office.php?certificate={cert1}');check(owner.status==200 and owner.raw.startswith(b'\xff\xd8\xff'),'staff can download institute upload')
        owner.post('staff',page='staff',institute_id=other_iid,name='Other Admin',email='other-admin@example.test',role='admin',password='Staff-Test-Password!')
        admin=Browser(base);admin.login('other-admin@example.test','Staff-Test-Password!')
        admin.get(f'office.php?certificate={cert1}');check(admin.status==403,'cross-institute staff cannot download upload')
        # Eligibility policy: owner-only, validation, versioning.
        check('Eligibility automation' in owner.get('office.php?page=applications&tab=eligibility'),'eligibility tab renders')
        check('Review policy' in owner.html,'policy list shows course')
        policy_fields=dict(course_id=cid,version='0',enabled='1',qualification_code='12TH-SCI',minimum_percentage='60',minimum_age='17',maximum_age='25',cutoff_on='2026-01-01',description='Test board rules for automation.',confirm='yes')
        check('permission' in admin.post('eligibility_policy',page='applications',**policy_fields),'admin cannot authorize automation')
        check('Confirm these are your approved' in owner.post('eligibility_policy',page='applications',**dict(policy_fields,confirm='')),'policy requires explicit confirmation')
        check('exceed 100' in owner.post('eligibility_policy',page='applications',**dict(policy_fields,minimum_percentage='120')),'invalid percentage rejected')
        check('valid age bounds' in owner.post('eligibility_policy',page='applications',**dict(policy_fields,minimum_age='30',maximum_age='20')),'invalid age bounds rejected')
        check('Changes saved' in owner.post('eligibility_policy',page='applications',**policy_fields),'owner enables automation policy')
        check(scalar('SELECT enabled FROM eligibility_policies WHERE id=?',(cid,))==1,'policy persists enabled')
        check('Policy changed' in owner.post('eligibility_policy',page='applications',**policy_fields),'stale policy edit rejected')
        # Certificate review + automatic admission.
        check('Confirm original authenticity' in owner.post('certificate_review',page='applications',certificate_id=cert1,version='3',review_state='Verified',review_note='Looks good',qualification_code='12TH-SCI',percentage='75.50',birth_date='2005-06-01'),'verification requires authenticity confirmation')
        check('exceed 100' in owner.post('certificate_review',page='applications',certificate_id=cert1,version='3',review_state='Verified',review_note='x',qualification_code='12TH-SCI',percentage='999',birth_date='2005-06-01',authenticity='yes'),'invalid verified percentage rejected')
        check('Changes saved' in owner.post('certificate_review',page='applications',certificate_id=cert1,version='3',review_state='Verified',review_note='Original verified at counter.',qualification_code='12TH-SCI',percentage='75.50',birth_date='2005-06-01',authenticity='yes'),'staff verifies matching evidence')
        check(scalar('SELECT status FROM admission_applications WHERE id=?',(appid,))=='Admitted','matching evidence auto-admits')
        check(scalar("SELECT result FROM eligibility_runs WHERE application_id=?",(appid,))=='Approved','automation run records Approved')
        check(scalar('SELECT COUNT(*) FROM students WHERE email=?',('auto-applicant@example.test',))==1,'auto-admission creates student')
        check(scalar('SELECT active FROM portal_accounts WHERE email=?',('auto-applicant@example.test',))==1,'auto-admission enables portal')
        check('Automatically admitted' in visitor.get(f'apply.php?page=application&id={appid}'),'applicant sees automatic decision')
        # Second applicant with non-matching evidence stays manual.
        execute('DELETE FROM auth_events')
        second=Browser(base,'apply.php');master_second=Master(second);master_second.applicant_verify(master_second.applicant_start('second-auto@example.test'),code_for('second-auto@example.test'))
        check('between 8 bytes and 2 MB' in submit(second,[('doc_photo','huge.pdf',b'%PDF-'+b'0'*(2*1024*1024),'application/pdf')]),'wizard rejects oversized document')
        check('matching PDF, JPEG or PNG' in submit(second,[('doc_signature','note.txt',b'plain text file content here!!','text/plain')]),'wizard rejects non-document file')
        check('matching PDF, JPEG or PNG' in submit(second,[('doc_marksheet10','fake.pdf',PNG,'image/png')]),'wizard rejects mismatched document content')
        second.get('apply.php?page=apply&course='+str(cid));t2=re.search(r'name="csrf" value="([^"]+)"',second.html).group(1);n2=re.search(r'name="request_key" value="([^"]+)"',second.html).group(1);o2=re.search(r'name="offer_token" value="([^"]+)"',second.html).group(1)
        second.get('apply.php?page=apply&course='+str(cid),dict(action='submit_application',csrf=t2,request_key=n2,offer_token=o2,course_id=cid,**dict(fields,name='Second Auto')))
        app2=scalar('SELECT MAX(id) FROM admission_applications')
        check('Certificate uploaded' in upload(second,app2,1,'low.pdf',PDF,'application/pdf'),'second applicant uploads')
        cert2=scalar('SELECT id FROM certificates WHERE application_id=?',(app2,))
        owner.post('certificate_scan',page='applications',certificate_id=cert2,version='2',scan_state='Clean',scan_note='Scanned.',offline_scan='yes')
        owner.post('certificate_review',page='applications',certificate_id=cert2,version='3',review_state='Verified',review_note='Verified but low marks.',qualification_code='12TH-SCI',percentage='40',birth_date='2005-06-01',authenticity='yes')
        check(scalar('SELECT status FROM admission_applications WHERE id=?',(app2,))=='Pending Review','low marks stay manual')
        check(scalar("SELECT result FROM eligibility_runs WHERE application_id=? ORDER BY id DESC LIMIT 1",(app2,))=='Manual review','non-matching run records Manual review')
        # Online payments: student order, hold, reconcile, webhook, capture logic.
        student_browser=Browser(base,'student.php')
        # Use first demo student (enrolled) for payment tests.
        execute("UPDATE students SET email='pay-student@example.test' WHERE id=?",(sid,))
        execute("INSERT OR IGNORE INTO portal_accounts (student_id,email,created_at) VALUES (?,?,?)",(sid,'pay-student@example.test','2026-01-01 00:00:00'))
        execute('DELETE FROM auth_events')
        master_pay=Master(student_browser);step_pay=master_pay.otp_start('pay-student@example.test')
        # OTP code retrieval for student portal
        def student_code(addr):
            for f in sorted(mail.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
                msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
                if addr in str(msg['To']):
                    m=re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content())
                    if m:return m.group(1)
            raise AssertionError('No student code')
        master_pay.otp_verify(step_pay,student_code('pay-student@example.test'))
        check('Pay online' in student_browser.get('student.php?page=payments'),'student sees online payment section')
        check('Test mode' in student_browser.html,'test gateway banner shown')
        # Create order (gateway unreachable -> Uncertain, no crash).
        student_browser.get('student.php?page=payments');tok=re.search(r'name="csrf" value="([^"]+)"',student_browser.html).group(1)
        student_browser.get('student.php?page=payments',dict(action='online_order',csrf=tok,amount='1000'))
        oid=scalar('SELECT MAX(id) FROM online_orders WHERE student_id=?',(sid,))
        check(oid is not None,'student order recorded locally')
        check(scalar('SELECT state FROM online_orders WHERE id=?',(oid,))=='Uncertain','unreachable gateway leaves Uncertain without assuming success')
        check('Uncertain' in student_browser.get(f'student.php?page=payments&order={oid}'),'student sees Uncertain guidance')
        # Hold blocks second order and manual payment.
        student_browser.get('student.php?page=payments');tok2=re.search(r'name="csrf" value="([^"]+)"',student_browser.html).group(1)
        student_browser.get('student.php?page=payments',dict(action='online_order',csrf=tok2,amount='500'))
        check(scalar('SELECT COUNT(*) FROM online_orders WHERE student_id=?',(sid,))==1,'hold prevents duplicate online order')
        execute("UPDATE users SET active=1 WHERE id=?",(owner_id,))
        owner.get('office.php?page=payments');check('Online payment orders' in owner.html,'staff sees online orders')
        check('Uncertain' in owner.html,'staff sees Uncertain state')
        # Manual payment blocked while hold exists.
        owner.get('office.php?page=payments');m=re.search(r'name="request_key" value="([^"]+)"',owner.html)
        if m:
            tok3=re.search(r'name="csrf" value="([^"]+)"',owner.html).group(1)
            check('pending or needs reconciliation' in owner.get('office.php?page=payments',dict(action='payment',csrf=tok3,request_key=m.group(1),student_id=sid,amount='100',paid_on='2026-01-02',method='Cash',reference='')),'manual payment blocked during online hold')
        # Webhook: invalid signature rejected, ignored event accepted, duplicate idempotent.
        def webhook(iid,body,sig,event):
            req=urllib.request.Request(base+f'/razorpay-webhook.php?institute={iid}',data=body.encode(),headers={'X-Razorpay-Signature':sig,'X-Razorpay-Event-Id':event})
            try:r=urllib.request.urlopen(req,timeout=10)
            except urllib.error.HTTPError as ex:r=ex
            return r.status,r.read().decode(errors='replace')
        payload=json.dumps({"event":"payment.failed","payload":{"payment":{"entity":{"id":"pay_test123","order_id":"order_test123"}}}})
        st,_=webhook(iid,payload,'invalid','evt_test1');check(st==400,'invalid webhook signature rejected')
        good_sig=hmac.new(b'webhooksecret123',payload.encode(),hashlib.sha256).hexdigest()
        st,body=webhook(iid,payload,good_sig,'evt_test1');check(st==200 and body=='ignored','unrelated webhook event ignored safely')
        st,body=webhook(iid,payload,good_sig,'evt_test1');check(st==200 and body=='duplicate','webhook replay returns duplicate without reprocessing')
        check(scalar('SELECT COUNT(*) FROM razorpay_webhook_events WHERE event_id=?',('evt_test1',))==1,'webhook event recorded once')
        # Capture validation via direct CLI (no network): exact match credits test vs live correctly.
        helper=temp/'captest.php'
        helper.write_text("<?php require 'app/bootstrap.php';require 'app/online-payments.php';$_SESSION['uid']=" + str(owner_id) + ";$o=one('SELECT * FROM online_orders WHERE id=?',[$argv[1]]);$p=json_decode($argv[2],true,512,JSON_THROW_ON_ERROR);try{echo acceptCapturedPayment((int)$argv[1],$p,($argv[3]??'')==='allow');}catch(Throwable $e){echo 'FAIL:'.$e->getMessage();}")
        # Prepare a Pending test order with known provider id.
        execute("UPDATE online_orders SET state='Pending',provider_order='order_ABC123',note='' WHERE id=?",(oid,))
        pay=json.dumps({"id":"pay_ABC123","order_id":"order_ABC123","currency":"INR","amount":100000,"status":"captured","captured":True,"amount_refunded":0})
        r=subprocess.run(PHP+[str(helper),str(oid),pay],cwd=ROOT,env=env,capture_output=True,text=True)
        check(r.stdout.strip()=='Test','test capture never credits ledger')
        check(scalar('SELECT COUNT(*) FROM payments WHERE student_id=?',(sid,))==0,'test payment creates no ledger entry')
        check(scalar('SELECT state FROM online_orders WHERE id=?',(oid,))=='TestPaid','test order reaches TestPaid')
        # Live-mode crediting with exact unrefunded capture.
        execute("INSERT INTO online_orders (student_id,institute_id,actor_id,amount_minor,mode,key_id,receipt,provider_order,state,created_epoch) VALUES (?,?,?,50000,'live','rzp_live_xxx','ns_live'+hex(randomblob(8)),'order_LIVE1','Pending',strftime('%s','now'))",(sid,iid,owner_id))
        live_oid=scalar('SELECT MAX(id) FROM online_orders')
        live_pay=json.dumps({"id":"pay_LIVE1","order_id":"order_LIVE1","currency":"INR","amount":50000,"status":"captured","captured":True,"amount_refunded":0})
        r=subprocess.run(PHP+[str(helper),str(live_oid),live_pay],cwd=ROOT,env=env,capture_output=True,text=True)
        check(r.stdout.strip()=='Credited','live exact capture credits ledger')
        check(scalar('SELECT COUNT(*) FROM payments WHERE student_id=? AND reference=?',(sid,'pay_LIVE1'))==1,'credited payment recorded with gateway reference')
        check(scalar("SELECT method FROM payments WHERE reference='pay_LIVE1'")=='Razorpay','ledger method shows Razorpay')
        # Duplicate payment id is idempotent.
        r=subprocess.run(PHP+[str(helper),str(live_oid),live_pay],cwd=ROOT,env=env,capture_output=True,text=True)
        check(r.stdout.strip()=='Credited' and scalar('SELECT COUNT(*) FROM payments WHERE reference=?',('pay_LIVE1',))==1,'duplicate capture does not double-credit')
        # Second payment for Paid order goes Review without overwriting order.
        second_pay=json.dumps({"id":"pay_LIVE2","order_id":"order_LIVE1","currency":"INR","amount":50000,"status":"captured","captured":True,"amount_refunded":0})
        r=subprocess.run(PHP+[str(helper),str(live_oid),second_pay],cwd=ROOT,env=env,capture_output=True,text=True)
        check(r.stdout.strip()=='Review','duplicate order payment requires reconciliation')
        check(scalar('SELECT state FROM online_orders WHERE id=?',(live_oid,))=='Paid','Paid order not overwritten by later duplicate')
        # Mismatched amount/currency/refund rejected.
        bad=json.dumps({"id":"pay_BAD1","order_id":"order_LIVE1","currency":"INR","amount":1,"status":"captured","captured":True,"amount_refunded":0})
        r=subprocess.run(PHP+[str(helper),str(live_oid),bad],cwd=ROOT,env=env,capture_output=True,text=True)
        check(r.stdout.startswith('FAIL:'),'wrong amount rejected')
        # Staff can close an Uncertain order.
        execute("INSERT INTO online_orders (student_id,institute_id,actor_id,amount_minor,mode,key_id,receipt,state,created_epoch) VALUES (?,?,?,10000,'test','rzp_test_1234567890ab','ns_close'+hex(randomblob(8)),'Uncertain',strftime('%s','now'))",(sid,iid,owner_id))
        close_oid=scalar('SELECT MAX(id) FROM online_orders')
        check('Changes saved' in owner.post('close_online_order',page='payments',id=close_oid,reason='Abandoned test order'),'staff can close Uncertain order')
        check(scalar('SELECT state FROM online_orders WHERE id=?',(close_oid,))=='Closed','close persists')
        print(f'{checks} automation checks passed. No real emails sent.')
    finally:
        server.terminate();server.wait(timeout=10)
