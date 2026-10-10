<?php
// login.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

// Nese tashme i kyqur shkon ne dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors  = [];
$success = '';
$old_email = '';

// Flash nga signup (mesazh suksesi)
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ip = client_ip();

    // CSRF kundrej sulmeve
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';

    // Rate limiting: max 5 tentativa per IP
    } elseif (login_attempts_count($conn, $ip) >= MAX_LOGIN_ATTEMPTS) {
        $errors[] = 'Shume tentativa te pasuksesshme. Provo perseri pas ' . LOGIN_WINDOW_MINUTES . ' minutash.';

    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $old_email = $email;

        // Kontrollon nese eshte thate
        if ($email === '' || $password === '') {
            $errors[] = 'Ploteso te gjitha fushat.';
        } else {
            try {
                // Kerko user-in ne databaze
                $stmt = $conn->prepare(
                    "SELECT id, username, password FROM regjistrimi WHERE email = ? LIMIT 1"
                );
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user   = $result->fetch_assoc();
                $stmt->close();

                // Verifiko passwordin
                if ($user && password_verify($password, $user['password'])) {

                    // Rigjenero session ID (mbrojtje session fixation)
                    session_regenerate_id(true);

                    // Pastro tentativat e vjetra per kete IP
                    clear_login_attempts($conn, $ip);

                    // Ruaj ne sesion
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    // Shko ne dashboard
                    redirect('dashboard.php');

                } else {
                    // Numro tentativen e gabuar
                    record_login_attempt($conn, $ip);

                    // Mesazh i pergjithshem (nuk tregon a ekziston emaili)
                    $errors[] = 'Kredencialet e pavlefshme.';
                }

            } catch (mysqli_sql_exception $e) {
                error_log("Login error: " . $e->getMessage());
                $errors[] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="auth-box">
    <h1>Login</h1>
    <p class="auth-sub">Mire se u ktheve. Hyr per te pare detyrat e tua.</p>

    <?php if ($success): ?>
        <div class="alert success"><p><?= e($success) ?></p></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <p><?= e($err) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <label>Email</label>
        <input type="email" name="email"
               value="<?= e($old_email) ?>"
               autocomplete="email" required>

        <label>Password</label>
        <input type="password" name="password"
               autocomplete="current-password" required>

        <button type="submit">Login</button>
    </form>

    <p>Nuk ke llogari? <a href="signup.php">Regjistrohu</a></p>
    <p><a href="index.php">&larr; Kthehu ne faqen kryesore</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
