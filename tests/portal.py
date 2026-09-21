#!/usr/bin/env python3
"""Student portal HTTP tests. Disposable SQLite + private captured mail only."""
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
ROOT=Path(__file__).resolve().parents[1]
PHP=shlex.split(os.environ.get('PHP_BIN','php'));checks=0

def check(ok,message):
    global checks
    assert ok,message
    checks+=1;print('PASS',message)

class Browser:
    def __init__(self,base,endpoint='student.php'):
        self.base=base;self.endpoint=endpoint
        self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def get(self,path=None,data=None):
        try:r=self.client.open(self.base+'/'+(path or self.endpoint),urllib.parse.urlencode(data).encode() if data is not None else None,timeout=40)
        except urllib.error.HTTPError as e:r=e
        self.status=r.status;self.headers=r.headers;self.raw=r.read();self.html=self.raw.decode(errors='replace');return self.html
    def post(self,action,page='dashboard',**fields):
        path=self.endpoint+'?page='+page;self.get(path)
        csrf=re.search(r'name="csrf" value="([^"]+)"',self.html).group(1)
        return self.get(path,dict(action=action,csrf=csrf,**fields))

with tempfile.TemporaryDirectory(prefix='northstar-portal-') as tmp:
    tmp=Path(tmp);db=tmp/'db.sqlite';cfg=tmp/'config.php';capture=tmp/'mail'
    cfg.write_text("<?php return ['dsn'=>'sqlite:"+str(db)+"','timezone'=>'Asia/Kolkata','auth_mode'=>'otp','environment'=>'local','mail'=>['transport'=>'log','from_email'=>'sender@example.test','log_path'=>'"+str(capture)+"']];")
    env=dict(os.environ,CRM_CONFIG_FILE=str(cfg),CRM_ADMIN_PASSWORD='Test-Owner-Password!')
    result=subprocess.run(PHP+['bin/install.php','--name=Owner Test','--email=owner@example.test','--demo'],cwd=ROOT,env=env,text=True,capture_output=True,check=True)
    check('Installed successfully' in result.stdout,'fresh installer includes portal schema')
    con=sqlite3.connect(db)
    def scalar(sql,args=()):return con.execute(sql,args).fetchone()[0]
    def execute(sql,args=()):con.execute(sql,args);con.commit()
    def clear_limits():execute('DELETE FROM auth_events')
    def code_for(address):
        for f in sorted(capture.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
            msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
            if address in msg['To']:
                return re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1)
        raise AssertionError('No code for '+address)
    def login(browser,address):
        clear_limits();browser.post('request_otp',email=address);browser.post('verify_otp',code=code_for(address))
    students=con.execute('SELECT id,institute_id FROM students ORDER BY id').fetchall();sid,iid=students[0];other_sid,other_iid=students[1]
    execute("UPDATE students SET name='First Private Student',email='first@example.test',admission_date='2026-01-01' WHERE id=?",(sid,))
    execute("UPDATE students SET name='Other Secret Student',email='second@example.test',admission_date='2026-01-01' WHERE id=?",(other_sid,))
    # Emulate an existing installation before this phase.
    for table in ['portal_otp_challenges','portal_events','portal_accounts']:execute('DROP TABLE '+table)
    with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    log=open(tmp/'server.log','w+')
    server=subprocess.Popen(PHP+['-d','opcache.enable=0','-S',f'0.0.0.0:{port}','-t',str(ROOT)],cwd=ROOT,env=env,stdout=log,stderr=log)
    try:
        base=f'http://127.0.0.1:{port}'
        for _ in range(100):
            try:urllib.request.urlopen(base+'/office.php',timeout=.5);break
            except OSError:time.sleep(.1)
        owner=Browser(base,'office.php');anon=Browser(base);a=Browser(base);b=Browser(base)
        check('Portal not ready yet' in anon.get() and anon.status==503,'pre-upgrade portal fails safely')
        anon.get('upgrade.php');check(anon.status==403,'anonymous visitor cannot upgrade')
        login(owner,'owner@example.test');check('Hello, Owner' in owner.html,'existing owner can sign in before portal migration')
        owner.get('upgrade.php');check('upgrade required' in owner.html,'upgrade page is available to group owner')
        csrf=re.search(r'name="csrf" value="([^"]+)"',owner.html).group(1)
        check('form expired' in owner.get('upgrade.php',dict(csrf='bad',backup='yes')),'upgrade requires CSRF')
        check('Confirm' in owner.get('upgrade.php',dict(csrf=csrf)),'upgrade requires backup acknowledgement')
        check('Upgrade complete' in owner.get('upgrade.php',dict(csrf=csrf,backup='yes')),'owner can perform browser upgrade')
        check('Upgrade complete' in owner.get('upgrade.php',dict(csrf=csrf,backup='yes')),'browser migration is repeatable')
        check(scalar('SELECT COUNT(*) FROM students')==len(students),'upgrade preserves existing students')
        check(scalar('SELECT COUNT(*) FROM portal_accounts')==0,'upgrade does not auto-enable any student')
        check('Enable student access' in owner.get('office.php?page=students'),'staff student list exposes portal controls')
        clear_limits();before=len(list(capture.glob('*.eml')))
        check('active student portal account' in a.post('request_otp',email='first@example.test'),'not-enabled email gets generic response')
        check(len(list(capture.glob('*.eml')))==before,'not-enabled student receives no code')
        check('Invalid, expired' in a.post('verify_otp',code='000000'),'unprovisioned student cannot verify')
        check('access enabled' in owner.post('portal_access',page='students',student_id=sid,access='enable'),'owner explicitly enables student portal')
        check('access enabled' in owner.post('portal_access',page='students',student_id=other_sid,access='enable'),'owner enables second institute student')
        check('Invalid' not in owner.post('admission_letter',page='students',student_id=sid),'admission document can be prepared for portal')
        owner.post('admission_letter',page='students',student_id=other_sid)
        doc=scalar("SELECT id FROM documents WHERE student_id=? AND kind='admission'",(sid,));other_doc=scalar('SELECT id FROM documents WHERE student_id=?',(other_sid,))
        owner.get('office.php?page=payments');nonce=re.search(r'name="request_key" value="([^"]+)"',owner.html).group(1)
        owner.post('payment',page='payments',student_id=sid,request_key=nonce,amount='1234.50',paid_on='2026-01-01',method='Cash',reference='')
        receipt=scalar("SELECT id FROM documents WHERE student_id=? AND kind='payment'",(sid,))
        a.get();check('Staff login' in a.html and 'Password' not in a.html,'separate student login screen uses OTP only')
        check('expired' in a.get(data=dict(action='request_otp',csrf='bad',email='first@example.test')),'portal login requires CSRF')
        clear_limits();a.post('request_otp',email='first@example.test');code=code_for('first@example.test')
        check(code not in a.html,'student OTP never appears in browser response')
        check('60 seconds' in a.post('request_otp',email='first@example.test'),'student resend is throttled')
        check('Request a new' in b.post('verify_otp',code=code),'student code is bound to requesting browser')
        check('Invalid, expired' in a.post('verify_otp',code='000000'),'incorrect student OTP rejected')
        check('Hello, First' in a.post('verify_otp',code=code),'student signs in through shared PHPMailer transport')
        check('Request a new' in a.post('verify_otp',code=code),'used student code cannot be replayed')
        check('₹1,234.50' in a.get() and '₹83,765.50' in a.html,'dashboard calculates own payment and remaining balance')
        check('Other Secret Student' not in a.html,'dashboard contains no other student data')
        for page in ['payments','documents','profile']:
            a.get('student.php?page='+page)
            check(a.status==200 and 'Other Secret Student' not in a.html,'student '+page+' is scoped to self')
        check('Second' not in a.get('student.php?page=profile&student_id='+str(other_sid)) and 'First Private Student' in a.html,'URL student_id cannot change profile owner')
        a.get('student.php?page=unknown');check(a.status==404,'unknown student route returns 404')
        a.get('student.php?document='+str(doc));check(a.raw.startswith(b'%PDF-') and a.headers.get_content_type()=='application/pdf','student can download own admission PDF')
        a.get('student.php?document='+str(receipt));check(a.raw.startswith(b'%PDF-'),'student can download own payment receipt PDF')
        a.get('student.php?document='+str(other_doc));check(a.status==404 and not a.raw.startswith(b'%PDF-'),'other-institute PDF ID is rejected')
        # A document in the SAME institute is still private to its student.
        execute('INSERT INTO documents (institute_id,student_id,kind,event_key,payload_json,created_at) SELECT ?,student_id,kind,?,payload_json,created_at FROM documents WHERE id=?',(iid,'test-same-institute',other_doc))
        same_doc=scalar("SELECT id FROM documents WHERE event_key='test-same-institute'")
        a.get('student.php?document='+str(same_doc));check(a.status==404,'same-institute other-student PDF is rejected')
        anon.get('student.php?document='+str(doc));check(anon.status==401,'anonymous student document request denied')
        a.get('document.php?id='+str(receipt));check(a.status==401,'student session cannot use staff document endpoint')
        check('Your workspace awaits' in a.get('office.php'),'student session cannot authenticate to staff CRM')
        a.get('upgrade.php');check(a.status==403,'student session cannot run database upgrade')
        for action in ['payment','portal_access','student_email','admit','login']:
            check('cannot perform' in a.post(action,student_id=other_sid,amount='10',access='enable',email='evil@example.test',password='fake'),'student cannot invoke '+action)
        check(scalar("SELECT COUNT(*) FROM portal_events WHERE student_id=? AND action='document_download'",(sid,))==2,'successful own-document downloads are audited')
        login(b,'second@example.test');check('Hello, Other' in b.html and 'First Private Student' not in b.html,'second student gets separate workspace')
        b.post('logout');check('Your student space awaits' in b.html,'student logout removes access')
        b.get('student.php?document='+str(other_doc));check(b.status==401,'logged-out student cannot download')
        # Expiry and bounded attempts, then cross-audience checks.
        clear_limits();b.post('request_otp',email='second@example.test');expired=code_for('second@example.test')
        execute('UPDATE portal_otp_challenges SET expires_at=1')
        check('Invalid, expired' in b.post('verify_otp',code=expired),'expired portal OTP rejected')
        clear_limits();b.post('request_otp',email='second@example.test');exhausted=code_for('second@example.test')
        for _ in range(5):b.post('verify_otp',code='000000')
        check('Invalid, expired' in b.post('verify_otp',code=exhausted),'student OTP locks after five wrong attempts')
        staff=Browser(base,'office.php');clear_limits();staff.post('request_otp',email='owner@example.test');staff_code=code_for('owner@example.test')
        check('Request a new' in anon.post('verify_otp',code=staff_code),'staff OTP cannot authenticate a student session')
        clear_limits();b.post('request_otp',email='second@example.test');pending=code_for('second@example.test')
        owner.post('portal_access',page='students',student_id=other_sid,access='disable')
        owner.post('portal_access',page='students',student_id=other_sid,access='enable')
        check('Invalid, expired' in b.post('verify_otp',code=pending),'disable then re-enable still invalidates previously issued student OTP')
        owner.post('portal_access',page='students',student_id=sid,access='disable')
        check('Your student space awaits' in a.get(),'disabling portal immediately invalidates an existing session')
        owner.post('portal_access',page='students',student_id=sid,access='enable');login(a,'first@example.test')
        owner.post('student_email',page='students',student_id=sid,email='changed@example.test')
        check('Your student space awaits' in a.get(),'changing email invalidates existing student sessions')
        check(scalar('SELECT active FROM portal_accounts WHERE student_id=?',(sid,))==0,'email change requires explicit staff re-enable')
        owner.post('student_email',page='students',student_id=sid,email='second@example.test')
        check('reserved for another' in owner.post('portal_access',page='students',student_id=sid,access='enable'),'shared email across students cannot be enabled')
        owner.post('student_email',page='students',student_id=sid,email='changed@example.test');owner.post('portal_access',page='students',student_id=sid,access='enable')
        login(a,'changed@example.test');check('Hello, First' in a.html,'re-enabled student can sign in using verified new email')
        # Institute admin/counsellor authorization around the enabling action.
        owner.post('staff',page='staff',institute_id=other_iid,name='Beta Admin',email='beta@example.test',role='admin')
        admin=Browser(base,'office.php');login(admin,'beta@example.test')
        check('not accessible' in admin.post('portal_access',page='students',student_id=sid,access='disable'),'institute admin cannot change another institute portal access')
        admin.get('upgrade.php');check(admin.status==403,'institute admin cannot run owner-only upgrade')
        counsellor=Browser(base,'office.php');login(counsellor,'counsellor1@example.test')
        check('permission' in counsellor.post('portal_access',page='students',student_id=sid,access='disable'),'counsellor cannot enable or disable portal accounts')
        check('access disabled' in admin.post('portal_access',page='students',student_id=other_sid,access='disable'),'institute admin can disable own student portal')
        # Staff and student sessions can coexist but must remain separate.
        owner.endpoint='student.php';login(owner,'changed@example.test')
        check('Hello, First' in owner.html and 'Hello, Owner' in owner.get('office.php'),'student login does not overwrite staff session in same browser')
        owner.post('logout');check('Hello, Owner' in owner.get('office.php'),'student logout does not destroy separate staff session')
        print(f'\n{checks} student portal checks passed. No real emails sent.')
        con.close()
    finally:
        server.terminate()
        try:server.wait(timeout=10)
        except subprocess.TimeoutExpired:server.kill();server.wait()
        log.seek(0);out=log.read();log.close()
        if 'Fatal error' in out or 'Warning:' in out:print(out);raise AssertionError('PHP warnings/errors')
