<?php
declare(strict_types=1);
// Northstar University website — single public front page driven live by CRM data.
// Sessionless: reads public CRM data and redirects sign-in by account type.
// Design: classic academic replica (deep blue #1A365D, terracotta #C53030, gold).
// Showcase strings (hero, badges, recruiters) are starter template; names, addresses,
// programs, dates and faculty are live CRM data. Chrome is bilingual (EN | हिन्दी).
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
$root = __DIR__;
// Language: ?lang=hi|en overrides, remembered in a cookie (no server session).
$siteLang = 'en';
if (($_GET['lang'] ?? '') === 'hi') { $siteLang = 'hi'; setcookie('uni_lang', 'hi', time() + 15552000, '/'); }
elseif (($_GET['lang'] ?? '') === 'en') { setcookie('uni_lang', 'en', time() + 15552000, '/'); }
elseif (($_COOKIE['uni_lang'] ?? '') === 'hi') { $siteLang = 'hi'; }
$htmlLang = $siteLang === 'hi' ? 'hi' : 'en';
$HI = ['Home' => 'होम', 'About' => 'हमारे बारे में', 'Programs' => 'कार्यक्रम', 'Admissions' => 'प्रवेश', 'Notices' => 'सूचनाएं', 'Faculty' => 'शिक्षक', 'Contact' => 'संपर्क करें',
'About us' => 'हमारे बारे में', 'Programs & courses' => 'कार्यक्रम व पाठ्यक्रम', 'Notices & dates' => 'सूचनाएं व तिथियां', 'Campus' => 'परिसर', 'Program' => 'कार्यक्रम', 'Fee' => 'शुल्क',
'Sign In' => 'साइन इन', 'Sign in' => 'साइन इन', 'Apply Now' => 'अभी आवेदन करें', 'Apply' => 'आवेदन करें', 'Apply for Admission' => 'प्रवेश हेतु आवेदन करें', 'Apply online' => 'ऑनलाइन आवेदन', 'Apply by' => 'अंतिम तिथि',
'ADMISSIONS 2024-25 OPEN FOR UNDERGRADUATE PROGRAMS' => 'स्नातक कार्यक्रमों में प्रवेश 2024-25 प्रारंभ', 'SCHOLARSHIPS 2024-25' => 'छात्रवृत्ति 2024-25', 'SEMESTER RESULTS DECLARED' => 'सेमेस्टर परिणाम घोषित',
'TRADITION MEETS INNOVATION: EST. 1887' => 'परंपरा से नवाचार तक: स्थापित 1887', 'Nurturing global leaders.' => 'वैश्विक नेताओं का निर्माण।',
'Explore Programs' => 'कार्यक्रम देखें', 'Student / Office Sign In' => 'छात्र / कार्यालय साइन इन', 'ACCREDITED' => 'प्रत्यायित', 'RANKED #5' => 'रैंक #5', 'APPROVED' => 'अनुमोदित',
'Campuses' => 'परिसर', 'Programs open' => 'खुले कार्यक्रम', 'programs open' => 'खुले कार्यक्रम', 'Cities' => 'शहर', 'Faculty members' => 'शिक्षक',
'FEATURED PROGRAMS' => 'प्रमुख कार्यक्रम', 'Open for applications' => 'आवेदन हेतु खुले', 'ADMISSION DATES' => 'प्रवेश तिथियां', 'Latest notices' => 'ताज़ा सूचनाएं',
'Open' => 'खुला', 'Closing soon' => 'शीघ्र बंद हो रहा', 'Details & Apply' => 'विवरण व आवेदन', 'View all' => 'सभी देखें', 'All notices' => 'सभी सूचनाएं', 'No admission notices right now.' => 'अभी कोई प्रवेश सूचना नहीं है।',
"Vice-Chancellor's Message" => 'कुलपति का संदेश', 'Office of the Vice-Chancellor' => 'कुलपति कार्यालय', 'Explore our programs' => 'हमारे कार्यक्रम देखें',
'Programs open here when admissions begin.' => 'प्रवेश प्रारंभ होने पर कार्यक्रम यहाँ दिखेंगे।', 'All programs' => 'सभी कार्यक्रम', 'Top recruiters' => 'प्रमुख नियोक्ता', 'Reach us' => 'संपर्क करें', 'All campuses' => 'सभी परिसर',
'Ready to join?' => 'जुड़ने के लिए तैयार?', 'Create Student Account' => 'छात्र खाता बनाएं', 'A campus built around students' => 'छात्रों के लिए बना परिसर',
'Quick Links' => 'त्वरित लिंक', 'Address' => 'पता', 'Portals' => 'पोर्टल', 'Follow Us' => 'फॉलो करें', 'Create account' => 'खाता बनाएं', 'Office login' => 'कार्यालय लॉगिन',
'All rights reserved.' => 'सर्वाधिकार सुरक्षित।', 'Admissions open · Apply online' => 'प्रवेश खुले हैं · ऑनलाइन आवेदन करें', 'Admissions open' => 'प्रवेश खुले हैं', 'Page not found' => 'पृष्ठ नहीं मिला',
'OUR CAMPUSES' => 'हमारे परिसर', 'Where you will study' => 'आप यहाँ पढ़ेंगे', 'How to join' => 'कैसे जुड़ें', 'Start your application' => 'अपना आवेदन प्रारंभ करें',
'ELIGIBILITY & DATES' => 'पात्रता व तिथियां', 'What you need to know' => 'जो आपको जानना चाहिए', 'Admission notices' => 'प्रवेश सूचनाएं', 'Learn from experienced teachers' => 'अनुभवी शिक्षकों से सीखें',
'Visit or call us' => 'पधारें या कॉल करें', 'Admission help' => 'प्रवेश सहायता', 'Back to home' => 'मुख्य पृष्ठ पर वापस', 'Create your account' => 'अपना खाता बनाएं',
'ONE SIGN-IN FOR EVERYONE' => 'सभी के लिए एक ही साइन-इन', 'Enter your email.' => 'अपना ईमेल लिखें।', 'Staff go to the' => 'स्टाफ़ पहुँचेंगे', 'office dashboard' => 'कार्यालय डैशबोर्ड',
'students go to the' => 'छात्र पहुँचेंगे', 'student dashboard' => 'छात्र डैशबोर्ड', '— automatically.' => '— स्वतः।', 'Your email address' => 'आपका ईमेल पता', 'Continue' => 'जारी रखें',
'Student sign-in' => 'छात्र साइन-इन', 'Create student account' => 'छात्र खाता बनाएं', 'Check' => 'देखें', 'Office sign-in' => 'कार्यालय साइन-इन', 'No account found for' => 'के लिए कोई खाता नहीं मिला', 'New student?' => 'नए छात्र?', 'or' => 'या',
'apply for admission' => 'प्रवेश हेतु आवेदन करें', 'Staff should contact the administrator.' => 'कर्मचारी प्रशासक से संपर्क करें।', 'Enter a valid email address.' => 'सही ईमेल पता लिखें।',
'Create your free student account in a minute, then apply online.' => 'एक मिनट में अपना निःशुल्क छात्र खाता बनाएं, फिर ऑनलाइन आवेदन करें।',
'The faculty directory will appear here once the office adds teachers.' => 'कार्यालय द्वारा शिक्षक जोड़े जाने पर शिक्षक निर्देशिका यहाँ दिखेगी।',
'Admissions are opening soon.' => 'प्रवेश शीघ्र प्रारंभ हो रहे हैं।', 'or contact the office.' => 'या कार्यालय से संपर्क करें।',
'No notices right now.' => 'अभी कोई सूचना नहीं है।', 'This university page does not exist.' => 'यह विश्वविद्यालय पृष्ठ मौजूद नहीं है।',
'The portal is not set up yet. Please contact the institute office.' => 'पोर्टल अभी स्थापित नहीं है। कृपया संस्थान कार्यालय से संपर्क करें।',
'Sign-in lookup is temporarily unavailable. Use the direct portal links below.' => 'साइन-इन खोज अस्थायी रूप से उपलब्ध नहीं है। नीचे दिए सीधे लिंक प्रयोग करें।',
'Contact details will appear here soon.' => 'संपर्क विवरण शीघ्र यहाँ दिखेगा।', 'Open setup wizard' => 'सेटअप विज़ार्ड खोलें', 'Welcome — setup required' => 'स्वागत — सेटअप आवश्यक'];
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
    if (!filter_var($detectEmail, FILTER_VALIDATE_EMAIL)) $detectError = tr('Enter a valid email address.');
    elseif (!$ready) $detectError = tr('The portal is not set up yet. Please contact the institute office.');
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
            $detectError = tr('Sign-in lookup is temporarily unavailable. Use the direct portal links below.');
        }
    }
}
$allowed = ['home', 'about', 'courses', 'admissions', 'notices', 'faculty', 'contact', 'login'];
if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    $page = 'notfound';
}
$programCount = count($programs);
$tickerItems = [tr('ADMISSIONS 2024-25 OPEN FOR UNDERGRADUATE PROGRAMS'), tr('SCHOLARSHIPS 2024-25'), tr('SEMESTER RESULTS DECLARED')];
foreach ($notices as $n) {
    $tickerItems[] = $siteLang === 'hi'
        ? ('प्रवेश ' . ($n['closing'] ? 'शीघ्र बंद' : 'खुले हैं') . ': ' . $n['course'] . ' — अंतिम तिथि ' . $n['closes'])
        : ('Admissions ' . ($n['closing'] ? 'closing soon' : 'open') . ': ' . $n['course'] . ' — apply by ' . $n['closes']);
}
if ($programCount) $tickerItems[] = $siteLang === 'hi'
    ? ('कुल ' . $programCount . ' कार्यक्रम · ' . count($institutes) . ' परिसरों में प्रवेश खुले')
    : ($programCount . ' program' . ($programCount === 1 ? '' : 's') . ' open across ' . count($institutes) . ' ' . (count($institutes) === 1 ? 'campus' : 'campuses'));
