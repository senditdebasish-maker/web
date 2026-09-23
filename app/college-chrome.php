<?php
declare(strict_types=1);
// College-style front-site chrome, used ONLY by index.php. Portals keep the
// shared siteHeader()/siteFooter() untouched. Every link reuses an existing
// route; no account, admission or payment logic lives here.
function collegeIcon(string $name): string {
    $p = [
        'cap' => '<path d="M12 3 2 8l10 5 10-5-10-5Z"/><path d="M6 10.5V15c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 8v6"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.6-3 2.8-4.5 5.5-4.5s4.9 1.5 5.5 4.5"/><circle cx="16.5" cy="9" r="2.6"/><path d="M15.5 14.6c2.9.1 4.4 1.6 5 4.4"/>',
        'flask' => '<path d="M9 3h6"/><path d="M10 3v5.5L5.5 17a2 2 0 0 0 1.8 3h9.4a2 2 0 0 0 1.8-3L14 8.5V3"/><path d="M7.5 14h9"/>',
        'bank' => '<path d="M2 8l10-5 10 5"/><rect x="4" y="8" width="16" height="12" rx="1"/><path d="M8 12v3M12 12v3M16 12v3M10 20v-3h4v3"/>',
        'case' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/>',
        'heart' => '<path d="M12 20s-7.5-4.7-7.5-10A4.3 4.3 0 0 1 12 7a4.3 4.3 0 0 1 7.5 3c0 5.3-7.5 10-7.5 10Z"/>',
        'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3Z"/><path d="M9 12l2 2 4-4"/>',
        'award' => '<circle cx="12" cy="9" r="4.5"/><path d="M9 13l-2 8 5-3 5 3-2-8"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M4 19a2 2 0 0 1 2-2h13"/>',
        'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'bus' => '<rect x="4" y="4" width="16" height="12" rx="2"/><path d="M4 11h16"/><circle cx="8" cy="18.5" r="1.6"/><circle cx="16" cy="18.5" r="1.6"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'down' => '<path d="M12 3v11"/><path d="M7 10l5 5 5-5"/><path d="M4 20h16"/>',
        'doc' => '<path d="M6 2.5h8L19 8v13.5H6V2.5Z"/><path d="M14 2.5V8h5"/><path d="M9 12h6M9 15.5h6"/>',
        'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c.8-3.6 3.4-5.4 7-5.4s6.2 1.8 7 5.4"/>',
        'userplus' => '<circle cx="10" cy="8" r="3.4"/><path d="M3.5 19.5c.7-3.4 3.1-5.1 6.5-5.1 1.4 0 2.7.3 3.7.9"/><path d="M17.5 14.5v6M14.5 17.5h6"/>',
        'trophy' => '<path d="M8 4h8v4a4 4 0 0 1-8 0V4Z"/><path d="M8 5H4.5a3.5 3.5 0 0 0 3.6 3.5M16 5h3.5a3.5 3.5 0 0 1-3.6 3.5"/><path d="M12 12v4M8.5 20h7M10 16.5h4"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['shield']) . '</svg>';
}
// Six feature highlights under the hero (starter template; edit freely).
function collegeHighlights(): array {
    return [
        ['cap', 'PCI Approved', 'Quality Education as per PCI Norms'],
        ['users', 'Experienced Faculty', 'Learn from Experts'],
        ['flask', 'Modern Laboratories', 'Hands-on Practical Training'],
        ['bank', 'Lush Campus', 'Conducive Learning Environment'],
        ['case', 'Placement Support', 'Career Guidance & Industry Connect'],
        ['heart', 'Community Service', 'For a Healthier Society'],
    ];
}
// Trust strip above the footer (starter template; edit freely).
function collegeTrustBadges(): array {
    return [
        ['shield', 'PCI Approved'], ['award', 'AICTE Recognized'], ['bank', 'Affiliated to MAKAUT, WB'],
        ['book', 'Digital Library'], ['home', 'Hostel Facility'], ['bus', 'Transport Facility'],
    ];
}
// Degree full-form subtitle for program cards (starter template wording).
function collegeDegreeLine(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'd.pharm') || str_contains($n, 'd pharm') || str_contains($n, 'diploma')) return '(Diploma in Pharmacy)';
    if (str_contains($n, 'b.pharm') || str_contains($n, 'b pharm') || str_contains($n, 'bachelor')) return '(Bachelor of Pharmacy)';
    if (str_contains($n, 'm.pharm') || str_contains($n, 'm pharm') || str_contains($n, 'master')) return '(Master of Pharmacy)';
    if (str_contains($n, 'ph.d') || str_contains($n, 'phd') || str_contains($n, 'doctor')) return '(Doctor of Philosophy)';
    return '';
}
// Degree short code for program cards, e.g. "Diploma in Pharmacy" -> "D.Pharm".
function collegeDegreeShort(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'd.pharm') || str_contains($n, 'd pharm') || str_contains($n, 'diploma')) return 'D.Pharm';
    if (str_contains($n, 'b.pharm') || str_contains($n, 'b pharm') || str_contains($n, 'bachelor')) return 'B.Pharm';
    if (str_contains($n, 'm.pharm') || str_contains($n, 'm pharm') || str_contains($n, 'master')) return 'M.Pharm';
    if (str_contains($n, 'ph.d') || str_contains($n, 'phd') || str_contains($n, 'doctor')) return 'Ph.D.';
    return '';
}
// Homepage showcase stats (starter template numbers; the About page keeps live CRM counts).
function collegeSpotStats(): array {
    return [
        ['cap', '500+', 'Students'],
        ['users', '50+', 'Faculty Members'],
        ['flask', '10+', 'Modern Labs'],
        ['shield', '100%', 'PCI Compliant'],
        ['trophy', 'Excellent', 'Placement Support'],
    ];
}
// Social placeholders with brand glyphs (spans until the institute shares real profile URLs).
function collegeSocial(): string {
    $fb = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1877f2"/><text x="12" y="17" text-anchor="middle" font-size="13" font-weight="bold" fill="#fff" font-family="sans-serif">f</text></svg>';
    $yt = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="6" width="19" height="12" rx="3.5" fill="#ff0000"/><path d="M10.5 9.8v4.4L14.5 12Z" fill="#fff"/></svg>';
    $ig = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#c13584"/><rect x="6.5" y="6.5" width="11" height="11" rx="3" fill="none" stroke="#fff" stroke-width="1.6"/><circle cx="12" cy="12" r="2.6" fill="none" stroke="#fff" stroke-width="1.6"/><circle cx="16" cy="8" r="1.1" fill="#fff"/></svg>';
    $li = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#0a66c2"/><text x="12" y="16.5" text-anchor="middle" font-size="10" font-weight="bold" fill="#fff" font-family="sans-serif">in</text></svg>';
    return '<div class="c-social"><span title="Facebook" aria-label="Facebook">' . $fb . '</span><span title="YouTube" aria-label="YouTube">' . $yt . '</span><span title="Instagram" aria-label="Instagram">' . $ig . '</span><span title="LinkedIn" aria-label="LinkedIn">' . $li . '</span></div>';
}
// Program card artwork keyed off the live course name; the building is the neutral fallback.
function collegeProgramImage(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'm.pharm') || str_contains($n, 'm pharm') || str_contains($n, 'master')) return 'assets/program-mpharm.jpg';
    if (str_contains($n, 'b.pharm') || str_contains($n, 'b pharm') || str_contains($n, 'bachelor')) return 'assets/program-bpharm.jpg';
    if (str_contains($n, 'd.pharm') || str_contains($n, 'd pharm') || str_contains($n, 'diploma')) return 'assets/program-dpharm.jpg';
    if (str_contains($n, 'ph.d') || str_contains($n, 'phd') || str_contains($n, 'doctor')) return 'assets/program-phd.jpg';
    return 'assets/college-hero.jpg';
}
function collegeNav(string $active): string {
    $home = siteHomeUrl();
    $key = $active === 'course' ? 'courses' : $active;
    $items = [
        ['Home', $home, 'home', []],
        ['About Us', $home . '?page=about', 'about', [['About the College', $home . '?page=about'], ['Our Campuses', $home . '?page=about#campuses'], ['Contact Us', $home . '?page=contact']]],
        ['Academics', $home . '?page=courses', 'courses', [['Programs & Courses', $home . '?page=courses'], ['Faculty', $home . '?page=faculty'], ['Admission Notices', $home . '?page=notices']]],
        ['Admissions', $home . '?page=admissions', 'admissions', [['How to Join', $home . '?page=admissions'], ['Apply Now', sitePublicUrl('apply.php')], ['Track Application', $home . '?page=login&show=applicant']]],
        ['Faculty', $home . '?page=faculty', 'faculty', []],
        ['Facilities', $home . '?page=about#campuses', '', []],
        ['Student Corner', sitePublicUrl('student.php'), '', [['Student Login', $home . '?page=login'], ['Student Dashboard', sitePublicUrl('student.php')], ['Create Account', $home . '?page=login&show=create'], ['Admission Inquiry', $home . '?page=login&show=inquiry']]],
        ['Placement', $home . '?page=admissions', '', []],
        ['Research', $home . '?page=faculty', '', []],
        ['Notices', $home . '?page=notices', 'notices', []],
        ['Contact', $home . '?page=contact', 'contact', []],
    ];
    $h = '<nav class="nav"><div class="container nav-inner"><ul class="nav-list" id="navList">';
    foreach ($items as [$label, $url, $slug, $kids]) {
        $act = ($slug !== '' && $slug === $key) ? ' active' : '';
        $prefix = $label === 'Home' ? '<i class="fa-solid fa-house"></i> ' : '';
        $h .= '<li class="nav-item' . $act . '"><a href="' . e($url) . '" class="nav-link">' . $prefix . e(tr($label)) . ($kids ? ' <i class="fa-solid fa-chevron-down"></i>' : '') . '</a>';
        if ($kids) {
            $h .= '<div class="dropdown">';
            foreach ($kids as [$klabel, $kurl]) $h .= '<a href="' . e($kurl) . '">' . e(tr($klabel)) . '</a>';
            $h .= '</div>';
        }
        $h .= '</li>';
    }
    $h .= '</ul></div></nav>';
    return $h;
}
// Scrolling "LATEST UPDATES" ticker; items are live CRM admission dates.
function nimitaTicker(array $items): string {
    if (!$items) return '';
    $track = '';
    foreach ([$items, $items] as $dup) foreach ($dup as $t) $track .= '<div class="ticker-item">' . e((string)$t) . '</div>';
    return '<div class="ticker"><div class="container ticker-inner"><div class="ticker-title"><i class="fa-solid fa-bullhorn"></i> ' . e(tr('LATEST UPDATES')) . '</div><div class="ticker-content"><div class="ticker-track">' . $track . '</div></div></div></div>';
}
function collegeHeader(string $brand, string $kindLine, string $page, string $headerAction, array $ticker): string {
    $home = siteHomeUrl();
    $tagline = 'Education | Research | Healthcare | A Better Tomorrow';
    $h = '<div class="topbar"><div class="container topbar-inner"><div class="top-left">';
    $h .= '<span><i class="fa-solid fa-circle-check"></i> ' . e(tr('Approved by AICTE')) . '</span>';
    $h .= '<span><i class="fa-solid fa-shield-halved"></i> ' . e(tr('PCI Approved')) . '</span>';
    $h .= '<span><i class="fa-solid fa-building-columns"></i> ' . e(tr('Affiliated to MAKAUT, WB')) . '</span>';
    $h .= '</div><div class="top-right">';
    if (str_contains($headerAction, 'u-profile-box')) $h .= $headerAction;
    else $h .= '<a href="' . e($home . '?page=login') . '"><i class="fa-solid fa-user-graduate"></i> ' . e(tr('Student Login')) . '</a><a href="' . e($home . '?page=login') . '"><i class="fa-solid fa-chalkboard-user"></i> ' . e(tr('Faculty Login')) . '</a><a href="' . e($home . '?page=login') . '"><i class="fa-solid fa-user-shield"></i> ' . e(tr('Admin Login')) . '</a>';
    $h .= siteToggle() . siteLangToggle($page) . '</div></div></div>';
    $h .= '<header class="header"><div class="container header-main">';
    $h .= '<a href="' . e($home) . '" class="brand"><div class="logo"><i class="fa-solid fa-book-open-reader"></i></div><div class="brand-text"><h1>' . e($brand) . '</h1><p>' . e($tagline) . '</p></div></a>';
    $h .= '<div class="header-actions">';
    $h .= '<a href="' . e($home . '?page=login&show=inquiry') . '" class="header-action"><i class="fa-solid fa-circle-question"></i>' . e(tr('Enquiry')) . '</a>';
    $h .= '<a href="' . e($home . '?page=notices') . '" class="header-action"><i class="fa-solid fa-download"></i>' . e(tr('Download')) . '</a>';
    $h .= '<a href="' . e($home . '?page=brochure') . '" class="header-action"><i class="fa-solid fa-file-pdf"></i>' . e(tr('Prospectus')) . '</a>';
    $h .= '<a href="' . e(sitePublicUrl('apply.php')) . '" class="apply-btn"><i class="fa-solid fa-user-plus"></i>' . e(tr('Apply Now')) . '</a>';
    $h .= '<button class="menu-toggle" id="menuToggle" aria-label="Open Menu"><i class="fa-solid fa-bars"></i></button>';
    $h .= '</div></div>';
    $h .= collegeNav($page) . '</header>' . nimitaTicker($ticker);
    return $h;
}
function collegeFooter(string $brand, string $kind, string $city = '', string $address = '', string $phone = ''): string {
    $home = siteHomeUrl();
    $h = '<footer class="footer"><div class="footer-main"><div class="container"><div class="footer-grid">';
    $h .= '<div class="footer-brand"><div class="logo" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#72efae;margin-bottom:15px;"><i class="fa-solid fa-book-open-reader"></i></div>';
    $h .= '<h2>' . e($brand) . '</h2><p>Education | Research | Healthcare | A Better Tomorrow. Building knowledgeable, skilled and responsible pharmacy professionals.</p>';
    $h .= '<div class="socials"><a href="#" class="social" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" class="social" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a><a href="#" class="social" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><a href="#" class="social" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></div></div>';
    $h .= '<div><h3>' . e(tr('Quick Links')) . '</h3><ul class="footer-links">';
    $h .= '<li><a href="' . e($home) . '">' . e(tr('Home')) . '</a></li><li><a href="' . e($home . '?page=about') . '">' . e(tr('About Us')) . '</a></li><li><a href="' . e($home . '?page=admissions') . '">' . e(tr('Admissions')) . '</a></li><li><a href="' . e($home . '?page=courses') . '">' . e(tr('Programs')) . '</a></li><li><a href="' . e($home . '?page=notices') . '">' . e(tr('Notices')) . '</a></li><li><a href="' . e($home . '?page=contact') . '">' . e(tr('Contact')) . '</a></li>';
    $h .= '</ul></div>';
    $h .= '<div><h3>' . e(tr('Student Corner')) . '</h3><ul class="footer-links">';
    $h .= '<li><a href="' . e($home . '?page=login') . '">' . e(tr('Student Login')) . '</a></li><li><a href="' . e($home . '?page=notices') . '">' . e(tr('Downloads')) . '</a></li><li><a href="' . e($home . '?page=notices') . '">' . e(tr('Examination')) . '</a></li><li><a href="' . e($home . '?page=notices') . '">' . e(tr('Results')) . '</a></li><li><a href="' . e($home . '?page=notices') . '">' . e(tr('Scholarships')) . '</a></li><li><a href="' . e($home . '?page=login&show=inquiry') . '">' . e(tr('Grievance')) . '</a></li>';
    $h .= '</ul></div>';
    $h .= '<div><h3>' . e(tr('Contact Us')) . '</h3>';
    if (trim($address . $city . $phone) === '') $h .= '<div class="contact-line"><i class="fa-solid fa-location-dot"></i><span>' . e(tr('Contact details will appear here soon.')) . '</span></div>';
    else {
        $loc = trim($address) !== '' ? $address . ($city !== '' ? ', ' . $city : '') : $city;
        if ($loc !== '') $h .= '<div class="contact-line"><i class="fa-solid fa-location-dot"></i><span>' . e($loc) . '</span></div>';
        if ($phone !== '') $h .= '<div class="contact-line"><i class="fa-solid fa-phone"></i><span>' . e($phone) . '</span></div>';
    }
    $h .= '<div class="contact-line"><i class="fa-solid fa-clock"></i><span>Mon - Sat: 10:00 AM - 5:00 PM</span></div>';
    $h .= '</div></div></div></div>';
    $h .= '<div class="footer-bottom"><div class="container footer-bottom-inner"><div>© ' . date('Y') . ' ' . e($brand) . '. ' . e(tr('All Rights Reserved.')) . '</div><div>' . e(tr('Privacy Policy')) . ' | ' . e(tr('Terms of Use')) . ' | ' . e(tr('Sitemap')) . '</div></div></div></footer>';
    $h .= '<button class="back-top" id="backTop" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></button>';
    return $h;
}
