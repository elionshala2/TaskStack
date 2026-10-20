<?php
// revert.php - anulon nje ndryshim (emaili ose passwordi) brenda 12 oreve nga njoftimi
// GET = shfaq faqen me buton; POST = zbaton anulimin (qe skanuesit e emaileve te mos e aktivizojne vetem duke hapur linkun)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

session_start();

$token  = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$state  = 'invalid'; // invalid | form | done | taken | error
$kind   = '';        // revert_email | revert_password
$errors = [];
$row    = null;

try {
    foreach (['revert_email', 'revert_password'] as $t) {
        $found = find_email_token($conn, $token, $t);
        if ($found) {
            $row   = $found;
            $kind  = $t;
            $state = 'form';
            break;
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Revert lookup error: " . $e->getMessage());
}

if ($state === 'form' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        try {
            $uid = (int)$row['user_id'];

            if ($kind === 'revert_email') {
                $old_email = (string)$row['payload'];

                // a eshte marre emaili i vjeter nga dikush tjeter ndërkohë?
                $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE email = ? AND id <> ? LIMIT 1");
                $stmt->bind_param("si", $old_email, $uid);
                $stmt->execute();
                $stmt->store_result();
                $taken = $stmt->num_rows > 0;
                $stmt->close();

                if ($old_email === '') {
                    $state = 'invalid';
                } elseif ($taken) {
                    $state = 'taken';
                } else {
                    // useri e hapi linkun nga inbox-i i emailit te vjeter, ndaj ai email vlen si i verifikuar
                    $stmt = $conn->prepare("UPDATE regjistrimi SET email = ?, email_verified = 1, session_version = session_version + 1 WHERE id = ?");
                    $stmt->bind_param("si", $old_email, $uid);
                    $stmt->execute();
                    $stmt->close();

                    mark_token_used($conn, (int)$row['id']);
                    invalidate_user_tokens($conn, $uid, 'email_change'); // asnje ndryshim tjeter ne pritje
                    $state = 'done';
                }
            } else { // revert_password
                $data     = json_decode((string)$row['payload'], true);
                $old_hash = is_array($data) ? (string)($data['hash'] ?? '') : '';
                $provider = (is_array($data) && ($data['provider'] ?? '') === 'google') ? 'google' : 'local';

                if (strlen($old_hash) < 20 || $old_hash[0] !== '$') {
                    $state = 'invalid';
                } else {
                    $stmt = $conn->prepare("UPDATE regjistrimi SET password = ?, provider = ?, session_version = session_version + 1 WHERE id = ?");
                    $stmt->bind_param("ssi", $old_hash, $provider, $uid);
                    $stmt->execute();
                    $stmt->close();

                    mark_token_used($conn, (int)$row['id']);
                    invalidate_user_tokens($conn, $uid, 'reset'); // linqet reset ne pritje behen te pavlefshem
                    $state = 'done';
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Revert error: " . $e->getMessage());
            $state = ((int)$e->getCode() === 1062) ? 'taken' : 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anulo ndryshimin - TaskStack</title>
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
            <h1>Anulo ndryshimin</h1>

            <?php if ($state === 'form'): ?>
                <?php if ($kind === 'revert_email'): ?>
                    <p class="auth-sub">Emaili i llogarise do te kthehet te <strong><?= e($row['payload']) ?></strong>.</p>
                <?php else: ?>
                    <p class="auth-sub">Passwordi i llogarise do te kthehet ne passwordin e meparshem (para rivendosjes).</p>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <button type="submit">Po, anuloje ndryshimin</button>
                </form>

            <?php elseif ($state === 'done'): ?>
                <div class="alert success">
                    <p><?= $kind === 'revert_email'
                            ? 'Emaili u kthye mbrapsht.'
                            : 'Passwordi u kthye ne ate te meparshmin.' ?></p>
                </div>
                <p>Nese nuk e ke bere ti ndryshimin, ndrysho edhe passwordin e emailit tend tani, pastaj ndrysho passwordin e TaskStack nga Settings.</p>
                <p><a href="login.php">Shko te Login &rarr;</a></p>

            <?php elseif ($state === 'taken'): ?>
                <div class="alert error">
                    <p>Ky email perdoret tashme nga nje llogari tjeter, ndaj nuk mund te kthehet.</p>
                </div>
                <p><a href="forgot_password.php">Nese s'mund te hysh, rivendos passwordin</a></p>

            <?php elseif ($state === 'error'): ?>
                <div class="alert error">
                    <p>Diqka shkoi gabim. Provo perseri me vone.</p>
                </div>

            <?php else: ?>
                <div class="alert error">
                    <p>Ky link eshte i pavlefshem, ka skaduar (vlen 12 ore) ose eshte perdorur tashme.</p>
                </div>
                <p><a href="login.php">&larr; Kthehu te Login</a></p>
            <?php endif; ?>
        </div>

    </div>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>