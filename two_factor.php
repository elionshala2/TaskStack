<?php
// two_factor.php - hapi i dyte i login-it (kodi nga emaili)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/two_factor.php';

session_start();

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$pending = $_SESSION['2fa_pending'] ?? null;
if (!$pending || (time() - (int)($pending['at'] ?? 0)) > TWO_FA_PENDING_MINUTES * 60) {
    unset($_SESSION['2fa_pending']);
    $_SESSION['flash_error'] = t('2fa.expired');
    redirect('login.php');
}

$user_id = (int)$pending['id'];
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = t('login.invalid_req');
    } else {
        try {
            $action = $_POST['action'] ?? 'verify';

            if ($action === 'cancel') {
                unset($_SESSION['2fa_pending']);
                redirect('login.php');
            } elseif ($action === 'resend') {
                $res = two_fa_send_code($conn, $user_id, $pending['email']);
                if ($res === 'wait') {
                    $errors[] = t('2fa.wait');
                } elseif ($res === true) {
                    $success = t('2fa.sent');
                } else {
                    $errors[] = t('2fa.mail_fail');
                }
            } else {
                $ip = client_ip();
                $ukey = user_key($user_id);
                if (login_attempts_count($conn, $ip) >= MAX_LOGIN_ATTEMPTS * 2) {
                    $errors[] = t('2fa.too_many');
                } else {
                    $res = two_fa_check_code($conn, $user_id, (string)($_POST['code'] ?? ''));
                    if ($res === 'ok') {
                        $username = $pending['username'];
                        unset($_SESSION['2fa_pending']);
                        clear_login_attempts($conn, $ukey);
                        login_user($conn, $user_id, $username);
                        redirect('index.php');
                    } elseif ($res === 'locked') {
                        $errors[] = t('2fa.too_many');
                    } else {
                        record_login_attempt($conn, $ip);
                        $errors[] = t('2fa.bad_code');
                    }
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log("2FA error: " . $e->getMessage());
            $errors[] = t('2fa.mail_fail');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('2fa.title')) ?> - TaskStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="otp-page">
        <div class="auth-box otp-box">
            <div class="otp-icon" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3 4 6v6c0 4.5 3.2 8.2 8 9 4.8-.8 8-4.5 8-9V6l-8-3z" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
            </div>

            <h1><?= e(t('2fa.title')) ?></h1>
            <p class="auth-sub"><?= e(t('2fa.sub', ['email' => mask_email($pending['email'])])) ?></p>

            <?php if ($success): ?>
                <div class="alert success">
                    <p><?= e($success) ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="verify">

                <label for="otpCode"><?= e(t('2fa.code')) ?></label>
                <input type="text" id="otpCode" name="code" class="otp-input"
                    inputmode="numeric" pattern="[0-9]*" maxlength="6"
                    autocomplete="one-time-code" enterkeyhint="done"
                    placeholder="······" autofocus required>

                <button type="submit"><?= e(t('2fa.verify')) ?></button>
            </form>

            <div class="otp-actions">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit"><?= e(t('2fa.resend')) ?></button>
                </form>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit"><?= e(t('2fa.cancel')) ?></button>
                </form>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>