<?php
// confirm_email.php - konfirmon emailin e ri (linku vjen ne emailin e ri)
// GET = shfaq faqen me buton; POST = zbaton ndryshimin (qe skanuesit e emaileve te mos e aktivizojne vetem duke hapur linkun)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

session_start();

$token  = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$state  = 'invalid'; // invalid | form | done | taken | error
$errors = [];
$row    = null;

try {
    $row = find_email_token($conn, $token, 'email_change');
    if ($row) {
        $state = 'form';
    }
} catch (mysqli_sql_exception $e) {
    error_log("Confirm email lookup error: " . $e->getMessage());
}

if ($state === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        try {
            $uid       = (int)$row['user_id'];
            $new_email = (string)$row['payload'];

            // a eshte marre ky email nga dikush tjeter ndërkohë?
            $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE email = ? AND id <> ? LIMIT 1");
            $stmt->bind_param("si", $new_email, $uid);
            $stmt->execute();
            $stmt->store_result();
            $taken = $stmt->num_rows > 0;
            $stmt->close();

            $stmt = $conn->prepare("SELECT username, email FROM regjistrimi WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || $new_email === '') {
                $state = 'invalid';
            } elseif ($taken) {
                $state = 'taken';
            } else {
                $old_email = $u['email'];

                // emaili i ri vlen si i verifikuar (useri ka klikuar linkun ne inbox-in e tij)
                $stmt = $conn->prepare("UPDATE regjistrimi SET email = ?, email_verified = 1 WHERE id = ?");
                $stmt->bind_param("si", $new_email, $uid);
                $stmt->execute();
                $stmt->close();

                mark_token_used($conn, (int)$row['id']);
                invalidate_user_tokens($conn, $uid, 'email_change');

                // njofto emailin e VJETER me link per ta anuluar ndryshimin (12 ore)
                send_email_changed_notice($conn, $uid, $old_email, $new_email, $u['username']);

                $state = 'done';
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Confirm email error: " . $e->getMessage());
            $state = ((int)$e->getCode() === 1062) ? 'taken' : 'error';
        }
    }
}

$logged_in = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmo emailin - TaskStack</title>
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
            <h1>Konfirmo emailin</h1>

            <?php if ($state === 'form'): ?>
                <p class="auth-sub">Konfirmo që emaili i llogarise në platformën TaskStack të bëhet <strong><?= e($row['payload']) ?></strong>.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <button type="submit">Po, ndrysho emailin</button>
                </form>

            <?php elseif ($state === 'done'): ?>
                <div class="alert success">
                    <p>Emaili u ndryshua me sukses! Përdore atë për t'u kyqur nga tani.</p>
                </div>
                <p><a href="<?= $logged_in ? 'settings.php' : 'login.php' ?>"><?= $logged_in ? 'Shko te Settings' : 'Shko te Login' ?> &rarr;</a></p>

            <?php elseif ($state === 'taken'): ?>
                <div class="alert error">
                    <p>Ky email është i përdorur nga një llogari tjeter, ndaj nuk mund të ndryshohet.</p>
                </div>
                <p><a href="settings.php">&larr; Kthehu te Settings</a></p>

            <?php elseif ($state === 'error'): ?>
                <div class="alert error">
                    <p>Diqka shkoi gabim. Provo përsëri me vone.</p>
                </div>

            <?php else: ?>
                <div class="alert error">
                    <p>Ky link është i pavlefshem, ka skaduar ose është perdorur tashme.</p>
                </div>
                <p><a href="settings.php">&larr; Kthehu te Settings</a></p>
            <?php endif; ?>
        </div>

    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>