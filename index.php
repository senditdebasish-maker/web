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
$siteHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (getenv('CRM_TRUST_HTTPS_PROXY') === '1' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$siteCookie = ['expires' => time() + 15552000, 'path' => '/', 'secure' => $siteHttps, 'samesite' => $siteHttps ? 'None' : 'Lax'];
if (($_GET['lang'] ?? '') === 'hi') { $siteLang = 'hi'; setcookie('uni_lang', 'hi', $siteCookie); }
elseif (($_GET['lang'] ?? '') === 'en') { setcookie('uni_lang', 'en', $siteCookie); }
elseif (($_COOKIE['uni_lang'] ?? '') === 'hi') { $siteLang = 'hi'; }
$htmlLang = $siteLang === 'hi' ? 'hi' : 'en';
$HI = ['Home' => 'होम', 'About' => 'हमारे बारे में', 'Programs' => 'कार्यक्रम', 'Admissions' => 'प्रवेश', 'Notices' => 'सूचनाएं', 'Faculty' => 'शिक्षक', 'Contact' => 'संपर्क करें',
'About us' => 'हमारे बारे में', 'Programs & courses' => 'कार्यक्रम व पाठ्यक्रम', 'Notices & dates' => 'सूचनाएं व तिथियां', 'Campus' => 'परिसर', 'Program' => 'कार्यक्रम', 'Fee' => 'शुल्क',
'Sign In' => 'साइन इन', 'Sign in' => 'साइन इन', 'Apply Now' => 'अभी आवेदन करें', 'Apply' => 'आवेदन करें', 'Apply for Admission' => 'प्रवेश हेतु आवेदन करें', 'Apply online' => 'ऑनलाइन आवेदन', 'Apply by' => 'अंतिम तिथि',
'ADMISSIONS 2024-25 OPEN FOR UNDERGRADUATE PROGRAMS' => 'स्नातक कार्यक्रमों में प्रवेश 2024-25 प्रारंभ', 'SCHOLARSHIPS 2024-25' => 'छात्रवृत्ति 2024-25', 'SEMESTER RESULTS DECLARED' => 'सेमेस्टर परिणाम घोषित',
'TRADITION MEETS INNOVATION: EST. 1887' => 'परंपरा से नवाचार तक: स्थापित 1887', 'Nurturing global leaders.' => 'वैश्विक नेताओं का निर्माण।',
'Explore Programs' => 'कार्यक्रम देखें', 'Student / Office Sign In' => 'छात्र / कार्यालय साइन इन', 'ACCREDITED' => 'प्रत्यायित', 'RANKED #5' => 'रैंक #5', 'APPROVED' => 'अनुमोदित',
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
'Verify & view application' => 'सत्यापित करें व आवेदन देखें'];
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
        if (function_exists('applicationsReady') && applicationsReady()) $programs = publicCourses(0);
    } catch (Throwable $e) {
        $programs = [];
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
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
if (!in_array($show, ['create', 'inquiry', 'recovery', 'applicant'], true)) $show = '';
=======
if (!in_array($show, ['create', 'inquiry', 'recovery'], true)) $show = '';
>>>>>>> parent of 549483e (new)
=======
if (!in_array($show, ['create', 'inquiry', 'recovery'], true)) $show = '';
>>>>>>> parent of 549483e (new)
=======
if (!in_array($show, ['create', 'inquiry', 'recovery', 'applicant'], true)) $show = '';
>>>>>>> parent of ba104b3 (new)
=======
if (!in_array($show, ['create', 'inquiry', 'recovery', 'applicant'], true)) $show = '';
>>>>>>> parent of ba104b3 (new)
$siteAction = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (string)($_POST['action'] ?? '') : '';
if (!in_array($siteAction, ['otp_start', 'otp_verify', 'create_start', 'create_verify', 'recovery_start', 'recovery_verify', 'inquiry_save', 'password_login', 'applicant_start', 'applicant_verify'], true)) $siteAction = '';
if ($ready) require_once $root . '/app/otp.php';
if ($page === 'login' || $siteAction !== '') {
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
                siteSession('northstar_site');
                break;
            case 'otp_start':
                siteCsrfCheckAny();
                $email = emailInput();
                $siteEmail = $email;
                $kind = siteDetectAccount($email);
                if ($kind === null) {
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
                    // Unknown email: guide the visitor to create an account, enquire or track an application.
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
                    $siteStep = 'chooser';
                    break;
                }
                if ($kind === 'staff' && !otpEnabled()) {
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
                    // Password-mode institutes sign staff in with passwords, not codes.
                    $siteStep = 'staff_password';
=======
                    $siteStep = 'chooser';
>>>>>>> parent of 549483e (new)
                    break;
                }
                if ($kind === 'staff' && !otpEnabled()) {
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
                    $siteStep = 'staff_password';
                    break;
                }
                $want = $kind === 'staff' ? 'northstar_session' : 'northstar_student';
                if (session_name() !== $want) siteSession($want);
                requestOtp($kind);
                $siteFlash = siteTakeFlash();
                $siteStep = 'code';
                break;
            case 'otp_verify':
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
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
=======
                $sess = siteResumePending();
                siteCsrfCheck();
                verifyOtp($sess === 'northstar_session' ? 'staff' : 'student');
                header('Location: ' . ($sess === 'northstar_session' ? 'office.php' : 'student.php'));
                exit;
            case 'create_start':
                siteCsrfCheckAny();
                input('name', 120);
                $vPhone = input('phone', 30);
                if (!preg_match('/^[+0-9 ()-]{7,30}$/D', $vPhone)) fail('Enter a valid contact phone number.');
                emailInput();
                input('address', 300);
                if (input('website', 200, false) !== '') fail('Unable to process this request.');
                siteSession('northstar_student');
                requestStudentUserCode();
                $siteFlash = siteTakeFlash();
                $siteStep = 'create_code';
                $show = 'create';
>>>>>>> parent of 549483e (new)
=======
                $sess = siteResumePending();
                siteCsrfCheck();
                verifyOtp($sess === 'northstar_session' ? 'staff' : 'student');
                header('Location: ' . ($sess === 'northstar_session' ? 'office.php' : 'student.php'));
                exit;
            case 'create_start':
                siteCsrfCheckAny();
                input('name', 120);
                $vPhone = input('phone', 30);
                if (!preg_match('/^[+0-9 ()-]{7,30}$/D', $vPhone)) fail('Enter a valid contact phone number.');
                emailInput();
                input('address', 300);
                if (input('website', 200, false) !== '') fail('Unable to process this request.');
                siteSession('northstar_student');
                requestStudentUserCode();
                $siteFlash = siteTakeFlash();
                $siteStep = 'create_code';
                $show = 'create';
>>>>>>> parent of 549483e (new)
=======
                $sess = siteResumePending();
                siteCsrfCheck();
                verifyOtp($sess === 'northstar_session' ? 'staff' : 'student');
                header('Location: ' . ($sess === 'northstar_session' ? 'office.php' : 'student.php'));
                exit;
            case 'create_start':
                siteCsrfCheckAny();
                input('name', 120);
                $vPhone = input('phone', 30);
                if (!preg_match('/^[+0-9 ()-]{7,30}$/D', $vPhone)) fail('Enter a valid contact phone number.');
                emailInput();
                input('address', 300);
                if (input('website', 200, false) !== '') fail('Unable to process this request.');
                siteSession('northstar_student');
                requestStudentUserCode();
                $siteFlash = siteTakeFlash();
                $siteStep = 'create_code';
                $show = 'create';
>>>>>>> parent of ba104b3 (new)
=======
                $sess = siteResumePending();
                siteCsrfCheck();
                verifyOtp($sess === 'northstar_session' ? 'staff' : 'student');
                header('Location: ' . ($sess === 'northstar_session' ? 'office.php' : 'student.php'));
                exit;
            case 'create_start':
                siteCsrfCheckAny();
                input('name', 120);
                $vPhone = input('phone', 30);
                if (!preg_match('/^[+0-9 ()-]{7,30}$/D', $vPhone)) fail('Enter a valid contact phone number.');
                emailInput();
                input('address', 300);
                if (input('website', 200, false) !== '') fail('Unable to process this request.');
                siteSession('northstar_student');
                requestStudentUserCode();
                $siteFlash = siteTakeFlash();
                $siteStep = 'create_code';
                $show = 'create';
>>>>>>> parent of ba104b3 (new)
                break;
            case 'create_verify':
                siteResumePending();
                siteCsrfCheck();
                verifyStudentUserCode();
                header('Location: student.php');
                exit;
            case 'recovery_start':
                siteCsrfCheckAny();
                $siteEmail = emailInput();
                siteSession('northstar_student');
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
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
            if ($siteAction === 'create_start' && isset($_SESSION['suid_pending'])) { $siteStep = 'create_code'; if (session_name() === 'northstar_auth') $page = 'create-account'; else $show = 'create'; }
=======
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
        if ($siteAction === 'create_start' && isset($_SESSION['suid_pending'])) {
            $siteStep = 'create_code';
            $show = 'create';
        }
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
        if ($siteAction === 'recovery_start' && isset($_SESSION['site_recovery'])) {
            $siteStep = 'recovery_code';
            $show = 'recovery';
        }
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
        if ($siteAction === 'password_login') {
            $siteStep = 'staff_password';
        }
        if ($siteAction === 'applicant_start' && isset($_SESSION['applicant_pending'])) {
            $siteStep = 'applicant_code';
            $show = 'applicant';
        }
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
        if (in_array($siteAction, ['create_start', 'create_verify'], true)) $show = 'create';
        if (in_array($siteAction, ['recovery_start', 'recovery_verify'], true)) $show = 'recovery';
        if (in_array($siteAction, ['applicant_start', 'applicant_verify'], true)) $show = 'applicant';
        if ($siteAction === 'inquiry_save') $show = 'inquiry';
    } catch (Throwable $e) {
        $siteError = tr('Sign-in lookup is temporarily unavailable. Use the direct portal links below.');
    }
}
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
$otpEmail = $siteEmail !== '' ? $siteEmail : (string)($_SESSION['otp']['email'] ?? $_SESSION['suid_pending']['email'] ?? $_SESSION['site_recovery']['email'] ?? $_SESSION['applicant_pending']['email'] ?? '');
$catalogCourse = null;
if ($page === 'course') foreach ($allPrograms as $candidate) if ((int)$candidate['id'] === (int)($_GET['course'] ?? 0)) {$catalogCourse = $candidate; break;}
$courseQuery = $page === 'courses' ? trim((string)($_GET['q'] ?? '')) : '';
$visiblePrograms = $courseQuery === '' ? $allPrograms : array_values(array_filter($allPrograms, fn($vp) => mb_stripos($vp['name'] . ' ' . $vp['institute_name'], $courseQuery) !== false));
$allowed = ['home', 'about', 'course', 'courses', 'admissions', 'notices', 'faculty', 'contact', 'login', 'create-account', 'brochure'];
=======
$otpEmail = $siteEmail !== '' ? $siteEmail : (string)($_SESSION['otp']['email'] ?? $_SESSION['suid_pending']['email'] ?? $_SESSION['site_recovery']['email'] ?? '');
$allowed = ['home', 'about', 'courses', 'admissions', 'notices', 'faculty', 'contact', 'login'];
>>>>>>> parent of 549483e (new)
=======
$otpEmail = $siteEmail !== '' ? $siteEmail : (string)($_SESSION['otp']['email'] ?? $_SESSION['suid_pending']['email'] ?? $_SESSION['site_recovery']['email'] ?? '');
$allowed = ['home', 'about', 'courses', 'admissions', 'notices', 'faculty', 'contact', 'login'];
>>>>>>> parent of 549483e (new)
=======
$otpEmail = $siteEmail !== '' ? $siteEmail : (string)($_SESSION['otp']['email'] ?? $_SESSION['suid_pending']['email'] ?? $_SESSION['site_recovery']['email'] ?? $_SESSION['applicant_pending']['email'] ?? '');
$allowed = ['home', 'about', 'courses', 'admissions', 'notices', 'faculty', 'contact', 'login'];
>>>>>>> parent of ba104b3 (new)
=======
$otpEmail = $siteEmail !== '' ? $siteEmail : (string)($_SESSION['otp']['email'] ?? $_SESSION['suid_pending']['email'] ?? $_SESSION['site_recovery']['email'] ?? $_SESSION['applicant_pending']['email'] ?? '');
$allowed = ['home', 'about', 'courses', 'admissions', 'notices', 'faculty', 'contact', 'login'];
>>>>>>> parent of ba104b3 (new)
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
    [$studentCount, tr('Students'), true],
    [count($teachers), tr('Faculty members'), true],
    [$programCount, tr('Programs open'), false],
    [count($institutes), tr('Campuses'), false],
    [count($cities), tr('Cities'), false],
];
$vcMsg = $siteLang === 'hi'
    ? $brand . ' में आपका हार्दिक स्वागत है। हमारी कक्षाएं, प्रयोगशालाएं और क्लीनिक एक ही उद्देश्य के लिए हैं — आपका विकास। स्नेही शिक्षकों, सत्यापित प्रवेश और आधुनिक छात्र पोर्टल के साथ हम आपके पहले आवेदन से दीक्षांत समारोह तक आपके साथ हैं।'
    : siteVcMessage($brand);
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
$formSession = $page === 'create-account' ? 'northstar_auth' : 'northstar_site';
$profile = ($_SERVER['REQUEST_METHOD'] === 'GET') ? siteProfile() : null;
siteSession($formSession);
$headerAction = $profile
    ? siteProfileBox($profile)
    : '<a class="u-btn ghost" href="' . e(siteHomeUrl() . '?page=login') . '">' . e(tr('Sign In')) . '</a>';
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
function uDocHead(string $title, string $brand): void {
    global $htmlLang;
    echo '<!doctype html><html lang="' . e($htmlLang) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . ' · ' . e($brand) . '</title><link rel="stylesheet" href="assets/theme.css"><link rel="stylesheet" href="assets/university.css"><link rel="stylesheet" href="assets/college.css"><script src="assets/theme.js" defer></script><script src="assets/site.js" defer></script></head><body class="college">';
}
?>
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<?php uDocHead(tr(['home' => 'Admissions open', 'about' => 'About us', 'course' => 'Course details', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'contact' => 'Contact', 'brochure' => 'College brochure', 'login' => 'Sign in', 'create-account' => 'Create your account', 'notfound' => 'Page not found'][$page] ?? 'Page not found'), $brand); ?>
<?=collegeHeader($brand, $brandKind . ($brandCity ? ' · ' . $brandCity : ''), $page, $headerAction, $tickerItems)?>
=======
<?php uDocHead(tr(['home' => 'Admissions open', 'about' => 'About us', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'contact' => 'Contact', 'login' => 'Sign in', 'notfound' => 'Page not found'][$page]), $brand); ?>
<?=siteHeader($brand, $brandKind . ($brandCity ? ' · ' . $brandCity : ''), $page, '<a class="u-btn ghost" href="' . e(siteHomeUrl() . '?page=login') . '">' . e(tr('Sign In')) . '</a><a class="u-btn solid" href="' . e(sitePublicUrl('apply.php')) . '">' . e(tr('Apply Now')) . '</a>' . siteLangToggle($page), $tickerItems)?>
>>>>>>> parent of 549483e (new)
=======
<?php uDocHead(tr(['home' => 'Admissions open', 'about' => 'About us', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'contact' => 'Contact', 'login' => 'Sign in', 'notfound' => 'Page not found'][$page]), $brand); ?>
<?=siteHeader($brand, $brandKind . ($brandCity ? ' · ' . $brandCity : ''), $page, '<a class="u-btn ghost" href="' . e(siteHomeUrl() . '?page=login') . '">' . e(tr('Sign In')) . '</a><a class="u-btn solid" href="' . e(sitePublicUrl('apply.php')) . '">' . e(tr('Apply Now')) . '</a>' . siteLangToggle($page), $tickerItems)?>
>>>>>>> parent of 549483e (new)
=======
<?php uDocHead(tr(['home' => 'Admissions open', 'about' => 'About us', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'contact' => 'Contact', 'login' => 'Sign in', 'notfound' => 'Page not found'][$page]), $brand); ?>
<?=siteHeader($brand, $brandKind . ($brandCity ? ' · ' . $brandCity : ''), $page, '<a class="u-btn ghost" href="' . e(siteHomeUrl() . '?page=login') . '">' . e(tr('Sign In')) . '</a><a class="u-btn solid" href="' . e(sitePublicUrl('apply.php')) . '">' . e(tr('Apply Now')) . '</a>' . siteLangToggle($page), $tickerItems)?>
>>>>>>> parent of ba104b3 (new)
=======
<?php uDocHead(tr(['home' => 'Admissions open', 'about' => 'About us', 'courses' => 'Programs & courses', 'admissions' => 'Admissions', 'notices' => 'Notices & dates', 'faculty' => 'Faculty', 'contact' => 'Contact', 'login' => 'Sign in', 'notfound' => 'Page not found'][$page]), $brand); ?>
<?=siteHeader($brand, $brandKind . ($brandCity ? ' · ' . $brandCity : ''), $page, '<a class="u-btn ghost" href="' . e(siteHomeUrl() . '?page=login') . '">' . e(tr('Sign In')) . '</a><a class="u-btn solid" href="' . e(sitePublicUrl('apply.php')) . '">' . e(tr('Apply Now')) . '</a>' . siteLangToggle($page), $tickerItems)?>
>>>>>>> parent of ba104b3 (new)
<main class="u-main">
<?php if (!$ready): ?>
<section class="u-card u-setup"><h1><?= e(tr('Welcome — setup required')) ?></h1><p>This university website is connected to the institute CRM, which has not been installed yet. The server administrator should open the setup wizard to create the database, owner account and first institute. This page updates itself automatically afterwards.</p><p><a class="u-btn solid" href="setup.php"><?= e(tr('Open setup wizard')) ?> →</a></p></section>
<?php elseif ($page === 'home'): ?>
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
<section class="u-hero" data-hero-slider>
<div class="u-hero-slides">
<article class="u-hero-slide is-active" style="--hero-image:url('assets/college-hero.jpg')"><div class="u-hero-inner c-hero-grid"><div><h1><?= e(tr('Shaping Healthcare Leaders')) ?><br><span class="c-accent"><?= e(tr('for a Healthier Tomorrow')) ?></span></h1><p class="c-hero-sub">Quality Pharmacy Education | Research Driven Learning | Community Healthcare</p><div class="u-cta"><a class="u-btn solid big" href="apply.php"><?= e(tr('Apply for Admission')) ?> →</a><a class="u-btn ghost big" href="?page=courses"><?= e(tr('Explore Programs')) ?> →</a></div></div></div></article>
<article class="u-hero-slide" style="--hero-image:url('assets/program-bpharm.jpg')"><div class="u-hero-inner c-hero-grid"><div><div class="u-eyebrow">YOUR NEXT CHAPTER STARTS HERE</div><h1>Find your place to grow.</h1><p class="c-hero-sub">Admissions open · Apply online</p><p>Explore open programs, see the latest admission dates and begin your application in minutes.</p><div class="u-cta"><a class="u-btn solid big" href="?page=courses">Explore Programs →</a><a class="u-btn ghost big" href="?page=admissions">How to join</a></div></div></div></article>
<article class="u-hero-slide" style="--hero-image:url('assets/program-mpharm.jpg')"><div class="u-hero-inner c-hero-grid"><div><div class="u-eyebrow">ONE SECURE FRONT DOOR</div><h1>Everything starts here.</h1><p class="c-hero-sub">One email · Every portal</p><p>Use one secure email verification flow to return to your admissions journey or campus portal.</p><div class="u-cta"><a class="u-btn solid big" href="?page=login">Open sign in →</a><a class="u-btn ghost big" href="?page=contact">Contact the institute</a></div></div></div></article>
</div><div class="u-hero-controls" aria-label="Hero slides"><button type="button" data-hero-prev aria-label="Previous slide">←</button><div class="u-hero-dots"><button type="button" class="is-active" data-hero-dot="0" aria-label="Slide 1"></button><button type="button" data-hero-dot="1" aria-label="Slide 2"></button><button type="button" data-hero-dot="2" aria-label="Slide 3"></button></div><button type="button" data-hero-next aria-label="Next slide">→</button></div>
</section>
<section class="c-highlights" aria-label="Highlights"><div class="c-wrap"><?php foreach (collegeHighlights() as [$icon, $title, $sub]): ?><div><span class="c-hi-icon"><?= collegeIcon($icon) ?></span><div><strong><?= e($title) ?></strong><small><?= e($sub) ?></small></div></div><?php endforeach; ?></div></section>
<div class="c-welcome"><div class="c-wrap c-welcome-grid">
<div><div class="u-eyebrow">Welcome to</div><h2><?= e($brand) ?></h2><p><?= e($brand) ?> is committed to quality pharmacy education, research and ethical healthcare practice. Our mission is to prepare students with knowledge, skills and values for a rapidly evolving healthcare industry.</p><p><a class="u-btn solid c-btn-navy" href="?page=about">Know More About Us →</a></p></div>
<div><aside class="c-quote"><?php if (is_file($root . '/assets/quote-photo.jpg')): ?><img class="c-quote-photo" src="assets/quote-photo.jpg" alt="Campus inspiration"><?php endif; ?><span class="c-quote-mark">“</span><blockquote>Dream is not that which you see while sleeping, it is something that does not let you sleep.</blockquote><cite>— Dr. A.P.J. Abdul Kalam</cite></aside></div>
<div><div class="c-updates"><div class="c-updates-head"><h3>📢 Latest Updates</h3><a href="?page=notices">View All →</a></div><?php if (!$notices): ?><p class="u-muted"><?= e(tr('No admission notices right now.')) ?></p><?php else: ?><ul><?php foreach (array_slice($notices, 0, 5) as $n): $nts = strtotime((string)$n['closes']) ?: time(); ?><li><span class="c-date"><strong><?= e(date('d', $nts)) ?></strong><small><?= e(date('M', $nts)) ?></small></span><div><a href="student.php?page=course&course=<?= (int)$n['id'] ?>">Admissions <?= $n['closing'] ? 'closing soon' : 'open' ?>: <?= e($n['course']) ?></a><small><?= e($n['institute']) ?> · <?= e(tr('Apply by')) ?> <?= e($n['closes']) ?></small></div></li><?php endforeach; ?></ul><?php endif; ?></div></div>
</div></div>
<section class="u-section c-programs"><div class="c-sec-head"><h2>Our Programs</h2><a href="?page=courses">View All Programs →</a></div><?php if (!$programs): ?><p class="u-muted"><?= e(tr('Admissions are opening soon.')) ?> <?= e(tr('Check')) ?> <a href="?page=notices"><?= e(tr('Notices')) ?></a> <?= e(tr('or contact the office.')) ?></p><?php else: ?><div class="c-prog-cards"><?php foreach (array_slice($programs, 0, 4) as $p): ?><article><img src="<?= e(collegeProgramImage($p['name'])) ?>" alt="" loading="lazy"><div class="c-prog-body"><?php if (count($institutes) > 1): ?><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><?php endif; ?><h3><?= e($p['name']) ?></h3><?php $deg = collegeDegreeLine($p['name']); if ($deg !== ''): ?><p class="c-degree"><?= e($deg) ?></p><?php endif; ?><p class="u-meta"><?= e($p['duration']) ?> · PCI Approved</p><a class="u-btn solid c-btn-navy" href="student.php?page=course&course=<?= (int)$p['id'] ?>">Learn More →</a></div></article><?php endforeach; ?></div><p class="u-more"><a href="?page=courses"><?= e(tr('View all')) ?> <?= count($allPrograms) ?> <?= e(tr('programs')) ?> →</a></p><?php endif; ?></section>
<div class="u-stats-band c-spot"><div class="c-wrap"><?php foreach (collegeSpotStats() as [$icon, $num, $label]): ?><div><span class="c-stat-icon"><?= collegeIcon($icon) ?></span><strong><?= e($num) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></div>
<div class="c-duo c-wrap">
<section class="u-section"><div class="c-sec-head"><h2>Campus Life</h2><a href="?page=about">View Gallery →</a></div><div class="c-tiles"><div class="c-camp"><img src="assets/campus-learning.jpg" alt="Students learning in the library" loading="lazy"><strong>Learning</strong></div><div class="c-camp"><img src="assets/campus-innovation.jpg" alt="Pharmacy research laboratory" loading="lazy"><strong>Innovation</strong></div><div class="c-camp"><img src="assets/campus-community.jpg" alt="Students in community service" loading="lazy"><strong>Community Service</strong></div><div class="c-camp"><img src="assets/campus-sports.jpg" alt="Cricket match on campus" loading="lazy"><strong>Beyond Classroom</strong></div></div></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Message from the Principal')) ?></div><h2><?= e(tr('Message from the Principal')) ?></h2><div class="u-vc-full c-principal"><?php if (is_file($root . '/assets/principal-photo.jpg')): ?><img class="c-person-photo" src="assets/principal-photo.jpg" alt="<?= e(tr('Principal')) ?>, <?= e($brand) ?>"><?php else: ?><span class="u-vc-portrait"><?= e(strtoupper(mb_substr($brand, 0, 1))) ?></span><?php endif; ?><div><blockquote>“<?= e($vcMsg) ?>”</blockquote><cite><?= e(tr('Principal')) ?> · <?= e($brand) ?></cite><p><a class="u-btn solid c-btn-navy" href="?page=about">Read More →</a></p></div></div></section>
</div>
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
<div>
<div class="quote-card"><div class="quote-icon"><i class="fa-solid fa-quote-left"></i></div><p>"The purpose of education is to prepare students with knowledge, skills and values that allow them to contribute meaningfully to society."</p><div class="quote-author"><div class="principal-avatar"><i class="fa-solid fa-user-tie"></i></div><div><strong><?= e(tr("Principal's Message")) ?></strong><span><?= e($brand) ?></span></div></div></div>
<div class="updates-card"><div class="updates-head"><h3><i class="fa-solid fa-bullhorn"></i><?= e(tr('Latest Updates')) ?></h3><a href="?page=notices" class="view-all"><?= e(tr('View All')) ?> →</a></div><div class="update-list"><?php if (!$notices): ?><p class="update-text"><?= e(tr('No admission notices right now.')) ?></p><?php else: ?><?php foreach (array_slice($notices, 0, 5) as $n): $nts = strtotime((string)$n['closes']) ?: time(); ?><div class="update"><div class="update-date"><strong><?= e(date('d', $nts)) ?></strong><small><?= e(date('M', $nts)) ?></small></div><div class="update-text"><a href="student.php?page=course&course=<?= (int)$n['id'] ?>">Admissions <?= $n['closing'] ? 'closing soon' : 'open' ?>: <?= e($n['course']) ?></a><?php if ($n['closing']): ?> <span class="new-badge"><?= e(tr('NEW')) ?></span><?php endif; ?></div></div><?php endforeach; ?><?php endif; ?></div></div>
</div>
</div></div></section>
<section class="section program-section" id="programs"><div class="container">
<div class="section-heading"><div class="small-title" style="color:var(--green);font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:1px;"><?= e(tr('Academic Excellence')) ?></div><h2 class="section-title"><?= e(tr('Our')) ?> <span><?= e(tr('Programs')) ?></span></h2><p>Explore our pharmacy education programs designed to build knowledge, practical skills and professional confidence.</p></div>
<?php if (!$programs): ?><p class="update-text"><?= e(tr('Admissions are opening soon.')) ?> <?= e(tr('Check')) ?> <a href="?page=notices"><?= e(tr('Notices')) ?></a> <?= e(tr('or contact the office.')) ?></p><?php else: ?><div class="program-grid"><?php foreach (array_slice($programs, 0, 4) as $p): $short = collegeDegreeShort($p['name']); $pdesc = trim((string)($p['description'] ?? '')); if ($pdesc === '') $pdesc = 'Quality pharmacy education with practical training, experienced faculty and research opportunities.'; ?><article class="program-card"><div class="program-image"><img src="<?= e(collegeProgramImage($p['name'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><span class="program-tag"><?= e(tr('Pharmacy')) ?></span></div><div class="program-body"><h3><?= e($short !== '' ? $short : $p['name']) ?></h3><h4><?= e($p['name']) ?></h4><div class="program-meta"><span><i class="fa-regular fa-clock"></i><?= e($p['duration']) ?></span><span><i class="fa-solid fa-flask"></i><?= e(tr('Practical')) ?></span></div><p><?= e(mb_substr($pdesc, 0, 110)) ?><?= mb_strlen($pdesc) > 110 ? '…' : '' ?></p><a href="student.php?page=course&course=<?= (int)$p['id'] ?>" class="learn-more"><?= e(tr('Learn More')) ?> <i class="fa-solid fa-arrow-right"></i></a></div></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
<section class="stats"><div class="container"><div class="stats-grid">
<div class="stat"><i class="fa-solid fa-graduation-cap"></i><div class="counter" data-target="500">0</div><p><?= e(tr('Students')) ?></p></div>
<div class="stat"><i class="fa-solid fa-users"></i><div class="counter" data-target="50">0</div><p><?= e(tr('Faculty Members')) ?></p></div>
<div class="stat"><i class="fa-solid fa-flask"></i><div class="counter" data-target="10">0</div><p><?= e(tr('Modern Laboratories')) ?></p></div>
<div class="stat"><i class="fa-solid fa-building-columns"></i><div class="counter" data-target="100" data-suffix="%">0</div><p><?= e(tr('Academic Compliance')) ?></p></div>
<div class="stat"><i class="fa-solid fa-trophy"></i><div class="counter" data-target="95" data-suffix="%">0</div><p><?= e(tr('Placement Support')) ?></p></div>
</div></div></section>
<section class="section"><div class="container"><div class="campus-grid">
<div>
<div style="margin-bottom:20px;"><div class="small-title" style="color:var(--green);font-size:13px;font-weight:800;text-transform:uppercase;"><?= e(tr('Life at Campus')) ?></div><h2 class="section-title"><?= e(tr('Campus')) ?> <span><?= e(tr('Life')) ?></span></h2></div>
<div class="campus-gallery"><div class="gallery-item"><img src="assets/campus-innovation.jpg" alt="Pharmacy research laboratory" loading="lazy"></div><div class="gallery-item"><img src="assets/campus-learning.jpg" alt="Students learning together" loading="lazy"></div><div class="gallery-item"><img src="assets/campus-sports.jpg" alt="Sports on campus" loading="lazy"></div></div>
</div>
<div>
<div style="margin-bottom:20px;"><div class="small-title" style="color:var(--green);font-size:13px;font-weight:800;text-transform:uppercase;"><?= e(tr('Leadership')) ?></div><h2 class="section-title"><?= e(tr('Message from the')) ?> <span><?= e(tr('Principal')) ?></span></h2></div>
<div class="principal-box"><div class="principal-content"><div class="principal-photo"><?php if (is_file($root . '/assets/principal-photo.jpg')): ?><img src="assets/principal-photo.jpg" alt="<?= e(tr('Principal')) ?>, <?= e($brand) ?>"><?php else: ?><i class="fa-solid fa-user-tie"></i><?php endif; ?></div><div><p>At <?= e($brand) ?>, we believe pharmacy education must go beyond textbooks.</p><p>Our goal is to nurture professional competence, ethical values, research ability and a commitment to serve society.</p><div class="principal-name"><strong><?= e(tr('Principal')) ?></strong><span><?= e($brand) ?></span></div></div></div></div>
</div>
</div></div></section>
<section class="section" style="padding-top:10px;"><div class="container"><div class="facility-strip">
<div class="facility"><i class="fa-solid fa-graduation-cap"></i><strong><?= e(tr('PCI Approved')) ?></strong></div>
<div class="facility"><i class="fa-solid fa-award"></i><strong>AICTE Recognized</strong></div>
<div class="facility"><i class="fa-solid fa-building-columns"></i><strong>Affiliated University</strong></div>
<div class="facility"><i class="fa-solid fa-book"></i><strong>Digital Library</strong></div>
<div class="facility"><i class="fa-solid fa-bed"></i><strong>Hostel Facility</strong></div>
<div class="facility"><i class="fa-solid fa-bus"></i><strong>Transport Facility</strong></div>
</div></div></section>
<section class="cta"><div class="container cta-inner"><div><h2><?= e(tr('Begin Your Journey in Pharmacy')) ?></h2><p>Applications are now open. Start your application online today.</p></div><div><a href="apply.php" class="btn btn-green"><?= e(tr('Apply Online Now')) ?> <i class="fa-solid fa-arrow-right"></i></a></div></div></section>
=======
<div class="c-trust"><div class="c-wrap"><?php foreach (collegeTrustBadges() as [$icon, $label]): ?><span><?= collegeIcon($icon) ?> <?= e($label) ?></span><?php endforeach; ?></div></div>
<section class="u-cta-band"><div><h2><?= e(tr('Ready to join?')) ?></h2><p><?= e(tr('Create your free student account in a minute, then apply online.')) ?></p></div><div><a class="u-btn light big" href="?page=login&show=create"><?= e(tr('Create Student Account')) ?> →</a> <a class="u-btn ghost big" href="student.php?page=admissions"><?= e(tr('Apply for Admission')) ?> →</a> <a class="u-btn ghost big" href="?page=login"><?= e(tr('Sign In')) ?></a></div></section>
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
=======
<div class="c-trust"><div class="c-wrap"><?php foreach (collegeTrustBadges() as [$icon, $label]): ?><span><?= collegeIcon($icon) ?> <?= e($label) ?></span><?php endforeach; ?></div></div>
<section class="u-cta-band"><div><h2><?= e(tr('Ready to join?')) ?></h2><p><?= e(tr('Create your free student account in a minute, then apply online.')) ?></p></div><div><a class="u-btn light big" href="?page=login&show=create"><?= e(tr('Create Student Account')) ?> →</a> <a class="u-btn ghost big" href="student.php?page=admissions"><?= e(tr('Apply for Admission')) ?> →</a> <a class="u-btn ghost big" href="?page=login"><?= e(tr('Sign In')) ?></a></div></section>
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
=======
<div class="c-trust"><div class="c-wrap"><?php foreach (collegeTrustBadges() as [$icon, $label]): ?><span><?= collegeIcon($icon) ?> <?= e($label) ?></span><?php endforeach; ?></div></div>
<section class="u-cta-band"><div><h2><?= e(tr('Ready to join?')) ?></h2><p><?= e(tr('Create your free student account in a minute, then apply online.')) ?></p></div><div><a class="u-btn light big" href="?page=login&show=create"><?= e(tr('Create Student Account')) ?> →</a> <a class="u-btn ghost big" href="student.php?page=admissions"><?= e(tr('Apply for Admission')) ?> →</a> <a class="u-btn ghost big" href="?page=login"><?= e(tr('Sign In')) ?></a></div></section>
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
<?php elseif ($page === 'about'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('About us')) ?></div><h1><?= e($brand) ?></h1><p><?= e($brandKind) ?> programs across <?= count($institutes) ?: 'our' ?> <?= count($institutes) === 1 ? 'campus' : 'campuses' ?><?= $cities ? ' in ' . e(implode(', ', $cities)) : '' ?>. Every figure on this website comes live from the institute CRM — programs, dates, campuses and contacts update automatically when the office updates its records.</p><div class="u-facts"><?php foreach ($stats as [$n, $label]): ?><div><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></section>
<section class="u-section" id="campuses"><div class="u-eyebrow"><?= e(tr('OUR CAMPUSES')) ?></div><h2><?= e(tr('Where you will study')) ?></h2><?php if (!$institutes): ?><p class="u-muted">Campus details will appear here once the office adds institutes.</p><?php else: ?><div class="u-grid"><?php foreach ($institutes as $i): ?><article class="u-card"><h3><?= e($i['name']) ?></h3><p class="u-meta"><?= e($i['kind']) ?> · <?= e($i['city']) ?></p><?php if (trim($i['address']) !== ''): ?><p><?= e($i['address']) ?></p><?php endif; ?><p class="u-phone">☎ <?= e($i['phone']) ?></p></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'course' && $catalogCourse): ?>
<section class="u-section"><div class="u-eyebrow"><?= e($catalogCourse['institute_name']) ?></div><h1><?= e($catalogCourse['name']) ?></h1><p class="u-meta"><?= e($catalogCourse['duration']) ?> · <?= e($catalogCourse['city']) ?></p><h2>About this course</h2><p><?= nl2br(e((string)($catalogCourse['description'] ?? ''))) ?></p><?php if ((int)$catalogCourse['admission_open']===1): ?><span class="u-pill">Admission open</span><p>Applications close <?= e($catalogCourse['closes_on']) ?>. Review the eligibility and privacy notice in the secure portal before submitting.</p><p><a class="u-btn solid" href="student.php?page=course&course=<?= (int)$catalogCourse['id'] ?>">Details &amp; Apply →</a></p><?php else: ?><p>Admissions are not open for this course right now.</p><p><a class="u-btn ghost" href="?page=courses">Back to all programs</a></p><?php endif; ?></section>
<?php elseif ($page === 'courses'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Programs & courses')) ?></div><h1><?= count($visiblePrograms) ?> <?= e(tr('programs')) ?></h1><form class="c-search c-search-page" action="index.php" method="get" role="search"><input type="hidden" name="page" value="courses"><input type="search" name="q" value="<?= e($courseQuery) ?>" placeholder="Search programs" aria-label="Search programs"><button class="u-btn solid" type="submit">Search</button></form><p>Explore every active course. Courses with an open admission listing can be applied for securely through the student portal.</p><?php if (!$visiblePrograms): ?><p class="u-muted"><?= $courseQuery !== '' ? 'No programs match your search.' : 'No active courses are available yet.' ?></p><?php else: ?><div class="u-grid"><?php foreach ($visiblePrograms as $p): ?><article class="u-card"><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><?php if ((int)$p['admission_open']===1): ?><span class="u-pill">Admission open</span><?php endif; ?><h3><?= e($p['name']) ?></h3><p class="u-meta"><?= e($p['duration']) ?> · <?= e($p['city']) ?></p><p><?= e(mb_substr((string)($p['description']??''), 0, 160)) ?><?=mb_strlen((string)($p['description']??''))>160?'…':''?></p><p class="u-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><?php if ((int)$p['admission_open']===1): ?><p class="u-apply-by"><?= e(tr('Apply by')) ?> <?= e($p['closes_on']) ?></p><a class="u-btn solid" href="student.php?page=course&course=<?= (int)$p['id'] ?>">Details &amp; Apply →</a><?php else: ?><a class="u-btn ghost" href="?page=course&course=<?= (int)$p['id'] ?>">Details →</a><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'admissions'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Admissions')) ?></div><h1><?= e(tr('How to join')) ?></h1><div class="u-steps"><div><span>1</span><strong><?= e(tr('Create your account')) ?></strong><p>Register with your email and verify the code.</p></div><div><span>2</span><strong><?= e(tr('Apply online')) ?></strong><p>One simple form for your chosen program.</p></div><div><span>3</span><strong>Upload certificates</strong><p>Add mark sheets when the office enables uploads.</p></div><div><span>4</span><strong>Office verification</strong><p>Eligibility and originals checked by the office.</p></div><div><span>5</span><strong>Track &amp; join</strong><p>Follow your status, then open your student portal.</p></div></div><p><a class="u-btn solid big" href="apply.php"><?= e(tr('Start your application')) ?> →</a> <a class="u-btn ghost big" href="?page=login&show=create"><?= e(tr('Create account')) ?></a></p></section>
=======
=======
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of ba104b3 (new)
=======
>>>>>>> parent of ba104b3 (new)
<section class="u-hero"><div class="u-hero-inner"><div class="u-eyebrow"><?= e(tr('TRADITION MEETS INNOVATION: EST. 1887')) ?></div><h1><?= e(tr('Nurturing global leaders.')) ?></h1><p><?= e($brand) ?> <?= e($siteLang === 'hi' ? 'सत्यापित प्रवेश, स्नेही शिक्षकों और आधुनिक छात्र पोर्टल के साथ फार्मेसी व संबद्ध चिकित्सा कार्यक्रम प्रदान करता है। अपना खाता बनाएं, ऑनलाइन आवेदन करें और अपने आवेदन को ट्रैक करें — सब एक ही स्थान पर।' : 'offers ' . $brandKind . ' programs with verified admissions, caring faculty and a modern student portal. Create your account, apply online and track your application — all in one place.') ?></p><div class="u-cta"><a class="u-btn solid big" href="apply.php"><?= e(tr('Apply for Admission')) ?> →</a><a class="u-btn ghost big" href="?page=courses"><?= e(tr('Explore Programs')) ?></a><a class="u-btn ghost big" href="?page=login"><?= e(tr('Student / Office Sign In')) ?></a></div></div></section>
<div class="u-hero-badges"><?php foreach (siteAccreditations() as [$icon, $title, $sub]): ?><div class="u-badge-card"><span class="u-badge-icon"><?= $icon ?></span><div><strong><?= e($title) ?></strong><small><?= e(tr($sub)) ?></small></div></div><?php endforeach; ?></div>
<div class="u-stats-band"><div><?php foreach ($stats as [$n, $label]): ?><div><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></div>
<div class="u-home-grid"><div class="u-home-main">
<section class="u-section"><div class="u-eyebrow"><?= e(tr("Vice-Chancellor's Message")) ?></div><h2><?= e(tr("Vice-Chancellor's Message")) ?></h2><div class="u-vc-full"><span class="u-vc-portrait"><?= e(strtoupper(mb_substr($brand, 0, 1))) ?></span><div><blockquote>“<?= e($vcMsg) ?>”</blockquote><cite><?= e(tr('Office of the Vice-Chancellor')) ?> · <?= e($brand) ?></cite></div></div></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('FEATURED PROGRAMS')) ?></div><h2><?= e(tr('Open for applications')) ?></h2><?php if (!$programs): ?><p class="u-muted"><?= e(tr('Admissions are opening soon.')) ?> <?= e(tr('Check')) ?> <a href="?page=notices"><?= e(tr('Notices')) ?></a> <?= e(tr('or contact the office.')) ?></p><?php else: ?><div class="u-grid"><?php foreach (array_slice($programs, 0, 4) as $p): ?><article class="u-card"><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><h3><?= e($p['name']) ?></h3><p class="u-meta"><?= e($p['duration']) ?> · <?= e($p['city']) ?></p><p class="u-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><p class="u-apply-by"><?= e(tr('Apply by')) ?> <?= e($p['closes_on']) ?></p><a class="u-btn solid" href="apply.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Details & Apply')) ?> →</a></article><?php endforeach; ?></div><p class="u-more"><a href="?page=courses"><?= e(tr('View all')) ?> <?= $programCount ?> <?= e(tr('programs open')) ?> →</a></p><?php endif; ?></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('ADMISSION DATES')) ?></div><h2><?= e(tr('Latest notices')) ?></h2><?php if (!$notices): ?><p class="u-muted"><?= e(tr('No admission notices right now.')) ?></p><?php else: ?><ul class="u-notices"><?php foreach (array_slice($notices, 0, 4) as $n): ?><li><span class="u-pill<?= $n['closing'] ? ' soon' : '' ?>"><?= e(tr($n['closing'] ? 'Closing soon' : 'Open')) ?></span><div><strong><?= e($n['course']) ?></strong><small><?= e($n['institute']) ?> · <?= e(tr('Apply by')) ?> <?= e($n['closes']) ?></small></div><a href="apply.php?page=course&course=<?= (int)$n['id'] ?>"><?= e(tr('Apply')) ?> →</a></li><?php endforeach; ?></ul><p class="u-more"><a href="?page=notices"><?= e(tr('All notices')) ?> →</a></p><?php endif; ?></section>
<section class="u-section"><div class="u-eyebrow"><?= e($siteLang === 'hi' ? 'क्यों चुनें' : 'WHY') ?> <?= e(strtoupper($brandKind)) ?></div><h2><?= e(tr('A campus built around students')) ?></h2><div class="u-grid"><div class="u-card"><h3>💊 Pharmacy focus</h3><p>Practical labs, experienced faculty and career guidance for D.Pharm and allied programs.</p></div><div class="u-card"><h3>📝 Simple admissions</h3><p>One online form, email verification and transparent tracking to the office decision.</p></div><div class="u-card"><h3>🎓 Student portal</h3><p>Fees, payments, documents, attendance, results and support — available after admission.</p></div><div class="u-card"><h3>🏛️ Trusted office</h3><p>Every admission is verified by the institute office before approval. No hidden process.</p></div></div></section>
</div><aside class="u-home-side">
<div class="u-mini-badges"><?php foreach ([['🏵', 'NAAC'], ['🎖', 'NIRF'], ['🏛', 'UGC']] as [$icon, $name]): ?><span><?= $icon ?> <?= e($name) ?></span><?php endforeach; ?></div>
<div class="u-side-card u-vc"><div class="u-side-title"><?= e(tr("Vice-Chancellor's Message")) ?></div><div class="u-vc-head"><span class="u-vc-avatar"><?= e(strtoupper(mb_substr($brand, 0, 1))) ?></span><div><blockquote>“<?= e($vcMsg) ?>”</blockquote><cite><?= e(tr('Office of the Vice-Chancellor')) ?></cite></div></div></div>
<div class="u-side-card"><div class="u-side-title"><?= e(tr('Explore our programs')) ?></div><div class="u-prog-grid"><?php $pi = 0; foreach (siteShowcasePrograms() as $sp): $pi++; ?><a class="u-prog-card u-prog-<?= $pi ?>" href="?page=courses"><span><?= e($sp) ?></span></a><?php endforeach; ?></div><?php if ($programs): ?><p class="u-more"><a href="?page=courses"><?= e(tr('All programs')) ?> →</a></p><?php else: ?><p class="u-muted"><?= e(tr('Programs open here when admissions begin.')) ?></p><?php endif; ?></div>
<div class="u-side-card"><div class="u-side-title"><?= e(tr('Top recruiters')) ?></div><div class="u-recruiters"><?php foreach (siteRecruiters() as [$icon, $name]): ?><span><?= $icon ?> <?= e($name) ?></span><?php endforeach; ?><span class="u-flag-tile"><?= siteFlag() ?> India</span></div></div>
<div class="u-side-card"><div class="u-side-title"><?= e(tr('Reach us')) ?></div><p><strong><?= e($brand) ?></strong></p><?php if (trim($brandAddress) !== ''): ?><p>📍 <?= e($brandAddress) ?><?= $brandCity ? ', ' . e($brandCity) : '' ?></p><?php elseif ($brandCity): ?><p>📍 <?= e($brandCity) ?></p><?php endif; ?><?php if ($brandPhone !== ''): ?><p class="u-phone">☎ <?= e($brandPhone) ?></p><?php endif; ?><p class="u-more"><a href="?page=contact"><?= e(tr('All campuses')) ?> →</a></p></div>
</aside></div>
<<<<<<< HEAD
<<<<<<< HEAD
<section class="u-cta-band"><div><h2><?= e(tr('Ready to join?')) ?></h2><p><?= e(tr('Create your free student account in a minute, then apply online.')) ?></p></div><div><a class="u-btn light big" href="student.php?page=register"><?= e(tr('Create Student Account')) ?> →</a> <a class="u-btn ghost big" href="?page=login"><?= e(tr('Sign In')) ?></a></div></section>
=======
<section class="u-cta-band"><div><h2><?= e(tr('Ready to join?')) ?></h2><p><?= e(tr('Create your free student account in a minute, then apply online.')) ?></p></div><div><a class="u-btn light big" href="?page=login&show=create"><?= e(tr('Create Student Account')) ?> →</a> <a class="u-btn ghost big" href="?page=login"><?= e(tr('Sign In')) ?></a></div></section>
>>>>>>> parent of ba104b3 (new)
=======
<section class="u-cta-band"><div><h2><?= e(tr('Ready to join?')) ?></h2><p><?= e(tr('Create your free student account in a minute, then apply online.')) ?></p></div><div><a class="u-btn light big" href="?page=login&show=create"><?= e(tr('Create Student Account')) ?> →</a> <a class="u-btn ghost big" href="?page=login"><?= e(tr('Sign In')) ?></a></div></section>
>>>>>>> parent of ba104b3 (new)
<?php elseif ($page === 'about'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('About us')) ?></div><h1><?= e($brand) ?></h1><p><?= e($brandKind) ?> programs across <?= count($institutes) ?: 'our' ?> <?= count($institutes) === 1 ? 'campus' : 'campuses' ?><?= $cities ? ' in ' . e(implode(', ', $cities)) : '' ?>. Every figure on this website comes live from the institute CRM — programs, dates, campuses and contacts update automatically when the office updates its records.</p><div class="u-facts"><?php foreach ($stats as [$n, $label]): ?><div><strong><?= (int)$n ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div></section>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('OUR CAMPUSES')) ?></div><h2><?= e(tr('Where you will study')) ?></h2><?php if (!$institutes): ?><p class="u-muted">Campus details will appear here once the office adds institutes.</p><?php else: ?><div class="u-grid"><?php foreach ($institutes as $i): ?><article class="u-card"><h3><?= e($i['name']) ?></h3><p class="u-meta"><?= e($i['kind']) ?> · <?= e($i['city']) ?></p><?php if (trim($i['address']) !== ''): ?><p><?= e($i['address']) ?></p><?php endif; ?><p class="u-phone">☎ <?= e($i['phone']) ?></p></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'courses'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Programs & courses')) ?></div><h1><?= $programCount ?> <?= e(tr('programs open')) ?></h1><p>Only programs explicitly opened by the institute are listed. Fees, dates and eligibility come straight from the CRM.</p><?php if (!$programs): ?><p class="u-muted"><?= e(tr('Admissions are opening soon.')) ?></p><?php else: ?><div class="u-grid"><?php foreach ($programs as $p): ?><article class="u-card"><div class="u-eyebrow"><?= e($p['institute_name']) ?></div><h3><?= e($p['name']) ?></h3><p class="u-meta"><?= e($p['duration']) ?> · <?= e($p['city']) ?></p><p><?= e(mb_substr($p['description'], 0, 160)) ?>…</p><p class="u-fee">₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></p><p class="u-apply-by"><?= e(tr('Apply by')) ?> <?= e($p['closes_on']) ?></p><a class="u-btn solid" href="apply.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Details & Apply')) ?> →</a></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'admissions'): ?>
<<<<<<< HEAD
<<<<<<< HEAD
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Admissions')) ?></div><h1><?= e(tr('How to join')) ?></h1><div class="u-steps"><div><span>1</span><strong><?= e(tr('Create your account')) ?></strong><p>Register with your email and verify the code.</p></div><div><span>2</span><strong><?= e(tr('Apply online')) ?></strong><p>One simple form for your chosen program.</p></div><div><span>3</span><strong>Upload certificates</strong><p>Add mark sheets when the office enables uploads.</p></div><div><span>4</span><strong>Office verification</strong><p>Eligibility and originals checked by the office.</p></div><div><span>5</span><strong>Track &amp; join</strong><p>Follow your status, then open your student portal.</p></div></div><p><a class="u-btn solid big" href="apply.php"><?= e(tr('Start your application')) ?> →</a> <a class="u-btn ghost big" href="student.php?page=register"><?= e(tr('Create account')) ?></a></p></section>
<<<<<<< HEAD
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of 549483e (new)
=======
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Admissions')) ?></div><h1><?= e(tr('How to join')) ?></h1><div class="u-steps"><div><span>1</span><strong><?= e(tr('Create your account')) ?></strong><p>Register with your email and verify the code.</p></div><div><span>2</span><strong><?= e(tr('Apply online')) ?></strong><p>One simple form for your chosen program.</p></div><div><span>3</span><strong>Upload certificates</strong><p>Add mark sheets when the office enables uploads.</p></div><div><span>4</span><strong>Office verification</strong><p>Eligibility and originals checked by the office.</p></div><div><span>5</span><strong>Track &amp; join</strong><p>Follow your status, then open your student portal.</p></div></div><p><a class="u-btn solid big" href="apply.php"><?= e(tr('Start your application')) ?> →</a> <a class="u-btn ghost big" href="?page=login&show=create"><?= e(tr('Create account')) ?></a></p></section>
>>>>>>> parent of ba104b3 (new)
=======
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Admissions')) ?></div><h1><?= e(tr('How to join')) ?></h1><div class="u-steps"><div><span>1</span><strong><?= e(tr('Create your account')) ?></strong><p>Register with your email and verify the code.</p></div><div><span>2</span><strong><?= e(tr('Apply online')) ?></strong><p>One simple form for your chosen program.</p></div><div><span>3</span><strong>Upload certificates</strong><p>Add mark sheets when the office enables uploads.</p></div><div><span>4</span><strong>Office verification</strong><p>Eligibility and originals checked by the office.</p></div><div><span>5</span><strong>Track &amp; join</strong><p>Follow your status, then open your student portal.</p></div></div><p><a class="u-btn solid big" href="apply.php"><?= e(tr('Start your application')) ?> →</a> <a class="u-btn ghost big" href="?page=login&show=create"><?= e(tr('Create account')) ?></a></p></section>
>>>>>>> parent of ba104b3 (new)
<section class="u-section"><div class="u-eyebrow"><?= e(tr('ELIGIBILITY & DATES')) ?></div><h2><?= e(tr('What you need to know')) ?></h2><p>D.Pharm applicants typically need <strong>10+2 with Physics, Chemistry and Biology/Mathematics</strong>. Final eligibility, seats and document verification are confirmed by the office. Never pay anyone outside the official student portal after admission.</p><?php if ($programs): ?><div class="u-table-wrap"><table><thead><tr><th><?= e(tr('Program')) ?></th><th><?= e(tr('Campus')) ?></th><th><?= e(tr('Fee')) ?></th><th><?= e(tr('Apply by')) ?></th><th></th></tr></thead><tbody><?php foreach ($programs as $p): ?><tr><td><strong><?= e($p['name']) ?></strong></td><td><?= e($p['institute_name']) ?></td><td>₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></td><td><?= e($p['closes_on']) ?></td><td><a href="apply.php?page=course&course=<?= (int)$p['id'] ?>"><?= e(tr('Apply')) ?> →</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php elseif ($page === 'notices'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Notices & dates')) ?></div><h1><?= e(tr('Admission notices')) ?></h1><p>Published automatically from live admission dates in the CRM. Detailed office notices for enrolled students appear inside the student portal.</p><?php if (!$notices): ?><p class="u-muted"><?= e(tr('No notices right now.')) ?></p><?php else: ?><ul class="u-notices"><?php foreach ($notices as $n): ?><li><span class="u-pill<?= $n['closing'] ? ' soon' : '' ?>"><?= e(tr($n['closing'] ? 'Closing soon' : 'Open')) ?></span><div><strong>Admissions <?= $n['closing'] ? 'closing soon' : 'open' ?>: <?= e($n['course']) ?></strong><small><?= e($n['institute']) ?> · Last date <?= e($n['closes']) ?></small></div><a href="apply.php?page=course&course=<?= (int)$n['id'] ?>"><?= e(tr('Apply')) ?> →</a></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php elseif ($page === 'faculty'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Faculty')) ?></div><h1><?= e(tr('Learn from experienced teachers')) ?></h1><p>Directory updates automatically from the CRM teaching roster. Contact details are shared by the office on request.</p><?php if (!$teachers): ?><p class="u-muted"><?= e(tr('The faculty directory will appear here once the office adds teachers.')) ?></p><?php else: ?><div class="u-grid"><?php foreach ($teachers as $t): ?><article class="u-card u-teacher"><span class="u-avatar"><?= e(strtoupper(substr($t['name'], 0, 1))) ?></span><div><h3><?= e($t['name']) ?></h3><p class="u-meta"><?= e($t['qualification']) ?></p><p class="u-meta"><?= e($t['institute_name']) ?></p></div></article><?php endforeach; ?></div><?php endif; ?></section>
<?php elseif ($page === 'contact'): ?>
<section class="u-section"><div class="u-eyebrow"><?= e(tr('Contact')) ?></div><h1><?= e(tr('Visit or call us')) ?></h1><p>Addresses and phone numbers come live from the CRM — always current.</p><p><a class="u-btn solid" href="?page=login&show=inquiry"><?= e(tr('Admission Inquiry')) ?> →</a></p><?php if (!$institutes): ?><p class="u-muted"><?= e(tr('Contact details will appear here soon.')) ?></p><?php else: ?><div class="u-grid"><?php foreach ($institutes as $i): ?><article class="u-card"><h3><?= e($i['name']) ?></h3><?php if (trim($i['address']) !== ''): ?><p>📍 <?= e($i['address']) ?>, <?= e($i['city']) ?></p><?php else: ?><p>📍 <?= e($i['city']) ?></p><?php endif; ?><p class="u-phone">☎ <?= e($i['phone']) ?></p><p><a class="u-btn ghost" href="?page=admissions"><?= e(tr('Admission help')) ?> →</a></p></article><?php endforeach; ?></div><?php endif; ?><p class="u-muted">Never share passwords or OTP codes with anyone, including callers claiming to be the office.</p></section>
<?php elseif ($page === 'brochure'): ?>
<section class="u-section"><div class="u-eyebrow">College brochure</div><h1><?= e($brand) ?></h1>
<?php if ($brochureError): ?><div class="u-alert error" role="alert"><?= e($brochureError) ?></div><?php endif; ?>
<div class="c-brochure-actions"><p><a class="u-btn solid" href="?page=brochure&format=pdf">Download PDF ↓</a></p><p class="u-muted">Generated from live institute records, or use your browser print function on this page.</p></div>
<div class="c-brochure-doc"><h1><?= e($brand) ?></h1><p><?= e($brandKind) ?><?= $brandCity ? ' · ' . e($brandCity) : '' ?><?= $brandPhone ? ' · ' . e($brandPhone) : '' ?></p><p>Approved by AICTE | PCI | Affiliated to MAKAUT, WB.</p><p>Admissions are verified by the institute office. Apply online and track your application from submission to the office decision.</p>
<?php if ($allPrograms): ?><table><thead><tr><th>Program</th><th>Institute</th><th>Duration</th><th>Fee</th></tr></thead><tbody><?php foreach ($allPrograms as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= e($p['institute_name']) ?></td><td><?= e($p['duration']) ?></td><td>₹<?= number_format((int)$p['fee_minor'] / 100, 2) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<p>Contact the office to confirm seats, eligibility and dates before applying.</p></div></section>
<?php elseif ($page === 'login'): ?>
<section class="u-login"><div class="u-card"><div class="u-eyebrow"><?= e(tr('ONE SIGN-IN FOR EVERYONE')) ?></div><h1><?= e(tr('Sign in')) ?></h1><p><?= e(tr('Enter your email.')) ?> <?= e(tr('Staff go to the')) ?> <strong><?= e(tr('office dashboard')) ?></strong>; <?= e(tr('students go to the')) ?> <strong><?= e(tr('student dashboard')) ?></strong> <?= e(tr('— automatically.')) ?></p>
<?php if ($siteError): ?><div class="u-alert error" role="alert"><?= e($siteError) ?></div><?php endif; ?>
<?php if ($siteFlash): ?><div class="u-alert" role="status"><?= e($siteFlash) ?></div><?php endif; ?>
<?php if ($siteStep === 'code'): ?>
<p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e($otpEmail) ?></strong>.</p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_verify"><label><?= e(tr('Enter the 6-digit code')) ?><input type="text" name="code" inputmode="numeric" maxlength="20" required autocomplete="one-time-code" placeholder="123456"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & sign in')) ?> →</button></form>
<div class="u-otp-alt"><form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_start"><input type="hidden" name="email" value="<?= e($otpEmail) ?>"><button class="u-link-button" type="submit"><?= e(tr('Resend code')) ?></button></form><span>·</span><a href="?page=login"><?= e(tr('Start over')) ?></a></div>
<?php elseif ($siteStep === 'chooser'): ?>
<div class="u-alert" role="status"><?= e(tr('No account found for')) ?> <strong><?= e($siteEmail) ?></strong>. <?= e(tr('New student?')) ?></div>
<h3><?= e(tr('Choose how to continue')) ?></h3>
<<<<<<< HEAD
<<<<<<< HEAD
<div class="u-account-btns"><button type="button" class="u-btn solid" data-modal-open="modal-create"><?= e(tr('I am a student — create my account')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-inquiry"><?= e(tr('I want to enquire about admission')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-applicant"><?= e(tr('I applied — track my application')) ?></button></div>
=======
<div class="u-account-btns"><button type="button" class="u-btn solid" data-modal-open="modal-create"><?= e(tr('I am a student — create my account')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-inquiry"><?= e(tr('I want to enquire about admission')) ?></button></div>
>>>>>>> parent of 549483e (new)
=======
<div class="u-account-btns"><button type="button" class="u-btn solid" data-modal-open="modal-create"><?= e(tr('I am a student — create my account')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-inquiry"><?= e(tr('I want to enquire about admission')) ?></button></div>
>>>>>>> parent of 549483e (new)
<p><?= e(tr('or')) ?> <a href="apply.php"><?= e(tr('apply for admission')) ?></a>. <?= e(tr('Staff should contact the administrator.')) ?></p>
<p><a href="?page=login"><?= e(tr('Back to sign in')) ?></a></p>
<?php elseif ($siteStep === 'staff_password'): ?>
<h3><?= e(tr('Staff password sign-in')) ?></h3>
<p><?= e(tr('Your institute uses password sign-in for staff. Continue below:')) ?></p>
<<<<<<< HEAD
<<<<<<< HEAD
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="password_login"><input type="hidden" name="email" value="<?= e($siteEmail) ?>"><label><?= e(tr('Your email address')) ?><input type="email" value="<?= e($siteEmail) ?>" disabled></label><label><?= e(tr('Password')) ?><input type="password" name="password" maxlength="72" required autocomplete="current-password"></label><button class="u-btn solid big" type="submit"><?= e(tr('Sign in to office')) ?> →</button></form>
<p><a href="?page=login"><?= e(tr('Back to sign in')) ?></a></p>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_start"><label><?= e(tr('Your email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email" value="<?= e($siteEmail) ?>" placeholder="you@example.com"></label><button class="u-btn solid big" type="submit"><?= e(tr('Send sign-in code')) ?> →</button></form>
<div class="u-account-btns"><button type="button" class="u-btn ghost" data-modal-open="modal-create"><?= e(tr('Create Student Account')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-inquiry"><?= e(tr('Admission Inquiry')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-recovery"><?= e(tr('Forgot password?')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-applicant"><?= e(tr('Track application')) ?></button></div>
<noscript><p><a href="?page=login&amp;show=create"><?= e(tr('Create Student Account')) ?></a> · <a href="?page=login&amp;show=inquiry"><?= e(tr('Admission Inquiry')) ?></a> · <a href="?page=login&amp;show=recovery"><?= e(tr('Forgot password?')) ?></a> · <a href="?page=login&amp;show=applicant"><?= e(tr('Track application')) ?></a></p></noscript>
<?php endif; ?>
</div></section>
<div class="u-modal" id="modal-create"<?= $show === 'create' ? ' data-open="1"' : '' ?>><div class="u-modal-card" role="dialog" aria-modal="true" aria-label="<?= e(tr('Create your student account')) ?>"><button type="button" class="u-modal-close" data-modal-close aria-label="Close">×</button><div class="u-eyebrow"><?= e(tr('Create Student Account')) ?></div><h2><?= e(tr('Create your student account')) ?></h2><p><?= e(tr('Verify your Gmail to create your account.')) ?></p>
<?php if (!function_exists('studentUsersReady') || !studentUsersReady()): ?>
<p>Registration is not ready yet. The institute must run the upgrade first.</p>
<?php elseif ($siteStep === 'create_code'): ?>
<p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e($otpEmail) ?></strong>.</p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_verify"><label><?= e(tr('Your verification code')) ?><input type="text" name="code" inputmode="numeric" maxlength="20" required autocomplete="one-time-code" placeholder="123456"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & sign in')) ?> →</button></form>
<?php else: ?>
<<<<<<< HEAD
<<<<<<< HEAD
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_start"><label><?= e(tr('Full name')) ?><input type="text" name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Home address')) ?><input type="text" name="address" maxlength="300" required autocomplete="street-address"></label><label><?= e(tr('Mobile number')) ?><input type="text" name="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" minlength="10" required autocomplete="tel" placeholder="10-digit mobile number"></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><input type="text" name="website" value="" class="u-honey" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="u-btn solid big" type="submit"><?= e(tr('Send verification code')) ?> →</button></form>
=======
<p><a class="u-btn solid" href="office.php?email=<?= urlencode($siteEmail) ?>"><?= e(tr('Continue to office login')) ?> →</a></p>
<p><a href="?page=login"><?= e(tr('Back to sign in')) ?></a></p>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_start"><label><?= e(tr('Your email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email" value="<?= e($siteEmail) ?>" placeholder="you@example.com"></label><button class="u-btn solid big" type="submit"><?= e(tr('Send sign-in code')) ?> →</button></form>
=======
<p><a class="u-btn solid" href="office.php?email=<?= urlencode($siteEmail) ?>"><?= e(tr('Continue to office login')) ?> →</a></p>
<p><a href="?page=login"><?= e(tr('Back to sign in')) ?></a></p>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="otp_start"><label><?= e(tr('Your email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email" value="<?= e($siteEmail) ?>" placeholder="you@example.com"></label><button class="u-btn solid big" type="submit"><?= e(tr('Send sign-in code')) ?> →</button></form>
>>>>>>> parent of 549483e (new)
<div class="u-account-btns"><button type="button" class="u-btn ghost" data-modal-open="modal-create"><?= e(tr('Create Student Account')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-inquiry"><?= e(tr('Admission Inquiry')) ?></button><button type="button" class="u-btn ghost" data-modal-open="modal-recovery"><?= e(tr('Forgot password?')) ?></button></div>
<noscript><p><a href="?page=login&amp;show=create"><?= e(tr('Create Student Account')) ?></a> · <a href="?page=login&amp;show=inquiry"><?= e(tr('Admission Inquiry')) ?></a> · <a href="?page=login&amp;show=recovery"><?= e(tr('Forgot password?')) ?></a></p></noscript>
<?php endif; ?>
<div class="u-login-links"><a href="student.php"><?= e(tr('Student sign-in')) ?> →</a><a href="office.php"><?= e(tr('Office sign-in')) ?> →</a><a href="student.php?page=register"><?= e(tr('Create student account')) ?> →</a></div></div></section>
<div class="u-modal" id="modal-create"<?= $show === 'create' ? ' data-open="1"' : '' ?>><div class="u-modal-card" role="dialog" aria-modal="true" aria-label="<?= e(tr('Create your student account')) ?>"><button type="button" class="u-modal-close" data-modal-close aria-label="Close">×</button><div class="u-eyebrow"><?= e(tr('Create Student Account')) ?></div><h2><?= e(tr('Create your student account')) ?></h2><p><?= e(tr('Verify your Gmail to create your account.')) ?></p>
<?php if ($siteStep === 'create_code'): ?>
<p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e($otpEmail) ?></strong>.</p>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_verify"><label><?= e(tr('Your verification code')) ?><input type="text" name="code" inputmode="numeric" maxlength="20" required autocomplete="one-time-code" placeholder="123456"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & sign in')) ?> →</button></form>
<?php else: ?>
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_start"><label><?= e(tr('Full name')) ?><input type="text" name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Home address')) ?><input type="text" name="address" maxlength="300" required autocomplete="street-address"></label><label><?= e(tr('Mobile number')) ?><input type="text" name="phone" maxlength="30" required autocomplete="tel" placeholder="+91 "></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><input type="text" name="website" value="" class="u-honey" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="u-btn solid big" type="submit"><?= e(tr('Send verification code')) ?> →</button></form>
<<<<<<< HEAD
>>>>>>> parent of 549483e (new)
=======
>>>>>>> parent of 549483e (new)
=======
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_start"><label><?= e(tr('Full name')) ?><input type="text" name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Home address')) ?><input type="text" name="address" maxlength="300" required autocomplete="street-address"></label><label><?= e(tr('Mobile number')) ?><input type="text" name="phone" maxlength="30" required autocomplete="tel" placeholder="+91 "></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><input type="text" name="website" value="" class="u-honey" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="u-btn solid big" type="submit"><?= e(tr('Send verification code')) ?> →</button></form>
>>>>>>> parent of ba104b3 (new)
=======
<form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_start"><label><?= e(tr('Full name')) ?><input type="text" name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Home address')) ?><input type="text" name="address" maxlength="300" required autocomplete="street-address"></label><label><?= e(tr('Mobile number')) ?><input type="text" name="phone" maxlength="30" required autocomplete="tel" placeholder="+91 "></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><input type="text" name="website" value="" class="u-honey" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="u-btn solid big" type="submit"><?= e(tr('Send verification code')) ?> →</button></form>
>>>>>>> parent of ba104b3 (new)
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
<section class="u-login"><div class="u-card"><div class="u-eyebrow">NEW STUDENT ACCOUNT</div><h1>Create your account</h1><p>Use your email to create a secure account. We will send a verification code.</p>
<?php if ($siteError): ?><div class="u-alert error" role="alert"><?= e($siteError) ?></div><?php endif; ?><?php if ($siteFlash): ?><div class="u-alert" role="status"><?= e($siteFlash) ?></div><?php endif; ?>
<?php if ($siteStep === 'create_code' || isset($_SESSION['suid_pending'])): ?><p><strong><?= e(tr('Check your inbox')) ?></strong> — <?= e(tr('We sent a 6-digit code to')) ?> <strong><?= e((string)($_SESSION['suid_pending']['email'] ?? 'your email')) ?></strong>.</p><form method="post"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_verify"><label><?= e(tr('Enter the 6-digit code')) ?><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required class="u-code"></label><button class="u-btn solid big" type="submit"><?= e(tr('Verify & sign in')) ?> →</button></form><p class="u-login-create"><a href="?page=create-account">Start over</a></p>
<?php else: ?><form method="post" class="u-form"><?= siteCsrfField() ?><input type="hidden" name="action" value="create_start"><label><?= e(tr('Full name')) ?><input name="name" maxlength="120" required autocomplete="name"></label><label><?= e(tr('Mobile number')) ?><input name="phone" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" minlength="10" required autocomplete="tel" placeholder="10-digit mobile number"></label><label><?= e(tr('Gmail / email address')) ?><input type="email" name="email" maxlength="200" required autocomplete="email"></label><label><?= e(tr('Home address')) ?><input name="address" maxlength="300" autocomplete="street-address"></label><button class="u-btn solid big" type="submit">Send verification code →</button></form><p class="u-login-create"><a href="?page=login">Already have an account? Sign in</a></p><?php endif; ?></div></section>
<?php else: ?>
<section class="u-card"><h1><?= e(tr('Page not found')) ?></h1><p><?= e(tr('This university page does not exist.')) ?></p><p><a class="u-btn solid" href="?"><?= e(tr('Back to home')) ?> →</a></p></section>
<?php endif; ?>
</main>
<?=collegeFooter($brand, $brandKind, $brandCity, $brandAddress, $brandPhone)?>
</body></html>
