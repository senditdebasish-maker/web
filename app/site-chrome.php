<?php
declare(strict_types=1);
// Shared university chrome: one header/nav/footer theme for the root website and every portal page.
// Every brand link points at the university front page, and every portal carries a
// "Back to website" link, so visitors can never get stuck inside admissions or a dashboard.
if (!function_exists('e')) {
    function e(mixed $s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}
// Bilingual site chrome. Pages using Hindi set $siteLang='hi' and an English→Hindi $HI map
// before rendering; every other caller (office, student, admissions) stays English by default.
if (!function_exists('tr')) {
    function tr(string $en): string {
        global $siteLang, $HI;
        if (($siteLang ?? 'en') !== 'hi') return $en;
        return (is_array($HI ?? null) && isset($HI[$en])) ? $HI[$en] : $en;
    }
}
// Web path of the project folder, e.g. '' on a domain root or '/institute-crm' under XAMPP htdocs.
// The project folder is the web root: index.php, office.php, student.php, apply.php and assets/ sit side by side.
function siteWebRoot(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    if ($dir === '/' || $dir === '.' || $dir === '\\' || $dir === '') return '';
    return rtrim($dir, '/');
}
// URL of the university front page (root index.php) from any page.
function siteHomeUrl(): string { return siteWebRoot() . '/index.php'; }
function sitePublicUrl(string $path): string { return siteWebRoot() . '/' . ltrim($path, '/'); }
function siteToggle(): string {
    return '<button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle dark mode" title="Toggle dark mode"><span aria-hidden="true">🌙</span></button>';
}
// EN | हिन्दी switch for the university site. Sessionless: ?lang= persists via a cookie.
function siteLangToggle(string $page): string {
    global $siteLang;
    $cur = $siteLang ?? 'en';
    $base = siteHomeUrl() . '?page=' . urlencode($page) . '&lang=';
    $en = '<a href="' . e($base . 'en') . '"' . ($cur !== 'hi' ? ' aria-current="true"' : '') . '>EN</a>';
    $hi = '<a href="' . e($base . 'hi') . '"' . ($cur === 'hi' ? ' aria-current="true"' : '') . '>हिन्दी</a>';
    return '<span class="u-lang">' . $en . ' | ' . $hi . '</span>';
}
function siteNav(string $active = ''): string {
    $home = siteHomeUrl();
    $links = ['' => 'Home', 'about' => 'About', 'courses' => 'Programs', 'admissions' => 'Admissions', 'notices' => 'Notices', 'faculty' => 'Faculty', 'contact' => 'Contact'];
    $h = '<nav class="u-nav" aria-label="University">';
    foreach ($links as $slug => $label) {
        $key = $slug === '' ? 'home' : $slug;
        $h .= '<a href="' . e($slug === '' ? $home : $home . '?page=' . $slug) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . e(tr($label)) . '</a>';
    }
    return $h . '</nav>';
}
// University crest (inline SVG shield: lamp of learning + open book). Decorative by design.
function siteCrest(): string {
    return '<svg viewBox="0 0 40 48" role="img" aria-label="University crest"><path d="M20 1 37 8v14c0 10-7.5 18-17 25C10.5 40 3 32 3 22V8Z" fill="#1A365D" stroke="#c9a227" stroke-width="2"/><path d="M20 5.5 33.5 10.5V22c0 8-6 14.5-13.5 20-7.5-5.5-13.5-12-13.5-20V10.5Z" fill="none" stroke="#e9cf7a" stroke-width="1"/><path d="M14 30c2-1.6 4-2.4 6-2.4s4 .8 6 2.4c-2 1.6-4 2.4-6 2.4s-4-.8-6-2.4Z" fill="#e9cf7a"/><path d="M20 27.6v-9m-3.4 1.6 3.4-4 3.4 4" stroke="#e9cf7a" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="20" cy="13" r="2.2" fill="#e9cf7a"/><path d="M12 35.5c2.5-1.4 5.2-2 8-2s5.5.6 8 2" stroke="#e9cf7a" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>';
}
function siteHeader(string $brand, string $kind, string $active, string $buttons, array $ticker = []): string {
    return '<header class="u-header"><a class="u-brand" href="' . e(siteHomeUrl()) . '" title="Back to the university website"><span class="u-brand-mark">' . siteCrest() . '</span><span>' . e($brand) . '<em>' . e($kind) . '</em></span></a>' . siteNav($active) . '<div class="u-header-btns">' . siteToggle() . $buttons . '</div></header>' . siteTicker($ticker);
}
function siteProfileBox(array $profile): string {
    return '<a class="u-profile-box" href="' . e($profile['url'] ?? siteHomeUrl()) . '"><span class="u-profile-avatar">' . e(strtoupper(mb_substr((string)($profile['name'] ?? ''), 0, 1))) . '</span><span><strong>' . e($profile['name'] ?? '') . '</strong><small>' . e($profile['role'] ?? '') . '</small></span></a>';
}
// Scrolling announcement strip under the header. Items repeat twice for a seamless loop.
function siteTicker(array $items): string {
    $items = array_values(array_filter(array_map('trim', array_map('strval', $items)), fn($s) => $s !== ''));
    if (!$items) return '';
    $loop = array_merge($items, $items);
    $h = '<div class="u-ticker" role="marquee" aria-label="Announcements"><div class="u-ticker-track">';
    foreach ($loop as $item) $h .= '<span>' . e($item) . '</span>';
    return $h . '</div></div>';
}
function siteBackLink(string $class = ''): string {
    return '<a' . ($class !== '' ? ' class="' . e($class) . '"' : '') . ' href="' . e(siteHomeUrl()) . '">← Back to website</a>';
}
// Primary CRM institute as [name, kind label, city, address, phone]; safe before install/upgrade.
function siteBrand(): array {
    $fallback = ['Northstar', 'Institutions', '', '', ''];
    try {
        if (!function_exists('rows')) return $fallback;
        $all = rows('SELECT * FROM institutes ORDER BY id LIMIT 1');
        $r = $all[0] ?? null;
        if (!$r) return $fallback;
        $kind = trim((string)($r['kind'] ?? ''));
        return [(string)($r['name'] ?? 'Northstar'), $kind !== '' ? $kind . ' Programs' : 'Institutions', (string)($r['city'] ?? ''), (string)($r['address'] ?? ''), (string)($r['phone'] ?? '')];
    } catch (Throwable $ignored) {
        return $fallback;
    }
}
// Small Indian flag placeholder (inline SVG tricolor).
function siteFlag(): string {
    return '<svg class="u-flag" viewBox="0 0 30 20" role="img" aria-label="Indian flag"><rect width="30" height="20" fill="#fff" stroke="#c9a227"/><rect width="30" height="6.7" fill="#FF9933"/><rect y="13.3" width="30" height="6.7" fill="#138808"/><circle cx="15" cy="10" r="2.4" fill="none" stroke="#06038D" stroke-width="1"/></svg>';
}
// Social icons (decorative placeholders until the institute shares its real profile URLs).
function siteSocial(): string {
    $icons = [['Facebook', 'f'], ['Instagram', 'ig'], ['Twitter', 't'], ['YouTube', '▶']];
    $h = '<div class="u-social">';
    foreach ($icons as [$label, $glyph]) $h .= '<span title="' . e($label) . '" aria-label="' . e($label) . '">' . e($glyph) . '</span>';
    return $h . '</div>';
}
function siteFooter(string $brand, string $kind, string $city = '', string $address = '', string $phone = ''): string {
    $home = siteHomeUrl();
    $h = '<footer class="u-footer"><div class="u-footer-grid"><div><strong>' . e($brand) . '</strong><p>' . e($kind) . ($city !== '' ? ' · ' . e($city) : '') . '</p>';
    $h .= '<h4>' . e(tr('Follow Us')) . '</h4>' . siteSocial() . '</div>';
    $h .= '<div><h4>' . e(tr('Quick Links')) . '</h4><nav aria-label="University"><a href="' . e($home . '?page=about') . '">' . e(tr('About us')) . '</a><a href="' . e($home . '?page=courses') . '">' . e(tr('Programs')) . '</a><a href="' . e($home . '?page=admissions') . '">' . e(tr('Admissions')) . '</a><a href="' . e($home . '?page=notices') . '">' . e(tr('Notices')) . '</a><a href="' . e($home . '?page=faculty') . '">' . e(tr('Faculty')) . '</a></nav></div>';
    $h .= '<div><h4>' . e(tr('Portals')) . '</h4><nav aria-label="Portals"><a href="' . e(sitePublicUrl('student.php?page=admissions')) . '">' . e(tr('Apply online')) . '</a><a href="' . e($home . '?page=login') . '">' . e(tr('Sign in')) . '</a></nav></div>';
    $h .= '<div><h4>' . e(tr('Address')) . '</h4><p>' . ($address !== '' ? e($address) . '<br>' : '') . ($city !== '' ? e($city) : '') . '</p>' . ($phone !== '' ? '<p>☎ ' . e($phone) . '</p>' : '') . '<p><a href="' . e($home . '?page=contact') . '">' . e(tr('All campuses')) . ' →</a></p></div></div>';
    $h .= '<div class="u-footer-bottom"><span>© ' . date('Y') . ' ' . e($brand) . '. ' . e(tr('All rights reserved.')) . '</span><span>' . e(tr('Admissions open · Apply online')) . '</span></div></footer>';
    return $h;
}
// ---- Homepage showcase content (STARTER TEMPLATE — replace with your real approvals,
// recruiters and welcome message; everything else on the site is live CRM data). ----
function siteAccreditations(): array {
    return [['🏵', 'NAAC A++', 'ACCREDITED'], ['🎖', 'NIRF RANKED #5', 'RANKED #5'], ['🏛', 'UGC', 'APPROVED']];
}
function siteShowcasePrograms(): array {
    return ['ENGINEERING & TECHNOLOGY', 'MEDICINE & HEALTH SCIENCES', 'MANAGEMENT STUDIES', 'ARTS & HUMANITIES'];
}
function siteRecruiters(): array {
    return [['🌐', 'International Companies'], ['🚚', 'DHL'], ['🏅', 'NIRF']];
}
function siteVcMessage(string $brand): string {
    return 'Warm welcome to ' . $brand . '. Our classrooms, laboratories and clinics exist for one purpose — your growth. With caring faculty, verified admissions and a modern student portal, we walk beside you from your first application to your graduation day and beyond.';
}
