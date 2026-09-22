#!/usr/bin/env python3
"""Operations, fees, exports and session-revocation checks using private SQLite fixtures."""
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

with tempfile.TemporaryDirectory(prefix='northstar-ops-') as temp:
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
        for table in ['fee_plan_revisions','fee_installments','fee_plans','attendance_revisions','attendance','class_days','enrollments','batches','teachers','staff_security']:
            execute('DROP TABLE '+table)
        legacy=Browser(base);check('Hello, Owner' in legacy.login('owner@example.test'),'legacy owner can log in without staff security table')
        legacy.get('office.php?page=teachers');check('Operations upgrade required' in legacy.html,'pre-upgrade operations fail safely')
        legacy.get('upgrade.php');token=re.search(r'name="csrf" value="([^"]+)"',legacy.html).group(1)
        check('Upgrade complete' in legacy.get('upgrade.php',dict(csrf=token,backup='yes')),'browser upgrades a portal-only database to operations')
        check('Upgrade complete' in legacy.get('upgrade.php',dict(csrf=token,backup='yes')),'operations browser upgrade is repeatable')
        check(scalar('SELECT COUNT(*) FROM students')==2,'operations upgrade preserves student records')
        def teacher(i,name):return dict(institute_id=i,name=name,email='teacher@example.test',phone='9000000000',qualification='MSc',active='1')
        check('Changes saved' in owner.post('teacher',page='teachers',**teacher(iid,'Teacher Alpha')),'create teacher directory record')
        owner.post('teacher',page='teachers',**teacher(other_iid,'Teacher Beta'))
        tid=scalar('SELECT id FROM teachers WHERE institute_id=?',(iid,));otid=scalar('SELECT id FROM teachers WHERE institute_id=?',(other_iid,))
        def batch(name,i=iid,c=cid,t=tid,capacity=1):return dict(institute_id=i,course_id=c,teacher_id=t,name=name,room='Lab A',starts_on='2026-01-01',ends_on='2030-12-31',capacity=str(capacity),active='1')
        check('same institute' not in owner.post('batch',page='batches',**batch('Alpha A')) and 'Changes saved' in owner.html,'create active batch')
        bid=scalar("SELECT id FROM batches WHERE name='Alpha A'")
        check('active teacher from this institute' in owner.post('batch',page='batches',**batch('Cross teacher',t=otid)),'cross-institute teacher assignment rejected')
        check('between 1 and 500' in owner.post('batch',page='batches',**batch('Zero',capacity=0)),'invalid batch capacity rejected')
        owner.post('batch',page='batches',**batch('Alpha B'));dest=scalar("SELECT id FROM batches WHERE name='Alpha B'")
        owner.post('batch',page='batches',**batch('Beta Batch',other_iid,other_cid,otid));obid=scalar("SELECT id FROM batches WHERE name='Beta Batch'")
        check('own institute and course' in owner.post('enrollment',page='batches',student_id=sid,batch_id=obid,starts_on='2026-01-02'),'cross-institute enrollment rejected')
        check('Changes saved' in owner.post('enrollment',page='batches',student_id=sid,batch_id=bid,starts_on='2026-01-02'),'allocate student to matching course and batch')
        check('already allocated' in owner.post('enrollment',page='batches',student_id=sid,batch_id=bid,starts_on='2026-01-02'),'duplicate allocation rejected')
        eid=scalar("SELECT id FROM enquiries WHERE institute_id=? AND status='New' LIMIT 1",(iid,))
        owner.post('admit',page='admissions',enquiry_id=eid,admission_date='2026-01-01');third=scalar('SELECT id FROM students WHERE enquiry_id=?',(eid,))
        check('at capacity' in owner.post('enrollment',page='batches',student_id=third,batch_id=bid,starts_on='2026-01-02'),'batch capacity enforced')
        check('cannot change' in owner.post('batch',page='batches',id=bid,**dict(batch('Alpha A'),starts_on='2025-01-01')),'existing batch course and dates are immutable')
        check('No eligible students' in owner.post('class_day',page='attendance',batch_id=dest,held_on='2026-01-03',topic='Empty class'),'empty roster rejected')
        check('roster created' in owner.post('class_day',page='attendance',batch_id=bid,held_on='2026-01-03',topic='Anatomy practice'),'class creation freezes eligible roster')
        day=scalar('SELECT id FROM class_days WHERE batch_id=?',(bid,))
        check(scalar('SELECT COUNT(*) FROM attendance WHERE class_id=?',(day,))==1,'class contains exactly eligible student')
        check('already exists' in owner.post('class_day',page='attendance',batch_id=bid,held_on='2026-01-03',topic='Duplicate'),'duplicate batch/day prevented')
        check('not in the future' in owner.post('class_day',page='attendance',batch_id=bid,held_on='2030-01-01',topic='Future'),'future attendance prevented')
        check('existing attendance roster' in owner.post('enrollment',page='batches',student_id=sid,batch_id=dest,starts_on='2026-01-03'),'transfer cannot rewrite frozen roster history')
        check('exactly the students' in owner.post('attendance',page='attendance',class_id=day,version='1',reason='',**{f'attendance[{sid}]':'Present',f'attendance[{other_sid}]':'Absent'}),'injected roster member rejected')
        check('Choose Present' in owner.post('attendance',page='attendance',class_id=day,version='1',reason='',**{f'attendance[{sid}]':'Made up'}),'invalid attendance status rejected')
        check('Changes saved' in owner.post('attendance',page='attendance',class_id=day,version='1',reason='',**{f'attendance[{sid}]':'Present'}),'complete register is finalized')
        check('another window' in owner.post('attendance',page='attendance',class_id=day,version='1',reason='Old page',**{f'attendance[{sid}]':'Absent'}),'stale attendance edit rejected')
        check('valid reason' in owner.post('attendance',page='attendance',class_id=day,version='2',reason='',**{f'attendance[{sid}]':'Late'}),'attendance correction requires reason')
        check('Changes saved' in owner.post('attendance',page='attendance',class_id=day,version='2',reason='Arrival time checked',**{f'attendance[{sid}]':'Late'}),'audited attendance correction succeeds')
        check(scalar('SELECT COUNT(*) FROM attendance_revisions WHERE class_id=?',(day,))==2,'attendance revisions retained')
        check('Changes saved' in owner.post('enrollment',page='batches',student_id=sid,batch_id=dest,starts_on='2026-01-10'),'valid later transfer succeeds')
        check(scalar('SELECT ends_on FROM enrollments WHERE student_id=? AND batch_id=?',(sid,bid))=='2026-01-09','prior allocation closes without deletion')
        check(scalar('SELECT COUNT(*) FROM attendance WHERE student_id=?',(sid,))==1,'transfer retains historical attendance')
        plan=dict(student_id=sid,version='0',reason='Initial payment agreement',**{'due_on[]':['2026-01-10','2030-01-10'],'installment_amount[]':['1000.00','84000.00']})
        check('exactly equal' in owner.post('fee_plan',page='fee-plans',**dict(plan,**{'installment_amount[]':['999','84000']})),'fee plan must match full agreed fee')
        check('unique date' in owner.post('fee_plan',page='fee-plans',**dict(plan,**{'due_on[]':['2026-01-10','2026-01-10']})),'duplicate installment dates rejected')
        check('before admission' in owner.post('fee_plan',page='fee-plans',**dict(plan,**{'due_on[]':['2025-01-10','2030-01-10']})),'pre-admission installments rejected')
        check('Changes saved' in owner.post('fee_plan',page='fee-plans',**plan),'fee schedule saved with integer paise')
        pid=scalar('SELECT id FROM fee_plans WHERE student_id=?',(sid,))
        check(scalar('SELECT SUM(amount_minor) FROM fee_installments WHERE plan_id=?',(pid,))==8500000,'installment total matches agreed fee exactly')
        check('another window' in owner.post('fee_plan',page='fee-plans',**plan),'stale fee plan revision rejected')
        check('Changes saved' in owner.post('fee_plan',page='fee-plans',**dict(plan,version='1',reason='Reviewed with student')),'fee plan can be revised with reason')
        check(scalar('SELECT COUNT(*) FROM fee_plan_revisions WHERE plan_id=?',(pid,))==2,'fee schedule revisions retained')
        # Force a revision write failure to prove installment replacement is atomic.
        execute("CREATE TRIGGER fail_plan_revision BEFORE INSERT ON fee_plan_revisions BEGIN SELECT RAISE(ABORT,'test'); END")
        check('could not be saved' in owner.post('fee_plan',page='fee-plans',**dict(plan,version='2',reason='Must roll back',**{'installment_amount[]':['2000','83000']})),'fee plan failure produces safe error')
        check(scalar('SELECT amount_minor FROM fee_installments WHERE plan_id=? ORDER BY due_on LIMIT 1',(pid,))==100000,'failed revision rolls back replacement installments')
        execute('DROP TRIGGER fail_plan_revision')
        def pay(amount):
            owner.get('office.php?page=payments');nonce=re.search(r'name="request_key" value="([^"]+)"',owner.html).group(1)
            return owner.post('payment',page='payments',student_id=sid,request_key=nonce,amount=amount,paid_on='2026-01-05',method='Cash',reference='')
        check('Payment recorded' in pay('500'),'record partial installment coverage')
        check('₹500.00' in owner.get('office.php?page=fee-plans&student='+str(sid)) and 'Overdue' in owner.html,'schedule shows partial overdue amount')
        def export(browser,kind,**fields):return browser.get('export.php',dict(csrf=browser.token(),report=kind,**fields))
        csv=export(owner,'fees',institute_id=iid)
        check(owner.headers.get_content_type()=='text/csv' and '500.00,Overdue' not in csv and '500.00,Scheduled' in csv,'fee CSV computes partial overdue balance')
        check("'=2+3" in csv,'CSV prefixes spreadsheet formula-like names')
        check('Beta Secret Student' not in csv,'fee export obeys selected institute')
        check(any(r[-1]=='No plan' and r[-2]=='' for r in csvlib.reader(io.StringIO(csv))),'unscheduled CSV rows do not falsely claim zero overdue')
        execute('UPDATE students SET name=? WHERE id=?',('\ufeff=2+3',sid));csv=export(owner,'fees',institute_id=iid)
        check("'\ufeff=2+3" in csv,'CSV neutralizes invisible-prefix formulas')
        execute("UPDATE students SET name='=2+3' WHERE id=?",(sid,))
        check('Late' in export(owner,'attendance',class_id=day),'attendance CSV exports finalized status')
        owner.get('export.php');check(owner.status==405,'CSV export requires POST')
        owner.get('export.php',dict(csrf='bad',report='fees',institute_id=iid));check(owner.status==403,'CSV export requires CSRF')
        pay('700');csv=export(owner,'fees',institute_id=iid)
        check('1200.00,83800.00,0.00,Scheduled' in csv,'extra payments cover earliest dues without negative overdue')
        owner.post('enrollment',page='batches',student_id=other_sid,batch_id=obid,starts_on='2026-01-02')
        owner.post('class_day',page='attendance',batch_id=obid,held_on='2026-01-03',topic='Beta secret lesson')
        other_day=scalar('SELECT id FROM class_days WHERE batch_id=?',(obid,))
        owner.post('attendance',page='attendance',class_id=other_day,version='1',reason='',**{f'attendance[{other_sid}]':'Absent'})
        owner.post('enrollment',page='batches',student_id=third,batch_id=bid,starts_on='2026-01-11')
        owner.post('class_day',page='attendance',batch_id=bid,held_on='2026-01-12',topic='Private peer lesson')
        peer_day=scalar('SELECT id FROM class_days WHERE batch_id=? AND held_on=?',(bid,'2026-01-12'))
        owner.post('attendance',page='attendance',class_id=peer_day,version='1',reason='',**{f'attendance[{third}]':'Absent'})
        owner.post('class_day',page='attendance',batch_id=dest,held_on='2026-01-11',topic='Unpublished lesson')
        # Enable own student portal, which must only show the student's published records.
        owner.post('portal_access',page='students',student_id=sid,access='enable')
        student=Browser(base,'student.php');master_student=Master(student);execute('DELETE FROM auth_events');step_student=master_student.otp_start('student@example.test')
        for f in sorted(mail.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
            msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
            if 'student@example.test' in msg['To']:
                code=re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1);break
        master_student.otp_verify(step_student,code)
        check('Anatomy practice' in student.get('student.php?page=academics') and '100.0%' in student.html,'student sees own attendance history and defined attendance rate')
        check('Alpha B' in student.html and '2026-01-09' in student.html,'student sees current and historical batch allocation')
        check('Unpublished lesson' not in student.html,'draft classes excluded from student view')
        check('Private peer lesson' not in student.html and 'Beta secret lesson' not in student.html,'same-institute and other-institute attendance remain private')
        check('Beta secret lesson' not in student.get('student.php?page=academics&student_id='+str(other_sid)),'student cannot switch academic record with URL IDs')
        check('2030-01-10' in student.get('student.php?page=payments') and '₹83,800.00' in student.html,'student sees fee schedule and remaining installment')
        check('cannot perform' in student.post('attendance',class_id=day,version='3'),'student cannot edit attendance')
        student.get('export.php');check(student.status==403,'student cannot use staff CSV endpoint')
        owner.post('staff',page='staff',institute_id=other_iid,name='Beta Admin',email='beta@example.test',role='admin',password='Staff-Test-Password!')
        admin=Browser(base);admin.login('beta@example.test','Staff-Test-Password!');admin_id=scalar("SELECT id FROM users WHERE email='beta@example.test'")
        check('not accessible' in admin.post('teacher',page='teachers',id=tid,**teacher(iid,'Changed')),'cross-institute teacher edit denied')
        check('not accessible' in admin.post('attendance',page='attendance',class_id=day,version='3',reason='Attack',**{f'attendance[{sid}]':'Absent'}),'cross-institute attendance write denied')
        check('not accessible' in admin.post('fee_plan',page='fee-plans',**dict(plan,version='2')),'cross-institute schedule write denied')
        admin.get('office.php?page=attendance&class='+str(day));check(admin.status==403,'direct cross-institute class URL denied')
        admin.get('office.php?page=batches&roster='+str(bid));check(admin.status==403,'direct cross-institute roster URL denied')
        export(admin,'fees',institute_id=iid);check(admin.status==403,'cross-institute CSV scope manipulation denied')
        export(admin,'attendance',class_id=day);check(admin.status==403,'cross-institute class export denied')
        admin.get('office.php?page=health');check(admin.status==403,'deployment diagnostics owner-only')
        for page in ['teachers','batches','attendance','fee-plans','fee-reports','health']:
            owner.get('office.php?page='+page);check(owner.status==200 and 'temporarily unavailable' not in owner.html,page+' screen renders')
        for page in ['teachers&edit='+str(tid),'batches&edit='+str(bid),'batches&roster='+str(bid),'attendance&class='+str(day),'fee-plans&student='+str(sid)]:
            owner.get('office.php?page='+page);check(owner.status==200 and 'temporarily unavailable' not in owner.html,page+' detail screen renders')
        owner.post('staff',page='staff',institute_id=iid,name='Counsellor',email='counsellor@example.test',role='counsellor',password='Staff-Test-Password!')
        counsellor=Browser(base);counsellor.login('counsellor@example.test','Staff-Test-Password!')
        check('permission' in counsellor.post('teacher',page='enquiries',**teacher(iid,'Forbidden')),'counsellor cannot change teaching directory')
        counsellor.get('office.php?page=fee-reports');check(counsellor.status==403,'counsellor cannot read financial report')
        check('Changes saved' in owner.post('revoke_sessions',page='staff',user_id=admin_id),'owner can revoke another staff account sessions')
        check(MASTER_MARK in admin.get(),'revoked staff session immediately denied on next request')
        admin.login('beta@example.test','Staff-Test-Password!')
        owner.post('staff_toggle',page='staff',id=admin_id);owner.post('staff_toggle',page='staff',id=admin_id)
        check(MASTER_MARK in admin.get(),'disable then re-enable does not revive old staff session')
        check('Changes saved' in owner.post('password',page='settings',current_password='Test-Owner-Password!',password='New-Owner-Password!',confirm_password='New-Owner-Password!'),'password change keeps changing browser authenticated')
        check(MASTER_MARK in other_owner.get(),'password change revokes another device session')
        cfg.write_text(cfg.read_text().replace("'auth_mode'=>'password'","'auth_mode'=>'otp'"))
        pending=Browser(base);master_pending=Master(pending);execute('DELETE FROM auth_events');step_pending=master_pending.otp_start('owner@example.test')
        check('Check your inbox' in step_pending,'pending staff OTP can be requested before revocation')
        for f in sorted(mail.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
            msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
            if 'owner@example.test' in msg['To']:
                pending_code=re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1);break
        check(MASTER_MARK in owner.post('revoke_sessions',page='settings',user_id=scalar("SELECT id FROM users WHERE role='owner'")),'self revoke signs out all own staff sessions')
        check('Invalid, expired' in master_pending.otp_verify(step_pending,pending_code),'session revocation invalidates a pending staff OTP')
        print(f'\n{checks} operations checks passed. No real emails sent.');con.close()
    finally:
        server.terminate()
        try:server.wait(timeout=10)
        except subprocess.TimeoutExpired:server.kill();server.wait()
        log.seek(0);text=log.read();log.close()
        if 'Fatal error' in text or 'Warning:' in text:print(text);raise AssertionError('PHP warnings/errors')
