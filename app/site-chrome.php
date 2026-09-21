<?php
declare(strict_types=1);
// Shared university chrome: one header/nav/footer theme for the root website and every portal page.
// Every brand link points at the university front page, and every portal carries a
// "Back to website" link, so visitors can never get stuck inside admissions or a dashboard.
if (!function_exists('e')) {
    function e(mixed $s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
}
// Web path of the project folder, e.g. '' on a domain root or '/institute-crm' under XAMPP htdocs.
function siteWebRoot(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    if (strtolower(basename($dir)) === 'public') $dir = dirname($dir);
    if ($dir === '/' || $dir === '.' || $dir === '\\' || $dir === '') return '';
    return rtrim($dir, '/');
}
// URL of the university front page (root index.php) from any page, including public/*.php.
// Installations that serve only public/ fall back to the portal home on the same host.
function siteHomeUrl(): string { return siteWebRoot() . '/index.php'; }
function sitePublicUrl(string $path): string { return siteWebRoot() . '/public/' . ltrim($path, '/'); }
function siteToggle(): string {
    return '<button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle dark mode" title="Toggle dark mode"><span aria-hidden="true">🌙</span></button>';
}
function siteNav(string $active = ''): string {
    $home = siteHomeUrl();
    $links = ['' => 'Home', 'about' => 'About', 'courses' => 'Programs', 'admissions' => 'Admissions', 'notices' => 'Notices', 'faculty' => 'Faculty', 'contact' => 'Contact'];
    $h = '<nav class="u-nav" aria-label="University">';
    foreach ($links as $slug => $label) {
        $key = $slug === '' ? 'home' : $slug;
        $h .= '<a href="' . e($slug === '' ? $home : $home . '?page=' . $slug) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . $label . '</a>';
    }
    return $h . '</nav>';
}
function siteHeader(string $brand, string $kind, string $active, string $buttons): string {
    return '<header class="u-header"><a class="u-brand" href="' . e(siteHomeUrl()) . '" title="Back to the university website"><span class="u-brand-mark">+</span><span>' . e($brand) . '<em>' . e($kind) . '</em></span></a>' . siteNav($active) . '<div class="u-header-btns">' . siteToggle() . $buttons . '</div></header>';
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
function siteFooter(string $brand, string $kind, string $city = '', string $address = '', string $phone = ''): string {
    $home = siteHomeUrl();
    $h = '<footer class="u-footer"><div><strong>' . e($brand) . '</strong><p>' . e($kind) . ($city !== '' ? ' · ' . e($city) : '') . '</p>';
    if (trim($address) !== '') $h .= '<p>' . e($address) . '</p>';
    if ($phone !== '') $h .= '<p>☎ ' . e($phone) . '</p>';
    $h .= '</div><nav aria-label="University"><a href="' . e($home . '?page=about') . '">About</a><a href="' . e($home . '?page=courses') . '">Programs</a><a href="' . e($home . '?page=admissions') . '">Admissions</a><a href="' . e($home . '?page=notices') . '">Notices</a><a href="' . e($home . '?page=contact') . '">Contact</a></nav>';
    $h .= '<nav aria-label="Portals"><a href="' . e(sitePublicUrl('apply.php')) . '">Apply</a><a href="' . e(sitePublicUrl('student.php?page=register')) . '">Create account</a><a href="' . e($home . '?page=login') . '">Sign in</a><a href="' . e(sitePublicUrl('index.php')) . '">Office</a></nav></footer>';
    return $h;
}
