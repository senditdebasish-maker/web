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
    if (str_contains($n, 'd.pharm') || str_contains($n, 'd pharm')) return '(Diploma in Pharmacy)';
    if (str_contains($n, 'b.pharm') || str_contains($n, 'b pharm')) return '(Bachelor of Pharmacy)';
    if (str_contains($n, 'm.pharm') || str_contains($n, 'm pharm')) return '(Master of Pharmacy)';
    if (str_contains($n, 'ph.d') || str_contains($n, 'phd')) return '(Doctor of Philosophy)';
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
    $h = '<nav class="c-nav" aria-label="College"><div class="c-wrap"><ul>';
    foreach ($items as [$label, $url, $slug, $kids]) {
        $cur = ($slug !== '' && $slug === $key) ? ' aria-current="page"' : '';
        $prefix = $label === 'Home' ? '<span class="c-nav-ico">' . collegeIcon('home') . '</span> ' : '';
        $h .= '<li' . ($kids ? ' class="c-has-kids"' : '') . '><a href="' . e($url) . '"' . $cur . '>' . $prefix . e($label) . ($kids ? ' <span class="c-caret" aria-hidden="true">▾</span>' : '') . '</a>';
        if ($kids) {
            $h .= '<ul class="c-drop">';
            foreach ($kids as [$klabel, $kurl]) $h .= '<li><a href="' . e($kurl) . '">' . e($klabel) . '</a></li>';
            $h .= '</ul>';
        }
        $h .= '</li>';
    }
    $h .= '</ul></div></nav>';
    return $h;
}
function collegeHeader(string $brand, string $kindLine, string $page, string $headerAction, array $ticker): string {
    $home = siteHomeUrl();
    $h = '<div class="c-topbar"><div class="c-wrap c-topbar-in"><span class="c-approvals">Approved by AICTE | PCI | Affiliated to MAKAUT, WB</span><span class="c-top-links">';
    if (str_contains($headerAction, 'u-profile-box')) $h .= $headerAction;
    else $h .= '<a href="' . e($home . '?page=login') . '">Student Login</a><a href="' . e($home . '?page=login') . '">Faculty Login</a><a href="' . e($home . '?page=login') . '">Admin Login</a>';
    $h .= '<a class="c-top-search" href="' . e($home . '?page=courses') . '" aria-label="Search programs">' . collegeIcon('search') . '</a>' . siteToggle() . siteLangToggle($page) . '</span></div></div>';
    $h .= '<header class="c-head"><div class="c-wrap c-head-in"><a class="c-brand u-brand" href="' . e($home) . '" title="Back to the college homepage"><span class="c-crest">' . siteCrest() . '</span><span><strong>' . e($brand) . '</strong><small>' . e($kindLine) . '</small></span></a>';
    $h .= '<span class="c-head-btns"><a href="' . e($home . '?page=login&show=inquiry') . '">' . collegeIcon('user') . '<span>Enquiry</span></a><a href="' . e($home . '?page=notices') . '">' . collegeIcon('down') . '<span>Download</span></a><a href="' . e($home . '?page=brochure') . '">' . collegeIcon('doc') . '<span>Download Brochure</span></a><a class="c-apply" href="' . e(sitePublicUrl('student.php?page=admissions')) . '">' . collegeIcon('userplus') . '<span>' . e(tr('Apply Now')) . '</span></a></span></div></header>';
    $h .= collegeNav($page) . siteTicker($ticker);
    return $h;
}
function collegeFooter(string $brand, string $kind, string $city = '', string $address = '', string $phone = ''): string {
    $home = siteHomeUrl();
    $h = '<footer class="c-footer"><div class="c-wrap c-foot-grid">';
    $h .= '<div><a class="c-brand light" href="' . e($home) . '"><span class="c-crest">' . siteCrest() . '</span><span><strong>' . e($brand) . '</strong><small>Education | Research | Healthcare | A Better Tomorrow</small></span></a></div>';
    $h .= '<div><h4>' . e(tr('Quick Links')) . '</h4><nav aria-label="Quick links"><a href="' . e($home) . '">Home</a><a href="' . e($home . '?page=about') . '">About Us</a><a href="' . e($home . '?page=admissions') . '">Admissions</a><a href="' . e($home . '?page=courses') . '">Programs</a><a href="' . e($home . '?page=notices') . '">Notices</a></nav></div>';
    $h .= '<div><h4>Student Corner</h4><nav aria-label="Student corner"><a href="' . e($home . '?page=login') . '">Student Login</a><a href="' . e(sitePublicUrl('apply.php')) . '">Apply online</a><a href="' . e($home . '?page=login&show=create') . '">Create account</a><a href="' . e($home . '?page=notices') . '">Downloads</a><a href="' . e($home . '?page=login&show=inquiry') . '">Grievance</a></nav></div>';
    $h .= '<div><h4>Contact Us</h4>';
    if (trim($address . $city . $phone) === '') $h .= '<p class="u-muted">Contact details will appear here soon.</p>';
    else {
        if (trim($address) !== '') $h .= '<p>📍 ' . e($address) . ($city !== '' ? ', ' . e($city) : '') . '</p>';
        elseif ($city !== '') $h .= '<p>📍 ' . e($city) . '</p>';
        if ($phone !== '') $h .= '<p>☎ ' . e($phone) . '</p>';
    }
    $h .= '</div>';
    $h .= '<div><h4>' . e(tr('Follow Us')) . '</h4>' . collegeSocial() . '<p class="c-script">Pharmacy for<br>a Healthier Tomorrow</p></div>';
    $h .= '</div><div class="c-foot-bottom"><div class="c-wrap"><span>© ' . date('Y') . ' ' . e($brand) . '. ' . e(tr('All rights reserved.')) . '</span><span class="c-legal"><a href="#">Privacy Policy</a> | <a href="#">Terms of Use</a> | <a href="#">Sitemap</a></span><span>Designed for Knowledge. Driven by Care.</span></div></div></footer>';
    return $h;
}
