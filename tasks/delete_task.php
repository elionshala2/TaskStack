<?php
// delete_task.php

require_once __DIR__ . '/../config.php'; // lidhja me databazen
require_once __DIR__ . '/../includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id = $_SESSION['user_id']; // merr user_id nga sessioni

// Vetem POST lejohet
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../dashboard.php'); // nese nuk eshte POST e kthen ne dashboard.php
}

// CSRF
if (!verify_csrf($_POST['csrf'] ?? '')) {
    $_SESSION['flash_error'] = 'Kerkese e pavlefshme. Provo perseri.';
    redirect('../dashboard.php');
}

$task_id = (int)($_POST['id'] ?? 0);

if ($task_id <= 0) {
    redirect('../dashboard.php');
}

try {
    // WHERE id = ? AND user_id = ? -> vetem task i userit aktual
    $stmt = $conn->prepare(
        "DELETE FROM tasks WHERE id = ? AND user_id = ?"
    );
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['flash_success'] = 'Detyra u fshi me sukses!'; // nese tasku u fshi me sukses e ruan nje mesazh ne session
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Delete task error: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Diqka shkoi gabim. Provo perseri.';
}

redirect('../dashboard.php'); // kthen ne dashboard.php
