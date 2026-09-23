<?php
declare(strict_types=1);
ini_set('display_errors','0');
require __DIR__.'/app/bootstrap.php';require_once __DIR__.'/app/operations.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_name('northstar_session');session_set_cookie_params(sessionCookieParams());session_start();
if(isset($_SESSION['last_seen'])&&time()-$_SESSION['last_seen']>1800){$_SESSION=[];session_regenerate_id(true);}
try {
    $u=opsGuard();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Use the report export form.');}
    if(!isset($_SESSION['csrf'])||!hash_equals($_SESSION['csrf'],input('csrf',128)))fail('Export form expired.');
    $_SESSION['last_seen']=time();$kind=choice('report',['fees','attendance']);$lines=[];
    if($kind==='fees'){
        $iid=$u['role']==='owner'?(int)input('institute_id'):(int)$u['institute_id'];if($iid)instituteAccess($iid);
        if($u['role']!=='owner' && (int)input('institute_id')!==$iid)fail('Institute not accessible.');
        $count=(int)query('SELECT COUNT(*) FROM students'.($iid?' WHERE institute_id=?':''),$iid?[$iid]:[])->fetchColumn();if($count>5000)fail('Export limited to 5000 students. Narrow the institute scope or arrange an administrator export.');
        $head=['Student ID','Student','Institute','Course','Email','Phone','Agreed fee INR','Paid INR','Unpaid INR','Overdue INR','Schedule'];
        $records=feeReportRows($iid,5001);if(count($records)>5000)fail('Export exceeds 5000 students. Narrow the institute scope.');
        foreach($records as $r)$lines[]=['ST-'.str_pad((string)$r['id'],5,'0',STR_PAD_LEFT),$r['name'],$r['institute_name'],$r['course_name'],$r['email'],$r['phone'],number_format($r['fee_minor']/100,2,'.',''),number_format($r['paid_minor']/100,2,'.',''),number_format(($r['fee_minor']-$r['paid_minor'])/100,2,'.',''),($r['plan_id']?number_format(max(0,$r['scheduled_before_today']-$r['paid_minor'])/100,2,'.',''):''),$r['plan_id']?'Scheduled':'No plan'];
        audit('fees_csv_exported','institutes',$iid);
    }else{
        $c=opsRecord('class_days',(int)input('class_id'));$head=['Class date','Student ID','Student','Attendance','Finalized'];
        foreach(rows('SELECT a.*,s.name,c.finalized FROM attendance a JOIN students s ON s.id=a.student_id JOIN class_days c ON c.id=a.class_id WHERE a.class_id=? ORDER BY s.name',[$c['id']]) as $r)$lines[]=[$c['held_on'],'ST-'.str_pad((string)$r['student_id'],5,'0',STR_PAD_LEFT),$r['name'],$r['status'],$r['finalized']?'Yes':'No'];
        audit('attendance_csv_exported','class_days',(int)$c['id']);
    }
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="northstar-'.$kind.'-'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$head,',','"','');
    foreach($lines as $line){$safe=array_map(static function($v){$v=(string)$v;return preg_match('/^[\s\p{Cf}\x00-\x1F]*[=+\-@]/u',$v)!==0?"'".$v:$v;},$line);fputcsv($out,$safe,',','"','');}fclose($out);
} catch(DomainException $e){http_response_code(403);echo e($e->getMessage());}
catch(Throwable $e){http_response_code(503);echo 'Report unavailable. Ask your administrator to check the upgrade.';}
