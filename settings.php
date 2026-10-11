<?php
// settings.php
// Faqja e cilesimeve: ndrysho username, ndrysho/vendos password, fshi llogarine

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id = (int)$_SESSION['user_id']; // merr user_id nga sessioni

// gabimet ndahen sipas seksionit, qe cdo karte te shfaqe vetem gabimet e veta
$errors = ['general' => [], 'username' => [], 'password' => [], 'delete' => []];

// Ngarkon te dhenat e userit nga databaza
function load_user(mysqli $conn, int $user_id): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, username, email, password, provider, google_id, created_at
         FROM regjistrimi WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Verifikon passwordin aktual, duke perdorur te njejtin rate limiting si login-i
function check_current_password(mysqli $conn, array $user, string $given, ?string &$err): bool
{
    $ip = client_ip();

    if (login_attempts_count($conn, $ip) >= MAX_LOGIN_ATTEMPTS) {
        $err = 'Shume tentativa te pasuksesshme. Provo perseri pas ' . LOGIN_WINDOW_MINUTES . ' minutash.';
        return false;
    }
    if ($given === '' || !password_verify($given, $user['password'])) {
        record_login_attempt($conn, $ip);
        $err = 'Password-i aktual eshte i gabuar.';
        return false;
    }
    clear_login_attempts($conn, $ip);
    return true;
}

// Rregullat e passwordit (te njejta me signup.php)
function password_errors(string $pw, string $cpw): array
{
    $errs = [];
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        $errs[] = 'Password: min 8 karaktere, te pakten 1 shkronje dhe 1 numer.';
    }
    if ($pw !== $cpw) {
        $errs[] = 'Password-et nuk perputhen.';
    }
    return $errs;
}

$user = load_user($conn, $user_id);

// nese llogaria nuk ekziston me (p.sh. u fshi nga nje tab tjeter)
if (!$user) {
    $_SESSION = [];
    redirect('login.php');
}

