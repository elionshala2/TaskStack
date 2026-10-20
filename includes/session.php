<?php
// includes/session.php
// Cilesimet e sigurta te sesionit + headers e sigurise (CSP etj.).
// Duhet te ngarkohet PARA session_start() dhe para cdo output.
// config.php e ngarkon automatikisht, ndaj te gjitha faqet e marrin pa ndryshim.
// logout.php (qe nuk ngarkon config.php) e ngarkon vete.

if (!defined('APP_SESSION_READY')) {
    define('APP_SESSION_READY', true);

    // A po vjen kerkesa permes HTTPS? (edhe pas proxy/load balancer)
    $app_https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    if (session_status() === PHP_SESSION_NONE) {
        // refuzon session ID qe nuk i ka krijuar serveri (mbrojtje nga session fixation)
        ini_set('session.use_strict_mode', '1');
        // session ID vetem ne cookie, kurre ne URL
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_set_cookie_params([
            'lifetime' => 0,          // zhduket kur mbyllet shfletuesi
            'path'     => '/',
            'domain'   => '',
            'secure'   => $app_https, // cookie dergohet vetem permes HTTPS (kur faqja eshte ne HTTPS)
            'httponly' => true,       // JavaScript nuk e lexon dot cookien (mbrojtje nga XSS)
            // Lax, jo Strict: me Strict cookie nuk dergohet kur Google te kthen ne faqe
            // dhe kyqja me Google do te prishej. Lax mbron prape nga CSRF ne POST nga faqe te hueja.
            'samesite' => 'Lax',
        ]);
    }

    // shton data-theme + lang ne <html> sipas cookie-ve (tema/gjuha) para se faqja te dergohet
    require_once __DIR__ . '/prefs.php';
    ob_start('prefs_html_filter');

    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');      // shfletuesi nuk "hamendeson" llojin e file-ve
        header('X-Frame-Options: DENY');                // faqja nuk hapet brenda iframe (clickjacking)
        header('Referrer-Policy: same-origin');         // URL-te me token (reset, verify) nuk dalin jashte faqes
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // SEO: vetem faqet publike indeksohen; dashboard, settings, login, API etj. marrin noindex
        $seo_public = ['index.php', 'features.php', 'signup.php', 'privacy.php', 'terms.php'];
        if (!in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $seo_public, true)) {
            header('X-Robots-Tag: noindex, nofollow');
        }
        // permbajtja ndryshon sipas cookie-t te gjuhes: caches nuk duhet ta perzjejne
        header('Vary: Cookie', false);

        // Content-Security-Policy:
        //  - script-src 'self'  -> asnje JS inline, vetem assets/script.js
        //  - style-src          -> 'unsafe-inline' sepse faqet perdorin style="--cat: ..." per ngjyrat
        //  - fonts              -> Google Fonts
        //  - form-action        -> accounts.google.com sepse butoni "Lidh Google" ben POST ne faqen tone
        //                          dhe ajo ridrejton te Google
        header(
            "Content-Security-Policy: default-src 'self'; "
                . "script-src 'self'; "
                . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                . "font-src 'self' https://fonts.gstatic.com; "
                . "img-src 'self' data:; "
                . "connect-src 'self'; "
                . "object-src 'none'; "
                . "base-uri 'self'; "
                . "frame-ancestors 'none'; "
                . "form-action 'self' https://accounts.google.com"
        );

        // HSTS vetem kur faqja eshte ne HTTPS (ne localhost/HTTP nuk ka efekt)
        if ($app_https) {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }
}
