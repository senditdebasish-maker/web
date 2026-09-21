<?php
declare(strict_types=1);
require_once __DIR__.'/site-chrome.php';
function icon(string $name): string {
    $paths=['dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>','institutes'=>'<path d="m3 9 9-6 9 6M5 10v10m7-10v10m7-10v10M3 21h18M2 9h20"/>','courses'=>'<path d="M12 6c-3-3-7-3-10-2v15c4-1 7-1 10 2 3-3 6-3 10-2V4c-3-1-7-1-10 2Zm0 0v15"/>','enquiries'=>'<path d="M21 11a8 8 0 0 1-8 8H5l-4 3V11a10 10 0 0 1 20 0ZM7 10h8M7 14h5"/>','followups'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','admissions'=>'<path d="M9 4H5v17h14V4h-4M9 2h6v5H9ZM8 14l3 3 5-6"/>','students'=>'<path d="m2 8 10-5 10 5-10 5-10-5Zm4 3v6c4 3 8 3 12 0v-6m4-3v8"/>','staff'=>'<circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 4a4 4 0 0 1 0 8m2 3c2 1 3 3 3 6"/>','settings'=>'<path d="M4 7h16M4 17h16M8 3v8m8 2v8"/>','audit'=>'<path d="M5 3h14v18H5zM8 7h8M8 11h8M8 15h5"/>'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['dashboard']).'</svg>';
}
function field(string $label,string $name,mixed $value='',string $type='text',bool $required=true): void {
    if ($_SERVER['REQUEST_METHOD']==='POST' && $type!=='password' && isset($_POST[$name]) && is_string($_POST[$name])) $value=$_POST[$name];
    $max=match($name){'name'=>120,'phone'=>30,'city'=>100,'kind'=>60,'duration'=>80,'address'=>300,'password','current_password','confirm_password'=>72,default=>200};
    echo '<label>'.e($label).'<input name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" '.($required?'required ':'').' maxlength="'.$max.'"'.($type==='password'?' autocomplete="new-password"':'').'></label>';
}
function selectField(string $label,string $name,array $options,mixed $value='',bool $required=true): void {
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST[$name]) && is_string($_POST[$name])) $value=$_POST[$name];
    echo '<label>'.e($label).'<select name="'.e($name).'" '.($required?'required':'').'>';
    echo '<option value="">Select '.e(strtolower($label)).'</option>';
    foreach($options as $key=>$text) echo '<option value="'.e($key).'" '.((string)$key===(string)$value?'selected':'').'>'.e($text).'</option>';
    echo '</select></label>';
}
function textArea(string $label,string $name,string $value='',bool $required=false): void { if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST[$name]) && is_string($_POST[$name])) $value=$_POST[$name]; echo '<label>'.e($label).'<textarea name="'.e($name).'" maxlength="3000" rows="4" '.($required?'required':'').'>'.e($value).'</textarea></label>'; }
function formStart(string $action,int $id=0): void { echo '<form method="post" class="stack">'.csrf().'<input type="hidden" name="action" value="'.e($action).'">'; if($id) echo '<input type="hidden" name="id" value="'.$id.'">'; }
function formEnd(string $label='Save changes'): void { echo '<button class="button" type="submit">'.e($label).' <span>→</span></button></form>'; }
function badge(string $text): string { return '<span class="badge '.e(strtolower(str_replace(' ','-',$text))).'">'.e($text).'</span>'; }
function money(int $minor): string { return '₹'.number_format($minor/100,2); }
function person(string $name,string $sub=''): string { return '<div class="person"><span class="avatar">'.e(strtoupper(substr($name,0,1))).'</span><div><strong>'.e($name).'</strong><small>'.e($sub).'</small></div></div>'; }
function emptyState(string $message): void { echo '<div class="empty"><span>◇</span><h3>Nothing here yet</h3><p>'.e($message).'</p></div>'; }
function table(array $heads, array $data, callable $render): void {
    if(!$data) { emptyState('Add your first record, or try a different search or institute.'); return; }
    echo '<div class="table-wrap"><table><thead><tr>'; foreach($heads as $h) echo '<th>'.e($h).'</th>'; echo '</tr></thead><tbody>';
    foreach($data as $r) { echo '<tr>'; foreach($render($r) as $cell) echo '<td>'.$cell.'</td>'; echo '</tr>'; } echo '</tbody></table></div>';
}
function options(array $data,string $label='name'): array { $o=[]; foreach($data as $r) $o[$r['id']]=$r[$label]; return $o; }
function editLink(string $page,int $id): string { return '<a class="text-link" href="?page='.$page.'&edit='.$id.'">View / edit ↗</a>'; }
function scoped(string $alias=''): array { global $scope; return $scope ? [($alias ? $alias.'.':'').'institute_id = ?',[$scope]] : ['1=1',[]]; }
function url(array $overrides=[]): string { return '?'.http_build_query(array_merge($_GET,$overrides)); }
function loginPrefill(): string { $v=is_string($_GET['email'] ?? null)?trim(substr($_GET['email'],0,200)):''; return filter_var($v,FILTER_VALIDATE_EMAIL)?$v:''; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Northstar · Institute CRM</title><?php if($user): ?><link rel="stylesheet" href="assets/app.css"><?php endif; ?><link rel="stylesheet" href="assets/university.css"><link rel="stylesheet" href="assets/theme.css"><?php if($user): ?><script src="assets/app.js" defer></script><?php endif; ?><script src="assets/theme.js" defer></script></head><body>
<?php if(!$user):
[$obBrand,$obKind,$obCity,$obAddr,$obPhone]=siteBrand();
?>
<?=siteHeader($obBrand,$obKind,'','<a class="u-btn ghost" href="student.php">Student sign-in</a><a class="u-btn ghost" href="apply.php">Admissions</a>'.siteToggle().siteBackLink())?>
<main class="u-auth-wrap"><div class="u-card"><div class="u-eyebrow">WELCOME BACK</div><h1>Your workspace awaits</h1><p class="u-muted">Sign in to manage your institute community.</p>
<?php if($error): ?><div class="u-alert error" role="alert"><?=e($error)?></div><?php endif; ?>
<?php if($flash): ?><div class="u-alert" role="status"><?=e($flash)?></div><?php endif; ?>
<?php if(otpEnabled()): ?>
<p><strong>&#9993; &nbsp; Secure email-code sign in</strong></p>
<?php if(($config['environment'] ?? '')==='local' && ($config['mail']['transport'] ?? '')==='log'): ?><p class="u-small">Local test mode: no real emails are sent. The server operator can read the code in the private <code>storage/mail/</code> capture files.</p><?php endif; ?>
<?php if(isset($_SESSION['otp'])): ?>
<p class="u-small">Enter the six-digit code for <strong><?=e($_SESSION['otp']['email'])?></strong>. Use this browser; the code expires in 5 minutes.</p>
<form method="post" class="u-form"><?=csrf()?><input type="hidden" name="action" value="verify_otp"><label>One-time code<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="000000" required class="u-code"></label><button class="u-btn solid" type="submit">Verify &amp; sign in →</button></form>
<hr class="u-rule">
<?php endif; ?>
<form method="post" class="u-form"><?=csrf()?><input type="hidden" name="action" value="request_otp"><?php field('Staff email address','email',$_SESSION['otp']['email'] ?? loginPrefill(),'email'); ?><button class="u-btn solid" type="submit"><?=isset($_SESSION['otp'])?'Send a new code':'Send sign-in code'?> →</button></form>
<p class="u-small">No password needed. Codes are sent only to existing, active staff accounts. Wait 60 seconds before resending.</p>
<?php else: ?>
<form method="post" class="u-form"><?=csrf()?><input type="hidden" name="action" value="login"><?php field('Email address','email',loginPrefill(),'email'); field('Password','password','','password'); ?><button class="u-btn solid" type="submit">Sign in to workspace →</button></form>
<?php endif; ?>
<p class="u-small">Need an account? Contact your institute administrator. First time setting up? Follow the README installation guide.</p>
<div class="u-auth-links"><a href="student.php">Student? Open your student portal →</a><a href="apply.php">New applicant? Browse courses &amp; apply →</a></div>
</div></main>
<?=siteFooter($obBrand,$obKind,$obCity,$obAddr,$obPhone)?>
<?php else:
try {
$admin=in_array($user['role'],['owner','admin'],true);
if(in_array($page,['staff','audit','payments','notifications','teachers','batches','attendance','fee-plans','fee-reports','health','exams','announcements','support','applications','student-accounts'],true) && !$admin) { http_response_code(403); $page='forbidden'; }
$institutes=$user['role']==='owner' ? rows('SELECT * FROM institutes ORDER BY name') : rows('SELECT * FROM institutes WHERE id=?',[$user['institute_id']]);
$scope=$user['role']==='owner' ? (int)($_GET['institute'] ?? ($_SESSION['scope'] ?? 0)) : (int)$user['institute_id'];
if($scope) instituteAccess($scope); $_SESSION['scope']=$scope;
[$where,$args]=scoped(); [$ewhere,$eargs]=scoped('e');
$courses=rows("SELECT * FROM courses WHERE $where ORDER BY name",$args);
$staff=rows("SELECT id,name,institute_id,role,active FROM users WHERE $where AND role <> 'owner' ORDER BY name",$args);
$nav=['dashboard'=>'Overview','enquiries'=>'Enquiries','followups'=>'Follow-ups','admissions'=>'Admissions','students'=>'Students','courses'=>'Courses','institutes'=>'Institutes'];
$nav['documents']='Documents';
if($admin) { $nav['applications']='Online applications'; $nav['student-accounts']='Student accounts'; $nav['exams']='Exams & results'; $nav['announcements']='Announcements'; $nav['support']='Student support'; $nav['teachers']='Teachers'; $nav['batches']='Batches'; $nav['attendance']='Attendance'; $nav['fee-plans']='Fee schedules'; $nav['fee-reports']='Fee reports'; if($user['role']==='owner') $nav['health']='Deployment checks'; $nav['payments']='Payments'; $nav['notifications']='Email notifications'; $nav['staff']='Team & access'; }
$titles=['student-accounts'=>'Verified student sign-ups and portal links.','applications'=>'Open your courses. Review every application.','exams'=>'Assess learning. Publish with confidence.','announcements'=>'Keep your students informed.','support'=>'A clear conversation with every student.','teachers'=>'Your teaching directory.','batches'=>'Organize courses, capacity and student allocation.','attendance'=>'Accurate daily registers with a correction history.','fee-plans'=>'Clear installment schedules for every student.','fee-reports'=>'See outstanding balances and overdue installments.','health'=>'A practical checklist, not a production certificate.','payments'=>'Record a payment. Keep a clear receipt.','documents'=>'Admission letters and payment receipts.','notifications'=>'A clear view of every student email.','dashboard'=>'A little clarity. A lot of progress.','enquiries'=>'Every conversation, a possibility.','followups'=>'Make the next connection.','admissions'=>'Their next chapter starts here.','students'=>'Your growing student community.','courses'=>'Learning paths, organized.','institutes'=>'One workspace. Every institute.','staff'=>'Good work starts with a great team.','settings'=>'Make yourself at home.','audit'=>'A clear record of activity.'];
$labels=$nav+['settings'=>'Account settings','audit'=>'Activity log','notfound'=>'Page not found','forbidden'=>'Access restricted'];
?>
<aside class="sidebar" id="sidebar"><a class="brand" href="?page=dashboard"><span class="brand-mark">N</span> northstar<span class="brand-dot">.</span></a><div class="workspace-label">INSTITUTE WORKSPACE <span>v1.1</span></div><nav><?php foreach($nav as $key=>$label): ?><a href="?page=<?=$key?>" class="nav-link <?=$page===$key?'active':''?>"><?=icon($key)?><span><?=e($label)?></span><?php if($key==='dashboard'): ?><span class="nav-dot"></span><?php endif; ?></a><?php endforeach; ?></nav><div class="sidebar-bottom"><div class="workspace-tip"><span>✦</span><strong>Room to grow.</strong><p>Less paperwork.<br>More possibilities.</p></div><?=siteBackLink('nav-link site-back')?><?php if($admin): ?><a class="nav-link <?=$page==='audit'?'active':''?>" href="?page=audit"><?=icon('audit')?> Activity log</a><?php endif; ?><a class="nav-link <?=$page==='settings'?'active':''?>" href="?page=settings"><?=icon('settings')?> Account settings</a><div class="sidebar-user"><?=person($user['name'],ucfirst($user['role']))?><form method="post"><?=csrf()?><input type="hidden" name="action" value="logout"><button class="icon-button" title="Sign out" aria-label="Sign out">↪</button></form></div></div></aside>
<div class="app"><header class="topbar"><div class="breadcrumb"><button class="icon-button menu-toggle" aria-label="Open navigation" aria-expanded="false">☰</button><span>Workspace</span><b>/</b><?=e($labels[$page] ?? 'Workspace')?></div><div class="topbar-right"><span class="today"><?=date('D, d M Y')?></span><form method="get" class="scope-form"><input type="hidden" name="page" value="<?=e($page)?>"><label class="sr-only" for="institute-filter">Institute</label><select id="institute-filter" name="institute"><?php if($user['role']==='owner'): ?><option value="0">All institutes</option><?php endif; ?><?php foreach($institutes as $i): ?><option value="<?=$i['id']?>" <?=$scope===(int)$i['id']?'selected':''?>><?=e($i['name'])?></option><?php endforeach; ?></select><button class="filter-button" type="submit">Apply</button></form><button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle dark mode" title="Toggle dark mode"><span aria-hidden="true">🌙</span></button><a class="top-avatar" href="?page=settings" aria-label="Account settings"><?=e(strtoupper(substr($user['name'],0,1)))?></a></div></header>
<main class="content">
<?php if($error): ?><div class="alert error" role="alert"><?=e($error)?></div><?php endif; ?><?php if($flash): ?><div class="alert success" role="status"><?=e($flash)?></div><?php endif; ?>
<div class="page-heading"><div><div class="eyebrow"><?= $page==='dashboard' ? 'YOUR CAMPUS, CONNECTED' : 'WORKSPACE / '.e(strtoupper($labels[$page] ?? '')) ?></div><h1><?=e($page==='dashboard' ? 'Overview' : ($labels[$page] ?? 'Workspace'))?></h1><p><?=e($titles[$page] ?? 'This page is not available.')?></p></div><?php
$creatable=in_array($page,['enquiries','followups'],true) || ($admin && in_array($page,['courses','staff'],true)) || ($page==='institutes' && $user['role']==='owner');
if($page==='dashboard'): ?><a class="button" href="?page=enquiries&edit=new"><span>＋</span> New enquiry</a><?php elseif($creatable): ?><a class="button" href="?page=<?=e($page)?>&edit=new">＋ Add <?=e(['enquiries'=>'enquiry','followups'=>'follow-up','courses'=>'course','staff'=>'team member','institutes'=>'institute'][$page])?></a><?php endif; ?></div>
<?php
$edit=$_GET['edit'] ?? null;
if($edit && $creatable):
$r=[];
if($edit!=='new') {
    $id=(int)$edit;
    if($page==='institutes') { instituteAccess($id); $r=one('SELECT * FROM institutes WHERE id=?',[$id]) ?? []; }
    elseif(in_array($page,['enquiries','courses'],true)) $r=record($page,$id);
    else fail('This record cannot be edited here.');
}
$formIid=(int)($r['institute_id'] ?? ($_POST['institute_id'] ?? $_GET['form_institute'] ?? $scope ?: ($institutes[0]['id'] ?? 0)));
if($formIid) instituteAccess($formIid);
$formCourses=$formIid ? rows('SELECT * FROM courses WHERE institute_id=? AND active=1 ORDER BY name',[$formIid]) : [];
$formStaff=$formIid ? rows("SELECT id,name FROM users WHERE institute_id=? AND active=1 AND role IN ('admin','counsellor') ORDER BY name",[$formIid]) : [];
?>
<section class="panel editor"><div class="panel-heading"><div><h2><?= $edit==='new' ? 'Create a new record' : 'Record details' ?></h2><p>Fields are required unless marked optional.</p></div><a class="close" href="?page=<?=e($page)?>" aria-label="Close editor">×</a></div>
<?php if($page!=='institutes' && $edit==='new'): ?><form method="get" class="form-context"><input type="hidden" name="page" value="<?=e($page)?>"><input type="hidden" name="edit" value="new"><?php selectField('Choose institute','form_institute',options($institutes),$formIid); ?><button class="button secondary">Load institute</button></form><p class="form-hint">Load the institute first to see its courses and counsellors.</p><?php endif; ?>
<?php
$action=['institutes'=>'institute','courses'=>'course','staff'=>'staff','enquiries'=>'enquiry','followups'=>'followup'][$page];
formStart($action,(int)($r['id'] ?? 0));
if($page!=='institutes') echo '<input type="hidden" name="institute_id" value="'.$formIid.'">';
echo '<div class="form-grid">';
if($page==='institutes') {
    field('Institute name','name',$r['name'] ?? ''); field('Type (Pharma, Medical, etc.)','kind',$r['kind'] ?? ''); field('City','city',$r['city'] ?? ''); field('Contact phone','phone',$r['phone'] ?? ''); field('Campus address (shown on the public university website)','address',$r['address'] ?? '');
} elseif($page==='courses') {
    field('Course name','name',$r['name'] ?? ''); field('Duration','duration',$r['duration'] ?? ''); field('Total course fee (INR)','fee',isset($r['fee_minor']) ? number_format($r['fee_minor']/100,2,'.','') : ''); selectField('Availability','active',['1'=>'Active','0'=>'Archived'],$r['active'] ?? '1');
} elseif($page==='staff') {
    field('Full name','name'); field('Email address','email','','email'); selectField('Role','role',$user['role']==='owner' ? ['admin'=>'Institute administrator','counsellor'=>'Counsellor'] : ['counsellor'=>'Counsellor']); if(!otpEnabled()) field('Initial password (12+ characters)','password','','password'); else echo '<p class="form-hint">This account will sign in with a code sent to the email above. Double-check the address.</p>';
} elseif($page==='enquiries') {
    if(($r['status'] ?? '')==='Admitted') { echo '<p>This enquiry is admitted and locked. View its student record in Students.</p>'; }
    field('Full name','name',$r['name'] ?? ''); field('Phone number','phone',$r['phone'] ?? '','tel'); field('Email (optional)','email',$r['email'] ?? '','email',false);
    selectField('Course','course_id',options($formCourses),$r['course_id'] ?? ''); selectField('Assigned counsellor','assigned_to',options($formStaff),$r['assigned_to'] ?? '');
    $sources=['Walk-in','Website','Referral','Phone','Social media','Other']; selectField('Enquiry source','source',array_combine($sources,$sources),$r['source'] ?? 'Walk-in'); $statuses=['New','Contacted','Interested','Lost']; selectField('Status','status',array_combine($statuses,$statuses),$r['status'] ?? 'New'); textArea('Notes (optional)','notes',$r['notes'] ?? '');
} elseif($page==='followups') {
    $open=$formIid ? rows("SELECT id,name FROM enquiries WHERE institute_id=? AND status NOT IN ('Admitted','Lost') ORDER BY name",[$formIid]) : [];
    foreach($open as &$opt) $opt['name'].=' · #'.$opt['id']; unset($opt);
    selectField('Enquiry','enquiry_id',options($open)); selectField('Assigned counsellor','assigned_to',options($formStaff)); field('Follow-up date','due_date',date('Y-m-d'),'date'); textArea('Plan for the conversation','notes','',true);
}
echo '</div>'; formEnd($page==='followups' ? 'Schedule follow-up' : 'Save record'); ?>
</section>
<?php endif;
if($page==='dashboard'):
$countEnquiries=(int)query("SELECT COUNT(*) FROM enquiries WHERE $where",$args)->fetchColumn();
$countOpen=(int)query("SELECT COUNT(*) FROM enquiries WHERE $where AND status NOT IN ('Admitted','Lost')",$args)->fetchColumn();
$countStudents=(int)query("SELECT COUNT(*) FROM students WHERE $where",$args)->fetchColumn();
$countDue=(int)query("SELECT COUNT(*) FROM followups WHERE $where AND completed_at IS NULL AND due_date<=?",[...$args,date('Y-m-d')])->fetchColumn();
$recent=rows("SELECT e.*, c.name course_name, i.name institute_name FROM enquiries e JOIN courses c ON c.id=e.course_id JOIN institutes i ON i.id=e.institute_id WHERE $ewhere ORDER BY e.id DESC LIMIT 5",$eargs);
[$fw,$fa]=scoped('f');
$due=rows("SELECT f.*,e.name,e.phone,u.name counsellor FROM followups f JOIN enquiries e ON e.id=f.enquiry_id JOIN users u ON u.id=f.assigned_to WHERE $fw AND f.completed_at IS NULL AND f.due_date<=? ORDER BY f.due_date LIMIT 4",[...$fa,date('Y-m-d')]);
?>
<section class="welcome-banner"><div><div class="banner-pill"><span></span> A fresh perspective on your day</div><h2>Hello, <?=e(explode(' ',$user['name'])[0])?> <span>✦</span></h2><p>You have <strong><?=$countDue?> follow-ups</strong> waiting for a conversation.<br>Let's help someone take their next step.</p><a href="?page=followups">View your follow-ups <span>→</span></a></div><div class="banner-art" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="art-star">✦</div><div class="art-card card-one">↗ <span>Every step<br><b>makes a difference.</b></span></div><div class="art-card card-two">✓ <span>Connected campuses</span></div></div></section>
<div class="stats"><?php foreach([['Total enquiries',$countEnquiries,'enquiries','Across your selected institutes','purple'],['Active conversations',$countOpen,'enquiries','Open admission opportunities','blue'],['Students enrolled',$countStudents,'students','A growing learning community','green'],['Follow-ups due',$countDue,'followups','Today and overdue','orange']] as [$label,$value,$dest,$hint,$color]): ?><a href="?page=<?=$dest?>" class="stat"><div class="stat-top"><span><?=e($label)?></span><span class="stat-icon <?=$color?>"><?=icon($dest)?></span></div><strong><?=$value?></strong><small><?=e($hint)?></small></a><?php endforeach; ?></div>
<div class="dashboard-grid"><section class="panel"><div class="panel-heading"><div><h2>Recent enquiries <span class="count-pill"><?=$countEnquiries?></span></h2><p>The newest faces in your pipeline</p></div><a class="text-link" href="?page=enquiries">View all ↗</a></div><?php table(['Prospective student','Course','Status'], $recent,fn($r)=>[person($r['name'],$r['phone']),'<span class="course-cell">'.e($r['course_name']).'</span>',badge($r['status'])]); ?></section><section class="panel followup-panel"><div class="panel-heading"><div><h2>Up next</h2><p>Your follow-up shortlist</p></div><span class="small-clock"><?=icon('followups')?></span></div><?php if(!$due) emptyState('You’re all caught up. Schedule a follow-up to keep conversations moving.'); foreach($due as $f): ?><a class="due-item" href="?page=followups"><div><?=person($f['name'],$f['counsellor'])?></div><span class="due-label <?=$f['due_date']<date('Y-m-d')?'overdue':''?>"><?=$f['due_date']<date('Y-m-d')?'Overdue':'Today'?></span></a><?php endforeach; ?><a class="panel-footer" href="?page=followups">See all follow-ups <span>→</span></a></section></div>
<div class="dashboard-grid bottom-grid"><section class="panel pipeline"><div class="panel-heading"><div><h2>Admissions pipeline</h2><p>From first hello to a new beginning</p></div><span class="subtle-tag">All time</span></div><div class="pipeline-stages"><?php $pipeline=rows("SELECT status,COUNT(*) total FROM enquiries WHERE $where GROUP BY status",$args); $counts=array_column($pipeline,'total','status'); foreach(['New','Contacted','Interested','Admitted'] as $idx=>$status): ?><div class="pipeline-stage"><span>0<?=$idx+1?> <b>→</b></span><strong><?= (int)($counts[$status] ?? 0) ?></strong><small><?=e($status)?></small><div class="stage-bar stage-<?=$idx?>"></div></div><?php endforeach; ?></div><p class="pipeline-note">Lost enquiries: <?= (int)($counts['Lost'] ?? 0) ?> · All counts reflect the selected institute.</p></section><section class="quick-panel"><span class="eyebrow">A LITTLE LESS ADMIN</span><h2>Your next action,<br>one click away.</h2><div class="quick-actions"><a href="?page=enquiries&edit=new">＋ Add an enquiry <span>↗</span></a><a href="?page=admissions">↗ Review admissions <span>↗</span></a><a href="?page=students">◈ Browse students <span>↗</span></a></div></section></div>
<?php elseif($page==='applications'): require __DIR__.'/application-staff-views.php'; ?>
<?php elseif($page==='student-accounts'): ?>
<section class="panel"><div class="panel-heading"><div><h2>Self-registered student accounts</h2><p>Students verify their own email here. Admit them through Online applications or Admissions; portal access still needs office approval in Students. Disabling here signs them out but does not change portal access.</p></div></div>
<?php if(!studentUsersReady()): ?><p class="inline-note">Run the upgrade to enable student registration.</p><?php else:
$spn=max(1,(int)($_GET['p'] ?? 1)); $susers=rows('SELECT * FROM student_users ORDER BY id DESC LIMIT 21 OFFSET '.(($spn-1)*20)); $smore=count($susers)>20; $susers=array_slice($susers,0,20);
table(['Student','Contact','Portal link','Access',''], $susers, function($r){
  $linked=one('SELECT a.id FROM portal_accounts a JOIN students s ON s.id=a.student_id WHERE a.email=? AND a.active=1 AND LOWER(s.email)=a.email',[$r['email']]);
  return [person($r['name'],'#'.$r['id'].' · registered '.$r['created_at']),'<strong>'.e($r['email']).'</strong><small>'.e($r['phone']).'</small>'.(trim($r['address']??'')!==''?'<small>'.e($r['address']).'</small>':''),$linked?badge('Linked'):badge('Not admitted'),badge($r['active']?'Active':'Disabled'),'<form method="post">'.csrf().'<input type="hidden" name="action" value="student_user_toggle"><input type="hidden" name="user_id" value="'.$r['id'].'"><button class="text-button">'.($r['active']?'Disable':'Restore').'</button></form>'];
}); ?>
<div class="pagination"><span>Page <?=$spn?></span><div><?php if($spn>1): ?><a class="button secondary" href="<?=e(url(['p'=>$spn-1]))?>">← Previous</a><?php endif; ?><?php if($smore): ?><a class="button secondary" href="<?=e(url(['p'=>$spn+1]))?>">Next →</a><?php endif; ?></div></div><?php endif; ?></section>
<?php elseif(in_array($page,['exams','announcements','support'],true)): require __DIR__.'/services-views.php'; ?>
<?php elseif(in_array($page,['teachers','batches','attendance','fee-plans','fee-reports','health'],true)): require __DIR__.'/operations-views.php'; ?>
<?php elseif(in_array($page,['payments','documents','notifications'],true)): require __DIR__.'/communication-views.php'; ?>
<?php elseif(in_array($page,['enquiries','students','admissions','followups','courses','institutes','staff','audit'],true)):
$q=is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']),0,120) : '';
$status=is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$pn=max(1,(int)($_GET['p'] ?? 1)); $limit=20; $offset=($pn-1)*$limit;
?>
<section class="panel records"><div class="list-toolbar"><form method="get" class="search-form"><input type="hidden" name="page" value="<?=e($page)?>"><label class="search-label"><span>⌕</span><input type="search" name="q" value="<?=e($q)?>" placeholder="Search <?=e(strtolower($labels[$page]))?>…" aria-label="Search records"></label><?php if($page==='enquiries'): ?><select name="status" aria-label="Filter status"><option value="">All statuses</option><?php foreach(['New','Contacted','Interested','Admitted','Lost'] as $s): ?><option <?=$s===$status?'selected':''?>><?=e($s)?></option><?php endforeach; ?></select><?php elseif($page==='followups'): ?><select name="status" aria-label="Filter follow-ups"><?php foreach([''=>'Open follow-ups','due'=>'Today & overdue','completed'=>'Completed','all'=>'All follow-ups'] as $k=>$s): ?><option value="<?=e($k)?>" <?=$k===$status?'selected':''?>><?=e($s)?></option><?php endforeach; ?></select><?php endif; ?><button class="button secondary">Search</button><a class="reset-link" href="?page=<?=e($page)?>">Reset</a></form><span class="subtle-tag">Live records</span></div>
<?php
$condition=''; $params=[]; $sql='';
if(in_array($page,['enquiries','admissions'],true)) {
    $condition=$ewhere; $params=$eargs;
    if($page==='admissions') $condition.=" AND e.status NOT IN ('Admitted','Lost')";
    elseif($status!=='') { $condition.=' AND e.status=?'; $params[]=$status; }
    if($q!=='') { $condition.=' AND (e.name LIKE ? OR e.phone LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%'); }
    $sql="SELECT e.*,c.name course_name,u.name counsellor,i.name institute_name FROM enquiries e JOIN courses c ON c.id=e.course_id JOIN users u ON u.id=e.assigned_to JOIN institutes i ON i.id=e.institute_id WHERE $condition ORDER BY e.id DESC";
} elseif($page==='students') {
    [$condition,$params]=scoped('s'); if($q!=='') { $condition.=' AND (s.name LIKE ? OR s.phone LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%'); }
    $sql="SELECT s.*,c.name course_name,i.name institute_name FROM students s JOIN courses c ON c.id=s.course_id JOIN institutes i ON i.id=s.institute_id WHERE $condition ORDER BY s.id DESC";
} elseif($page==='followups') {
    [$condition,$params]=scoped('f');
    if($status==='completed') $condition.=' AND f.completed_at IS NOT NULL'; elseif($status!=='all') $condition.=' AND f.completed_at IS NULL';
    if($status==='due') { $condition.=' AND f.due_date<=?'; $params[]=date('Y-m-d'); }
    if($q!=='') { $condition.=' AND e.name LIKE ?'; $params[]='%'.$q.'%'; }
    $sql="SELECT f.*,e.name,e.phone,u.name counsellor,i.name institute_name FROM followups f JOIN enquiries e ON e.id=f.enquiry_id JOIN users u ON u.id=f.assigned_to JOIN institutes i ON i.id=f.institute_id WHERE $condition ORDER BY f.due_date,f.id";
} elseif($page==='courses') {
    [$condition,$params]=scoped('c'); if($q!=='') { $condition.=' AND c.name LIKE ?'; $params[]='%'.$q.'%'; }
    $sql="SELECT c.*,i.name institute_name FROM courses c JOIN institutes i ON i.id=c.institute_id WHERE $condition ORDER BY c.id DESC";
} elseif($page==='institutes') {
    $condition=$scope?'id=?':'1=1'; $params=$scope?[$scope]:[];
    if($q!=='') { $condition.=' AND name LIKE ?'; $params[]='%'.$q.'%'; }
    $sql="SELECT * FROM institutes WHERE $condition ORDER BY id DESC";
} elseif($page==='staff') {
    requireRole(['owner','admin']); [$condition,$params]=scoped('u'); $condition.=" AND u.role<>'owner'";
    if($q!=='') { $condition.=' AND (u.name LIKE ? OR u.email LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%'); }
    $sql="SELECT u.id,u.name,u.email,u.role,u.active,u.institute_id,i.name institute_name FROM users u JOIN institutes i ON i.id=u.institute_id WHERE $condition ORDER BY u.id DESC";
} elseif($page==='audit') {
    requireRole(['owner','admin']); [$condition,$params]=scoped('u');
    if($q!=='') { $condition.=' AND (u.name LIKE ? OR a.action LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%'); }
    $sql="SELECT a.*,u.name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE $condition ORDER BY a.id DESC";
}
$data=rows($sql.' LIMIT '.($limit+1).' OFFSET '.$offset,$params); $more=count($data)>$limit; $data=array_slice($data,0,$limit);
if($page==='enquiries') table(['Student / contact','Institute & course','Counsellor','Status',''], $data,fn($r)=>[person($r['name'],$r['phone']),'<strong>'.e($r['course_name']).'</strong><small>'.e($r['institute_name']).'</small>',e($r['counsellor']),badge($r['status']),editLink('enquiries',(int)$r['id'])]);
elseif($page==='admissions') {
    if(!$admin) echo '<p class="inline-note">Counsellors can review candidates. An administrator or owner must confirm admission.</p>';
    table(['Candidate','Institute & course','Status','Confirm admission'], $data,function($r)use($admin){
        $action=$admin ? '<details class="row-details"><summary>Admit student →</summary><form method="post" class="stack">'.csrf().'<input type="hidden" name="action" value="admit"><input type="hidden" name="enquiry_id" value="'.$r['id'].'"><p>This creates a student and closes pending follow-ups. The current course fee is recorded.</p><label>Admission date<input type="date" name="admission_date" value="'.date('Y-m-d').'" max="'.date('Y-m-d').'" required></label><button class="button">Confirm admission</button></form></details>' : '<span class="muted">Administrator required</span>';
        return [person($r['name'],$r['phone']),'<strong>'.e($r['course_name']).'</strong><small>'.e($r['institute_name']).'</small>',badge($r['status']),$action];
    });
} elseif($page==='students') table(['Student','Student ID','Institute & course','Admitted','Agreed course fee','Email & documents'], $data,function($r)use($admin){
    $actions='<small>'.e($r['email'] ?: 'No email — notifications blocked').'</small>';
    if($admin) $actions.=portalControl($r);
    if($admin) $actions.='<details class="row-details"><summary>Manage email / letter</summary><form method="post" class="stack">'.csrf().'<input type="hidden" name="action" value="student_email"><input type="hidden" name="student_id" value="'.$r['id'].'"><label>Student email<input name="email" type="email" required maxlength="200" value="'.e($r['email']).'"></label><button class="button">Save student email</button></form><form method="post" class="stack">'.csrf().'<input type="hidden" name="action" value="admission_letter"><input type="hidden" name="student_id" value="'.$r['id'].'"><button class="button secondary">Create admission letter</button></form></details>';
    return [person($r['name'],$r['phone']),'<span class="mono">ST-'.str_pad((string)$r['id'],5,'0',STR_PAD_LEFT).'</span>','<strong>'.e($r['course_name']).'</strong><small>'.e($r['institute_name']).'</small>',e($r['admission_date']),money((int)$r['fee_minor']),$actions];
});
elseif($page==='followups') table(['Student','Due date','Assigned to','Conversation plan','Action / outcome'],$data,function($r){
    $action=$r['completed_at'] ? badge('Completed').'<small>'.e($r['outcome']).'</small>' : '<details class="row-details"><summary>Complete follow-up</summary><form method="post" class="stack">'.csrf().'<input type="hidden" name="action" value="complete_followup"><input type="hidden" name="id" value="'.$r['id'].'"><label>Conversation outcome<textarea name="outcome" required maxlength="3000" rows="3"></textarea></label><button class="button">Mark complete</button></form></details>';
    return [person($r['name'],$r['phone']),e($r['due_date']).(!$r['completed_at'] && $r['due_date']<date('Y-m-d') ? '<small class="overdue">Overdue</small>' : ''),e($r['counsellor']),'<span class="notes">'.e($r['notes']).'</span>',$action];
});
elseif($page==='courses') table(['Course','Institute','Duration','Course fee','Status',''],$data,fn($r)=>['<strong>'.e($r['name']).'</strong>',e($r['institute_name']),e($r['duration']),money((int)$r['fee_minor']),badge($r['active']?'Active':'Archived'),$admin?editLink('courses',(int)$r['id']):'']);
elseif($page==='institutes') table(['Institute','Specialization','City','Contact',''],$data,fn($r)=>[person($r['name'],'Institute #'.$r['id']),badge($r['kind']),e($r['city']).(trim($r['address'] ?? '')!==''?'<br><small>'.e($r['address']).'</small>':''),e($r['phone']),$user['role']==='owner'?editLink('institutes',(int)$r['id']):'']);
elseif($page==='staff') table(['Team member','Institute','Role','Access',''],$data,function($r)use($user){
    $can=$user['role']==='owner' || ($r['role']==='counsellor' && (int)$r['id']!==(int)$user['id']);
    $revoke=$can && operationsReady()?'<form method="post">'.csrf().'<input type="hidden" name="action" value="revoke_sessions"><input type="hidden" name="user_id" value="'.$r['id'].'"><button class="text-button">Revoke sessions</button></form>':'';
    return [person($r['name'],$r['email']),e($r['institute_name']),badge(ucfirst($r['role'])),badge($r['active']?'Active':'Disabled'),$can?'<form method="post">'.csrf().'<input type="hidden" name="action" value="staff_toggle"><input type="hidden" name="id" value="'.$r['id'].'"><button class="text-button">'.($r['active']?'Disable access':'Restore access').'</button></form>'.$revoke:''];
});
elseif($page==='audit') table(['Time','Team member','Action','Record'],$data,fn($r)=>[e($r['created_at']),e($r['name'] ?? 'System'),badge($r['action']),e($r['entity']).' #'.e($r['entity_id'])]);
?><div class="pagination"><span>Page <?=$pn?> · <?=count($data)?> records</span><div><?php if($pn>1): ?><a class="button secondary" href="<?=e(url(['p'=>$pn-1]))?>">← Previous</a><?php endif; ?><?php if($more): ?><a class="button secondary" href="<?=e(url(['p'=>$pn+1]))?>">Next →</a><?php endif; ?></div></div></section>
<?php if($page==='students'): ?><p class="footnote">Add a student email to enable notifications. New admissions automatically create a letter; use “Create admission letter” for older records. Payments and PDF receipts are managed in Payments and Documents.</p><?php endif; ?>
<?php elseif($page==='settings'): ?><section class="panel settings-panel"><div class="panel-heading"><div><h2>Account & security</h2><p><?=e($user['email'])?> · <?=e(ucfirst($user['role']))?></p></div></div><div class="settings-body"><?php if($user['role']==='owner'): ?><p class="inline-note"><a href="upgrade.php">Upgrade installed modules →</a> · <a href="student.php">Student portal ↗</a></p><?php endif; ?><?php if(otpEnabled()): ?><div class="auth-method">✉ Email OTP enabled</div><p class="inline-note">Sign in using a five-minute code delivered to your registered staff email. Password login is disabled. Contact the server administrator if you cannot access your mailbox.</p><?php else: formStart('password'); field('Current password','current_password','','password'); field('New password (12+ characters)','password','','password'); field('Confirm new password','confirm_password','','password'); formEnd('Update password'); endif; if(operationsReady()) { formStart('revoke_sessions'); echo '<input type="hidden" name="user_id" value="'.(int)$user['id'].'">'; formEnd('Sign out all my staff sessions'); } ?></div></section>
<?php else: emptyState($page==='forbidden'?'Your role does not have access to this page.':'Use the sidebar to return to your workspace.'); endif; ?>
<footer class="app-footer"><span><b>northstar.</b> A space for better beginnings.</span><span>Institute CRM · Email & receipts · <?=siteBackLink()?></span></footer></main></div>
<?php } catch(DomainException $ex) { http_response_code(403); echo '<main class="fatal"><h1>Record unavailable</h1><p>'.e($ex->getMessage()).'</p><a href="?page=dashboard&institute=0">Return to overview</a></main>'; } catch(Throwable $ex) { error_log((string)$ex); http_response_code(500); echo '<main class="fatal"><h1>Workspace temporarily unavailable</h1><p>Please check database setup or contact your administrator.</p></main>'; } endif; ?>
</body></html>