// Llogari vetem me Google = passwordi eshte i rastesishem, useri nuk e di
$is_google_only = ($user['provider'] ?? 'local') === 'google';
$old_username   = $user['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors['general'][] = 'Kerkese e pavlefshme. Provo perseri.';

    // ---------- 1. Ndrysho username ----------
    } elseif ($action === 'username') {

        $new = trim($_POST['username'] ?? '');
        $old_username = $new;

        if (strlen($new) < 3 || strlen($new) > 20) {
            $errors['username'][] = 'Username duhet 3-20 karaktere.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $new)) {
            $errors['username'][] = 'Username mund te permbaje vetem shkronja, numra dhe _.';
        } elseif ($new === $user['username']) {
            $errors['username'][] = 'Ky eshte username-i yt aktual.';
        }

        if (empty($errors['username'])) {
            try {
                // a e ka dikush tjeter?
                $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE username = ? AND id <> ? LIMIT 1");
                $stmt->bind_param("si", $new, $user_id);
                $stmt->execute();
                $stmt->store_result();
                $taken = $stmt->num_rows > 0;
                $stmt->close();

                if ($taken) {
                    $errors['username'][] = 'Ky username ekziston! Zgjidh nje tjeter.';
                } else {
                    $stmt = $conn->prepare("UPDATE regjistrimi SET username = ? WHERE id = ?");
                    $stmt->bind_param("si", $new, $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['username']      = $new; // navbar-i e merr emrin e ri menjehere
                    $_SESSION['flash_success'] = 'Username u ndryshua me sukses!';
                    redirect('settings.php');
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Settings username error: " . $e->getMessage());
                if ((int)$e->getCode() === 1062) {
                    $errors['username'][] = 'Ky username ekziston! Zgjidh nje tjeter.';
                } else {
                    $errors['username'][] = 'Diqka shkoi gabim. Provo perseri.';
                }
            }
        }

    // ---------- 2. Ndrysho / vendos password ----------
    } elseif ($action === 'password') {

        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['cpassword'] ?? '');

        // llogarite me password duhet te japin passwordin aktual; ato vetem me Google jo
        if (!$is_google_only) {
            $err = null;
            if (!check_current_password($conn, $user, $current, $err)) {
                $errors['password'][] = $err;
            }
        }

        foreach (password_errors($new, $confirm) as $pe) {
            $errors['password'][] = $pe;
        }

        if (empty($errors['password']) && !$is_google_only && hash_equals($current, $new)) {
            $errors['password'][] = 'Password-i i ri duhet te jete ndryshe nga ai aktual.';
        }

        if (empty($errors['password'])) {
            try {
                $hash = password_hash($new, PASSWORD_DEFAULT);

                // provider behet 'local' qe nga tani faqja te kerkoje passwordin aktual
                // (google_id mbetet, ndaj kyqja me Google vazhdon te funksionoje)
                $stmt = $conn->prepare("UPDATE regjistrimi SET password = ?, provider = 'local' WHERE id = ?");
                $stmt->bind_param("si", $hash, $user_id);
                $stmt->execute();
                $stmt->close();

                session_regenerate_id(true); // siguri pas ndryshimit te kredencialeve

                $_SESSION['flash_success'] = $is_google_only
                    ? 'Password-i u vendos! Tani mund te kyqesh edhe me email dhe password.'
                    : 'Password-i u ndryshua me sukses!';
                redirect('settings.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Settings password error: " . $e->getMessage());
                $errors['password'][] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }

    // ---------- 3. Fshi llogarine ----------
    } elseif ($action === 'delete') {

        if ($is_google_only) {
            // pa password -> useri shkruan username-in per konfirmim
            if (trim((string)($_POST['confirm_text'] ?? '')) !== $user['username']) {
                $errors['delete'][] = 'Shkruaj username-in tend saktesisht per te konfirmuar.';
            }
        } else {
            $err = null;
            if (!check_current_password($conn, $user, (string)($_POST['delete_password'] ?? ''), $err)) {
                $errors['delete'][] = $err;
            }
        }

        if (empty($errors['delete'])) {
            try {
                // gjithcka ose asgje (transaksion)
                $conn->begin_transaction();

                $stmt = $conn->prepare("DELETE FROM tasks WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM categories WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM regjistrimi WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                // dil nga llogaria dhe trego mesazhin ne login
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['flash_success'] = 'Llogaria dhe te gjitha te dhenat e saj u fshine.';
                redirect('login.php');

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                error_log("Settings delete error: " . $e->getMessage());
                $errors['delete'][] = 'Diqka shkoi gabim. Llogaria nuk u fshi. Provo perseri.';
            }
        }
    }
}

// flash nga veprimet (ndryshim username / password)
$flash = '';
if (!empty($_SESSION['flash_success'])) {
    $flash = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// te dhenat per shfaqje
$initial      = mb_strtoupper(mb_substr($user['username'], 0, 1));
$member_since = date('d.m.Y', strtotime($user['created_at']));
$has_google   = !empty($user['google_id']);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TaskStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="page-center settings-page">

    <div class="page-header">
        <p class="eyebrow">Llogaria</p>
        <h1>Settings</h1>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert error">
            <?php foreach ($errors['general'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="settings-stack">

        <!-- ================= PROFILI ================= -->
        <section class="profile-card">
            <div class="avatar" aria-hidden="true"><?= e($initial) ?></div>
            <div class="profile-info">
                <div class="profile-name"><?= e($user['username']) ?></div>
                <div class="profile-email"><?= e($user['email']) ?></div>
                <div class="profile-chips">
                    <?php if (!$is_google_only): ?><span class="chip">Email + password</span><?php endif; ?>
                    <?php if ($has_google): ?><span class="chip chip-google">Google</span><?php endif; ?>
                    <span class="chip chip-muted">Anetar qe nga <?= e($member_since) ?></span>
                </div>
            </div>
        </section>

        <!-- ================= USERNAME ================= -->
        <form method="POST" class="settings-card" id="username" novalidate>
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="username">

            <div class="settings-head">
                <h2>Username</h2>
                <p>Ky emer shfaqet ne dashboard dhe ne navbar.</p>
            </div>

            <?php if (!empty($errors['username'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['username'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label for="usernameInput">Username i ri</label>
            <input type="text" id="usernameInput" name="username" maxlength="20"
                   value="<?= e($old_username) ?>" autocomplete="username" required>
            <small class="hint">3-20 karaktere: shkronja, numra dhe _.</small>

            <div class="form-actions">
                <button type="submit" class="btn">Ruaj username</button>
            </div>
        </form>

        <!-- ================= PASSWORD ================= -->
        <form method="POST" class="settings-card" id="password-card" novalidate>
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">

            <div class="settings-head">
                <?php if ($is_google_only): ?>
                    <h2>Vendos nje password</h2>
                    <p>Hyn me Google, ndaj nuk ke password. Vendos nje nese deshiron te kyqesh edhe me email dhe password.</p>
                <?php else: ?>
                    <h2>Ndrysho password-in</h2>
                    <p>Per siguri, shkruaj passwordin aktual para se te vendosesh nje te ri.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($errors['password'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['password'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$is_google_only): ?>
                <label for="current_password">Password aktual</label>
                <input type="password" id="current_password" name="current_password"
                       placeholder="••••••••" autocomplete="current-password" required>
            <?php endif; ?>

            <div class="form-row">
                <div>
                    <label for="password">Password i ri</label>
                    <input type="password" name="password" id="password"
                           placeholder="••••••••" autocomplete="new-password" required>
                    <div class="strength"><span id="strengthBar"></span></div>
                    <small class="hint" id="pwHint">Min 8 karaktere, shkronje dhe numer.</small>
                </div>
                <div>
                    <label for="cpassword">Konfirmo password-in</label>
                    <input type="password" name="cpassword" id="cpassword"
                           placeholder="••••••••" autocomplete="new-password" required>
                    <small class="hint" id="matchHint"></small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $is_google_only ? 'Vendos password' : 'Ndrysho password' ?></button>
            </div>
        </form>

        <!-- ================= FSHI LLOGARINE ================= -->
        <form method="POST" class="settings-card danger" id="delete" novalidate
              onsubmit="return confirm('Je i sigurt? Llogaria dhe te gjitha detyrat do te fshihen pergjithmone.');">
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">

            <div class="settings-head">
                <h2>Fshi llogarine</h2>
                <p>Kjo fshin llogarine tende, te gjitha detyrat dhe kategorite. Nuk mund te kthehet mbrapsht.</p>
            </div>

            <?php if (!empty($errors['delete'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['delete'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($is_google_only): ?>
                <label for="confirm_text">Shkruaj <strong><?= e($user['username']) ?></strong> per te konfirmuar</label>
                <input type="text" id="confirm_text" name="confirm_text"
                       placeholder="<?= e($user['username']) ?>" autocomplete="off" required>
            <?php else: ?>
                <label for="delete_password">Konfirmo me password-in tend</label>
                <input type="password" id="delete_password" name="delete_password"
                       placeholder="••••••••" autocomplete="current-password" required>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Fshi llogarine time</button>
            </div>
        </form>

    </div><!-- /settings-stack -->

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>