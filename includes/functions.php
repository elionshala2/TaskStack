<?php
// includes/functions.php

// Escape Output -- mbrojtja nga sulmet XSS
function e($str) { // teksti nga useri shikohet nga htmlspecialchars per XSS
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF tokeni -- mbrojtja nga sulmet CSRF - Cross Site Request Forgery
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf($token) { // verifikimi i tokenit te CSRF me lart ^^^
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ?? '');
}

// redirect userin ne URL te caktuar edhe e bon scriptin exit
function redirect($url) {
    header("Location: $url");
    exit;
}

// Kontrollon nese useri osht i kyqur, nese jo e perdor funksionin redirect per me kthy ne login
function require_login() {
    global $conn;
    validate_session($conn); // sesion i vjeter (pas ndryshimit te passwordit / "mbyll sesionet e tjera") hiqet ketu
    if (empty($_SESSION['user_id'])) {
        $prefix = str_contains($_SERVER['SCRIPT_NAME'], '/tasks/') ? '../' : '';
        redirect($prefix . 'login.php');
    }
}

// =================================================================
// Sesionet: cdo user ka nje "session_version" ne databaze. Cdo sesion ruan kopjen e vet ($_SESSION['sv']).
// Kur versioni rritet (ndryshim/reset password, "Mbyll sesionet e tjera"), te gjitha sesionet me version
// te vjeter pushojne se vlejne dhe useri duhet te kyqet perseri.
// =================================================================

// Merr versionin aktual te sesionit per nje user (null nese useri nuk ekziston me)
function get_session_version($conn, int $user_id): ?int {
    $stmt = $conn->prepare("SELECT session_version FROM regjistrimi WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['session_version'] : null;
}

// Rrit versionin => te gjitha sesionet ekzistuese te userit behen te pavlefshme. Kthen versionin e ri.
// Sesioni aktual duhet ta ruaje versionin e ri vete: $_SESSION['sv'] = bump_session_version(...)
function bump_session_version($conn, int $user_id): int {
    $stmt = $conn->prepare("UPDATE regjistrimi SET session_version = session_version + 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    return (int)get_session_version($conn, $user_id);
}

// Kyq userin: rigjeneron session ID (mbrojtje session fixation), rrotullon tokenin CSRF
// dhe ruan versionin aktual te sesionit.
function login_user($conn, int $user_id, string $username): void {
    $sv = get_session_version($conn, $user_id) ?? 1;
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['user_id']  = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['sv']       = $sv;
}

// Kontrollon qe sesioni i kyqur eshte ende i vlefshem: useri ekziston dhe versioni perputhet.
// Nese jo, e c'kyq sesionin (pa e prekur session ID, qe te mund te thirret edhe pasi ka filluar output-i).
function validate_session($conn): void {
    static $done = false;
    if ($done || empty($_SESSION['user_id'])) {
        return;
    }
    $done  = true;
    $valid = false;

    try {
        $current = get_session_version($conn, (int)$_SESSION['user_id']);
        $valid   = $current !== null
            && isset($_SESSION['sv'])
            && (int)$_SESSION['sv'] === $current;
    } catch (mysqli_sql_exception $e) {
        error_log("Session validate error: " . $e->getMessage());
        $valid = false; // ne dyshim, mos e lejo sesionin
    }

    if (!$valid) {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['sv'], $_SESSION['google_link']);
        $_SESSION['flash_error'] = 'Sesioni yt perfundoi. Kyqu perseri.';
    }
}

// Siguron qe useri te ka kategorine default "Inbox"
function ensure_default_category($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    // Nese nuk ka kategori, krijo Inbox default
    if ($row['count'] == 0) {
        $stmt = $conn->prepare(
            "INSERT INTO categories (user_id, name, color, is_default) VALUES (?, 'Inbox', '#3b82f6', 1)"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Kontrollon qe ngjyra eshte hex e vlefshme (#rrggbb)
function safe_color($c, $fallback = '#3b82f6') {
    return (is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c)) ? $c : $fallback;
}

// Ngjyrat qe mund te zgjedhe useri per kategorite
function category_colors() {
    return ['#3b82f6', '#7c3aed', '#06b6d4', '#22c55e', '#f59e0b', '#ef4444', '#ec4899', '#64748b'];
}

// =================================================================
// Rate limiting per login (max 5 tentativa per IP)
// Nese tabela login_attempts nuk ekziston, login-i vazhdon normalisht, nuk e krijon per arsyje sigurie
// =================================================================

define('MAX_LOGIN_ATTEMPTS', 5); // maksimum 5 tentative per IP, mund te ndryshohet
define('LOGIN_WINDOW_MINUTES', 15); // 15 minuta nuk mund te tentohet per IP

function client_ip() { // merr IP e klientit, nese nuk ekziston kthen 0.0.0.0
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function login_attempts_count($conn, $ip) { // kontrollon sa tentativa ka pasur IPja e dhene, nese ka me shume se 5 kthen 0, nese jo kthen numrin e tentativave
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM login_attempts
             WHERE ip = ? AND attempted_at > (NOW() - INTERVAL " . (int)LOGIN_WINDOW_MINUTES . " MINUTE)"
        );
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['c'] ?? 0);
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit count error: " . $e->getMessage());
        return 0;
    }
}

function record_login_attempt($conn, $ip) { // regjistron nje tentative per IP e dhene
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit insert error: " . $e->getMessage());
    }
}

function clear_login_attempts($conn, $ip) { // nese useri kyqet me sukses i fshin tentativat e IPse nga databaza
    try {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit clear error: " . $e->getMessage());
    }
}