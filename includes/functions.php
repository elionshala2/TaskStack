<?php
// includes/functions.php

// Escape Output -- mbrojtja nga sulmet XSS
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF tokeni -- mbrojtja nga sulmet CSRF
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf($token) {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ?? '');
}

// Redirecti
function redirect($url) {
    header("Location: $url");
    exit;
}

// Kontrollon nese useri eshte i kyqur ne llogari
function require_login() {
    if (empty($_SESSION['user_id'])) {
        $prefix = str_contains($_SERVER['SCRIPT_NAME'], '/tasks/') ? '../' : '';
        redirect($prefix . 'login.php');
    }
}

// Siguron qe useri te kaje kategorine default "Inbox"
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

// =========================================================
// Rate limiting per login (max 5 tentativa per IP)
// Nese tabela login_attempts nuk ekziston, login-i vazhdon normalisht
// =========================================================
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_WINDOW_MINUTES', 15);

function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function login_attempts_count($conn, $ip) {
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

function record_login_attempt($conn, $ip) {
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit insert error: " . $e->getMessage());
    }
}

function clear_login_attempts($conn, $ip) {
    try {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit clear error: " . $e->getMessage());
    }
}
