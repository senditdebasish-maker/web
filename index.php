<?php
declare(strict_types=1);
// Northstar University website — single public front page driven live by CRM data.
// Sessionless: reads public CRM data and redirects sign-in by account type.
// Design: Nimita-style pharmacy college homepage (navy #062d5b, green #06965d).
// Starter template strings (hero, features, stats, quote) are fixed; names,
// addresses, programs, dates and faculty are live CRM data. Chrome is bilingual
// (EN | हिन्दी). Inner pages reuse the shared theme/university stylesheets.
ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
$root = __DIR__;
// Language: ?lang=hi|en overrides, remembered in a cookie (no server session).
$siteLang = 'en';
$siteHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (getenv('CRM_TRUST_HTTPS_PROXY') === '1' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$siteCookie = ['expires' => time() + 15552000, 'path' => '/', 'secure' => $siteHttps, 'samesite' => $siteHttps ? 'None' : 'Lax'];
if (($_GET['lang'] ?? '') === 'hi') { $siteLang = 'hi'; setcookie('uni_lang', 'hi', $siteCookie); }
elseif (($_GET['lang'] ?? '') === 'en') { setcookie('uni_lang', 'en', $siteCookie); }
elseif (($_COOKIE['uni_lang'] ?? '') === 'hi') { $siteLang = 'hi'; }
$htmlLang = $siteLang === 'hi' ? 'hi' : 'en';
$HI = ['Home' => 'होम', 'About' => 'हमारे बारे में', 'Programs' => 'कार्यक्रम', 'Admissions' => 'प्रवेश', 'Notices' => 'सूचनाएं', 'Faculty' => 'शिक्षक', 'Contact' => 'संपर्क करें',
'About us' => 'हमारे बारे में', 'Programs & courses' => 'कार्यक्रम व पाठ्यक्रम', 'Notices & dates' => 'सूचनाएं व तिथियां', 'Campus' => 'परिसर', 'Program' => 'कार्यक्रम', 'Fee' => 'शुल्क',
'Sign In' => 'साइन इन', 'Sign in' => 'साइन इन', 'Apply Now' => 'अभी आवेदन करें', 'Apply' => 'आवेदन करें', 'Apply for Admission' => 'प्रवेश हेतु आवेदन करें', 'Apply online' => 'ऑनलाइन आवेदन', 'Apply by' => 'अंतिम तिथि',
'Explore Programs' => 'कार्यक्रम देखें', 'Student / Office Sign In' => 'छात्र / कार्यालय साइन इन',
'Students' => 'छात्र', 'Campuses' => 'परिसर', 'Programs open' => 'खुले कार्यक्रम', 'programs open' => 'खुले कार्यक्रम', 'Cities' => 'शहर', 'Faculty members' => 'शिक्षक',
'FEATURED PROGRAMS' => 'प्रमुख कार्यक्रम', 'Open for applications' => 'आवेदन हेतु खुले', 'ADMISSION DATES' => 'प्रवेश तिथियां', 'Latest notices' => 'ताज़ा सूचनाएं',
'Open' => 'खुला', 'Closing soon' => 'शीघ्र बंद हो रहा', 'Details & Apply' => 'विवरण व आवेदन', 'View all' => 'सभी देखें', 'All notices' => 'सभी सूचनाएं', 'No admission notices right now.' => 'अभी कोई प्रवेश सूचना नहीं है।',
"Vice-Chancellor's Message" => 'कुलपति का संदेश', 'Office of the Vice-Chancellor' => 'कुलपति कार्यालय', 'Explore our programs' => 'हमारे कार्यक्रम देखें',
'Programs open here when admissions begin.' => 'प्रवेश प्रारंभ होने पर कार्यक्रम यहाँ दिखेंगे।', 'All programs' => 'सभी कार्यक्रम', 'Top recruiters' => 'प्रमुख नियोक्ता', 'Shaping Healthcare Leaders' => 'हेल्थकेयर लीडर्स तैयार करना', 'for a Healthier Tomorrow' => 'एक स्वस्थ कल के लिए', 'Message from the Principal' => 'प्राचार्य का संदेश', 'Principal' => 'प्राचार्य', 'Reach us' => 'संपर्क करें', 'All campuses' => 'सभी परिसर',
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
'Contact details will appear here soon.' => 'संपर्क विवरण शीघ्र यहाँ दिखेगा।', 'Open setup wizard' => 'सेटअप विज़ार्ड खोलें', 'Welcome — setup required' => 'स्वागत — सेटअप आवश्यक',
'Send sign-in code' => 'साइन-इन कोड भेजें', 'Check your inbox' => 'अपना इनबॉक्स देखें', 'We sent a 6-digit code to' => 'हमने 6 अंकों का कोड भेजा है',
'Enter the 6-digit code' => '6 अंकों का कोड लिखें', 'Verify & sign in' => 'सत्यापित करें व साइन इन करें', 'Resend code' => 'कोड पुनः भेजें',
'Admission Inquiry' => 'प्रवेश पूछताछ', 'Forgot password?' => 'पासवर्ड भूल गए?', 'Full name' => 'पूरा नाम', 'Mobile number' => 'मोबाइल नंबर',
'Gmail / email address' => 'Gmail / ईमेल पता', 'Home address' => 'घर का पता', 'Program of interest' => 'इच्छुक कार्यक्रम',
'Your message (optional)' => 'आपका संदेश (वैकल्पिक)', 'Send inquiry' => 'पूछताछ भेजें', 'Send verification code' => 'सत्यापन कोड भेजें',
'Recover access' => 'पहुंच पुनः प्राप्त करें', 'Back to sign in' => 'साइन इन पर वापस', 'Start over' => 'पुनः प्रारंभ करें',
'Staff password sign-in' => 'कर्मचारी पासवर्ड साइन-इन', 'Continue to office login' => 'कार्यालय लॉगिन पर जाएं',
'Choose how to continue' => 'आगे कैसे बढ़ें चुनें', 'I am a student — create my account' => 'मैं छात्र हूं — मेरा खाता बनाएं',
'I want to enquire about admission' => 'मैं प्रवेश हेतु पूछताछ करना चाहता हूं', 'Your verification code' => 'आपका सत्यापन कोड',
'Account recovery' => 'खाता पुनर्प्राप्ति', 'Create your student account' => 'अपना छात्र खाता बनाएं',
'Verify your Gmail to create your account.' => 'अपना खाता बनाने हेतु अपना Gmail सत्यापित करें।',
'Verify your Gmail to recover access.' => 'पहुंच पुनः प्राप्त करने हेतु अपना Gmail सत्यापित करें।',
'Tell us about yourself and the office will respond.' => 'अपने बारे में बताएं, कार्यालय उत्तर देगा।',
'Your inquiry has been received. The office will contact you soon.' => 'आपकी पूछताछ प्राप्त हो गई है। कार्यालय शीघ्र संपर्क करेगा।',
'Your institute uses password sign-in for staff. Continue below:' => 'आपका संस्थान कर्मचारियों हेतु पासवर्ड साइन-इन प्रयोग करता है। नीचे जाएं:',
'Password' => 'पासवर्ड', 'Sign in to office' => 'कार्यालय में साइन इन करें', 'Track application' => 'आवेदन ट्रैक करें',
'I applied — track my application' => 'मैंने आवेदन किया है — अपना आवेदन ट्रैक करें',
'Enter the email from your application. We will send a verification code.' => 'अपने आवेदन वाला ईमेल लिखें। हम सत्यापन कोड भेजेंगे।',
'Verify & view application' => 'सत्यापित करें व आवेदन देखें',
'Approved by AICTE' => 'AICTE द्वारा अनुमोदित', 'PCI Approved' => 'PCI अनुमोदित', 'Affiliated to MAKAUT, WB' => 'MAKAUT, WB से संबद्ध',
'Student Login' => 'छात्र लॉगिन', 'Faculty Login' => 'शिक्षक लॉगिन', 'Admin Login' => 'एडमिन लॉगिन',
'Enquiry' => 'पूछताछ', 'Download' => 'डाउनलोड', 'Prospectus' => 'विवरणिका',
'About Us' => 'हमारे बारे में', 'Academics' => 'शैक्षणिक', 'Facilities' => 'सुविधाएं', 'Student Corner' => 'छात्र कॉर्नर', 'Placement' => 'प्लेसमेंट', 'Research' => 'अनुसंधान',
'LATEST UPDATES' => 'ताज़ा जानकारी', 'Latest Updates' => 'ताज़ा जानकारी', 'View All' => 'सभी देखें',
'Pharmacy Education • Research • Healthcare' => 'फार्मेसी शिक्षा • अनुसंधान • स्वास्थ्य सेवा',
'Shaping' => 'गढ़ रहे', 'Healthcare Leaders' => 'हेल्थकेयर लीडर्स',
'Our' => 'हमारे', 'Life' => 'जीवन', 'Message from the' => 'संदेश', "Principal's Message" => 'प्राचार्य का संदेश',
'Leadership' => 'नेतृत्व', 'Life at Campus' => 'परिसर जीवन', 'Welcome to our institution' => 'हमारे संस्थान में आपका स्वागत है', 'Welcome to' => 'स्वागत है',
'Know More About Us' => 'हमारे बारे में और जानें', 'Learn More' => 'और जानें', 'Academic Excellence' => 'शैक्षणिक उत्कृष्टता',
'Contact Us' => 'संपर्क करें', 'Downloads' => 'डाउनलोड', 'Examination' => 'परीक्षा', 'Results' => 'परिणाम', 'Scholarships' => 'छात्रवृत्ति', 'Grievance' => 'शिकायत',
'NEW' => 'नया', 'Practical' => 'प्रायोगिक', 'Pharmacy' => 'फार्मेसी',
'Begin Your Journey in Pharmacy' => 'फार्मेसी में अपनी यात्रा शुरू करें', 'Apply Online Now' => 'अभी ऑनलाइन आवेदन करें',
'Modern Laboratories' => 'आधुनिक प्रयोगशालाएं', 'Academic Compliance' => 'शैक्षणिक अनुपालन', 'Placement Support' => 'प्लेसमेंट सहायता',
'All Rights Reserved.' => 'सर्वाधिकार सुरक्षित।', 'Privacy Policy' => 'गोपनीयता नीति', 'Terms of Use' => 'उपयोग की शर्तें', 'Sitemap' => 'साइटमैप',
'Primary' => 'मुख्य', 'Mobile' => 'मोबाइल', 'Menu' => 'मेनू', 'Open menu' => 'मेनू खोलें', 'Close menu' => 'मेनू बंद करें',
'About' => 'हमारे बारे में', 'Academics' => 'शैक्षणिक', 'Campus Life' => 'परिसर जीवन', 'Apply' => 'आवेदन करें',
'Notices' => 'सूचनाएं', 'Account' => 'खाता', 'Login' => 'लॉगिन', 'Staff Login' => 'स्टाफ़ लॉगिन', 'Quick navigation' => 'त्वरित नेविगेशन',
'About the Institution' => 'संस्थान के बारे में', 'Vision & Mission' => 'दृष्टि व ध्येय', 'Accreditation' => 'प्रत्यायन',
'Laboratories' => 'प्रयोगशालाएं', 'Library' => 'पुस्तकालय', 'Academic Calendar' => 'शैक्षणिक कैलेंडर',
'Admission Process' => 'प्रवेश प्रक्रिया', 'Eligibility' => 'पात्रता', 'Fee Structure' => 'शुल्क संरचना', 'Important Dates' => 'महत्वपूर्ण तिथियां',
'Apply Online' => 'ऑनलाइन आवेदन करें', 'Track Application' => 'आवेदन ट्रैक करें', 'Hostel' => 'छात्रावास', 'Transport' => 'परिवहन',
'Student Activities' => 'छात्र गतिविधियां', 'Student Dashboard' => 'छात्र डैशबोर्ड', 'Support' => 'सहायता', 'Enquire' => 'पूछताछ करें',
'Latest updates' => 'ताज़ा जानकारी', 'Latest notices' => 'ताज़ा सूचनाएं', 'All' => 'सभी', 'Search' => 'खोजें', 'Search programs' => 'कार्यक्रम खोजें',
'Search notices' => 'सूचनाएं खोजें', 'Back to top' => 'ऊपर जाएं', 'Mon - Sat: 10:00 AM - 5:00 PM' => 'सोम - शनि: प्रातः 10:00 - सायं 5:00',
'Building knowledgeable, skilled and responsible pharmacy professionals through quality education, research and healthcare.' => 'गुणवत्तापूर्ण शिक्षा, अनुसंधान व स्वास्थ्य सेवा द्वारा जानकार, कुशल व जिम्मेदार फार्मेसी पेशेवर तैयार करना।',
'Admissions open' => 'प्रवेश खुले हैं', 'Explore our programs' => 'हमारे कार्यक्रम देखें', 'Why choose us' => 'हमें क्यों चुनें',
'Featured programs' => 'प्रमुख कार्यक्रम', 'View all programs' => 'सभी कार्यक्रम देखें', 'Campus life' => 'परिसर जीवन',
'Get in touch' => 'संपर्क करें', 'Our campuses' => 'हमारे परिसर',
'How to apply' => 'आवेदन कैसे करें', 'Eligibility & dates' => 'पात्रता व तिथियां', 'Fee details' => 'शुल्क विवरण',
'Last date' => 'अंतिम तिथि', 'Duration' => 'अवधि', 'Campus' => 'परिसर', 'Learn more' => 'और जानें',
'No programs match your search.' => 'आपकी खोज से कोई कार्यक्रम नहीं मिला।', 'No active courses are available yet.' => 'अभी कोई सक्रिय पाठ्यक्रम उपलब्ध नहीं है।',
'One account for everything' => 'सब कुछ के लिए एक खाता', 'Sign in to continue' => 'जारी रखने हेतु साइन इन करें'];
$configFile = getenv('CRM_CONFIG_FILE') ?: $root . '/config.php';
$ready = is_file($configFile);
if ($ready) {
    require $root . '/app/bootstrap.php';
    require_once $root . '/app/applications.php';
}
require_once $root . '/app/site-chrome.php';
require_once $root . '/app/college-chrome.php';
require_once $root . '/app/site-accounts.php';
require_once $root . '/app/portal.php';
$institutes = [];
$programs = [];
$allPrograms = [];
$teachers = [];
$studentCount = 0;
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
        if (function_exists('applicationsReady') && applicationsReady()) {
            $allPrograms=rows("SELECT c.id,c.name,c.duration,c.fee_minor,c.institute_id,i.name institute_name,i.kind,i.city,i.phone,l.description,l.eligibility,l.privacy_notice,l.opens_on,l.closes_on,l.accepting,CASE WHEN l.id IS NOT NULL AND l.accepting=1 AND l.opens_on<=? AND l.closes_on>=? THEN 1 ELSE 0 END admission_open FROM courses c JOIN institutes i ON i.id=c.institute_id LEFT JOIN admission_listings l ON l.id=c.id WHERE c.active=1 ORDER BY i.name,c.name,c.id",[date('Y-m-d'),date('Y-m-d')]);
            $programs=array_values(array_filter($allPrograms,fn($course)=>(int)$course['admission_open']===1));
        } else $allPrograms=rows("SELECT c.id,c.name,c.duration,c.fee_minor,c.institute_id,i.name institute_name,i.kind,i.city,i.phone,0 admission_open FROM courses c JOIN institutes i ON i.id=c.institute_id WHERE c.active=1 ORDER BY i.name,c.name,c.id");
    } catch (Throwable $e) {
        $programs = [];
        $allPrograms = [];
    }
    try {
        $teachers = rows('SELECT t.name,t.qualification,i.name institute_name FROM teachers t JOIN institutes i ON i.id=t.institute_id WHERE t.active=1 ORDER BY i.name,t.name LIMIT 60');
    } catch (Throwable $e) {
        $teachers = [];
    }
    try {
        $studentCount = (int)query('SELECT COUNT(*) FROM students')->fetchColumn();
    } catch (Throwable $e) {
        $studentCount = 0;
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
$siteStep = 'email';
$siteError = null;
$siteFlash = null;
$siteEmail = '';
$show = is_string($_GET['show'] ?? null) ? $_GET['show'] : '';
if (!in_array($show, ['create', 'inquiry', 'recovery', 'applicant'], true)) $show = '';
$siteAction = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (string)($_POST['action'] ?? '') : '';
if (!in_array($siteAction, ['otp_start', 'otp_verify', 'create_start', 'create_verify', 'recovery_start', 'recovery_verify', 'inquiry_save', 'password_login', 'applicant_start', 'applicant_verify'], true)) $siteAction = '';
if ($ready) require_once $root . '/app/otp.php';
if (in_array($page, ['login', 'create-account'], true) || $siteAction !== '') {
    if ($siteAction !== '') $page = 'login';
    if ($page === 'login' && isset($_GET['course']) && $ready) {
        try {
            $carry = one('SELECT id FROM courses WHERE id = ? AND active = 1', [(int)$_GET['course']]);
            if ($carry) { siteSession('northstar_applicant'); $_SESSION['apply_course'] = (int)$carry['id']; }
        } catch (Throwable $e) { /* course carry is best-effort */ }
    }
    try {
        if (!$ready) throw new DomainException(tr('The portal is not set up yet. Please contact the institute office.'));
        if ($siteAction !== '') requireCookies();
        switch ($siteAction) {
            case '':
                siteSession($page === 'create-account' ? 'northstar_auth' : 'northstar_site');
                if ($page === 'create-account' && isset($_SESSION['suid_pending'])) $siteStep = 'create_code';
                break;
            case 'otp_start':
                siteCsrfCheckAny();
                $email = emailInput();
                $siteEmail = $email;
                $kind = siteDetectAccount($email);
                if ($kind === null) {
                    // Unknown email: guide the visitor to create an account, enquire or track an application.
                    $siteStep = 'chooser';
                    break;
                }
                if ($kind === 'staff' && !otpEnabled()) {
                    // Password-mode institutes sign staff in with passwords, not codes.
                    $siteStep = 'staff_password';
                    break;
                }
                siteSession('northstar_auth');
                requestOtp($kind);
                $siteFlash = siteTakeFlash();
                $siteStep = 'code';
                break;
            case 'otp_verify':
                siteCsrfCheckAny();
                siteResumePending();
                $audience = (string)($_SESSION['otp']['audience'] ?? '');
                $siteEmail = (string)($_SESSION['otp']['email'] ?? '');
                if (!in_array($audience, ['staff', 'student'], true)) fail('Your session expired. Start again.');
                verifyOtp($audience);
                $target = $audience === 'staff' ? 'northstar_session' : 'northstar_site';
                siteTransferSession($target);
                header('Location: ' . ($audience === 'staff' ? 'office.php' : 'student.php'));
                exit;
            case 'create_start':
                siteCsrfCheckAny();
                requestStudentUserCode();
                $siteFlash = siteTakeFlash();
                $siteStep = 'create_code';
                if (session_name() === 'northstar_auth') $page = 'create-account'; else $show = 'create';
                break;
            case 'create_verify':
                siteResumePending();
                siteCsrfCheck();
                verifyStudentUserCode();
                siteTransferSession('northstar_site');
                header('Location: student.php');
                exit;
            case 'recovery_start':
                siteCsrfCheckAny();
                $siteEmail = emailInput();
                siteSession('northstar_site');
                siteRecoveryRequest();
                $siteFlash = siteTakeFlash();
                $siteStep = 'recovery_code';
                $show = 'recovery';
                break;
            case 'recovery_verify':
                siteResumePending();
                siteCsrfCheck();
                siteRecoveryVerify();
                header('Location: student.php');
                exit;
            case 'password_login':
                siteCsrfCheckAny();
                $siteEmail = emailInput();
                siteSession('northstar_session');
                sitePasswordLogin();
                header('Location: office.php');
                exit;
            case 'applicant_start':
                siteCsrfCheckAny();
                $siteEmail = emailInput();
                siteSession('northstar_applicant');
                requestApplicantCode();
                $siteFlash = siteTakeFlash();
                $siteStep = 'applicant_code';
                $show = 'applicant';
                break;
            case 'applicant_verify':
                siteCsrfCheckAny();
                siteResumePending();
                $siteEmail = (string)($_SESSION['applicant_pending']['email'] ?? '');
                verifyApplicantCode();
                $selected = (int)($_SESSION['apply_course'] ?? 0);
                unset($_SESSION['apply_course']);
                $next = ($selected && publicCourses(0, $selected)) ? 'apply&course=' . $selected : 'dashboard';
                header('Location: apply.php?page=' . $next);
                exit;
            case 'inquiry_save':
                siteCsrfCheckAny();
                siteInquirySave();
                $siteStep = 'inquiry_done';
                $show = 'inquiry';
                break;
        }
    } catch (DomainException $e) {
        $siteError = $e->getMessage();
        $siteStep = ['otp_verify' => 'code', 'create_verify' => 'create_code', 'recovery_verify' => 'recovery_code', 'applicant_verify' => 'applicant_code'][$siteAction] ?? $siteStep;
        if ($siteAction === 'otp_start' && isset($_SESSION['otp'])) $siteStep = 'code';
            if ($siteAction === 'create_start' && isset($_SESSION['suid_pending'])) { $siteStep = 'create_code'; if (session_name() === 'northstar_auth') $page = 'create-account'; else $show = 'create'; }
        if ($siteAction === 'recovery_start' && isset($_SESSION['site_recovery'])) {
            $siteStep = 'recovery_code';
            $show = 'recovery';
        }
        if ($siteAction === 'password_login') {
            $siteStep = 'staff_password';
        }
        if ($siteAction === 'applicant_start' && isset($_SESSION['applicant_pending'])) {
            $siteStep = 'applicant_code';
            $show = 'applicant';
        }
        if (in_array($siteAction, ['create_start', 'create_verify'], true)) $show = 'create';
        if (in_array($siteAction, ['recovery_start', 'recovery_verify'], true)) $show = 'recovery';
        if (in_array($siteAction, ['applicant_start', 'applicant_verify'], true)) $show = 'applicant';
        if ($siteAction === 'inquiry_save') $show = 'inquiry';
    } catch (Throwable $e) {
        $siteError = tr('Sign-in is temporarily unavailable. Please try again.');
    }
}
$otpEmail = $siteEmail !== '' ? $siteEmail : (string)($_SESSION['otp']['email'] ?? $_SESSION['suid_pending']['email'] ?? $_SESSION['site_recovery']['email'] ?? $_SESSION['applicant_pending']['email'] ?? '');
$catalogCourse = null;
if ($page === 'course') foreach ($allPrograms as $candidate) if ((int)$candidate['id'] === (int)($_GET['course'] ?? 0)) {$catalogCourse = $candidate; break;}
$courseQuery = $page === 'courses' ? trim((string)($_GET['q'] ?? '')) : '';
$visiblePrograms = $courseQuery === '' ? $allPrograms : array_values(array_filter($allPrograms, fn($vp) => mb_stripos($vp['name'] . ' ' . $vp['institute_name'], $courseQuery) !== false));
$allowed = ['home', 'about', 'course', 'courses', 'admissions', 'notices', 'faculty', 'facilities', 'contact', 'login', 'create-account', 'brochure'];
if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    $page = 'notfound';
}
$brochureError = null;
if ($page === 'brochure' && ($_GET['format'] ?? '') === 'pdf') {
    try {
        if (!$ready) throw new DomainException('Setup required.');
        dependencies();
        $brows = '';
        foreach ($allPrograms as $bp) $brows .= '<tr><td>' . e($bp['name']) . '</td><td>' . e($bp['institute_name']) . '</td><td>' . e($bp['duration']) . '</td><td>INR ' . number_format((int)$bp['fee_minor'] / 100, 2) . '</td></tr>';
        $bhtml = '<!doctype html><html><head><meta charset="UTF-8"><style>body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#292738;margin:30px}h1{color:#0e2c56;font-size:26px;margin-bottom:2px}p{line-height:1.7}table{width:100%;border-collapse:collapse;margin-top:14px}th,td{padding:8px;border:1px solid #d7dee9;text-align:left}th{background:#eef2f8}.foot{margin-top:20px;font-size:9px;color:#857c91}</style></head><body><h1>' . e($brand) . '</h1><p>' . e($brandKind) . ($brandCity ? ' &middot; ' . e($brandCity) : '') . ($brandPhone ? ' &middot; ' . e($brandPhone) : '') . '</p><p>Approved by AICTE | PCI | Affiliated to MAKAUT, WB. Admissions are verified by the institute office; apply online and track your application from submission to the office decision.</p><table><tr><th>Program</th><th>Institute</th><th>Duration</th><th>Fee</th></tr>' . $brows . '</table><p class="foot">Generated from live institute records. Contact the office to confirm seats, eligibility and dates before applying.</p></body></html>';
        $bcache = $root . '/storage/pdf-cache';
        if (!is_dir($bcache) && !mkdir($bcache, 0700, true) && !is_dir($bcache)) throw new RuntimeException('PDF cache is not writable.');
        $bopt = new \Dompdf\Options();
        $bopt->set('isRemoteEnabled', false);
        $bopt->set('isPhpEnabled', false);
        $bopt->set('isJavascriptEnabled', false);
        $bopt->set('chroot', $root . '/vendor/dompdf/dompdf');
        $bopt->set('tempDir', $bcache);
        $bopt->set('fontCache', $bcache);
        $bpdf = new \Dompdf\Dompdf($bopt);
        $bpdf->loadHtml($bhtml, 'UTF-8');
        $bpdf->setPaper('A4');
        $bpdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="college-brochure.pdf"');
        echo $bpdf->output();
        exit;
    } catch (Throwable $e) {
        $brochureError = 'The PDF download is unavailable right now. You can print this page instead.';
    }
}
$programCount = count($programs);
$tickerItems = [];
$ayStart = (int)date('Y') - ((int)date('n') < 4 ? 1 : 0);
$ayLabel = $ayStart . '–' . substr((string)($ayStart + 1), 2);
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
    [$studentCount, tr('Students'), true],
    [count($teachers), tr('Faculty members'), true],
    [$programCount, tr('Programs open'), false],
    [count($institutes), tr('Campuses'), false],
    [count($cities), tr('Cities'), false],
];
$formSession = $page === 'create-account' ? 'northstar_auth' : 'northstar_site';
$profile = ($_SERVER['REQUEST_METHOD'] === 'GET') ? siteProfile() : null;
siteSession($formSession);
$headerAction = $profile
    ? siteProfileBox($profile)
    : '<a class="u-btn ghost" href="' . e(siteHomeUrl() . '?page=login') . '">' . e(tr('Sign In')) . '</a>';
