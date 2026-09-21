<?php
declare(strict_types=1);
// Northstar University website — single public front page driven live by CRM data.
// Sessionless: reads public CRM data and redirects sign-in by account type.
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
$root = __DIR__;
$configFile = getenv('CRM_CONFIG_FILE') ?: $root . '/config.php';
$ready = is_file($configFile);
if ($ready) {
    require $root . '/app/bootstrap.php';
    require_once $root . '/app/applications.php';
}
require_once $root . '/app/site-chrome.php';
$institutes = [];
$programs = [];
$teachers = [];
if ($ready) {
    try {
        $institutes = rows('SELECT * FROM institutes ORDER BY id');
    } catch (Throwable $e) {
        $institutes = [];
    }
    foreach ($institutes as &$inst) {
        if (!array_key_exists('address', $inst)) $inst['address'] = '';
        $inst['address'] = (string)($inst['address'] ?? '');
    }
    unset($inst);
    try {
        if (function_exists('applicationsReady') && applicationsReady()) $programs = publicCourses(0);
    } catch (Throwable $e) {
        $programs = [];
    }
    try {
        $teachers = rows('SELECT t.name,t.qualification,i.name institute_name FROM teachers t JOIN institutes i ON i.id=t.institute_id WHERE t.active=1 ORDER BY i.name,t.name LIMIT 60');
    } catch (Throwable $e) {
        $teachers = [];
    }
}
$primary = $institutes[0] ?? null;
$brand = $primary['name'] ?? 'Northstar Institutions';
$brandKind = $primary['kind'] ?? 'Pharma & Medical';
$brandCity = $primary['city'] ?? '';
$brandPhone = $primary['phone'] ?? '';
$brandAddress = $primary['address'] ?? '';
$cities = array_values(array_unique(array_filter(array_column($institutes, 'city'))));
$today = date('Y-m-d');
$notices = [];
foreach ($programs as $p) {
    $closing = ($p['closes_on'] ?? '') !== '' && $p['closes_on'] < date('Y-m-d', strtotime('+45 days'));
    $notices[] = ['course' => $p['name'], 'institute' => $p['institute_name'], 'closes' => $p['closes_on'], 'id' => (int)$p['id'], 'closing' => $closing];
}
usort($notices, fn($a, $b) => strcmp($a['closes'], $b['closes']));
$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
$detectError = null;
$detectEmail = '';
$detectUnknown = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'detect') {
    $page = 'login';
    $detectEmail = strtolower(trim(substr((string)($_POST['email'] ?? ''), 0, 200)));
    if (!filter_var($detectEmail, FILTER_VALIDATE_EMAIL)) $detectError = 'Enter a valid email address.';
    elseif (!$ready) $detectError = 'The portal is not set up yet. Please contact the institute office.';
    else {
        try {
            if (one('SELECT id FROM users WHERE email=? AND active=1', [$detectEmail])) {
                header('Location: public/index.php?email=' . urlencode($detectEmail));
                exit;
            }
            $isStudent = one('SELECT a.id FROM portal_accounts a JOIN students s ON s.id=a.student_id WHERE a.email=? AND a.active=1 AND LOWER(s.email)=a.email', [$detectEmail])
                ?: one('SELECT id FROM student_users WHERE email=? AND active=1', [$detectEmail])
                ?: one('SELECT id FROM students WHERE LOWER(email)=?', [$detectEmail]);
            if ($isStudent) {
                header('Location: public/student.php?email=' . urlencode($detectEmail));
                exit;
            }
            $detectUnknown = true;
        } catch (Throwable $e) {
            $detectError = 'Sign-in lookup is temporarily unavailable. Use the direct portal links below.';
        }
    }
}
$allowed = ['home', 'about', 'courses', 'admissions', 'notices', 'faculty', 'contact', 'login'];
if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    $page = 'notfound';
}
$programCount = count($programs);
$stats = [
    [count($institutes), 'Campuses'],
    [$programCount, 'Programs open'],
    [count($cities), 'Cities'],
    [count($teachers), 'Faculty members'],
];
function uDocHead(string $title, string $brand): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · ' . e($brand) . '</title><link rel="stylesheet" href="public/assets/theme.css"><link rel="stylesheet" href="public/assets/university.css"><script src="public/assets/theme.js" defer></script></head><body>';
}
?>
<?php uDocHead(['home' => 'Admissions open', 'about' => 'About us', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'contact' => 'Contact', 'login' => 'Sign in', 'notfound' => 'Page not found'][$page], $brand); ?>
<div class="u-topbar"><span>Admissions open · D.Pharm &amp; allied medical programs</span><span><a href="public/student.php">Student Login</a> · <a href="public/index.php">Office Login</a></span></div>
<?=siteHeader($brand,$brandKind,$page,'<a class="u-btn ghost" href="'.e(siteHomeUrl().'?page=login').'">Sign In</a><a class="u-btn solid" href="'.e(sitePublicUrl('apply.php')).'">Apply Now</a>')?>
<main class="u-main">
<?php if (!$ready): ?>
<section class="u-card u-setup"><h1>Welcome — setup required</h1><p>This university website is connected to the institute CRM, which has not been installed yet. The server administrator should open the setup wizard to create the database, owner account and first institute. This page updates itself automatically afterwards.</p><p><a class="u-btn solid" href="public/setup.php">Open setup wizard →</a></p></section>
<?php elseif ($page === 'home'): ?>
<section class="u-hero"><div><div class="u-eyebrow">ADMISSIONS OPEN · <?= e($brandKind) ?></div><h1>Study at <?= e($brand) ?>.</h1><p>D.Pharm and allied medical programs with verified admissions, caring faculty and a modern student portal. Create your account, apply online and track your application — all in one place.</p><div class="u-cta"><a class="u-btn solid big" href="public/apply.php">Apply for Admission →</a><a class="u-btn ghost big" href="?page=courses">Explore Programs</a><a class="u-btn ghost big" href="?page=login">Student / Office Sign In</a></div><div class="u-badges"><span>✓ Online Applications</span><span>✓ Verified Admissions</span><span>✓ Secure Student Portal</span></div></div><div class="u-hero-facts" aria-label="At a glance"><?php foreach ($stats as [$n, $label]): ?><div><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></section>
<section class="u-section"><div class="u-eyebrow">FEATURED PROGRAMS</div><h2>Open for applications</h2><?php if (!$programs): ?><p class="u-muted">Admissions are opening soon. Check <a href="?page=notices">Notices</a> or contact the office.</p><?php else: ?><div class="u-grid"><?php foreach (array_slice($programs, 0, 3) as $p): ?><article class="u-card"><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><h3><?= e($p['name']) ?></h3><p class="u-meta"><?= e($p['duration']) ?> · <?= e($p['city']) ?></p><p class="u-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><a class="u-btn solid" href="public/apply.php?page=course&course=<?= (int)$p['id'] ?>">Details &amp; Apply →</a></article><?php endforeach; ?></div><p class="u-more"><a href="?page=courses">View all <?= $programCount ?> programs →</a></p><?php endif; ?></section>
<section class="u-section"><div class="u-eyebrow">WHY <?= e(strtoupper($brandKind)) ?></div><h2>A campus built around students</h2><div class="u-grid four"><div class="u-card"><h3>💊 Pharmacy focus</h3><p>Practical labs, experienced faculty and career guidance for D.Pharm and allied programs.</p></div><div class="u-card"><h3>📝 Simple admissions</h3><p>One online form, email verification and transparent tracking to the office decision.</p></div><div class="u-card"><h3>🎓 Student portal</h3><p>Fees, payments, documents, attendance, results and support — available after admission.</p></div><div class="u-card"><h3>🏛️ Trusted office</h3><p>Every admission is verified by the institute office before approval. No hidden process.</p></div></div></section>
<section class="u-section"><div class="u-eyebrow">ADMISSION DATES</div><h2>Latest notices</h2><?php if (!$notices): ?><p class="u-muted">No admission notices right now.</p><?php else: ?><ul class="u-notices"><?php foreach (array_slice($notices, 0, 4) as $n): ?><li><span class="u-pill<?= $n['closing'] ? ' soon' : '' ?>"><?= $n['closing'] ? 'Closing soon' : 'Open' ?></span><div><strong><?= e($n['course']) ?></strong><small><?= e($n['institute']) ?> · Apply by <?= e($n['closes']) ?></small></div><a href="public/apply.php?page=course&course=<?= (int)$n['id'] ?>">Apply →</a></li><?php endforeach; ?></ul><p class="u-more"><a href="?page=notices">All notices →</a></p><?php endif; ?></section>
<section class="u-cta-band"><div><h2>Ready to join?</h2><p>Create your free student account in a minute, then apply online.</p></div><div><a class="u-btn light big" href="public/student.php?page=register">Create Student Account →</a> <a class="u-btn ghost big" href="?page=login">Sign In</a></div></section>
<?php elseif ($page === 'about'): ?>
<section class="u-section"><div class="u-eyebrow">ABOUT US</div><h1><?= e($brand) ?></h1><p><?= e($brandKind) ?> programs across <?= count($institutes) ?: 'our' ?> <?= count($institutes) === 1 ? 'campus' : 'campuses' ?><?= $cities ? ' in ' . e(implode(', ', $cities)) : '' ?>. Every figure on this website comes live from the institute CRM — programs, dates, campuses and contacts update automatically when the office updates its records.</p><div class="u-facts"><?php foreach ($stats as [$n, $label]): ?><div><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></section>
<section class="u-section"><div class="u-eyebrow">OUR CAMPUSES</div><h2>Where you will study</h2><?php if (!$institutes): ?><p class="u-muted">Campus details will appear here once the office adds institutes.</p><?php else: ?><div class="u-grid"><?php foreach ($institutes as $i): ?><article class="u-card"><h3><?= e($i['name']) ?></h3><p class="u-meta"><?= e($i['kind']) ?> · <?= e($i['city']) ?></p><?php if (trim($i['address']) !== ''): ?><p><?= e($i['address']) ?></p><?php endif; ?><p class="u-phone">☎ <?= e($i['phone']) ?></p></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'courses'): ?>
<section class="u-section"><div class="u-eyebrow">PROGRAMS &amp; COURSES</div><h1><?= $programCount ?> program<?= $programCount === 1 ? '' : 's' ?> open</h1><p>Only programs explicitly opened by the institute are listed. Fees, dates and eligibility come straight from the CRM.</p><?php if (!$programs): ?><p class="u-muted">Admissions are opening soon. Please check back or contact the office.</p><?php else: ?><div class="u-grid"><?php foreach ($programs as $p): ?><article class="u-card"><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><h3><?= e($p['name']) ?></h3><p class="u-meta"><?= e($p['duration']) ?> · <?= e($p['city']) ?></p><p><?= e(mb_substr($p['description'], 0, 160)) ?>…</p><p class="u-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><p class="u-apply-by">Apply by <?= e($p['closes_on']) ?></p><a class="u-btn solid" href="public/apply.php?page=course&course=<?= (int)$p['id'] ?>">Details &amp; Apply →</a></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'admissions'): ?>
<section class="u-section"><div class="u-eyebrow">ADMISSIONS</div><h1>How to join</h1><div class="u-steps"><div><span>1</span><strong>Create your account</strong><p>Register with your email and verify the code.</p></div><div><span>2</span><strong>Apply online</strong><p>One simple form for your chosen program.</p></div><div><span>3</span><strong>Upload certificates</strong><p>Add mark sheets when the office enables uploads.</p></div><div><span>4</span><strong>Office verification</strong><p>Eligibility and originals checked by the office.</p></div><div><span>5</span><strong>Track &amp; join</strong><p>Follow your status, then open your student portal.</p></div></div><p><a class="u-btn solid big" href="public/apply.php">Start your application →</a> <a class="u-btn ghost big" href="public/student.php?page=register">Create account</a></p></section>
<section class="u-section"><div class="u-eyebrow">ELIGIBILITY &amp; DATES</div><h2>What you need to know</h2><p>D.Pharm applicants typically need <strong>10+2 with Physics, Chemistry and Biology/Mathematics</strong>. Final eligibility, seats and document verification are confirmed by the office. Never pay anyone outside the official student portal after admission.</p><?php if ($programs): ?><div class="u-table-wrap"><table><thead><tr><th>Program</th><th>Campus</th><th>Fee</th><th>Apply by</th><th></th></tr></thead><tbody><?php foreach ($programs as $p): ?><tr><td><strong><?= e($p['name']) ?></strong></td><td><?= e($p['institute_name']) ?></td><td>₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></td><td><?= e($p['closes_on']) ?></td><td><a href="public/apply.php?page=course&course=<?= (int)$p['id'] ?>">Apply →</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php elseif ($page === 'notices'): ?>
<section class="u-section"><div class="u-eyebrow">NOTICES &amp; DATES</div><h1>Admission notices</h1><p>Published automatically from live admission dates in the CRM. Detailed office notices for enrolled students appear inside the student portal.</p><?php if (!$notices): ?><p class="u-muted">No notices right now.</p><?php else: ?><ul class="u-notices"><?php foreach ($notices as $n): ?><li><span class="u-pill<?= $n['closing'] ? ' soon' : '' ?>"><?= $n['closing'] ? 'Closing soon' : 'Open' ?></span><div><strong>Admissions <?= $n['closing'] ? 'closing soon' : 'open' ?>: <?= e($n['course']) ?></strong><small><?= e($n['institute']) ?> · Last date <?= e($n['closes']) ?></small></div><a href="public/apply.php?page=course&course=<?= (int)$n['id'] ?>">Apply →</a></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php elseif ($page === 'faculty'): ?>
<section class="u-section"><div class="u-eyebrow">FACULTY</div><h1>Learn from experienced teachers</h1><p>Directory updates automatically from the CRM teaching roster. Contact details are shared by the office on request.</p><?php if (!$teachers): ?><p class="u-muted">The faculty directory will appear here once the office adds teachers.</p><?php else: ?><div class="u-grid"><?php foreach ($teachers as $t): ?><article class="u-card u-teacher"><span class="u-avatar"><?= e(strtoupper(substr($t['name'], 0, 1))) ?></span><div><h3><?= e($t['name']) ?></h3><p class="u-meta"><?= e($t['qualification']) ?></p><p class="u-meta"><?= e($t['institute_name']) ?></p></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'contact'): ?>
<section class="u-section"><div class="u-eyebrow">CONTACT</div><h1>Visit or call us</h1><p>Addresses and phone numbers come live from the CRM — always current.</p><?php if (!$institutes): ?><p class="u-muted">Contact details will appear here soon.</p><?php else: ?><div class="u-grid"><?php foreach ($institutes as $i): ?><article class="u-card"><h3><?= e($i['name']) ?></h3><?php if (trim($i['address']) !== ''): ?><p>📍 <?= e($i['address']) ?>, <?= e($i['city']) ?></p><?php else: ?><p>📍 <?= e($i['city']) ?></p><?php endif; ?><p class="u-phone">☎ <?= e($i['phone']) ?></p><p><a class="u-btn ghost" href="?page=admissions">Admission help →</a></p></article><?php endforeach; ?></div><?php endif; ?><p class="u-muted">Never share passwords or OTP codes with anyone, including callers claiming to be the office.</p></section>
<?php elseif ($page === 'login'): ?>
<section class="u-login"><div class="u-card"><div class="u-eyebrow">ONE SIGN-IN FOR EVERYONE</div><h1>Sign in</h1><p>Enter your email. Staff go to the <strong>office dashboard</strong>; students go to the <strong>student dashboard</strong> — automatically.</p>
<?php if ($detectError): ?><div class="u-alert error" role="alert"><?= e($detectError) ?></div><?php endif; ?>
<?php if ($detectUnknown): ?><div class="u-alert" role="status">No account found for <strong><?= e($detectEmail) ?></strong>. New student? <a href="public/student.php?page=register">Create your account</a> or <a href="public/apply.php">apply for admission</a>. Staff should contact the administrator.</div><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="detect"><label>Your email address<input type="email" name="email" maxlength="200" required autocomplete="email" value="<?= e($detectEmail) ?>" placeholder="you@example.com"></label><button class="u-btn solid big" type="submit">Continue →</button></form>
<div class="u-login-links"><a href="public/student.php">Student sign-in →</a><a href="public/index.php">Office sign-in →</a><a href="public/student.php?page=register">Create student account →</a></div></div></section>
<?php else: ?>
<section class="u-card"><h1>Page not found</h1><p>This university page does not exist.</p><p><a class="u-btn solid" href="?">Back to home →</a></p></section>
<?php endif; ?>
</main>
<?=siteFooter($brand,$brandKind,$brandCity,$brandAddress,$brandPhone)?>
</body></html>
