<?php
// includes/seo.php
// SEO i perbashket per faqet publike: title, description, canonical, hreflang (sq/en),
// Open Graph, Twitter Card, ikonat, manifest dhe te dhena te strukturuara JSON-LD (schema.org).
//
// Perdorimi ne <head> te nje faqeje publike (pas charset dhe viewport):
//   require_once __DIR__ . '/includes/seo.php';
//   seo_head(['title' => '...', 'desc' => '...', 'path' => 'features.php', 'schema' => [ ... ]]);
//
// Domaini merret nga SITE_URL (opsionale) ose APP_URL ne .env. Shiko SEO_GUIDE.md.

if (!function_exists('seo_site_url')) {

    // URL baze e faqes pa "/" ne fund, p.sh. https://taskstack.wuaze.com
    function seo_site_url(): string
    {
        $u = trim((string)($_ENV['SITE_URL'] ?? ''));
        if ($u === '') {
            $u = trim((string)($_ENV['APP_URL'] ?? ''));
        }
        if ($u === '') {
            $u = 'https://taskstack.wuaze.com';
        }
        return rtrim($u, '/');
    }

    // JSON per <script type="application/ld+json"> (siguri: nuk lejon mbylljen e </script>)
    function seo_json(array $data): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
        $out = json_encode($data, $flags);
        return $out === false ? '{}' : $out;
    }

    // Entitetet qe persëriten ne cdo faqe: Organization + WebSite
    function seo_base_schema(string $site): array
    {
        $logo = [
            '@type'  => 'ImageObject',
            '@id'    => $site . '/#logo',
            'url'    => $site . '/assets/icon-512.png',
            'width'  => 512,
            'height' => 512,
        ];
        return [
            [
                '@type'        => 'Organization',
                '@id'          => $site . '/#organization',
                'name'         => 'TaskStack',
                'url'          => $site . '/',
                'logo'         => $logo,
                'image'        => $site . '/assets/og-image.png',
                'founder'      => ['@type' => 'Person', 'name' => 'Elion Shala'],
                'email'        => 'taskstack2026@gmail.com',
                'areaServed'   => ['Kosovo', 'Albania', 'Worldwide'],
                'knowsLanguage' => ['sq', 'en'],
            ],
            [
                '@type'           => 'WebSite',
                '@id'             => $site . '/#website',
                'url'             => $site . '/',
                'name'            => 'TaskStack',
                'alternateName'   => ['TaskStack Detyra', 'TaskStack Tasks & Reminders'],
                'inLanguage'      => ['sq', 'en'],
                'publisher'       => ['@id' => $site . '/#organization'],
            ],
        ];
    }

    // Shkruan tag-et SEO ne <head>.
    // $o: title, desc, path (p.sh. '' ose 'features.php'), index (bool, default true),
    //     schema (array me entitete JSON-LD shtese), og_type (default 'website')
    function seo_head(array $o): void
    {
        $lang  = function_exists('pref_lang') ? pref_lang() : 'sq';
        $site  = seo_site_url();
        $path  = (string)($o['path'] ?? '');
        $index = $o['index'] ?? true;
        $title = (string)($o['title'] ?? 'TaskStack');
        $desc  = (string)($o['desc'] ?? '');

        $url_sq = $site . '/' . $path;
        $url_en = $site . '/' . $path . '?lang=en';
        $canon  = $lang === 'en' ? $url_en : $url_sq;
        $image  = $site . '/assets/og-image.png';
        $alt    = $lang === 'en'
            ? 'TaskStack - free task manager and to-do list with reminders'
            : 'TaskStack - aplikacion falas per detyra dhe përkujtime';

        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        echo '<title>' . $h($title) . "</title>\n";
        if ($desc !== '') {
            echo '    <meta name="description" content="' . $h($desc) . "\">\n";
        }

        if ($index) {
            echo '    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
            echo '    <link rel="canonical" href="' . $h($canon) . "\">\n";
            echo '    <link rel="alternate" hreflang="sq" href="' . $h($url_sq) . "\">\n";
            echo '    <link rel="alternate" hreflang="en" href="' . $h($url_en) . "\">\n";
            echo '    <link rel="alternate" hreflang="x-default" href="' . $h($url_sq) . "\">\n";
        } else {
            echo '    <meta name="robots" content="noindex, nofollow">' . "\n";
        }

        echo '    <meta name="author" content="Elion Shala">' . "\n";
        echo '    <meta name="theme-color" content="#0a0a0f">' . "\n";
        echo '    <meta name="application-name" content="TaskStack">' . "\n";
        echo '    <meta name="apple-mobile-web-app-title" content="TaskStack">' . "\n";

        // ikonat + manifest
        echo '    <link rel="icon" href="' . $h($site) . '/favicon.ico" sizes="any">' . "\n";
        echo '    <link rel="icon" type="image/png" sizes="48x48" href="' . $h($site) . '/assets/favicon-48.png">' . "\n";
        echo '    <link rel="icon" type="image/png" sizes="192x192" href="' . $h($site) . '/assets/icon-192.png">' . "\n";
        echo '    <link rel="apple-touch-icon" href="' . $h($site) . '/assets/apple-touch-icon.png">' . "\n";
        echo '    <link rel="manifest" href="' . $h($site) . '/site.webmanifest">' . "\n";

        // Open Graph (Facebook, LinkedIn, WhatsApp, Telegram, Discord ...)
        echo '    <meta property="og:type" content="' . $h((string)($o['og_type'] ?? 'website')) . "\">\n";
        echo '    <meta property="og:site_name" content="TaskStack">' . "\n";
        echo '    <meta property="og:title" content="' . $h($title) . "\">\n";
        if ($desc !== '') {
            echo '    <meta property="og:description" content="' . $h($desc) . "\">\n";
        }
        echo '    <meta property="og:url" content="' . $h($canon) . "\">\n";
        echo '    <meta property="og:image" content="' . $h($image) . "\">\n";
        echo '    <meta property="og:image:type" content="image/png">' . "\n";
        echo '    <meta property="og:image:width" content="1200">' . "\n";
        echo '    <meta property="og:image:height" content="630">' . "\n";
        echo '    <meta property="og:image:alt" content="' . $h($alt) . "\">\n";
        echo '    <meta property="og:locale" content="' . ($lang === 'en' ? 'en_US' : 'sq_AL') . "\">\n";
        echo '    <meta property="og:locale:alternate" content="' . ($lang === 'en' ? 'sq_AL' : 'en_US') . "\">\n";

        // Twitter / X
        echo '    <meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '    <meta name="twitter:title" content="' . $h($title) . "\">\n";
        if ($desc !== '') {
            echo '    <meta name="twitter:description" content="' . $h($desc) . "\">\n";
        }
        echo '    <meta name="twitter:image" content="' . $h($image) . "\">\n";
        echo '    <meta name="twitter:image:alt" content="' . $h($alt) . "\">\n";

        // JSON-LD: Organization + WebSite + entitetet e faqes
        if ($index) {
            $graph = array_merge(seo_base_schema($site), $o['schema'] ?? []);
            $doc   = ['@context' => 'https://schema.org', '@graph' => $graph];
            echo '    <script type="application/ld+json">' . seo_json($doc) . "</script>\n";
        }
    }
}
