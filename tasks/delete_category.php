<?php
// delete_category.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];

// Vetem POST lejohet
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../categories.php');
}

// CSRF
if (!verify_csrf($_POST['csrf'] ?? '')) {
    $_SESSION['flash_error'] = 'Kerkese e pavlefshme. Provo perseri.';
    redirect('../categories.php');
}

$cat_id = (int)($_POST['id'] ?? 0);

if ($cat_id <= 0) {
    redirect('../categories.php');
}

try {
    // Kategoria default (Inbox) nuk fshihet
    $stmt = $conn->prepare(
        "SELECT is_default FROM categories WHERE id = ? AND user_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $cat_id, $user_id);
    $stmt->execute();
    $cat = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cat) {
        $_SESSION['flash_error'] = 'Kategoria nuk u gjet.';
        redirect('../categories.php');
    }

    if ((int)$cat['is_default'] === 1) {
        $_SESSION['flash_error'] = 'Kategoria default nuk mund te fshihet.';
        redirect('../categories.php');
    }

    // Detyrat e saj mbeten pa kategori
    $stmt = $conn->prepare(
        "UPDATE tasks SET category_id = NULL WHERE category_id = ? AND user_id = ?"
    );
    $stmt->bind_param("ii", $cat_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Fshije kategorine (vetem nese i perket userit)
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cat_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['flash_success'] = 'Kategoria u fshi me sukses!';
    }
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log("Delete category error: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Diqka shkoi gabim. Provo perseri.';
}

redirect('../categories.php');
