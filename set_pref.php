<?php
// set_pref.php - ruan preferencat (tema, gjuha, pranimi i cookie) ne cookie dhe kthen userin ku ishte

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/prefs.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$next = safe_next($_POST['next'] ?? '/');

if (verify_csrf($_POST['csrf'] ?? '')) {
    $type = $_POST['type'] ?? '';

    if ($type === 'theme') {
        $value = $_POST['value'] ?? '';
        if (in_array($value, PREF_THEMES, true)) {
            set_pref_cookie('ts_theme', $value);
        }
    } elseif ($type === 'lang') {
        $value = $_POST['value'] ?? '';
        if (in_array($value, PREF_LANGS, true)) {
            set_pref_cookie('ts_lang', $value);
        }
    } elseif ($type === 'cookie_consent') {
        set_pref_cookie('ts_cookie_ok', '1');
    }
}

redirect($next);