function uDocHead(string $title, string $brand): void {
<<<<<<< Updated upstream
    global $htmlLang, $page, $brandCity, $brandPhone, $brandAddress, $brandKind;
    $desc = $brand . ' — ' . $brandKind . ' admissions, programs, notices and student services' . ($brandCity !== '' ? ' in ' . $brandCity : '') . '.';
    $org = ['@context' => 'https://schema.org', '@type' => 'EducationalOrganization', 'name' => $brand];
    if ($brandCity !== '' || $brandAddress !== '') $org['address'] = trim($brandAddress . ($brandAddress !== '' && $brandCity !== '' ? ', ' : '') . $brandCity);
    if ($brandPhone !== '') $org['telephone'] = $brandPhone;
    echo '<!doctype html><html lang="' . e($htmlLang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' · ' . e($brand) . '</title>'
        . '<meta name="description" content="' . e($desc) . '">'
        . '<meta name="theme-color" content="#0e2c56">'
        . '<meta property="og:type" content="website"><meta property="og:title" content="' . e($title) . ' · ' . e($brand) . '">'
        . '<meta property="og:description" content="' . e($desc) . '">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/university.css"><link rel="stylesheet" href="assets/future.css">'
        . '<script type="application/ld+json">' . json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>'
        . '<script src="assets/theme.js" defer></script><script src="assets/site.js" defer></script><script src="assets/future.js" defer></script>'
        . '</head><body class="future" data-page="' . e($page) . '"><a class="f-skip" href="#main">' . e(tr('Skip to content')) . '</a>';
=======
    global $htmlLang;
    echo '<!doctype html><html lang="' . e($htmlLang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · ' . e($brand) . '</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/university.css"><link rel="stylesheet" href="assets/college.css"><link rel="stylesheet" href="assets/nimita.css?v=site-theme-v2"><script src="assets/theme.js" defer></script><script src="assets/site.js" defer></script><script src="assets/nimita.js" defer></script></head><body class="college">';
>>>>>>> Stashed changes
}
?>
<?php uDocHead(tr(['home' => 'Admissions open', 'about' => 'About us', 'course' => 'Course details', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'facilities' => 'Campus facilities', 'contact' => 'Contact', 'brochure' => 'College brochure', 'login' => 'Sign in', 'create-account' => 'Create your account', 'notfound' => 'Page not found'][$page] ?? 'Page not found'), $brand); ?>
<?=collegeHeader($brand, $brandKind, $brandCity, $brandPhone, $page, $headerAction, $tickerItems)?>
<main class="f-main" id="main">
<?php if (!$ready): ?>
<section class="f-wrap"><div class="f-page-head"><h1><?= e(tr('Welcome — setup required')) ?></h1><p>This university website is connected to the institute CRM, which has not been installed yet. The server administrator should open the setup wizard to create the database, owner account and first institute. This page updates itself automatically afterwards.</p><p><a class="f-btn primary" href="setup.php"><?= e(tr('Open setup wizard')) ?> →</a></p></div></section>
<?php elseif ($page === 'home'): ?>
<section class="f-hero"<?php if (is_file($root . '/assets/college-hero.jpg')): ?> style="background-image:url('assets/college-hero.jpg')"<?php endif; ?>><div class="f-hero-overlay"></div><div class="f-wrap f-hero-in">
<div class="f-hero-badge"><?php if ($programs): ?><span class="f-pulse"></span><?= e(tr('Admissions open')) ?> <?= e($ayLabel) ?><?php else: ?><?= e(tr('Explore our programs')) ?><?php endif; ?></div>
<h1><?= e(tr('Shaping')) ?> <?= e(tr('Healthcare Leaders')) ?> <span><?= e(tr('for a Healthier Tomorrow')) ?></span></h1>
<p class="f-hero-sub">Build your future with quality <?= e(strtolower($brandKind)) ?> education, practical training, experienced faculty and research opportunities.</p>
<div class="f-hero-cta"><a href="apply.php" class="f-btn primary big"><?= e(tr('Apply for Admission')) ?> →</a><a href="#programs" class="f-btn ghostlight big"><?= e(tr('Explore Programs')) ?> ↓</a></div>
<div class="f-hero-stats">
<div><strong data-count="<?= (int)$studentCount ?>">0</strong><span><?= e(tr('Students')) ?></span></div>
<div><strong data-count="<?= count($teachers) ?>">0</strong><span><?= e(tr('Faculty members')) ?></span></div>
<div><strong data-count="<?= (int)$programCount ?>">0</strong><span><?= e(tr('Programs open')) ?></span></div>
<div><strong data-count="<?= count($institutes) ?>">0</strong><span><?= e(tr('Campuses')) ?></span></div>
</div>
</div></section>
<section class="f-section"><div class="f-wrap">
<div class="f-sec-head f-reveal"><div><div class="f-eyebrow"><?= e(tr('Why choose us')) ?></div><h2><?= e(tr('Welcome to')) ?> <?= e($brand) ?></h2></div><a class="f-btn ghost" href="?page=about"><?= e(tr('Know More About Us')) ?> →</a></div>
<div class="f-trust f-reveal"><span><?= collegeIcon('shield') ?><?= e(tr('Approved by AICTE')) ?></span><span><?= collegeIcon('award') ?><?= e(tr('PCI Approved')) ?></span><span><?= collegeIcon('bank') ?><?= e(tr('Affiliated to MAKAUT, WB')) ?></span></div>
<div class="f-grid cols-4">
<div class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('cap') ?></div><h3><?= e(tr('PCI Approved')) ?></h3><p>Quality pharmacy education with academic compliance.</p></div>
<div class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('users') ?></div><h3>Experienced Faculty</h3><p>Learn from <?= count($teachers) ?> qualified teachers across our campuses.</p></div>
<div class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('flask') ?></div><h3><?= e(tr('Modern Laboratories')) ?></h3><p>Practical training for real healthcare skills.</p></div>
<div class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('case') ?></div><h3><?= e(tr('Placement Support')) ?></h3><p>Career guidance from admission to employment.</p></div>
</div>
</div></section>
<section class="f-section alt"><div class="f-wrap f-split">
<div class="f-reveal"><div class="f-eyebrow"><?= e(tr('Welcome to our institution')) ?></div><h2><?= e($brand) ?></h2>
<p><?= e($brand) ?> is committed to providing quality pharmacy education and developing competent, ethical and responsible healthcare professionals.</p>
<p>Through modern laboratories, experienced teachers, research opportunities and practical exposure, students are prepared to meet the evolving requirements of the pharmaceutical industry and healthcare sector.</p>
<ul class="f-checks"><li>Industry-oriented education</li><li>Experienced teaching faculty</li><li>Practical laboratory training</li><li>Career &amp; placement guidance</li></ul>
<p><a class="f-btn primary" href="?page=about"><?= e(tr('Know More About Us')) ?> →</a></p></div>
<aside class="f-quote f-reveal" id="principal"><div class="f-ico"><?= collegeIcon('book') ?></div><p>"The purpose of education is to prepare students with knowledge, skills and values that allow them to contribute meaningfully to society."</p><div class="f-quote-by"><span class="f-avatar"><?= collegeIcon('user') ?></span><div><strong><?= e(tr("Principal's Message")) ?></strong><span><?= e($brand) ?></span></div></div></aside>
</div></section>
<section class="f-section" id="programs"><div class="f-wrap">
<div class="f-sec-head f-reveal"><div><div class="f-eyebrow"><?= e(tr('Academic Excellence')) ?></div><h2><?= e(tr('Our')) ?> <?= e(tr('Programs')) ?></h2><p>Explore our pharmacy education programs designed to build knowledge, practical skills and professional confidence.</p></div><a class="f-btn ghost" href="?page=courses"><?= e(tr('View all programs')) ?> →</a></div>
<?php if (!$programs): ?><p class="f-muted"><?= e(tr('Admissions are opening soon.')) ?> <?= e(tr('Check')) ?> <a href="?page=notices"><?= e(tr('Notices')) ?></a> <?= e(tr('or contact the office.')) ?></p><?php else: ?><div class="f-grid cols-3"><?php foreach (array_slice($programs, 0, 6) as $p): $short = collegeDegreeShort($p['name']); $pdesc = trim((string)($p['description'] ?? '')); if ($pdesc === '') $pdesc = 'Quality pharmacy education with practical training, experienced faculty and research opportunities.'; ?><article class="f-card f-prog f-reveal"><div class="f-prog-img"><img src="<?= e(collegeProgramImage($p['name'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><span class="f-tag"><?= e(tr('Pharmacy')) ?></span></div><div class="f-prog-body"><h3><?= e($short !== '' ? $short : $p['name']) ?></h3><?php if ($short !== ''): ?><h4><?= e($p['name']) ?></h4><?php endif; ?><div class="f-meta"><span><?= collegeIcon('clock') ?><?= e($p['duration']) ?></span><span><?= collegeIcon('flask') ?><?= e(tr('Practical')) ?></span></div><p><?= e(mb_substr($pdesc, 0, 110)) ?><?= mb_strlen($pdesc) > 110 ? '…' : '' ?></p><a class="f-more" href="student.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Learn More')) ?> →</a></div></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<section class="f-band"><div class="f-wrap f-band-grid">
<div class="f-reveal"><strong data-count="<?= (int)$studentCount ?>">0</strong><span><?= e(tr('Students')) ?></span></div>
<div class="f-reveal"><strong data-count="<?= count($teachers) ?>">0</strong><span><?= e(tr('Faculty members')) ?></span></div>
<div class="f-reveal"><strong data-count="<?= (int)$programCount ?>">0</strong><span><?= e(tr('Programs open')) ?></span></div>
<div class="f-reveal"><strong data-count="<?= count($institutes) ?>">0</strong><span><?= e(tr('Campuses')) ?></span></div>
</div></section>
<section class="f-section"><div class="f-wrap f-split">
<div class="f-reveal"><div class="f-eyebrow"><?= e(tr('Latest notices')) ?></div><h2><?= e(tr('Notices & dates')) ?></h2>
<?php if (!$notices): ?><p class="f-muted"><?= e(tr('No admission notices right now.')) ?></p><?php else: ?><div class="f-updates"><?php foreach (array_slice($notices, 0, 5) as $n): $nts = strtotime((string)$n['closes']) ?: time(); ?><div class="f-update"><div class="f-date"><strong><?= e(date('d', $nts)) ?></strong><small><?= e(date('M', $nts)) ?></small></div><div><a href="student.php?page=course&course=<?= (int)$n['id'] ?>">Admissions <?= $n['closing'] ? 'closing soon' : 'open' ?>: <?= e($n['course']) ?></a><?php if ($n['closing']): ?> <span class="f-pill soon"><?= e(tr('NEW')) ?></span><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?>
<p><a class="f-btn ghost" href="?page=notices"><?= e(tr('All notices')) ?> →</a></p></div>
<div class="f-reveal"><div class="f-eyebrow"><?= e(tr('Campus life')) ?></div><h2><?= e(tr('Life at Campus')) ?></h2><div class="f-gallery"><img src="assets/campus-innovation.jpg" alt="Pharmacy research laboratory" loading="lazy"><img src="assets/campus-learning.jpg" alt="Students learning together" loading="lazy"><img src="assets/campus-sports.jpg" alt="Sports on campus" loading="lazy"></div><p><a class="f-btn ghost" href="?page=facilities"><?= e(tr('Facilities')) ?> →</a></p></div>
</div></section>
<section class="f-cta"><div class="f-wrap f-cta-in f-reveal"><div><h2><?= e(tr('Begin Your Journey in Pharmacy')) ?></h2><p>Applications are now open. Start your application online today.</p></div><div><a href="apply.php" class="f-btn accent big"><?= e(tr('Apply Online Now')) ?> →</a></div></div></section>
<?php elseif ($page === 'about'): ?>
<<<<<<< Updated upstream
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow"><?= e(tr('About us')) ?></div><h1><?= e($brand) ?></h1><p><?= e($brandKind) ?> programs across <?= count($institutes) ?: 'our' ?> <?= count($institutes) === 1 ? 'campus' : 'campuses' ?><?= $cities ? ' in ' . e(implode(', ', $cities)) : '' ?>. Every figure on this website comes live from the institute CRM — programs, dates, campuses and contacts update automatically when the office updates its records.</p></div>
<div class="f-grid cols-3">
<div class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('target') ?></div><h3>Our Mission</h3><p>To provide accessible, quality pharmacy education that builds professional competence, ethical values and research ability.</p></div>
<div class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('eye') ?></div><h3>Our Vision</h3><p>A healthier tomorrow led by skilled pharmacy professionals serving industry, healthcare and society.</p></div>
<div class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('heart') ?></div><h3>Our Values</h3><p>Knowledge, skill and service — learning that goes beyond textbooks into real community healthcare.</p></div>
</div>
</section>
<div class="f-band"><div class="f-wrap f-band-grid">
<?php foreach ($stats as [$n, $label]): ?><div><strong data-count="<?= (int)$n ?>">0</strong><span><?= e($label) ?></span></div><?php endforeach; ?>
</div></div>
<section class="f-section" id="campuses"><div class="f-wrap">
<div class="f-sec-head f-reveal"><div><div class="f-eyebrow"><?= e(tr('OUR CAMPUSES')) ?></div><h2><?= e(tr('Where you will study')) ?></h2></div></div>
<?php if (!$institutes): ?><p class="f-muted">Campus details will appear here once the office adds institutes.</p><?php else: ?><div class="f-grid cols-3"><?php foreach ($institutes as $i): ?><article class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('pin') ?></div><h3><?= e($i['name']) ?></h3><p class="f-meta"><?= e($i['kind']) ?> · <?= e($i['city']) ?></p><?php if (trim($i['address']) !== ''): ?><p><?= e($i['address']) ?></p><?php endif; ?><p><a class="f-more" href="tel:<?= e(preg_replace('/[^+0-9]/', '', (string)$i['phone'])) ?>"><?= collegeIcon('phone') ?> <?= e($i['phone']) ?></a></p></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
=======
<section class="about-hero"><div class="container about-hero-inner"><div><div class="hero-label">ABOUT <?= e(mb_strtoupper($brand)) ?></div><h1>Building skilled, ethical <span>healthcare professionals</span></h1><p><?= e($brand) ?> brings together academic learning, practical training and a commitment to better health outcomes for every community we serve.</p><div class="hero-buttons"><a href="apply.php" class="btn btn-green">Apply for Admission <i class="fa-solid fa-arrow-right"></i></a><a href="#campuses" class="btn btn-outline">Explore Our Campus <i class="fa-solid fa-arrow-down"></i></a></div></div></div></section>
<section class="section about-intro"><div class="container about-intro-grid"><div class="about-image"><img src="assets/campus-community.jpg" alt="Students at <?= e($brand) ?>" loading="lazy"><div class="about-image-note"><i class="fa-solid fa-graduation-cap"></i><strong>Education with purpose</strong><span>Knowledge · Skill · Service</span></div></div><div class="about-copy"><div class="small-title">WELCOME TO OUR INSTITUTION</div><h2 class="section-title">Learn. Lead. <span>Serve.</span></h2><p><?= e($brand) ?> offers <?= e(mb_strtolower($brandKind)) ?> education in an environment designed for curiosity, confidence and professional growth.</p><p>Our students learn through experienced mentorship, modern laboratories and practical exposure that connect classroom knowledge with the needs of the healthcare sector.</p><div class="about-checks"><div><i class="fa-solid fa-circle-check"></i> Student-centred learning</div><div><i class="fa-solid fa-circle-check"></i> Practical professional training</div><div><i class="fa-solid fa-circle-check"></i> Ethical healthcare values</div><div><i class="fa-solid fa-circle-check"></i> Career-focused guidance</div></div></div></div></section>
<section class="about-values"><div class="container"><div class="section-heading"><div class="small-title">OUR FOUNDATION</div><h2 class="section-title">What guides <span>every learner</span></h2><p>A strong academic foundation, meaningful practical experience and a lasting responsibility to society.</p></div><div class="about-value-grid"><article><i class="fa-solid fa-book-open"></i><h3>Academic Excellence</h3><p>Clear concepts, disciplined study and high standards for professional learning.</p></article><article><i class="fa-solid fa-flask"></i><h3>Practical Learning</h3><p>Laboratory work and real-world exposure that turn knowledge into confidence.</p></article><article><i class="fa-solid fa-hand-holding-heart"></i><h3>Service &amp; Ethics</h3><p>Responsible healthcare professionals who care for people and communities.</p></article></div></div></section>
<section class="stats about-stats"><div class="container"><div class="stats-grid"><?php foreach ($stats as [$n, $label]): ?><div class="stat"><i class="fa-solid fa-circle-check"></i><div class="counter" data-target="<?= (int)$n ?>"><?= (int)$n ?></div><p><?= e($label) ?></p></div><?php endforeach; ?></div></div></section>
<section class="section about-campus" id="campuses"><div class="container"><div class="section-heading"><div class="small-title">OUR CAMPUSES</div><h2 class="section-title">A place to <span>learn and grow</span></h2><p>Explore the academic spaces and support available to every learner.</p></div><?php if (!$institutes): ?><p class="update-text">Campus details will appear here once the office adds institutes.</p><?php else: ?><div class="about-campus-grid"><?php foreach ($institutes as $i): ?><article class="about-campus-card"><div class="about-campus-icon"><i class="fa-solid fa-building-columns"></i></div><div><h3><?= e($i['name']) ?></h3><p class="about-campus-meta"><?= e($i['kind']) ?> · <?= e($i['city']) ?></p><?php if (trim($i['address']) !== ''): ?><p><?= e($i['address']) ?></p><?php endif; ?><a href="?page=contact" class="learn-more">Contact campus <i class="fa-solid fa-arrow-right"></i></a></div></article><?php endforeach; ?></div><?php endif; ?></div></section>
<section class="cta"><div class="container cta-inner"><div><h2>Shape Your Future in Pharmacy</h2><p>Join a learning community built for knowledge, skills and service.</p></div><div><a href="apply.php" class="btn btn-green">Apply Online Now <i class="fa-solid fa-arrow-right"></i></a></div></div></section>
>>>>>>> Stashed changes
<?php elseif ($page === 'course' && $catalogCourse): ?>
<section class="f-wrap"><div class="f-crumb f-reveal"><a href="?page=courses"><?= e(tr('Programs')) ?></a> › <?= e($catalogCourse['name']) ?></div>
<div class="f-detail f-reveal"><div class="f-eyebrow"><?= e($catalogCourse['institute_name']) ?></div><h1><?= e($catalogCourse['name']) ?></h1>
<p class="f-meta"><span><?= collegeIcon('clock') ?><?= e($catalogCourse['duration']) ?></span><span><?= collegeIcon('pin') ?><?= e($catalogCourse['city']) ?></span><span><?= collegeIcon('card') ?>₹<?= number_format((int)$catalogCourse['fee_minor'] / 100, 2) ?></span></p>
<h2>About this course</h2><p><?= nl2br(e((string)($catalogCourse['description'] ?? ''))) ?></p>
<?php if ((int)$catalogCourse['admission_open'] === 1): ?><p><span class="f-pill open"><?= e(tr('Open')) ?></span></p><p>Applications close <?= e($catalogCourse['closes_on']) ?>. Review the eligibility and privacy notice in the secure portal before submitting.</p><p><a class="f-btn primary" href="student.php?page=course&course=<?= (int)$catalogCourse['id'] ?>"><?= e(tr('Details & Apply')) ?> →</a></p><?php else: ?><p class="f-muted">Admissions are not open for this course right now.</p><p><a class="f-btn ghost" href="?page=courses">Back to all programs</a></p><?php endif; ?></div></section>
<?php elseif ($page === 'courses'): ?>
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow"><?= e(tr('Programs & courses')) ?></div><h1><?= count($visiblePrograms) ?> <?= e(tr('Programs')) ?></h1><p>Explore every active course. Courses with an open admission listing can be applied for securely through the student portal.</p>
<form class="f-search" action="index.php" method="get" role="search"><input type="hidden" name="page" value="courses"><input type="search" name="q" value="<?= e($courseQuery) ?>" placeholder="<?= e(tr('Search programs')) ?>" aria-label="<?= e(tr('Search programs')) ?>"><button class="f-btn primary" type="submit"><?= e(tr('Search')) ?></button></form></div>
<?php if (!$visiblePrograms): ?><p class="f-muted"><?= $courseQuery !== '' ? e(tr('No programs match your search.')) : e(tr('No active courses are available yet.')) ?></p><?php else: ?><div class="f-grid cols-3"><?php foreach ($visiblePrograms as $p): $short = collegeDegreeShort($p['name']); $open = (int)$p['admission_open'] === 1; ?><article class="f-card f-prog f-reveal"><div class="f-prog-img"><img src="<?= e(collegeProgramImage($p['name'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><?php if ($open): ?><span class="f-tag"><?= e(tr('Open')) ?></span><?php endif; ?></div><div class="f-prog-body"><div class="f-eyebrow"><?= e($p['institute_name']) ?></div><h3><?= e($short !== '' ? $short : $p['name']) ?></h3><?php if ($short !== ''): ?><h4><?= e($p['name']) ?></h4><?php endif; ?><div class="f-meta"><span><?= collegeIcon('clock') ?><?= e($p['duration']) ?></span><span><?= collegeIcon('pin') ?><?= e($p['city']) ?></span></div><p><?= e(mb_substr((string)($p['description'] ?? ''), 0, 140)) ?><?= mb_strlen((string)($p['description'] ?? '')) > 140 ? '…' : '' ?></p><p class="f-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><?php if ($open): ?><p class="f-apply-by"><?= e(tr('Apply by')) ?> <?= e($p['closes_on']) ?></p><p><a class="f-btn primary" href="student.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Details & Apply')) ?> →</a></p><?php else: ?><p><a class="f-btn ghost" href="?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Details')) ?> →</a></p><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'admissions'): ?>
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow"><?= e(tr('Admissions')) ?></div><h1><?= e(tr('How to join')) ?></h1><p><a class="f-btn primary big" href="apply.php"><?= e(tr('Start your application')) ?> →</a> <a class="f-btn ghost big" href="?page=login&show=create"><?= e(tr('Create account')) ?></a></p></div>
<ol class="f-steps">
<li class="f-reveal"><span>1</span><div><strong><?= e(tr('Create your account')) ?></strong><p>Register with your email and verify the code.</p></div></li>
<li class="f-reveal"><span>2</span><div><strong><?= e(tr('Apply online')) ?></strong><p>One simple form for your chosen program.</p></div></li>
<li class="f-reveal"><span>3</span><div><strong>Upload certificates</strong><p>Add mark sheets when the office enables uploads.</p></div></li>
<li class="f-reveal"><span>4</span><div><strong>Office verification</strong><p>Eligibility and originals checked by the office.</p></div></li>
<li class="f-reveal"><span>5</span><div><strong>Track &amp; join</strong><p>Follow your status, then open your student portal.</p></div></li>
</ol></section>
<section class="f-section alt" id="eligibility"><div class="f-wrap">
<div class="f-eyebrow"><?= e(tr('ELIGIBILITY & DATES')) ?></div><h2><?= e(tr('What you need to know')) ?></h2>
<p>D.Pharm applicants typically need <strong>10+2 with Physics, Chemistry and Biology/Mathematics</strong>. Final eligibility, seats and document verification are confirmed by the office. Never pay anyone outside the official student portal after admission.</p>
<?php if ($programs): ?><div class="f-table-wrap"><table class="f-table"><thead><tr><th><?= e(tr('Program')) ?></th><th><?= e(tr('Campus')) ?></th><th><?= e(tr('Fee')) ?></th><th><?= e(tr('Apply by')) ?></th><th></th></tr></thead><tbody><?php foreach ($programs as $p): ?><tr><td><strong><?= e($p['name']) ?></strong></td><td><?= e($p['institute_name']) ?></td><td>₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></td><td><?= e($p['closes_on']) ?></td><td><a class="f-more" href="apply.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Apply')) ?> →</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<p><a class="f-btn ghost" href="?page=notices"><?= e(tr('Important Dates')) ?> →</a></p>
</div></section>
<?php elseif ($page === 'notices'): ?>
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow"><?= e(tr('Notices & dates')) ?></div><h1><?= e(tr('Admission notices')) ?></h1><p>Published automatically from live admission dates in the CRM. Detailed office notices for enrolled students appear inside the student portal.</p></div>
<?php if (!$notices): ?><p class="f-muted"><?= e(tr('No notices right now.')) ?></p><?php else: ?>
<div class="f-notice-bar f-reveal"><div class="f-search"><input type="search" data-notice-search placeholder="<?= e(tr('Search notices')) ?>" aria-label="<?= e(tr('Search notices')) ?>"></div>
<div class="f-chips" role="group" aria-label="<?= e(tr('Notices')) ?>">
<button class="f-chip" data-cat-filter="all" aria-pressed="true"><?= e(tr('All')) ?> (<?= count($notices) ?>)</button>
<button class="f-chip" data-cat-filter="open" aria-pressed="false"><?= e(tr('Open')) ?> (<?= count(array_filter($notices, fn($x) => !$x['closing'])) ?>)</button>
<button class="f-chip" data-cat-filter="closing" aria-pressed="false"><?= e(tr('Closing soon')) ?> (<?= count(array_filter($notices, fn($x) => $x['closing'])) ?>)</button>
</div></div>
<p class="f-muted"><span data-notice-count><?= count($notices) ?></span> <?= e(tr('Notices')) ?></p>
<div data-notice-list><?php foreach ($notices as $n): ?><article class="f-notice f-reveal" data-cat="<?= $n['closing'] ? 'closing' : 'open' ?>"><span class="f-pill <?= $n['closing'] ? 'soon' : 'open' ?>"><?= e(tr($n['closing'] ? 'Closing soon' : 'Open')) ?></span><div><strong>Admissions <?= $n['closing'] ? 'closing soon' : 'open' ?>: <?= e($n['course']) ?></strong><small><?= e($n['institute']) ?> · <?= e(tr('Last date')) ?> <?= e($n['closes']) ?></small></div><a class="f-btn ghost" href="apply.php?page=course&course=<?= (int)$n['id'] ?>"><?= e(tr('Apply')) ?> →</a></article><?php endforeach; ?></div>
<p class="f-muted" data-notice-empty style="display:none"><?= e(tr('No notices right now.')) ?></p><?php endif; ?></section>
<?php elseif ($page === 'faculty'): ?>
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow"><?= e(tr('Faculty')) ?></div><h1><?= e(tr('Learn from experienced teachers')) ?></h1><p>Directory updates automatically from the CRM teaching roster. Contact details are shared by the office on request.</p></div>
<?php if (!$teachers): ?><p class="f-muted"><?= e(tr('The faculty directory will appear here once the office adds teachers.')) ?></p><?php else: ?><div class="f-grid cols-3"><?php foreach ($teachers as $t): ?><article class="f-card f-teacher f-reveal"><span class="f-avatar"><?= e(strtoupper(substr($t['name'], 0, 1))) ?></span><div><h3><?= e($t['name']) ?></h3><p class="f-meta"><?= e($t['qualification']) ?></p><p class="f-meta"><?= e($t['institute_name']) ?></p></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'facilities'): ?>
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow"><?= e(tr('Campus Life')) ?></div><h1><?= e(tr('Facilities')) ?></h1><p>Learning spaces and student services that support everyday campus life. Contact the office for the latest details about any facility.</p></div>
<div class="f-grid cols-3">
<article class="f-card f-reveal" id="labs"><div class="f-ico"><?= collegeIcon('flask') ?></div><h3><?= e(tr('Laboratories')) ?></h3><p>Practical training laboratories for pharmaceutical experiments and hands-on learning.</p></article>
<article class="f-card f-reveal" id="library"><div class="f-ico"><?= collegeIcon('book') ?></div><h3><?= e(tr('Library')) ?></h3><p>Study resources, reference books and a quiet reading environment for students.</p></article>
<article class="f-card f-reveal" id="hostel"><div class="f-ico"><?= collegeIcon('home') ?></div><h3><?= e(tr('Hostel')) ?></h3><p>Accommodation support for outstation students. Seat availability is confirmed by the office.</p></article>
<article class="f-card f-reveal" id="transport"><div class="f-ico"><?= collegeIcon('bus') ?></div><h3><?= e(tr('Transport')) ?></h3><p>Transport assistance on major routes. Timings and routes are confirmed by the office.</p></article>
<article class="f-card f-reveal" id="activities"><div class="f-ico"><?= collegeIcon('heart') ?></div><h3><?= e(tr('Student Activities')) ?></h3><p>Sports, cultural events and community health programs through the academic year.</p></article>
<article class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('phone') ?></div><h3><?= e(tr('Get in touch')) ?></h3><p>Ask the office about facilities, visits and admissions.</p><p><a class="f-btn ghost" href="?page=contact"><?= e(tr('Contact Us')) ?> →</a></p></article>
</div></section>
<?php elseif ($page === 'contact'): ?>
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow"><?= e(tr('Contact')) ?></div><h1><?= e(tr('Visit or call us')) ?></h1><p>Addresses and phone numbers come live from the CRM — always current.</p><p><a class="f-btn primary" href="?page=login&show=inquiry"><?= e(tr('Admission Inquiry')) ?> →</a></p></div>
<?php if (!$institutes): ?><p class="f-muted"><?= e(tr('Contact details will appear here soon.')) ?></p><?php else: ?><div class="f-grid cols-3"><?php foreach ($institutes as $i): ?><article class="f-card f-reveal"><div class="f-ico"><?= collegeIcon('pin') ?></div><h3><?= e($i['name']) ?></h3><?php if (trim($i['address']) !== ''): ?><p><?= collegeIcon('pin') ?> <?= e($i['address']) ?>, <?= e($i['city']) ?></p><?php else: ?><p><?= collegeIcon('pin') ?> <?= e($i['city']) ?></p><?php endif; ?><p><a class="f-more" href="tel:<?= e(preg_replace('/[^+0-9]/', '', (string)$i['phone'])) ?>"><?= collegeIcon('phone') ?> <?= e($i['phone']) ?></a></p><p><a class="f-btn ghost" href="?page=admissions"><?= e(tr('Admission help')) ?> →</a></p></article><?php endforeach; ?></div><?php endif; ?>
<p class="f-muted">Never share passwords or OTP codes with anyone, including callers claiming to be the office.</p></section>
<?php elseif ($page === 'brochure'): ?>
<section class="f-wrap"><div class="f-page-head f-reveal"><div class="f-eyebrow">College brochure</div><h1><?= e($brand) ?></h1></div>
<?php if ($brochureError): ?><div class="u-alert error" role="alert"><?= e($brochureError) ?></div><?php endif; ?>
<p><a class="f-btn primary" href="?page=brochure&format=pdf">Download PDF ↓</a></p><p class="f-muted">Generated from live institute records, or use your browser print function on this page.</p>
<div class="f-detail"><h2><?= e($brand) ?></h2><p><?= e($brandKind) ?><?= $brandCity ? ' · ' . e($brandCity) : '' ?><?= $brandPhone ? ' · ' . e($brandPhone) : '' ?></p><p>Approved by AICTE | PCI | Affiliated to MAKAUT, WB.</p><p>Admissions are verified by the institute office. Apply online and track your application from submission to the office decision.</p>
<?php if ($allPrograms): ?><div class="f-table-wrap"><table class="f-table"><thead><tr><th>Program</th><th>Institute</th><th>Duration</th><th>Fee</th></tr></thead><tbody><?php foreach ($allPrograms as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= e($p['institute_name']) ?></td><td>₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<p>Contact the office to confirm seats, eligibility and dates before applying.</p></div></section>
<?php elseif ($page === 'login'): ?>
<section class="f-auth"><div class="f-wrap f-auth-grid">
<aside class="f-auth-side f-reveal"><div class="f-eyebrow"><?= e(tr('ONE SIGN-IN FOR EVERYONE')) ?></div><h2><?= e(tr('One account for everything')) ?></h2>
<ul class="f-checks"><li>Students — sign in with a secure email code</li><li>New students — create a free account in a minute</li><li>Applicants — track an application with your email</li><li>Staff — reach the office dashboard with your email</li></ul>
<p><a class="f-btn ghostlight" href="?page=login&show=inquiry"><?= e(tr('Admission Inquiry')) ?> →</a></p></aside>
<div class="u-login f-reveal"><div class="u-card"><div class="u-eyebrow"><?= e(tr('ONE SIGN-IN FOR EVERYONE')) ?></div><h1><?= e(tr('Sign in')) ?></h1><p><?= e(tr('Enter your email.')) ?> <?= e(tr('We will send a secure verification code.')) ?></p>
<?php if ($siteError): ?><div class="u-alert error" role="alert"><?= e($siteError) ?></div><?php endif; ?>
<?php if ($siteFlash): ?><div class="u-alert" role="status"><?= e($siteFlash) ?></div><?php endif; ?>
<?php if ($siteStep === 'code'): ?>
<p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e($otpEmail) ?></strong>.</p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_verify"><label><?= e(tr('Enter the 6-digit code')) ?><input type="text" name="code" inputmode="numeric" maxlength="20" required autocomplete="one-time-code" placeholder="123456"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & sign in')) ?> →</button></form>
<div class="u-otp-alt"><form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_start"><input type="hidden" name="email" value="<?= e($otpEmail) ?>"><button class="u-link-button" type="submit"><?= e(tr('Resend code')) ?></button></form><span>·</span><a href="?page=login"><?= e(tr('Start over')) ?></a></div>
<?php elseif ($siteStep === 'chooser'): ?>
<div class="u-alert" role="status"><?= e(tr('No account found for')) ?> <strong><?= e($siteEmail) ?></strong>. <?= e(tr('New student?')) ?></div>
<h3><?= e(tr('Choose how to continue')) ?></h3>
<div class="u-account-btns"><button type="button" class="u-btn solid" data-modal-open="modal-create"><?= e(tr('I am a student — create my account')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-inquiry"><?= e(tr('I want to enquire about admission')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-applicant"><?= e(tr('I applied — track my application')) ?></button></div>
<p><?= e(tr('or')) ?> <a href="apply.php"><?= e(tr('apply for admission')) ?></a>. <?= e(tr('Staff should contact the administrator.')) ?></p>
<p><a href="?page=login"><?= e(tr('Back to sign in')) ?></a></p>
<?php elseif ($siteStep === 'staff_password'): ?>
<h3><?= e(tr('Staff password sign-in')) ?></h3>
<p><?= e(tr('Your institute uses password sign-in for staff. Continue below:')) ?></p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="password_login"><input type="hidden" name="email" value="<?= e($siteEmail) ?>"><label><?= e(tr('Your email address')) ?><input type="email" value="<?= e($siteEmail) ?>" disabled></label><label><?= e(tr('Password')) ?><input type="password" name="password" maxlength="72" required autocomplete="current-password"></label><button class="u-btn solid big" type="submit"><?= e(tr('Sign in to office')) ?> →</button></form>
<p><a href="?page=login"><?= e(tr('Back to sign in')) ?></a></p>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_start"><label><?= e(tr('Your email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email" value="<?= e($siteEmail) ?>" placeholder="you@example.com"></label><button class="u-btn solid big" type="submit"><?= e(tr('Send sign-in code')) ?> →</button></form>
<div class="u-account-btns"><button type="button" class="u-btn ghost" data-modal-open="modal-create"><?= e(tr('Create Student Account')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-inquiry"><?= e(tr('Admission Inquiry')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-recovery"><?= e(tr('Forgot password?')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-applicant"><?= e(tr('Track application')) ?></button></div>
<noscript><p><a href="?page=login&amp;show=create"><?= e(tr('Create Student Account')) ?></a> · <a href="?page=login&amp;show=inquiry"><?= e(tr('Admission Inquiry')) ?></a> · <a href="?page=login&amp;show=recovery"><?= e(tr('Forgot password?')) ?></a> · <a href="?page=login&amp;show=applicant"><?= e(tr('Track application')) ?></a></p></noscript>
<?php endif; ?>
</div></div>
</div></section>
<div class="u-modal" id="modal-create"<?= $show === 'create' ? ' data-open="1"' : '' ?>><div class="u-modal-card" role="dialog" aria-modal="true" aria-label="<?= e(tr('Create your student account')) ?>"><button type="button" class="u-modal-close" data-modal-close aria-label="Close">×</button><div class="u-eyebrow"><?= e(tr('Create Student Account')) ?></div><h2><?= e(tr('Create your student account')) ?></h2><p><?= e(tr('Verify your Gmail to create your account.')) ?></p>
<?php if (!function_exists('studentUsersReady') || !studentUsersReady()): ?>
<p>Registration is not ready yet. The institute must run the upgrade first.</p>
<?php elseif ($siteStep === 'create_code'): ?>
<p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e($otpEmail) ?></strong>.</p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_verify"><label><?= e(tr('Your verification code')) ?><input type="text" name="code" inputmode="numeric" maxlength="20" required autocomplete="one-time-code" placeholder="123456"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & sign in')) ?> →</button></form>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_start"><label><?= e(tr('Full name')) ?><input type="text" name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Home address')) ?><input type="text" name="address" maxlength="300" required autocomplete="street-address"></label><label><?= e(tr('Mobile number')) ?><input type="text" name="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" minlength="10" required autocomplete="tel" placeholder="10-digit mobile number"></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><input type="text" name="website" value="" class="u-honey" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="u-btn solid big" type="submit"><?= e(tr('Send verification code')) ?> →</button></form>
<?php endif; ?></div></div>
<div class="u-modal" id="modal-recovery"<?= $show === 'recovery' ? ' data-open="1"' : '' ?>><div class="u-modal-card" role="dialog" aria-modal="true" aria-label="<?= e(tr('Account recovery')) ?>"><button type="button" class="u-modal-close" data-modal-close aria-label="Close">×</button><div class="u-eyebrow"><?= e(tr('Forgot password?')) ?></div><h2><?= e(tr('Account recovery')) ?></h2><p><?= e(tr('Verify your Gmail to recover access.')) ?></p>
<?php if ($siteStep === 'recovery_code'): ?>
<p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e($otpEmail) ?></strong>.</p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="recovery_verify"><label><?= e(tr('Your verification code')) ?><input type="text" name="code" inputmode="numeric" maxlength="20" required autocomplete="one-time-code" placeholder="123456"></label><button class="u-btn solid big" type="submit"><?= e(tr('Recover access')) ?> →</button></form>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="recovery_start"><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><button class="u-btn solid big" type="submit"><?= e(tr('Send verification code')) ?> →</button></form>
<?php endif; ?></div></div>
<div class="u-modal" id="modal-inquiry"<?= $show === 'inquiry' ? ' data-open="1"' : '' ?>><div class="u-modal-card" role="dialog" aria-modal="true" aria-label="<?= e(tr('Admission Inquiry')) ?>"><button type="button" class="u-modal-close" data-modal-close aria-label="Close">×</button><div class="u-eyebrow"><?= e(tr('Admission Inquiry')) ?></div><h2><?= e(tr('Admission Inquiry')) ?></h2><p><?= e(tr('Tell us about yourself and the office will respond.')) ?></p>
<?php if ($siteStep === 'inquiry_done'): ?>
<div class="u-alert" role="status"><?= e(tr('Your inquiry has been received. The office will contact you soon.')) ?></div>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="inquiry_save"><label><?= e(tr('Full name')) ?><input type="text" name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Mobile number')) ?><input type="text" name="phone" maxlength="30" required autocomplete="tel" placeholder="+91 "></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><label><?= e(tr('Home address')) ?><input type="text" name="address" maxlength="300" autocomplete="street-address"></label><label><?= e(tr('Program of interest')) ?><select name="course_id" required><option value="">—</option><?php foreach (siteCourseOptions() as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> — <?= e($c['iname']) ?></option><?php endforeach; ?></select></label><label><?= e(tr('Your message (optional)')) ?><textarea name="message" maxlength="1000" rows="3"></textarea></label><input type="text" name="website" value="" class="u-honey" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="u-btn solid big" type="submit"><?= e(tr('Send inquiry')) ?> →</button></form>
<?php endif; ?></div></div>
<div class="u-modal" id="modal-applicant"<?= $show === 'applicant' ? ' data-open="1"' : '' ?>><div class="u-modal-card" role="dialog" aria-modal="true" aria-label="<?= e(tr('Track application')) ?>"><button type="button" class="u-modal-close" data-modal-close aria-label="Close">×</button><div class="u-eyebrow"><?= e(tr('Track application')) ?></div><h2><?= e(tr('Track application')) ?></h2><p><?= e(tr('Enter the email from your application. We will send a verification code.')) ?></p>
<?php if ($siteStep === 'applicant_code'): ?>
<p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e($otpEmail) ?></strong>.</p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="applicant_verify"><label><?= e(tr('Your verification code')) ?><input type="text" name="code" inputmode="numeric" maxlength="20" required autocomplete="one-time-code" placeholder="123456"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & view application')) ?> →</button></form>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="applicant_start"><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email" value="<?= e($otpEmail) ?>"></label><input type="text" name="website" value="" class="u-honey" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="u-btn solid big" type="submit"><?= e(tr('Send verification code')) ?> →</button></form>
<?php endif; ?></div></div>
<?php elseif ($page === 'create-account'): ?>
<section class="f-auth"><div class="f-wrap f-auth-grid">
<aside class="f-auth-side f-reveal"><div class="f-eyebrow">NEW STUDENT ACCOUNT</div><h2><?= e(tr('Create your account')) ?></h2>
<ul class="f-checks"><li>One account for applications and the student portal</li><li>Verified by a secure email code</li><li>Track every application step online</li></ul>
<p><a class="f-btn ghostlight" href="?page=login"><?= e(tr('Back to sign in')) ?> →</a></p></aside>
<div class="u-login f-reveal"><div class="u-card"><div class="u-eyebrow">NEW STUDENT ACCOUNT</div><h1>Create your account</h1><p>Use your email to create a secure account. We will send a verification code.</p>
<?php if ($siteError): ?><div class="u-alert error" role="alert"><?= e($siteError) ?></div><?php endif; ?><?php if ($siteFlash): ?><div class="u-alert" role="status"><?= e($siteFlash) ?></div><?php endif; ?>
<?php if ($siteStep === 'create_code' || isset($_SESSION['suid_pending'])): ?><p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e((string)($_SESSION['suid_pending']['email'] ?? 'your email')) ?></strong>.</p><form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_verify"><label><?= e(tr('Enter the 6-digit code')) ?><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required class="u-code"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & sign in')) ?> →</button></form><p class="u-login-create"><a href="?page=create-account">Start over</a></p>
<?php else: ?><form method="post" class="u-form"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_start"><label><?= e(tr('Full name')) ?><input name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Mobile number')) ?><input name="phone" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" minlength="10" required autocomplete="tel" placeholder="10-digit mobile number"></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><label><?= e(tr('Home address')) ?><input name="address" maxlength="300" autocomplete="street-address"></label><button class="u-btn solid big" type="submit">Send verification code →</button></form><p class="u-login-create"><a href="?page=login">Already have an account? Sign in</a></p><?php endif; ?></div></div>
</div></section>
<?php else: ?>
<section class="f-wrap"><div class="f-page-head"><div class="f-eyebrow">404</div><h1><?= e(tr('Page not found')) ?></h1><p><?= e(tr('This university page does not exist.')) ?></p><p><a class="f-btn primary" href="?"><?= e(tr('Back to home')) ?> →</a></p></div></section>
<?php endif; ?>
</main>
<?=collegeFooter($brand, $brandKind, $brandCity, $brandAddress, $brandPhone)?>
</body></html>
