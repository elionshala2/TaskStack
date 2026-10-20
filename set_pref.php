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
// URL-ja ?lang=en ka përparësi mbi cookie; e heqim qe butoni AL/EN te funksionoje
$next = preg_replace('/([?&])lang=[^&#]*&?/', '$1', $next) ?? $next;
$next = rtrim($next, '?&');
if ($next === '') {
    $next = '/';
}

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
            if (!empty($_SESSION['user_id'])) { // per emailet e përkujtimeve
                try {
                    $stmt = $conn->prepare("UPDATE regjistrimi SET language = ? WHERE id = ?");
                    $uid  = (int)$_SESSION['user_id'];
                    $stmt->bind_param("si", $value, $uid);
                    $stmt->execute();
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    error_log('Save language error: ' . $e->getMessage());
                }
            }
        }
    } elseif ($type === 'cookie_consent') {
        set_pref_cookie('ts_cookie_ok', '1');
    }
}

redirect($next);
