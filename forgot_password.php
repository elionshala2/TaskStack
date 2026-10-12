<?php
// forgot_password.php - useri shkruan emailin dhe merr link per te vendosur password te ri

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

session_start();

if (!empty($_SESSION['user_id'])) {
    redirect('settings.php'); // i kyqur: password ndryshohet nga Settings
}

$errors = [];
$success = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = client_ip();

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';

    } elseif (mail_requests_count($conn, $ip) >= MAX_MAIL_REQUESTS) {
        $errors[] = 'Shume kerkesa. Provo perseri pas ' . MAIL_WINDOW_MINUTES . ' minutash.';

    } else {
        $email = trim($_POST['email'] ?? '');
        $old_email = $email;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email i pavlefshem.';
        } else {
            record_mail_request($conn, $ip);

            try {
                $stmt = $conn->prepare("SELECT id, username FROM regjistrimi WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && !recent_token_exists($conn, (int)$user['id'], 'reset')) {
                    send_reset_email($conn, (int)$user['id'], $email, $user['username']);
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Forgot password error: " . $e->getMessage());
            }

            // mesazh i njejte gjithmone (nuk zbulon nese emaili ekziston)
            $success = 'Nëse ky email ekziston, do të marrësh një link për ta vendosur passwordin e ri (kontrollo edhe spam). Linku vlen 1 ore.';
            $old_email = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harrova passwordin - TaskStack</title>
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
        <h1>Harrova passwordin</h1>
        <p class="auth-sub">Shkruaj emailin e llogarisë dhe të dergojme një link për të vendosur password te ri.</p>

        <?php if ($success): ?>
            <div class="alert success"><p><?= e($success) ?></p></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label>Email</label>
            <input type="email" name="email" value="<?= e($old_email) ?>"
                   placeholder="name@example.com" autocomplete="email" required>

            <button type="submit">Dërgo linkun</button>
        </form>

        <p><a href="login.php">&larr; Kthehu te Login</a></p>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>