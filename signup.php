<?php
// signup.php

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF

// Nese je i kyqur shko ne dashboard direkt
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php'); // nese useri eshte i kyqur, e kthen ne dashboard
}

$errors = []; // per grumbullim te erroreve
$old = ['username' => '', 'email' => '']; // qe vlerat e formes mos te fshihen nese ka errore

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // mbrojtja kunder sulmeve CSRF - Cross-Site Request Forgery
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $cpassword = $_POST['cpassword'] ?? '';

        $old['username'] = $username;
        $old['email']    = $email;

        // Validime
        if (strlen($username) < 3 || strlen($username) > 20) {
            $errors[] = 'Username duhet 3-20 karaktere.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username mund te permbaje vetem shkronja, numra dhe _.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email i pavlefshem.';
        }

        if (strlen($password) < 8
            || !preg_match('/[A-Za-z]/', $password)
            || !preg_match('/\d/', $password)) {
            $errors[] = 'Password: min 8 karaktere, te pakten 1 shkronje dhe 1 numer.';
        }

        if ($password !== $cpassword) {
            $errors[] = 'Password-et nuk perputhen.';
        }

        // Kontroll dublikat + insert
        if (empty($errors)) {
            try {
                // 1. Kontrollo a ekziston vetem username-i
                $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();
                $username_exists = $stmt->num_rows > 0;
                $stmt->close();

                // 2. Kontrollo a ekziston vetem email-i
                $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                $email_exists = $stmt->num_rows > 0;
                $stmt->close();

                // 3. Shfaq gabimin specifik sipas rastit
                if ($username_exists && $email_exists) {
                    $errors[] = 'Email dhe username (te dyja) ekzistojne! Ju lutem zgjidhni nje tjeter kombinim ose provoni te kyqeni.';
                } elseif ($username_exists) {
                    $errors[] = 'Ky username ekziston!  Ju lutem zgjidhni nje tjeter ose provoni te kyqeni.';
                } elseif ($email_exists) {
                    $errors[] = 'Ky email ekziston! Zgjedh nje tjeter ose provoni te kyqeni.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare(
                        "INSERT INTO regjistrimi (username, email, password) VALUES (?, ?, ?)"
                    );
                    $stmt->bind_param("sss", $username, $email, $hash);
                    $stmt->execute();
                    $stmt->close();

                    // ID e userit te ri
                    $new_user_id = $conn->insert_id;

                    // Krijo kategorine default Inbox
                    ensure_default_category($conn, $new_user_id);

                    $_SESSION['flash_success'] = 'Llogaria u krijua me sukses! Tani mund te hysh.';
                    redirect('login.php');
                }

            } catch (mysqli_sql_exception $e) {
                error_log("Signup error: " . $e->getMessage());

                // Nese eshte dublikat trajtoje
                if ((int)$e->getCode() === 1062) {
                    $errors[] = 'Ky email ose username ekziston tashme.';
                } else {
                    $errors[] = 'Diqka shkoi gabim. Provo perseri.';
                }
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
    <title>Sign Up - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="auth-layout">

    <?php require_once __DIR__ . '/includes/auth-side.php'; // paneli i majte me preview ?>

    <div class="auth-box">
        <h1>Signup</h1>
        <p class="auth-sub">Krijo llogarine dhe fillo te organizohesh sot.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $err): ?>
                    <p><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="signupForm" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label>Username</label>
            <input type="text" name="username" maxlength="20"
                   value="<?= e($old['username']) ?>"
                   placeholder="username"
                   autocomplete="username" required>

            <label>Email</label>
            <input type="email" name="email"
                   value="<?= e($old['email']) ?>"
                   placeholder="name@example.com"
                   autocomplete="email" required>

            <label>Password</label>
            <input type="password" name="password" id="password"
                   placeholder="••••••••"
                   autocomplete="new-password" required>
            <div class="strength"><span id="strengthBar"></span></div>
            <small class="hint" id="pwHint">Min 8 karaktere, shkronje dhe numer.</small>

            <label>Confirm Password</label>
            <input type="password" name="cpassword" id="cpassword"
                   placeholder="••••••••"
                   autocomplete="new-password" required>
            <small class="hint" id="matchHint"></small>

            <button type="submit">Regjistrohu</button>
        </form>

        <div class="auth-divider"><span>ose</span></div>

        <!-- Signup me Google -->
        <a href="google-callback.php" class="btn-google">
            <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
            Regjistrohu me Google
        </a>

        <p>Ke llogari? <a href="login.php">Login</a></p>
        <p><a href="index.php">&larr; Kthehu ne faqen kryesore</a></p>
    </div>

</div><!-- /auth-layout -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>