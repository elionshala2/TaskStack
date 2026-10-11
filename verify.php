<?php
// verify.php - useri kliket linkun nga emaili per te verifikuar emailin

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

session_start();

$ok = false;
$token = (string)($_GET['token'] ?? '');

try {
    $row = find_email_token($conn, $token, 'verify');

    if ($row) {
        $uid = (int)$row['user_id'];

        $stmt = $conn->prepare("UPDATE regjistrimi SET email_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        mark_token_used($conn, (int)$row['id']);
        $ok = true;
    }
} catch (mysqli_sql_exception $e) {
    error_log("Verify error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikimi i emailit - TaskStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="auth-layout">

    <?php require_once __DIR__ . '/includes/auth-side.php'; ?>

    <div class="auth-box">
        <h1>Verifikimi i emailit</h1>

        <?php if ($ok): ?>
            <div class="alert success"><p>Emaili u verifikua me sukses! Tani mund te kyqesh.</p></div>
            <p><a href="login.php">Shko te Login &rarr;</a></p>
        <?php else: ?>
            <div class="alert error"><p>Ky link eshte i pavlefshem ose ka skaduar.</p></div>
            <p><a href="resend_verification.php">Dergo nje link te ri verifikimi</a></p>
            <p><a href="index.php">&larr; Kthehu ne faqen kryesore</a></p>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>