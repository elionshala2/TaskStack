<?php
// logout.php

require_once __DIR__ . '/includes/session.php'; // cilesimet e njejta te cookie-t si ne faqet e tjera

session_start();

// Dalja vetem me POST + CSRF (nje link GET nga faqe e huaj nuk mund te te c'kyqe)
$csrf_ok = isset($_SESSION['csrf'], $_POST['csrf'])
    && hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$csrf_ok) {
    header("Location: index.php");
    exit;
}

// Fshi te gjitha variablat e sesionit
$_SESSION = [];

// Fshi cookien e sesionit nga browseri
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000, // vendos kohen e skadimit ne te kaluar
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php");
exit;