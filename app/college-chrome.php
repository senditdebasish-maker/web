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
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'chev' => '<path d="M6 9l6 6 6-6"/>',
        'arrow' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'bell' => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l1.5 2.5h-15L6 16Z"/><path d="M10 21a2.2 2.2 0 0 0 4 0"/>',
        'pin' => '<path d="M12 21s-6.5-5.4-6.5-10.5A6.5 6.5 0 0 1 12 4a6.5 6.5 0 0 1 6.5 6.5C18.5 15.6 12 21 12 21Z"/><circle cx="12" cy="10.5" r="2.3"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/>',
        'check' => '<path d="M4.5 12.5l5 5L19.5 7"/>',
        'cal' => '<rect x="4" y="6" width="16" height="14" rx="2"/><path d="M4 10h16M8 3v5M16 3v5"/>',
        'spark' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>',
        'send' => '<path d="M21 3L10 14"/><path d="M21 3l-7 18-4-7-7-4 18-7Z"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-8M21 20H3"/>',
        'grad' => '<path d="M12 4L2 9l10 5 10-5-10-5Z"/><path d="M6 11.5V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-4.5"/><path d="M22 9v5"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'eye' => '<path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.8"/>',
        'target' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.8"/><circle cx="12" cy="12" r="1.4"/>',
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
<<<<<<< HEAD
// Degree short code for program cards, e.g. "Diploma in Pharmacy" -> "D.Pharm".
function collegeDegreeShort(string $name): string {
    $n = strtolower($name);
    if (str_contains($n, 'd.pharm') || str_contains($n, 'd pharm')) return 'D.Pharm';
    if (str_contains($n, 'b.pharm') || str_contains($n, 'b pharm')) return 'B.Pharm';
    if (str_contains($n, 'm.pharm') || str_contains($n, 'm pharm')) return 'M.Pharm';
    if (str_contains($n, 'ph.d') || str_contains($n, 'phd')) return 'Ph.D.';
    return '';
}
=======
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
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
function collegeNavItems(): array {
    $home = siteHomeUrl();
    $pu = $home . '?page=';
    $apply = sitePublicUrl('apply.php');
    $student = sitePublicUrl('student.php');
    return [
        ['label' => 'Home', 'url' => $home, 'slug' => 'home', 'icon' => 'home', 'kids' => []],
        ['label' => 'About', 'url' => $pu . 'about', 'slug' => 'about', 'icon' => 'bank', 'kids' => [
            ['About the Institution', $pu . 'about'],
            ["Principal's Message", $home . '#principal'],
            ['Vision & Mission', $pu . 'about'],
            ['Accreditation', $pu . 'about'],
            ['Campus', $pu . 'about#campuses'],
        ]],
        ['label' => 'Academics', 'url' => $pu . 'courses', 'slug' => 'courses', 'icon' => 'cap', 'kids' => [
            ['Programs', $pu . 'courses'],
            ['Faculty', $pu . 'faculty'],
            ['Laboratories', $pu . 'facilities#labs'],
            ['Library', $pu . 'facilities#library'],
            ['Academic Calendar', $pu . 'notices'],
        ]],
        ['label' => 'Admissions', 'url' => $pu . 'admissions', 'slug' => 'admissions', 'icon' => 'send', 'kids' => [
            ['Admission Process', $pu . 'admissions'],
            ['Eligibility', $pu . 'admissions#eligibility'],
            ['Fee Structure', $pu . 'courses'],
            ['Important Dates', $pu . 'notices'],
            ['Apply Online', $apply],
            ['Track Application', $pu . 'login&show=applicant'],
        ]],
        ['label' => 'Campus Life', 'url' => $pu . 'facilities', 'slug' => 'facilities', 'icon' => 'heart', 'kids' => [
            ['Facilities', $pu . 'facilities'],
            ['Hostel', $pu . 'facilities#hostel'],
            ['Transport', $pu . 'facilities#transport'],
            ['Student Activities', $pu . 'facilities#activities'],
        ]],
        ['label' => 'Student Corner', 'url' => $student, 'slug' => '', 'icon' => 'users', 'kids' => [
            ['Student Login', $pu . 'login'],
            ['Student Dashboard', $student],
            ['Results', $student],
            ['Notices', $pu . 'notices'],
            ['Downloads', $pu . 'brochure'],
            ['Support', $pu . 'contact'],
        ]],
        ['label' => 'Placement', 'url' => $pu . 'admissions', 'slug' => '', 'icon' => 'case', 'kids' => []],
        ['label' => 'Research', 'url' => $pu . 'faculty', 'slug' => '', 'icon' => 'flask', 'kids' => []],
        ['label' => 'Notices', 'url' => $pu . 'notices', 'slug' => 'notices', 'icon' => 'bell', 'kids' => []],
        ['label' => 'Contact', 'url' => $pu . 'contact', 'slug' => 'contact', 'icon' => 'phone', 'kids' => []],
    ];
<<<<<<< HEAD
}
function collegeNav(string $active): string {
    $key = $active === 'course' ? 'courses' : $active;
    $h = '<nav class="f-navwrap" aria-label="' . e(tr('Primary')) . '"><div class="f-wrap"><ul class="f-nav" role="menubar">';
    foreach (collegeNavItems() as $it) {
        $act = ($it['slug'] !== '' && $it['slug'] === $key) ? ' aria-current="page"' : '';
        $h .= '<li role="none"' . ($it['kids'] ? ' class="f-hasdrop"' : '') . '><a role="menuitem" href="' . e($it['url']) . '"' . $act . '>' . collegeIcon($it['icon']) . '<span>' . e(tr($it['label'])) . '</span>' . ($it['kids'] ? collegeIcon('chev') : '') . '</a>';
        if ($it['kids']) {
            $h .= '<div class="f-drop" role="menu">';
            foreach ($it['kids'] as [$kl, $ku]) $h .= '<a role="menuitem" href="' . e($ku) . '">' . e(tr($kl)) . '</a>';
            $h .= '</div>';
=======
    $h = '<nav class="c-nav" aria-label="College"><div class="c-wrap"><ul>';
    foreach ($items as [$label, $url, $slug, $kids]) {
        $cur = ($slug !== '' && $slug === $key) ? ' aria-current="page"' : '';
        $prefix = $label === 'Home' ? '<span class="c-nav-ico">' . collegeIcon('home') . '</span> ' : '';
        $h .= '<li' . ($kids ? ' class="c-has-kids"' : '') . '><a href="' . e($url) . '"' . $cur . '>' . $prefix . e($label) . ($kids ? ' <span class="c-caret" aria-hidden="true">▾</span>' : '') . '</a>';
        if ($kids) {
            $h .= '<ul class="c-drop">';
            foreach ($kids as [$klabel, $kurl]) $h .= '<li><a href="' . e($kurl) . '">' . e($klabel) . '</a></li>';
            $h .= '</ul>';
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
        }
        $h .= '</li>';
    }
    return $h . '</ul></div></nav>';
}
<<<<<<< HEAD
// Scrolling "LATEST UPDATES" ticker; items are live CRM admission dates. Empty = hidden.
function nimitaTicker(array $items): string {
    $items = array_values(array_filter(array_map('trim', array_map('strval', $items)), fn($t) => $t !== ''));
    if (!$items) return '';
    $track = '';
    foreach ([$items, $items] as $dup) foreach ($dup as $t) $track .= '<span class="f-ticker-item">' . e($t) . '</span>';
    return '<div class="f-ticker" role="marquee" aria-label="' . e(tr('Latest updates')) . '"><div class="f-wrap f-ticker-in"><span class="f-ticker-label">' . collegeIcon('bell') . ' ' . e(tr('LATEST UPDATES')) . '</span><div class="f-ticker-view"><div class="f-ticker-track">' . $track . '</div></div></div></div>';
}
function collegeDrawer(string $page): string {
    $key = $page === 'course' ? 'courses' : $page;
    $h = '<div class="f-scrim" data-drawer-scrim></div><aside class="f-drawer" id="fDrawer" aria-hidden="true" aria-label="' . e(tr('Site menu')) . '">';
    $h .= '<div class="f-drawer-head"><span class="f-drawer-title">' . e(tr('Menu')) . '</span><button class="f-drawer-close" data-drawer-close type="button" aria-label="' . e(tr('Close menu')) . '">' . collegeIcon('close') . '</button></div><nav aria-label="' . e(tr('Mobile')) . '"><ul class="f-drawer-list">';
    $i = 0;
    foreach (collegeNavItems() as $it) {
        $act = ($it['slug'] !== '' && $it['slug'] === $key) ? ' aria-current="page"' : '';
        if ($it['kids']) {
            $i++;
            $pid = 'facc' . $i;
            $h .= '<li><button class="f-acc-btn" type="button" aria-expanded="false" aria-controls="' . $pid . '">' . collegeIcon($it['icon']) . '<span>' . e(tr($it['label'])) . '</span>' . collegeIcon('chev') . '</button><div class="f-acc-panel" id="' . $pid . '">';
            foreach ($it['kids'] as [$kl, $ku]) $h .= '<a href="' . e($ku) . '">' . e(tr($kl)) . '</a>';
            $h .= '</div></li>';
        } else {
            $h .= '<li><a class="f-drawer-link" href="' . e($it['url']) . '"' . $act . '>' . collegeIcon($it['icon']) . '<span>' . e(tr($it['label'])) . '</span></a></li>';
        }
    }
    $home = siteHomeUrl();
    $h .= '</ul></nav><div class="f-drawer-cta"><a class="f-btn ghost" href="' . e($home . '?page=login&show=inquiry') . '">' . e(tr('Enquire')) . '</a><a class="f-btn primary" href="' . e(sitePublicUrl('apply.php')) . '">' . e(tr('Apply Online')) . '</a></div></aside>';
    return $h;
}
function collegeHeader(string $brand, string $kind, string $city, string $phone, string $page, string $headerAction, array $ticker): string {
    $home = siteHomeUrl();
    $pu = $home . '?page=';
    $login = $pu . 'login';
    $tagline = 'Education | Research | Healthcare | A Better Tomorrow';
    $h = '<div class="f-topbar"><div class="f-wrap f-topbar-in"><div class="f-top-left">';
    if ($phone !== '') $h .= '<a href="tel:' . e(preg_replace('/[^+0-9]/', '', $phone)) . '">' . collegeIcon('phone') . ' <span>' . e($phone) . '</span></a>';
    if ($city !== '') $h .= '<span class="f-top-addr">' . collegeIcon('pin') . ' <span>' . e($city) . '</span></span>';
    $h .= '</div><div class="f-top-right">';
    if (str_contains($headerAction, 'u-profile-box')) $h .= $headerAction;
    else $h .= '<a href="' . e($login) . '">' . collegeIcon('user') . ' <span class="f-top-lbl">' . e(tr('Student Login')) . '</span></a><a href="' . e($login) . '">' . collegeIcon('shield') . ' <span class="f-top-lbl">' . e(tr('Staff Login')) . '</span></a>';
    $h .= siteToggle() . siteLangToggle($page) . '</div></div></div>';
    $h .= '<header class="f-head"><div class="f-wrap f-head-in">';
    $h .= '<button class="f-burger" data-drawer-open type="button" aria-label="' . e(tr('Open menu')) . '" aria-expanded="false" aria-controls="fDrawer">' . collegeIcon('menu') . '</button>';
    $h .= '<a href="' . e($home) . '" class="f-brand" aria-label="' . e($brand) . '"><span class="f-crest">' . collegeIcon('cap') . '</span><span class="f-brand-t"><strong>' . e($brand) . '</strong><small>' . e($tagline) . '</small></span></a>';
    $h .= '<div class="f-head-cta"><a class="f-btn ghost" href="' . e($pu . 'login&show=inquiry') . '">' . e(tr('Enquire')) . '</a><a class="f-btn primary" href="' . e(sitePublicUrl('apply.php')) . '">' . e(tr('Apply Online')) . '</a></div>';
    $h .= '<a class="f-m-login" href="' . e($login) . '" aria-label="' . e(tr('Login')) . '">' . collegeIcon('user') . '</a>';
    $h .= '</div>' . collegeNav($page) . '</header>';
    $h .= collegeDrawer($page) . nimitaTicker($ticker);
    $authedUrl = '';
    if (str_contains($headerAction, 'u-profile-box') && preg_match('/href="([^"]+)"/', $headerAction, $m)) $authedUrl = (string)$m[1];
    $acct = $authedUrl !== '' ? $authedUrl : $login;
    $h .= '<nav class="f-bottomnav" aria-label="' . e(tr('Quick navigation')) . '">'
        . '<a data-nav="home" href="' . e($home) . '">' . collegeIcon('home') . '<span>' . e(tr('Home')) . '</span></a>'
        . '<a data-nav="programs" href="' . e($pu . 'courses') . '">' . collegeIcon('cap') . '<span>' . e(tr('Programs')) . '</span></a>'
        . '<a data-nav="apply" href="' . e(sitePublicUrl('apply.php')) . '">' . collegeIcon('send') . '<span>' . e(tr('Apply')) . '</span></a>'
        . '<a data-nav="notices" href="' . e($pu . 'notices') . '">' . collegeIcon('bell') . '<span>' . e(tr('Notices')) . '</span></a>'
        . '<a data-nav="account" href="' . e($acct) . '">' . collegeIcon('user') . '<span>' . e(tr('Account')) . '</span></a></nav>';
=======
function collegeHeader(string $brand, string $kindLine, string $page, string $headerAction, array $ticker): string {
    $home = siteHomeUrl();
    $h = '<div class="c-topbar"><div class="c-wrap c-topbar-in"><span class="c-approvals">Approved by AICTE | PCI | Affiliated to MAKAUT, WB</span><span class="c-top-links">';
    if (str_contains($headerAction, 'u-profile-box')) $h .= $headerAction;
    else $h .= '<a href="' . e($home . '?page=login') . '">Student Login</a><a href="' . e($home . '?page=login') . '">Faculty Login</a><a href="' . e($home . '?page=login') . '">Admin Login</a>';
    $h .= '<a class="c-top-search" href="' . e($home . '?page=courses') . '" aria-label="Search programs">' . collegeIcon('search') . '</a>' . siteToggle() . siteLangToggle($page) . '</span></div></div>';
    $h .= '<header class="c-head"><div class="c-wrap c-head-in"><a class="c-brand u-brand" href="' . e($home) . '" title="Back to the college homepage"><span class="c-crest">' . siteCrest() . '</span><span><strong>' . e($brand) . '</strong><small>' . e($kindLine) . '</small></span></a>';
    $h .= '<span class="c-head-btns"><a href="' . e($home . '?page=login&show=inquiry') . '">' . collegeIcon('user') . '<span>Enquiry</span></a><a href="' . e($home . '?page=notices') . '">' . collegeIcon('down') . '<span>Download</span></a><a href="' . e($home . '?page=brochure') . '">' . collegeIcon('doc') . '<span>Download Brochure</span></a><a class="c-apply" href="' . e(sitePublicUrl('student.php?page=admissions')) . '">' . collegeIcon('userplus') . '<span>' . e(tr('Apply Now')) . '</span></a></span></div></header>';
    $h .= collegeNav($page) . siteTicker($ticker);
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
    return $h;
}
function collegeFooter(string $brand, string $kind, string $city = '', string $address = '', string $phone = ''): string {
    $home = siteHomeUrl();
<<<<<<< HEAD
    $pu = $home . '?page=';
    $student = sitePublicUrl('student.php');
    $h = '<footer class="f-footer"><div class="f-wrap"><div class="f-foot-grid">';
    $h .= '<div><a href="' . e($home) . '" class="f-brand" style="margin-bottom:12px"><span class="f-crest">' . collegeIcon('cap') . '</span><span class="f-brand-t"><strong>' . e($brand) . '</strong><small>' . e($kind) . '</small></span></a>';
    $h .= '<p>' . e(tr('Building knowledgeable, skilled and responsible pharmacy professionals through quality education, research and healthcare.')) . '</p></div>';
    $links = [
        'Quick Links' => [['Home', $home], ['About Us', $pu . 'about'], ['Admissions', $pu . 'admissions'], ['Programs', $pu . 'courses'], ['Notices', $pu . 'notices'], ['Contact', $pu . 'contact']],
        'Academics' => [['Programs', $pu . 'courses'], ['Faculty', $pu . 'faculty'], ['Laboratories', $pu . 'facilities#labs'], ['Library', $pu . 'facilities#library'], ['Academic Calendar', $pu . 'notices']],
        'Admissions' => [['Admission Process', $pu . 'admissions'], ['Eligibility', $pu . 'admissions#eligibility'], ['Fee Structure', $pu . 'courses'], ['Apply Online', sitePublicUrl('apply.php')], ['Track Application', $pu . 'login&show=applicant']],
        'Student Corner' => [['Student Login', $pu . 'login'], ['Student Dashboard', $student], ['Results', $student], ['Downloads', $pu . 'brochure'], ['Support', $pu . 'contact']],
    ];
    foreach ($links as $title => $ls) {
        $h .= '<nav aria-label="' . e(tr($title)) . '"><h3>' . e(tr($title)) . '</h3><ul class="f-foot-links">';
        foreach ($ls as [$l, $u]) $h .= '<li><a href="' . e($u) . '">' . e(tr($l)) . '</a></li>';
        $h .= '</ul></nav>';
    }
    $h .= '<div><h3>' . e(tr('Contact Us')) . '</h3><ul class="f-contact-list">';
    $loc = trim($address) !== '' ? $address . ($city !== '' ? ', ' . $city : '') : $city;
    if ($loc !== '') $h .= '<li>' . collegeIcon('pin') . '<span>' . e($loc) . '</span></li>';
    if ($phone !== '') $h .= '<li>' . collegeIcon('phone') . '<a href="tel:' . e(preg_replace('/[^+0-9]/', '', $phone)) . '">' . e($phone) . '</a></li>';
    if ($loc === '' && $phone === '') $h .= '<li>' . collegeIcon('pin') . '<span>' . e(tr('Contact details will appear here soon.')) . '</span></li>';
    $h .= '<li>' . collegeIcon('clock') . '<span>' . e(tr('Mon - Sat: 10:00 AM - 5:00 PM')) . '</span></li></ul></div>';
    $h .= '</div><div class="f-foot-bottom"><div>&copy; ' . date('Y') . ' ' . e($brand) . '. ' . e(tr('All Rights Reserved.')) . '</div><div>' . e(tr('Privacy Policy')) . ' &middot; ' . e(tr('Terms of Use')) . ' &middot; ' . e(tr('Sitemap')) . '</div></div></div></footer>';
    $h .= '<button class="f-backtop" data-backtop type="button" aria-label="' . e(tr('Back to top')) . '">' . collegeIcon('arrow') . '</button>';
=======
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
>>>>>>> parent of 00f5970 (Nimita-style pharmacy homepage: hero, features, programs, stats, campus, principal, facilities, CTA, footer)
    return $h;
}
