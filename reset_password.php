<?php
// reset_password.php - faqja ku hap linku nga emaili dhe vendoset password i ri

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

session_start();

$errors = [];
$token  = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$valid  = false;
$row    = null;

try {
    $row   = find_email_token($conn, $token, 'reset');
    $valid = $row !== null;
} catch (mysqli_sql_exception $e) {
    error_log("Reset lookup error: " . $e->getMessage());
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $password  = (string)($_POST['password'] ?? '');
        $cpassword = (string)($_POST['cpassword'] ?? '');

        // Te njejtat rregulla si ne signup.php
        if (strlen($password) < 8
            || !preg_match('/[A-Za-z]/', $password)
            || !preg_match('/\d/', $password)) {
            $errors[] = 'Password: min 8 karaktere, te pakten 1 shkronje dhe 1 numer.';
        }
        if ($password !== $cpassword) {
            $errors[] = 'Password-et nuk perputhen.';
        }

        if (empty($errors)) {
            try {
                $uid  = (int)$row['user_id'];
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // ruaj te dhenat e vjetra (per linkun "anulo ndryshimin" ne email)
                $stmt = $conn->prepare("SELECT username, email, password, provider FROM regjistrimi WHERE id = ? LIMIT 1");
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $prev = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // provider = 'local' (si ne settings.php); email_verified = 1 sepse useri e provoi qe e zoteron emailin;
                // session_version + 1 => te gjitha sesionet ekzistuese mbyllen (dikush mund ta kete pasur llogarine te hapur)
                $stmt = $conn->prepare(
                    "UPDATE regjistrimi SET password = ?, provider = 'local', email_verified = 1,
                            session_version = session_version + 1
                     WHERE id = ?"
                );
                $stmt->bind_param("si", $hash, $uid);
                $stmt->execute();
                $stmt->close();

                // linku nuk perdoret me, dhe te gjithe linqet e tjere reset per kete user behen te pavlefshem
                mark_token_used($conn, (int)$row['id']);
                $stmt = $conn->prepare("UPDATE email_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
                $stmt->bind_param("i", $uid);
                $stmt->execute();
                $stmt->close();

                // njofto emailin e llogarise, me link per ta anuluar ndryshimin (12 ore)
                if ($prev) {
                    send_password_changed_notice($conn, $uid, $prev['email'], $prev['username'], $prev['password'], $prev['provider'] ?: 'local');
                }

                $_SESSION['flash_success'] = 'Passwordi u ndryshua me sukses! Tani mund te kyqesh.';
                redirect('login.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Reset password error: " . $e->getMessage());
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
    <title>Password i ri - TaskStack</title>
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
        <h1>Password i ri</h1>

        <?php if (!$valid): ?>
            <div class="alert error"><p>Ky link eshte i pavlefshem, ka skaduar ose eshte perdorur tashme.</p></div>
            <p><a href="forgot_password.php">Kerko nje link te ri</a></p>
            <p><a href="login.php">&larr; Kthehu te Login</a></p>

        <?php else: ?>
            <p class="auth-sub">Zgjidh nje password te ri per llogarine tende.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="signupForm" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <label>Password i ri</label>
                <input type="password" name="password" id="password"
                       placeholder="••••••••" autocomplete="new-password" required>
                <div class="strength"><span id="strengthBar"></span></div>
                <small class="hint" id="pwHint">Min 8 karaktere, shkronje dhe numer.</small>

                <label>Konfirmo password-in</label>
                <input type="password" name="cpassword" id="cpassword"
                       placeholder="••••••••" autocomplete="new-password" required>
                <small class="hint" id="matchHint"></small>

                <button type="submit">Ruaj passwordin</button>
            </form>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>