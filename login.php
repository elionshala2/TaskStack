<?php
// login.php

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // startohet sessioni per te ruajtur user_id dhe tokenin CSRF

// Nese tashme i kyqur shkon ne dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php'); // redirect ne dashboard nese useri eshte i kyqur
}

$errors  = []; // array per te ruajtur gabimet
$success = ''; // mesazh suksesi nga signup.php
$old_email = ''; // variabla per te mbajtur emailin e futur ne rast gabimi

// Flash nga signup (mesazh suksesi)
if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Flash nga google-callback.php (mesazh gabimi)
if (!empty($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ip = client_ip(); // merr IP e klientit per rate limiting

    // CSRF kundrej sulmeve
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';

    // Rate limiting: max 5 tentativa per IP
    } elseif (login_attempts_count($conn, $ip) >= MAX_LOGIN_ATTEMPTS) {
        $errors[] = 'Shume tentativa te pasuksesshme. Provo perseri pas ' . LOGIN_WINDOW_MINUTES . ' minutash.'; // nuk tregon sa minuta, vetem mesazh i pergjithshem (ne kete rast 15 minuta)

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

    <div class="auth-divider"><span>ose</span></div>

    <!-- Google Login Button at the bottom -->
    <a href="google-callback.php" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Vazhdo me Google
    </a>

    <p>Nuk ke llogari? <a href="signup.php">Regjistrohu</a></p>
    <p><a href="index.php">&larr; Kthehu ne faqen kryesore</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>