if ($brandPhone !== '') $tickerItems[] = ($siteLang === 'hi' ? 'प्रवेश हेल्पलाइन: ' : 'Admission helpline: ') . $brandPhone;
$stats = [
    [count($institutes), tr('Campuses')],
    [$programCount, tr('Programs open')],
    [count($cities), tr('Cities')],
    [count($teachers), tr('Faculty members')],
];
$vcMsg = $siteLang === 'hi'
    ? $brand . ' में आपका हार्दिक स्वागत है। हमारी कक्षाएं, प्रयोगशालाएं और क्लीनिक एक ही उद्देश्य के लिए हैं — आपका विकास। स्नेही शिक्षकों, सत्यापित प्रवेश और आधुनिक छात्र पोर्टल के साथ हम आपके पहले आवेदन से दीक्षांत समारोह तक आपके साथ हैं।'
    : siteVcMessage($brand);
function uDocHead(string $title, string $brand): void {
    global $htmlLang;
    echo '<!doctype html><html lang="' . e($htmlLang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · ' . e($brand) . '</title><link rel="stylesheet" href="public/assets/theme.css"><link rel="stylesheet" href="public/assets/university.css"><script src="public/assets/theme.js" defer></script></head><body>';
}
?>
<?php uDocHead(tr(['home' => 'Admissions open', 'about' => 'About us', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'contact' => 'Contact', 'login' => 'Sign in', 'notfound' => 'Page not found'][$page]), $brand); ?>
<?=siteHeader($brand, $brandKind . ($brandCity ? ' · ' . $brandCity : ''), $page, '<a class="u-btn ghost" href="' . e(siteHomeUrl() . '?page=login') . '">' . e(tr('Sign In')) . '</a><a class="u-btn solid" href="' . e(sitePublicUrl('apply.php')) . '">' . e(tr('Apply Now')) . '</a>' . siteLangToggle($page), $tickerItems)?>
<main class="u-main">
<?php if (!$ready): ?>
<section class="u-card u-setup"><h1><?= e(tr('Welcome — setup required')) ?></h1><p>This university website is connected to the institute CRM, which has not been installed yet. The server administrator should open the setup wizard to create the database, owner account and first institute. This page updates itself automatically afterwards.</p><p><a class="u-btn solid" href="public/setup.php"><?= e(tr('Open setup wizard')) ?> →</a></p></section>
<?php elseif ($page === 'home'): ?>
<section class="u-hero"><div class="u-hero-inner"><div class="u-eyebrow"><?= e(tr('TRADITION MEETS INNOVATION: EST. 1887')) ?></div><h1><?= e(tr('Nurturing global leaders.')) ?></h1><p><?= e($brand) ?> <?= e($siteLang === 'hi' ? 'सत्यापित प्रवेश, स्नेही शिक्षकों और आधुनिक छात्र पोर्टल के साथ फार्मेसी व संबद्ध चिकित्सा कार्यक्रम प्रदान करता है। अपना खाता बनाएं, ऑनलाइन आवेदन करें और अपने आवेदन को ट्रैक करें — सब एक ही स्थान पर।' : 'offers ' . $brandKind . ' programs with verified admissions, caring faculty and a modern student portal. Create your account, apply online and track your application — all in one place.') ?></p><div class="u-cta"><a class="u-btn solid big" href="public/apply.php"><?= e(tr('Apply for Admission')) ?> →</a><a class="u-btn ghost big" href="?page=courses"><?= e(tr('Explore Programs')) ?></a><a class="u-btn ghost big" href="?page=login"><?= e(tr('Student / Office Sign In')) ?></a></div></div></section>
<div class="u-hero-badges"><?php foreach (siteAccreditations() as [$icon, $title, $sub]): ?><div class="u-badge-card"><span class="u-badge-icon"><?= $icon ?></span><div><strong><?= e($title) ?></strong><small><?= e(tr($sub)) ?></small></div></div><?php endforeach; ?></div>
<div class="u-stats-band"><div><?php foreach ($stats as [$n, $label]): ?><div><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></div>
<div class="u-home-grid"><div class="u-home-main">
<section class="u-section"><div class="u-eyebrow"><?= e(tr("Vice-Chancellor's Message")) ?></div><h2><?= e(tr("Vice-Chancellor's Message")) ?></h2><div class="u-vc-full"><span class="u-vc-portrait"><?= e(strtoupper(mb_substr($brand, 0, 1))) ?></span><div><blockquote>“<?= e($vcMsg) ?>”</blockquote><cite><?= e(tr('Office of the Vice-Chancellor')) ?> · <?= e($brand) ?></cite></div></div></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('FEATURED PROGRAMS')) ?></div><h2><?= e(tr('Open for applications')) ?></h2><?php if (!$programs): ?><p class="u-muted"><?= e(tr('Admissions are opening soon.')) ?> <?= e(tr('Check')) ?> <a href="?page=notices"><?= e(tr('Notices')) ?></a> <?= e(tr('or contact the office.')) ?></p><?php else: ?><div class="u-grid"><?php foreach (array_slice($programs, 0, 4) as $p): ?><article class="u-card"><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><h3><?= e($p['name']) ?></h3><p class="u-meta"><?= e($p['duration']) ?> · <?= e($p['city']) ?></p><p class="u-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><p class="u-apply-by"><?= e(tr('Apply by')) ?> <?= e($p['closes_on']) ?></p><a class="u-btn solid" href="public/apply.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Details & Apply')) ?> →</a></article><?php endforeach; ?></div><p class="u-more"><a href="?page=courses"><?= e(tr('View all')) ?> <?= $programCount ?> <?= e(tr('programs open')) ?> →</a></p><?php endif; ?></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('ADMISSION DATES')) ?></div><h2><?= e(tr('Latest notices')) ?></h2><?php if (!$notices): ?><p class="u-muted"><?= e(tr('No admission notices right now.')) ?></p><?php else: ?><ul class="u-notices"><?php foreach (array_slice($notices, 0, 4) as $n): ?><li><span class="u-pill<?= $n['closing'] ? ' soon' : '' ?>"><?= e(tr($n['closing'] ? 'Closing soon' : 'Open')) ?></span><div><strong><?= e($n['course']) ?></strong><small><?= e($n['institute']) ?> · <?= e(tr('Apply by')) ?> <?= e($n['closes']) ?></small></div><a href="public/apply.php?page=course&course=<?= (int)$n['id'] ?>"><?= e(tr('Apply')) ?> →</a></li><?php endforeach; ?></ul><p class="u-more"><a href="?page=notices"><?= e(tr('All notices')) ?> →</a></p><?php endif; ?></section>
<section class="u-section"><div class="u-eyebrow"><?= e($siteLang === 'hi' ? 'क्यों चुनें' : 'WHY') ?> <?= e(strtoupper($brandKind)) ?></div><h2><?= e(tr('A campus built around students')) ?></h2><div class="u-grid"><div class="u-card"><h3>💊 Pharmacy focus</h3><p>Practical labs, experienced faculty and career guidance for D.Pharm and allied programs.</p></div><div class="u-card"><h3>📝 Simple admissions</h3><p>One online form, email verification and transparent tracking to the office decision.</p></div><div class="u-card"><h3>🎓 Student portal</h3><p>Fees, payments, documents, attendance, results and support — available after admission.</p></div><div class="u-card"><h3>🏛️ Trusted office</h3><p>Every admission is verified by the institute office before approval. No hidden process.</p></div></div></section>
</div><aside class="u-home-side">
<div class="u-mini-badges"><?php foreach ([['🏵', 'NAAC'], ['🎖', 'NIRF'], ['🏛', 'UGC']] as [$icon, $name]): ?><span><?= $icon ?> <?= e($name) ?></span><?php endforeach; ?></div>
<div class="u-side-card u-vc"><div class="u-side-title"><?= e(tr("Vice-Chancellor's Message")) ?></div><div class="u-vc-head"><span class="u-vc-avatar"><?= e(strtoupper(mb_substr($brand, 0, 1))) ?></span><div><blockquote>“<?= e($vcMsg) ?>”</blockquote><cite><?= e(tr('Office of the Vice-Chancellor')) ?></cite></div></div></div>
<div class="u-side-card"><div class="u-side-title"><?= e(tr('Explore our programs')) ?></div><div class="u-prog-grid"><?php $pi = 0; foreach (siteShowcasePrograms() as $sp): $pi++; ?><a class="u-prog-card u-prog-<?= $pi ?>" href="?page=courses"><span><?= e($sp) ?></span></a><?php endforeach; ?></div><?php if ($programs): ?><p class="u-more"><a href="?page=courses"><?= e(tr('All programs')) ?> →</a></p><?php else: ?><p class="u-muted"><?= e(tr('Programs open here when admissions begin.')) ?></p><?php endif; ?></div>
<div class="u-side-card"><div class="u-side-title"><?= e(tr('Top recruiters')) ?></div><div class="u-recruiters"><?php foreach (siteRecruiters() as [$icon, $name]): ?><span><?= $icon ?> <?= e($name) ?></span><?php endforeach; ?><span class="u-flag-tile"><?= siteFlag() ?> India</span></div></div>
<div class="u-side-card"><div class="u-side-title"><?= e(tr('Reach us')) ?></div><p><strong><?= e($brand) ?></strong></p><?php if (trim($brandAddress) !== ''): ?><p>📍 <?= e($brandAddress) ?><?= $brandCity ? ', ' . e($brandCity) : '' ?></p><?php elseif ($brandCity): ?><p>📍 <?= e($brandCity) ?></p><?php endif; ?><?php if ($brandPhone !== ''): ?><p class="u-phone">☎ <?= e($brandPhone) ?></p><?php endif; ?><p class="u-more"><a href="?page=contact"><?= e(tr('All campuses')) ?> →</a></p></div>
</aside></div>
<section class="u-cta-band"><div><h2><?= e(tr('Ready to join?')) ?></h2><p><?= e(tr('Create your free student account in a minute, then apply online.')) ?></p></div><div><a class="u-btn light big" href="public/student.php?page=register"><?= e(tr('Create Student Account')) ?> →</a> <a class="u-btn ghost big" href="?page=login"><?= e(tr('Sign In')) ?></a></div></section>
<?php elseif ($page === 'about'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('About us')) ?></div><h1><?= e($brand) ?></h1><p><?= e($brandKind) ?> programs across <?= count($institutes) ?: 'our' ?> <?= count($institutes) === 1 ? 'campus' : 'campuses' ?><?= $cities ? ' in ' . e(implode(', ', $cities)) : '' ?>. Every figure on this website comes live from the institute CRM — programs, dates, campuses and contacts update automatically when the office updates its records.</p><div class="u-facts"><?php foreach ($stats as [$n, $label]): ?><div><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('OUR CAMPUSES')) ?></div><h2><?= e(tr('Where you will study')) ?></h2><?php if (!$institutes): ?><p class="u-muted">Campus details will appear here once the office adds institutes.</p><?php else: ?><div class="u-grid"><?php foreach ($institutes as $i): ?><article class="u-card"><h3><?= e($i['name']) ?></h3><p class="u-meta"><?= e($i['kind']) ?> · <?= e($i['city']) ?></p><?php if (trim($i['address']) !== ''): ?><p><?= e($i['address']) ?></p><?php endif; ?><p class="u-phone">☎ <?= e($i['phone']) ?></p></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'courses'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Programs & courses')) ?></div><h1><?= $programCount ?> <?= e(tr('programs open')) ?></h1><p>Only programs explicitly opened by the institute are listed. Fees, dates and eligibility come straight from the CRM.</p><?php if (!$programs): ?><p class="u-muted"><?= e(tr('Admissions are opening soon.')) ?></p><?php else: ?><div class="u-grid"><?php foreach ($programs as $p): ?><article class="u-card"><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><h3><?= e($p['name']) ?></h3><p class="u-meta"><?= e($p['duration']) ?> · <?= e($p['city']) ?></p><p><?= e(mb_substr($p['description'], 0, 160)) ?>…</p><p class="u-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><p class="u-apply-by"><?= e(tr('Apply by')) ?> <?= e($p['closes_on']) ?></p><a class="u-btn solid" href="public/apply.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Details & Apply')) ?> →</a></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'admissions'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Admissions')) ?></div><h1><?= e(tr('How to join')) ?></h1><div class="u-steps"><div><span>1</span><strong><?= e(tr('Create your account')) ?></strong><p>Register with your email and verify the code.</p></div><div><span>2</span><strong><?= e(tr('Apply online')) ?></strong><p>One simple form for your chosen program.</p></div><div><span>3</span><strong>Upload certificates</strong><p>Add mark sheets when the office enables uploads.</p></div><div><span>4</span><strong>Office verification</strong><p>Eligibility and originals checked by the office.</p></div><div><span>5</span><strong>Track &amp; join</strong><p>Follow your status, then open your student portal.</p></div></div><p><a class="u-btn solid big" href="public/apply.php"><?= e(tr('Start your application')) ?> →</a> <a class="u-btn ghost big" href="public/student.php?page=register"><?= e(tr('Create account')) ?></a></p></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('ELIGIBILITY & DATES')) ?></div><h2><?= e(tr('What you need to know')) ?></h2><p>D.Pharm applicants typically need <strong>10+2 with Physics, Chemistry and Biology/Mathematics</strong>. Final eligibility, seats and document verification are confirmed by the office. Never pay anyone outside the official student portal after admission.</p><?php if ($programs): ?><div class="u-table-wrap"><table><thead><tr><th><?= e(tr('Program')) ?></th><th><?= e(tr('Campus')) ?></th><th><?= e(tr('Fee')) ?></th><th><?= e(tr('Apply by')) ?></th><th></th></tr></thead><tbody><?php foreach ($programs as $p): ?><tr><td><strong><?= e($p['name']) ?></strong></td><td><?= e($p['institute_name']) ?></td><td>₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></td><td><?= e($p['closes_on']) ?></td><td><a href="public/apply.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Apply')) ?> →</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php elseif ($page === 'notices'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Notices & dates')) ?></div><h1><?= e(tr('Admission notices')) ?></h1><p>Published automatically from live admission dates in the CRM. Detailed office notices for enrolled students appear inside the student portal.</p><?php if (!$notices): ?><p class="u-muted"><?= e(tr('No notices right now.')) ?></p><?php else: ?><ul class="u-notices"><?php foreach ($notices as $n): ?><li><span class="u-pill<?= $n['closing'] ? ' soon' : '' ?>"><?= e(tr($n['closing'] ? 'Closing soon' : 'Open')) ?></span><div><strong>Admissions <?= $n['closing'] ? 'closing soon' : 'open' ?>: <?= e($n['course']) ?></strong><small><?= e($n['institute']) ?> · Last date <?= e($n['closes']) ?></small></div><a href="public/apply.php?page=course&course=<?= (int)$n['id'] ?>"><?= e(tr('Apply')) ?> →</a></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php elseif ($page === 'faculty'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Faculty')) ?></div><h1><?= e(tr('Learn from experienced teachers')) ?></h1><p>Directory updates automatically from the CRM teaching roster. Contact details are shared by the office on request.</p><?php if (!$teachers): ?><p class="u-muted"><?= e(tr('The faculty directory will appear here once the office adds teachers.')) ?></p><?php else: ?><div class="u-grid"><?php foreach ($teachers as $t): ?><article class="u-card u-teacher"><span class="u-avatar"><?= e(strtoupper(substr($t['name'], 0, 1))) ?></span><div><h3><?= e($t['name']) ?></h3><p class="u-meta"><?= e($t['qualification']) ?></p><p class="u-meta"><?= e($t['institute_name']) ?></p></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'contact'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Contact')) ?></div><h1><?= e(tr('Visit or call us')) ?></h1><p>Addresses and phone numbers come live from the CRM — always current.</p><?php if (!$institutes): ?><p class="u-muted"><?= e(tr('Contact details will appear here soon.')) ?></p><?php else: ?><div class="u-grid"><?php foreach ($institutes as $i): ?><article class="u-card"><h3><?= e($i['name']) ?></h3><?php if (trim($i['address']) !== ''): ?><p>📍 <?= e($i['address']) ?>, <?= e($i['city']) ?></p><?php else: ?><p>📍 <?= e($i['city']) ?></p><?php endif; ?><p class="u-phone">☎ <?= e($i['phone']) ?></p><p><a class="u-btn ghost" href="?page=admissions"><?= e(tr('Admission help')) ?> →</a></p></article><?php endforeach; ?></div><?php endif; ?><p class="u-muted">Never share passwords or OTP codes with anyone, including callers claiming to be the office.</p></section>
<?php elseif ($page === 'login'): ?>
<section class="u-login"><div class="u-card"><div class="u-eyebrow"><?= e(tr('ONE SIGN-IN FOR EVERYONE')) ?></div><h1><?= e(tr('Sign in')) ?></h1><p><?= e(tr('Enter your email.')) ?> <?= e(tr('Staff go to the')) ?> <strong><?= e(tr('office dashboard')) ?></strong>; <?= e(tr('students go to the')) ?> <strong><?= e(tr('student dashboard')) ?></strong> <?= e(tr('— automatically.')) ?></p>
<?php if ($detectError): ?><div class="u-alert error" role="alert"><?= e($detectError) ?></div><?php endif; ?>
<?php if ($detectUnknown): ?><div class="u-alert" role="status"><?= e(tr('No account found for')) ?> <strong><?= e($detectEmail) ?></strong>. <?= e(tr('New student?')) ?> <a href="public/student.php?page=register"><?= e(tr('Create your account')) ?></a> <?= e(tr('or')) ?> <a href="public/apply.php"><?= e(tr('apply for admission')) ?></a>. <?= e(tr('Staff should contact the administrator.')) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="action" value="detect"><label><?= e(tr('Your email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email" value="<?= e($detectEmail) ?>" placeholder="you@example.com"></label><button class="u-btn solid big" type="submit"><?= e(tr('Continue')) ?> →</button></form>
<div class="u-login-links"><a href="public/student.php"><?= e(tr('Student sign-in')) ?> →</a><a href="public/index.php"><?= e(tr('Office sign-in')) ?> →</a><a href="public/student.php?page=register"><?= e(tr('Create student account')) ?> →</a></div></div></section>
<?php else: ?>
<section class="u-card"><h1><?= e(tr('Page not found')) ?></h1><p><?= e(tr('This university page does not exist.')) ?></p><p><a class="u-btn solid" href="?"><?= e(tr('Back to home')) ?> →</a></p></section>
<?php endif; ?>
</main>
<?=siteFooter($brand, $brandKind, $brandCity, $brandAddress, $brandPhone)?>
</body></html>
