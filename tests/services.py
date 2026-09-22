#!/usr/bin/env python3
"""Student services and recovery checks using private SQLite fixtures."""
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
    def get(self,path=None,data=None):
        try:r=self.client.open(self.base+'/'+(path or self.endpoint),urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None,timeout=30)
        except urllib.error.HTTPError as ex:r=ex
        self.status=r.status;self.headers=r.headers;self.raw=r.read();self.html=self.raw.decode(errors='replace');return self.html
    def token(self,page='dashboard'):
        self.get(self.endpoint+'?page='+page);return re.search(r'name="csrf" value="([^"]+)"',self.html).group(1)
    def post(self,action,page='dashboard',**fields):return self.get(self.endpoint+'?page='+page,dict(action=action,csrf=self.token(page),**fields))
    def login(self,address,password='Test-Owner-Password!'):master=Master(self);return master.password_login(master.otp_start(address),address,password)

with tempfile.TemporaryDirectory(prefix='northstar-services-') as temp:
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
        # Verify upgrade from the previous operations release, with no new student data loss.
        for table in ['exam_revisions','exam_results','exams','announcement_revisions','announcements','support_messages','support_tickets','runtime_jobs']:
            execute('DROP TABLE '+table)
        owner.get('office.php?page=exams');check('Student services upgrade required' in owner.html,'old installations have safe services upgrade prompt')
        owner.get('upgrade.php');token=re.search(r'name="csrf" value="([^"]+)"',owner.html).group(1)
        check('Upgrade complete' in owner.get('upgrade.php',dict(csrf=token,backup='yes')),'owner upgrades services tables without reinstalling')
        check('Upgrade complete' in owner.get('upgrade.php',dict(csrf=token,backup='yes')),'services migration is repeatable')
        check(scalar('SELECT COUNT(*) FROM students')==2,'services migration preserves admissions')
        batches=[]
        for inst,course in [(iid,cid),(other_iid,other_cid)]:
            owner.post('teacher',page='teachers',institute_id=inst,name='Teacher '+str(inst),email='',phone='000',qualification='',active='1')
            teacher=scalar('SELECT id FROM teachers WHERE institute_id=?',(inst,))
            owner.post('batch',page='batches',institute_id=inst,course_id=course,teacher_id=teacher,name='Batch '+str(inst),room='Lab',starts_on='2026-01-01',ends_on='2030-12-31',capacity='30',active='1')
            batches.append(scalar('SELECT id FROM batches WHERE institute_id=?',(inst,)))
        bid,other_bid=batches
        for student_id,batch_id in [(sid,bid),(other_sid,other_bid)]:owner.post('enrollment',page='batches',student_id=student_id,batch_id=batch_id,starts_on='2026-01-02')
        eid=scalar("SELECT id FROM enquiries WHERE institute_id=? AND status='New' LIMIT 1",(iid,));owner.post('admit',page='admissions',enquiry_id=eid,admission_date='2026-01-01')
        peer=scalar('SELECT id FROM students WHERE enquiry_id=?',(eid,));owner.post('enrollment',page='batches',student_id=peer,batch_id=bid,starts_on='2026-01-02')
        exam=dict(batch_id=bid,title='Pharmacology <script>',held_on='2026-01-05',maximum='100',pass_mark='40')
        check('no later than today' in owner.post('exam_create',page='exams',**dict(exam,held_on='2030-01-01')),'future exam entry rejected')
        check('Maximum must be positive' in owner.post('exam_create',page='exams',**dict(exam,maximum='0')),'zero maximum rejected')
        check('pass marks cannot exceed' in owner.post('exam_create',page='exams',**dict(exam,pass_mark='101')),'invalid pass threshold rejected')
        check('Changes saved' in owner.post('exam_create',page='exams',**exam),'completed assessment created')
        xid=scalar('SELECT id FROM exams WHERE batch_id=?',(bid,))
        check(scalar('SELECT COUNT(*) FROM exam_results WHERE exam_id=?',(xid,))==2,'exam roster freezes eligible students')
        check('Grade the complete roster' in owner.post('exam_publish',page='exams',exam_id=xid,version='1',state='Published',reason='Publish'),'incomplete results cannot be published')
        grade=dict(exam_id=xid,version='1',reason='Initial verified marks',**{f'marks[{sid}]':'95.50',f'marks[{peer}]':'',f'result_status[{sid}]':'Present',f'result_status[{peer}]':'Absent'})
        check('exceeds this exam maximum' in owner.post('exam_grade',page='exams',**dict(grade,**{f'marks[{sid}]':'100.01'})),'out-of-range marks rejected')
        check('at most two decimal places' in owner.post('exam_grade',page='exams',**dict(grade,**{f'marks[{sid}]':'2e1'})),'scientific notation marks rejected')
        check('blank for absent' in owner.post('exam_grade',page='exams',**dict(grade,**{f'marks[{peer}]':'0'})),'absent students cannot receive a numeric mark')
        check('exactly the exam roster' in owner.post('exam_grade',page='exams',**dict(grade,**{f'marks[{other_sid}]':'1'})),'injected exam roster member rejected')
        check('Changes saved' in owner.post('exam_grade',page='exams',**grade),'full marks saved in draft')
        check(scalar('SELECT score_minor FROM exam_results WHERE exam_id=? AND student_id=?',(xid,sid))==9550,'marks stored as exact hundredths')
        check('another window' in owner.post('exam_grade',page='exams',**grade),'stale mark entry rejected')
        # Portal identity comes only from the student session, never a submitted student_id.
        owner.post('portal_access',page='students',student_id=sid,access='enable')
        student=Browser(base,'student.php');master_student=Master(student);step_student=master_student.otp_start('student@example.test')
        for f in sorted(mail.glob('*.eml'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
            msg=email.message_from_bytes(f.read_bytes(),policy=policy.default)
            if 'student@example.test' in msg['To']:
                code=re.search(r'code is: (\d{6})',msg.get_body(preferencelist=('plain',)).get_content()).group(1);break
        master_student.otp_verify(step_student,code)
        check('No published results yet' in student.get('student.php?page=results'),'draft marks hidden from student')
        check('Changes saved' in owner.post('exam_publish',page='exams',exam_id=xid,version='2',state='Published',reason='Approved by office'),'complete assessment published')
        check('95.50' in student.get('student.php?page=results') and 'Pharmacology &lt;script&gt;' in student.html,'student sees escaped own published marks')
        check('Absent' not in student.html,'same-institute peer result stays private')
        check('Withdraw published results' in owner.post('exam_grade',page='exams',**dict(grade,version='3')),'published marks cannot change silently')
        check('Changes saved' in owner.post('exam_publish',page='exams',exam_id=xid,version='3',state='Draft',reason='Review appeal'),'published assessment can be withdrawn with reason')
        check('No published results yet' in student.get('student.php?page=results'),'withdrawal removes marks from portal')
        check(scalar('SELECT COUNT(*) FROM exam_revisions WHERE exam_id=?',(xid,))==3,'grading and publication revisions retained')
        owner.post('exam_grade',page='exams',**dict(grade,version='4',reason='Appeal correction',**{f'marks[{sid}]':'98.25'}))
        owner.post('exam_publish',page='exams',exam_id=xid,version='5',state='Published',reason='Appeal approved')
        check('98.25' in student.get('student.php?page=results&student_id='+str(peer)),'URL student ID cannot switch result ownership')
        owner.post('exam_publish',page='exams',exam_id=xid,version='6',state='Draft',reason='Transaction test')
        execute("CREATE TRIGGER fail_exam_revision BEFORE INSERT ON exam_revisions BEGIN SELECT RAISE(ABORT,'test rollback'); END")
        check('could not be saved' in owner.post('exam_grade',page='exams',**dict(grade,version='7',reason='Must roll back')),'revision write failure produces safe grading error')
        check(scalar('SELECT score_minor FROM exam_results WHERE exam_id=? AND student_id=?',(xid,sid))==9825 and scalar('SELECT version FROM exams WHERE id=?',(xid,))==7,'failed grade revision rolls back marks and version together')
        execute('DROP TRIGGER fail_exam_revision')
        owner.post('exam_publish',page='exams',exam_id=xid,version='7',state='Published',reason='Transaction test completed')
        # Institute and current-batch notices, scheduling and optimistic edits.
        notice=dict(institute_id=iid,batch_id='0',title='Office hours <img>',body='Bring your original documents. <script>alert(1)</script>',starts_on='2026-01-01',ends_on='2030-12-31',state='Draft',reason='Initial notice')
        check('Changes saved' in owner.post('announcement',page='announcements',**notice),'draft announcement created')
        aid=scalar('SELECT id FROM announcements WHERE institute_id=?',(iid,))
        check('No current announcements' in student.get('student.php?page=announcements'),'draft notice hidden')
        check('Changes saved' in owner.post('announcement',page='announcements',id=aid,version='1',**dict(notice,state='Published')),'announcement published')
        check('Office hours &lt;img&gt;' in student.get('student.php?page=announcements') and '&lt;script&gt;' in student.html,'announcement body rendered as escaped plain text')
        check('changed or institute does not match' in owner.post('announcement',page='announcements',id=aid,version='1',**notice),'stale announcement edit rejected')
        owner.post('announcement',page='announcements',**dict(notice,institute_id=other_iid,title='Beta confidential',state='Published'))
        owner.post('announcement',page='announcements',**dict(notice,title='Future notice',starts_on='2030-01-01',state='Published'))
        owner.post('announcement',page='announcements',**dict(notice,title='Expired notice',ends_on='2026-01-02',state='Published'))
        owner.post('announcement',page='announcements',**dict(notice,title='Your batch briefing',batch_id=bid,state='Published'))
        student.get('student.php?page=announcements')
        check('Beta confidential' not in student.html and 'Future notice' not in student.html and 'Expired notice' not in student.html,'other institute, future and expired notices hidden')
        check('Your batch briefing' in student.html,'current-batch announcement visible')
        check('from this institute' in owner.post('announcement',page='announcements',**dict(notice,batch_id=other_bid)),'cross-institute notice targeting rejected')
        # Student support: anti-forgery, identity binding, idempotency and retention.
        def message(browser,action,detail='',**fields):
            page='support'+detail;browser.get(browser.endpoint+'?page='+page)
            nonce=re.search(r'name="request_key" value="([^"]+)"',browser.html).group(1)
            return browser.get(browser.endpoint+'?page='+page,dict(action=action,csrf=re.search(r'name="csrf" value="([^"]+)"',browser.html).group(1),request_key=nonce,**fields))
        student.get('student.php?page=support',dict(action='support_create',csrf='bad',subject='X',category='General',body='No'))
        check('form expired' in student.html,'student support requires CSRF')
        check('message was saved' in message(student,'support_create',subject='Correct my documents',category='Documents',body='Please review <script>my record</script>',student_id=other_sid),'student can open a support ticket')
        ticket=scalar('SELECT id FROM support_tickets');check(scalar('SELECT student_id FROM support_tickets WHERE id=?',(ticket,))==sid,'ticket student ID is session-bound')
        check('&lt;script&gt;my record&lt;/script&gt;' in student.get('student.php?page=support&ticket='+str(ticket)),'ticket text is escaped')
        duplicate=scalar('SELECT request_key FROM support_messages WHERE ticket_id=?',(ticket,))
        student.post('support_create',page='support',request_key=duplicate,subject='Duplicate',category='Documents',body='Repeated')
        check('already processed' in student.html and scalar('SELECT COUNT(*) FROM support_tickets')==1 and scalar('SELECT COUNT(*) FROM support_messages')==1,'duplicate ticket request creates no second message')
        check('Changes saved' in message(owner,'support_reply','&ticket='+str(ticket),ticket_id=ticket,version='2',body='We need your admission number.',status='Waiting'),'staff replies and updates ticket status')
        check('We need your admission number.' in student.get('student.php?page=support&ticket='+str(ticket)),'student receives staff reply in own conversation')
        check('another window' in message(student,'support_reply','&ticket='+str(ticket),ticket_id=ticket,version='2',body='Stale response'),'stale reply cannot overwrite latest ticket state')
        check('message was saved' in message(student,'support_reply','&ticket='+str(ticket),ticket_id=ticket,version='3',body='Here is my admission number.'),'student replies to waiting conversation')
        check(scalar('SELECT status FROM support_tickets WHERE id=?',(ticket,))=='Open','student reply returns ticket to open queue')
        message(owner,'support_reply','&ticket='+str(ticket),ticket_id=ticket,version='4',body='Corrected by office.',status='Resolved')
        student.post('support_reply',page='support',ticket_id=ticket,version='5',body='Should fail',request_key='not-a-nonce')
        check('Message form expired' in student.html,'fabricated support nonce rejected')
        student.get('student.php?page=support');nonce=re.search(r'name="request_key" value="([^"]+)"',student.html).group(1)
        check('resolved' in student.post('support_reply',page='support',ticket_id=ticket,version='5',body='Should fail',request_key=nonce),'students cannot reply to resolved tickets')
        message(owner,'support_reply','&ticket='+str(ticket),ticket_id=ticket,version='5',body='Reopening for follow-up',status='Open')
        check(scalar('SELECT COUNT(*) FROM support_messages WHERE ticket_id=?',(ticket,))==5,'ticket conversation retained without edits or deletion')
        # Same-institute foreign ticket and direct mutation attempts.
        execute("INSERT INTO support_tickets (institute_id,student_id,subject,category,created_at,updated_at) VALUES (?,?,'Peer confidential','General','2026-01-01 00:00:00','2026-01-01 00:00:00')",(iid,peer))
        peer_ticket=scalar('SELECT MAX(id) FROM support_tickets')
        student.get('student.php?page=support&ticket='+str(peer_ticket));check(student.status==403 and 'Peer confidential' not in student.html,'direct same-institute peer ticket access denied safely')
        student.post('support_reply',page='support',ticket_id=peer_ticket,version='1',body='Attack',request_key=nonce);check('Ticket not accessible' in student.html,'peer ticket reply rejected')
        check('cannot perform' in student.post('exam_publish',page='results',exam_id=xid,version='6',state='Draft',reason='Attack'),'student cannot publish or withdraw results')
        for n in range(4):message(student,'support_create',subject='Follow up '+str(n),category='General',body='A separate question')
        check('five active tickets' in message(student,'support_create',subject='Too many',category='General',body='Another'),'active ticket limit enforced')
        stamp=scalar('SELECT MAX(created_at) FROM support_messages')
        needed=30-scalar('SELECT COUNT(*) FROM support_messages WHERE student_id=?',(sid,))
        for n in range(needed):execute('INSERT INTO support_messages (ticket_id,student_id,body,request_key,created_at) VALUES (?,?,?,?,?)',(ticket,sid,'Rate fixture','rate-'+str(n),stamp))
        check('Message limit reached' in message(student,'support_reply','&ticket='+str(ticket),ticket_id=ticket,version='6',body='Over hourly limit'),'per-student hourly message limit enforced')
        for n in range(200):execute('INSERT INTO support_messages (ticket_id,student_id,body,request_key,created_at) VALUES (?,?,?,?,?)',(peer_ticket,peer,'Limit fixture','thread-limit-'+str(n),stamp))
        check('Conversation limit reached' in message(owner,'support_reply','&ticket='+str(peer_ticket),ticket_id=peer_ticket,version='1',body='Extra reply',status='Open'),'conversation cap prevents unbounded replies')
        check('Changes saved' in message(owner,'support_reply','&ticket='+str(peer_ticket),ticket_id=peer_ticket,version='1',body='Final resolution',status='Resolved'),'staff can close a full conversation without trapping the student')
        check(scalar('SELECT COUNT(*) FROM support_messages WHERE ticket_id=?',(peer_ticket,))==201,'only final closure note extends conversation limit')
        owner.post('staff',page='staff',institute_id=other_iid,name='Beta Admin',email='beta@example.test',role='admin',password='Staff-Test-Password!')
        admin=Browser(base);admin.login('beta@example.test','Staff-Test-Password!')
        for url in ['exams&exam='+str(xid),'announcements&edit='+str(aid),'support&ticket='+str(ticket)]:
            admin.get('office.php?page='+url);check(admin.status==403,'cross-institute '+url+' denied')
        check('not accessible' in admin.post('exam_grade',page='exams',**dict(grade,version='6')),'cross-institute grade write denied')
        check('not accessible' in admin.post('support_reply',page='support',ticket_id=ticket,version='6',body='Attack',status='Resolved',request_key='bad'),'cross-institute support write denied')
        owner.post('staff',page='staff',institute_id=iid,name='Counsellor',email='counsellor@example.test',role='counsellor',password='Staff-Test-Password!')
        counsellor=Browser(base);counsellor.login('counsellor@example.test','Staff-Test-Password!')
        for page in ['exams','announcements','support']:
            counsellor.get('office.php?page='+page);check(counsellor.status==403,'counsellor cannot read '+page)
            owner.get('office.php?page='+page);check(owner.status==200 and 'temporarily unavailable' not in owner.html,page+' staff screen renders')
        for url in ['exams&exam='+str(xid),'announcements&edit='+str(aid),'support&ticket='+str(ticket)]:
            owner.get('office.php?page='+url);check(owner.status==200 and 'temporarily unavailable' not in owner.html,url+' details render')
        # CLI diagnostics and a real SQLite backup restored into an isolated connection.
        job=subprocess.run(PHP+['bin/send-notifications.php','--limit=1'],cwd=ROOT,env=env,capture_output=True,text=True)
        check(job.returncode==0 and scalar("SELECT outcome FROM runtime_jobs WHERE job_key='notifications'")=='ok','worker records completed operational heartbeat')
        health=subprocess.run(PHP+['bin/health.php'],cwd=ROOT,env=env,capture_output=True,text=True)
        import json,hashlib
        report=json.loads(health.stdout)
        check(report['status']=='warn' and (health.returncode==1 or any('php-wasm' in x for x in PHP)) and any(x['check']=='Notification worker' and x['status']=='ok' for x in report['checks']),'health CLI reports local warnings and recent heartbeat')
        check(str(database) not in health.stdout and 'Test-Owner-Password' not in health.stdout,'health report excludes private paths and credentials')
        backups=temp/'backups';backups.mkdir()
        backup=subprocess.run(PHP+['bin/backup.php','--directory='+str(backups)],cwd=ROOT,env=env,capture_output=True,text=True)
        check(backup.returncode==0,'CLI creates consistent SQLite backup outside project')
        manifest=json.loads(next(backups.glob('*.json')).read_text());saved=backups/manifest['file']
        check(hashlib.sha256(saved.read_bytes()).hexdigest()==manifest['sha256'],'backup checksum matches actual bytes')
        restored=sqlite3.connect(saved)
        check(restored.execute('PRAGMA integrity_check').fetchone()[0]=='ok' and restored.execute('SELECT COUNT(*) FROM support_messages').fetchone()[0]==scalar('SELECT COUNT(*) FROM support_messages'),'isolated backup restore passes integrity and conversation count checks')
        check(restored.execute('SELECT score_minor FROM exam_results WHERE student_id=?',(sid,)).fetchone()[0]==9825,'isolated backup retains exact published marks')
        restored.close()
        unsafe=subprocess.run(PHP+['bin/backup.php','--directory='+str(ROOT/'storage')],cwd=ROOT,env=env,capture_output=True,text=True)
        check('Backups cannot be stored' in unsafe.stderr and (unsafe.returncode!=0 or any('php-wasm' in x for x in PHP)),'backup helper refuses application/web-tree destination')
        original=cfg.read_text();cfg.write_text(original.replace(str(database),str(temp/'missing'/'broken.sqlite')))
        try:
            failure=subprocess.run(PHP+['bin/backup.php','--directory='+str(backups)],cwd=ROOT,env=env,capture_output=True,text=True)
            check('Backup failed' in failure.stderr and not list(backups.glob('.northstar-*')),'failed backup removes temporary artifacts')
            badhealth=subprocess.run(PHP+['bin/health.php'],cwd=ROOT,env=env,capture_output=True,text=True)
            check(json.loads(badhealth.stdout)['status']=='fail' and str(temp) not in badhealth.stdout,'health database failure is machine-readable without leaking paths')
        finally:cfg.write_text(original)
        print(f'\n{checks} student services checks passed. No real emails sent.');con.close()
    finally:
        server.terminate()
        try:server.wait(timeout=10)
        except subprocess.TimeoutExpired:server.kill();server.wait()
        log.seek(0);text=log.read();log.close()
        if 'Fatal error' in text or 'Warning:' in text:print(text);raise AssertionError('PHP warnings/errors')
