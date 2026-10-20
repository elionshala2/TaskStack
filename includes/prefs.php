<?php
// includes/prefs.php
// Preferencat e userit (tema + gjuha) te ruajtura ne cookie, dhe funksioni t() per perkthime.

const PREF_LANGS  = ['sq', 'en'];
const PREF_THEMES = ['dark', 'light'];

function pref_lang(): string
{
    $l = $_COOKIE['ts_lang'] ?? 'sq';
    return in_array($l, PREF_LANGS, true) ? $l : 'sq';
}

function pref_theme(): string
{
    $t = $_COOKIE['ts_theme'] ?? 'dark';
    return in_array($t, PREF_THEMES, true) ? $t : 'dark';
}

// Vendos cookie te preferencave (1 vit)
function set_pref_cookie(string $name, string $value): void
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    setcookie($name, $value, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Perkthimi: t('celesi') ose t('celesi', ['n' => 5]) per {n}
function t(string $key, array $vars = []): string
{
    static $dict = null;
    if ($dict === null) {
        $dict = require __DIR__ . '/lang.php';
    }
    $lang = pref_lang();
    $str  = $dict[$key][$lang] ?? $dict[$key]['sq'] ?? $key;
    foreach ($vars as $k => $v) {
        $str = str_replace('{' . $k . '}', (string)$v, $str);
    }
    return $str;
}

// URL e sigurt per te ktheyer userin (vetem path relativ, jo faqe te huaja)
function safe_next(?string $next): string
{
    $next = (string)$next;
    if (
        $next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, "\\")
        || preg_match('/[\r\n]/', $next)
    ) {
        return '/';
    }
    return $next;
}

// Fjalori shqip => anglisht per faqet pa t() (ndertohet nje here: celesat e papastruar + te escape-uar per HTML)
function prefs_en_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (require __DIR__ . '/lang_map.php' as $sq => $en) {
            $map[$sq] = $en;
            $esc = htmlspecialchars($sq, ENT_QUOTES, 'UTF-8');
            if ($esc !== $sq) {
                $map[$esc] = htmlspecialchars($en, ENT_QUOTES, 'UTF-8');
            }
        }
    }
    return $map;
}

// Shton data-theme dhe lang ne <html> te cdo faqe (para output-it, pa flash te temes se gabuar)
// dhe, kur gjuha eshte EN, perkthen tekstet shqip te faqes (strtr zgjedh gjithmone fjaline me te gjate)
function prefs_html_filter(string $buffer): string
{
    $lang = pref_lang();
    // faqet ligjore (privacy/terms) mbeten ne shqip: perkthimi i pjesshem i tekstit ligjor eshte me i keq se asnje
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($lang === 'en' && !in_array($script, ['privacy.php', 'terms.php'], true)) {
        $buffer = strtr($buffer, prefs_en_map());
    }
    return preg_replace(
        '/<html lang="sq">/',
        '<html lang="' . $lang . '" data-theme="' . pref_theme() . '">',
        $buffer,
        1
    ) ?? $buffer;
}
