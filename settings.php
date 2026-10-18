<?php
// settings.php
// Faqja e cilesimeve: ndrysho username, ndrysho/vendos password, fshi llogarine

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme
require_once __DIR__ . '/includes/mailer.php'; // emailet (ndryshimi i emailit)
require_once __DIR__ . '/includes/two_factor.php'; // 2FA me email
require_once __DIR__ . '/includes/passkeys.php'; // passkeys (WebAuthn)

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id = (int)$_SESSION['user_id']; // merr user_id nga sessioni

// gabimet ndahen sipas seksionit, qe cdo karte te shfaqe vetem gabimet e veta
$errors = ['general' => [], 'username' => [], 'email' => [], 'password' => [], 'google' => [], 'sessions' => [], 'twofa' => [], 'delete' => [], 'passkeys' => [], 'reminders' => []];

// Ngarkon te dhenat e userit nga databaza
function load_user(mysqli $conn, int $user_id): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, username, email, password, provider, google_id, created_at, two_factor_enabled
         FROM regjistrimi WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
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

// Llogarite vetem me Google nuk kane password per ta konfirmuar veprimet e rrezikshme; ne vend te kesaj
// duhet te verifikojne perseri me Google (vlen 5 minuta, vendoset nga google-callback.php)
$reauth_ok = !empty($_SESSION['reauth_until']) && (int)$_SESSION['reauth_until'] >= time();
$twofa_pending = !empty($_SESSION['2fa_enable_pending']); // pret kodin nga emaili per ta aktivizuar 2FA
// Kujtesat e afateve (kolona shtohet nga migration.sql; pa te, karta fshihet)
$reminders_available = false;
$reminders_on        = true;
try {
    $stmt = $conn->prepare("SELECT reminders_enabled FROM regjistrimi WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rrow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($rrow) { $reminders_available = true; $reminders_on = (int)$rrow['reminders_enabled'] === 1; }
} catch (mysqli_sql_exception $e) {
    error_log('Settings reminders load: ' . $e->getMessage());
}
$old_new_email  = ''; // qe emaili i shkruar mos te fshihet nese ka gabim

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors['general'][] = 'Kerkese e pavlefshme. Provo perseri.';

    // ---------- 0. 2FA (aktivizo / konfirmo kodin / çaktivizo) ----------
    } elseif (in_array($action, ['2fa_start', '2fa_confirm', '2fa_disable', '2fa_cancel'], true)) {

        $twofa_on = (int)$user['two_factor_enabled'] === 1;
        try {
            if ($is_google_only) {
                $errors['twofa'][] = t('2fa.google_note');

            } elseif ($action === '2fa_start' && !$twofa_on) {
                $res = two_fa_send_code($conn, $user_id, $user['email']);
                if ($res === true || $res === 'wait') {
                    $_SESSION['2fa_enable_pending'] = true;
                    $_SESSION['flash_success'] = t('2fa.confirm_enable');
                    redirect('settings.php#two-factor');
                }
                $errors['twofa'][] = t('2fa.mail_fail');

            } elseif ($action === '2fa_cancel') {
                unset($_SESSION['2fa_enable_pending']);
                redirect('settings.php#two-factor');

            } elseif ($action === '2fa_confirm' && !$twofa_on && !empty($_SESSION['2fa_enable_pending'])) {
                $res = two_fa_check_code($conn, $user_id, (string)($_POST['code'] ?? ''));
                if ($res === 'ok') {
                    $stmt = $conn->prepare("UPDATE regjistrimi SET two_factor_enabled = 1 WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                    unset($_SESSION['2fa_enable_pending']);
                    $_SESSION['flash_success'] = t('2fa.enabled_ok');
                    redirect('settings.php#two-factor');
                }
                $errors['twofa'][] = ($res === 'locked') ? t('2fa.too_many') : t('2fa.bad_code');

            } elseif ($action === '2fa_disable' && $twofa_on) {
                $err = null;
                if (check_current_password($conn, $user, (string)($_POST['password'] ?? ''), $err)) {
                    $stmt = $conn->prepare("UPDATE regjistrimi SET two_factor_enabled = 0 WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['flash_success'] = t('2fa.disabled_ok');
                    redirect('settings.php#two-factor');
                }
                $errors['twofa'][] = $err;
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Settings 2FA error: " . $e->getMessage());
            $errors['twofa'][] = t('2fa.mail_fail');
        }
        $twofa_pending = !empty($_SESSION['2fa_enable_pending']);

    // ---------- Kujtesat e afateve ----------
    } elseif ($action === 'reminders' && $reminders_available) {
        $on = !empty($_POST['reminders_enabled']) ? 1 : 0;
        try {
            $stmt = $conn->prepare("UPDATE regjistrimi SET reminders_enabled = ? WHERE id = ?");
            $stmt->bind_param("ii", $on, $user_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = t('rem.saved');
            redirect('settings.php#reminders');
        } catch (mysqli_sql_exception $e) {
            error_log('Settings reminders save: ' . $e->getMessage());
            $errors['reminders'][] = t('2fa.mail_fail');
        }

    // ---------- Fshi nje passkey ----------
    } elseif ($action === 'passkey_delete') {
        $pid = (int)($_POST['passkey_id'] ?? 0);
        $ok  = false;
        if ($is_google_only) {
            if ($reauth_ok) { $ok = true; } else { $errors['passkeys'][] = t('pk.need_reauth'); }
        } else {
            $err = null;
            if (check_current_password($conn, $user, (string)($_POST['password'] ?? ''), $err)) { $ok = true; } else { $errors['passkeys'][] = $err; }
        }
        if ($ok) {
            try {
                $stmt = $conn->prepare("DELETE FROM passkeys WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $pid, $user_id);
                $stmt->execute();
                $stmt->close();
                unset($_SESSION['reauth_until']);
                $_SESSION['flash_success'] = t('pk.deleted');
                redirect('settings.php#passkeys');
            } catch (mysqli_sql_exception $e) {
                error_log('Settings passkey delete: ' . $e->getMessage());
                $errors['passkeys'][] = t('pk.unavailable');
            }
        }

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

    // ---------- 1b. Ndrysho emailin (konfirmohet me link ne emailin e ri) ----------
    } elseif ($action === 'email') {

        $new_email     = mb_strtolower(trim($_POST['new_email'] ?? ''));
        $old_new_email = $new_email;

        if ($is_google_only) {
            $errors['email'][] = 'Vendos fillimisht nje password (seksioni me poshte) para se te ndryshosh emailin.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Email i pavlefshem.';
        } elseif (strcasecmp($new_email, $user['email']) === 0) {
            $errors['email'][] = 'Ky eshte emaili yt aktual.';
        } else {
            $err = null;
            if (!check_current_password($conn, $user, (string)($_POST['email_password'] ?? ''), $err)) {
                $errors['email'][] = $err;
            }
        }

        if (empty($errors['email'])) {
            try {
                $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE email = ? AND id <> ? LIMIT 1");
                $stmt->bind_param("si", $new_email, $user_id);
                $stmt->execute();
                $stmt->store_result();
                $taken = $stmt->num_rows > 0;
                $stmt->close();

                if ($taken) {
                    $errors['email'][] = 'Ky email perdoret nga nje llogari tjeter.';
                } elseif (recent_token_exists($conn, $user_id, 'email_change')) {
                    $errors['email'][] = 'Sapo te dergova nje email. Prit rreth 1 minute para se te provosh perseri.';
                } elseif (send_email_change_email($conn, $user_id, $new_email, $user['username'])) {
                    $_SESSION['flash_success'] = 'Te dergova nje link konfirmimi ne ' . $new_email . '. Emaili nuk ndryshon derisa ta konfirmosh (linku vlen 1 ore).';
                    redirect('settings.php');
                } else {
                    $errors['email'][] = 'Emaili nuk u dergua. Provo perseri.';
                }
            } catch (mysqli_sql_exception $e) {
                error_log("Settings email error: " . $e->getMessage());
                $errors['email'][] = 'Diqka shkoi gabim. Provo perseri.';
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
        } elseif (!$reauth_ok) {
            $errors['password'][] = 'Per siguri, verifiko fillimisht identitetin me Google (butoni "Verifiko me Google" me siper).';
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
                // session_version + 1 => te gjitha sesionet e tjera mbyllen (kjo pajisje mbetet e kyqur)
                $stmt = $conn->prepare(
                    "UPDATE regjistrimi SET password = ?, provider = 'local', session_version = session_version + 1 WHERE id = ?"
                );
                $stmt->bind_param("si", $hash, $user_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['sv'] = (int)get_session_version($conn, $user_id); // sesioni aktual e merr versionin e ri
                unset($_SESSION['reauth_until']); // verifikimi me Google perdoret vetem nje here
                session_regenerate_id(true); // siguri pas ndryshimit te kredencialeve

                $_SESSION['flash_success'] = $is_google_only
                    ? 'Password-i u vendos! Tani mund te kyqesh edhe me email dhe password. Sesionet ne pajisjet e tjera u mbyllen.'
                    : 'Password-i u ndryshua me sukses! Sesionet ne pajisjet e tjera u mbyllen.';
                redirect('settings.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Settings password error: " . $e->getMessage());
                $errors['password'][] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }

    // ---------- 2b. Mbyll sesionet ne pajisjet e tjera ----------
    } elseif ($action === 'logout_others') {

        try {
            // rrit versionin => cdo sesion tjeter bie; kjo pajisje e ruan versionin e ri
            $_SESSION['sv'] = bump_session_version($conn, $user_id);
            session_regenerate_id(true);

            $_SESSION['flash_success'] = 'U mbyllen sesionet ne te gjitha pajisjet e tjera. Kjo pajisje mbetet e kyqur.';
            redirect('settings.php');

        } catch (mysqli_sql_exception $e) {
            error_log("Settings logout others error: " . $e->getMessage());
            $errors['sessions'][] = 'Diqka shkoi gabim. Provo perseri.';
        }

    // ---------- 3. Shkput Google ----------
    } elseif ($action === 'unlink') {

        if ($is_google_only || empty($user['google_id'])) {
            // pa password, Google eshte e vetmja menyre per t'u kyqur
            $errors['google'][] = 'Vendos fillimisht nje password para se te shkeputesh Google.';
        } else {
            $err = null;
            if (!check_current_password($conn, $user, (string)($_POST['unlink_password'] ?? ''), $err)) {
                $errors['google'][] = $err;
            }
        }

        if (empty($errors['google'])) {
            try {
                $stmt = $conn->prepare("UPDATE regjistrimi SET google_id = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Google u shkeput nga llogaria.';
                redirect('settings.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Settings unlink error: " . $e->getMessage());
                $errors['google'][] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }

    // ---------- 4. Fshi llogarine ----------
    } elseif ($action === 'delete') {

        if ($is_google_only) {
            // pa password -> useri shkruan username-in per konfirmim
            if (!$reauth_ok) {
                $errors['delete'][] = 'Per siguri, verifiko fillimisht identitetin me Google (butoni "Verifiko me Google" me siper).';
            } elseif (trim((string)($_POST['confirm_text'] ?? '')) !== $user['username']) {
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

// gabim nga google-callback.php (lidhja me Google)
if (!empty($_SESSION['flash_error'])) {
    $errors['general'][] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
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
    <link rel="stylesheet" href="assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/assets/style.css') ?>">
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

        <!-- ================= 2FA ================= -->
        <?php $twofa_on = (int)$user['two_factor_enabled'] === 1; ?>
        <section class="settings-card" id="two-factor">
            <div class="settings-head">
                <h2><?= e(t('2fa.set_title')) ?></h2>
                <p><?= e($is_google_only ? t('2fa.google_note') : ($twofa_on ? t('2fa.set_on') : t('2fa.set_off'))) ?></p>
            </div>

            <?php if (!empty($errors['twofa'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['twofa'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$is_google_only): ?>
                <?php if ($twofa_on): ?>
                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="2fa_disable">
                        <label for="twofaPw"><?= e(t('2fa.cur_pw')) ?></label>
                        <input type="password" id="twofaPw" name="password" autocomplete="current-password" required>
                        <div class="form-actions"><button type="submit" class="btn"><?= e(t('2fa.disable')) ?></button></div>
                    </form>
                <?php elseif ($twofa_pending): ?>
                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="2fa_confirm">
                        <label for="twofaCode"><?= e(t('2fa.code')) ?></label>
                        <input type="text" id="twofaCode" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="123456" required>
                        <div class="form-actions"><button type="submit" class="btn"><?= e(t('2fa.verify')) ?></button></div>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="2fa_start">
                        <p><button type="submit" class="link-btn"><?= e(t('2fa.resend')) ?></button></p>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="2fa_start">
                        <div class="form-actions"><button type="submit" class="btn"><?= e(t('2fa.enable')) ?></button></div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- ================= PASSKEYS ================= -->
        <?php $pk_list = passkeys_for_user($conn, $user_id); ?>
        <?php if ($pk_list !== null): ?>
        <section class="settings-card" id="passkeys">
            <div class="settings-head">
                <h2><?= e(t('pk.set_title')) ?></h2>
                <p><?= e(t('pk.set_desc')) ?></p>
            </div>

            <?php if (!empty($errors['passkeys'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['passkeys'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($pk_list): ?>
                <ul class="passkey-list">
                    <?php foreach ($pk_list as $pk): ?>
                        <li>
                            <div class="passkey-info">
                                <strong><?= e($pk['name']) ?></strong>
                                <span><?= e(t('pk.created')) ?> <?= e(date('d.m.Y', strtotime($pk['created_at']))) ?>
                                    &middot; <?= e(t('pk.last_used')) ?> <?= $pk['last_used_at'] ? e(date('d.m.Y', strtotime($pk['last_used_at']))) : e(t('pk.never')) ?></span>
                            </div>
                            <form method="POST" class="passkey-del" data-confirm="<?= e(t('pk.confirm_del')) ?>">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="passkey_delete">
                                <input type="hidden" name="passkey_id" value="<?= (int)$pk['id'] ?>">
                                <?php if (!$is_google_only): ?>
                                    <input type="password" name="password" placeholder="<?= e(t('pk.pw_short')) ?>" autocomplete="current-password" required>
                                <?php endif; ?>
                                <button type="submit" class="btn-outline"><?= e(t('pk.delete')) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="passkey-empty"><?= e(t('pk.none')) ?></p>
            <?php endif; ?>

            <p id="passkeyUnsupported" class="passkey-empty"><?= e(t('pk.unsupported')) ?></p>
            <div id="passkeyRegister" hidden data-endpoint="passkey_api.php" data-csrf="<?= e(csrf_token()) ?>"
                 data-msg-cancel="<?= e(t('pk.cancelled')) ?>" data-msg-fail="<?= e(t('pk.failed')) ?>">
                <label for="passkeyName"><?= e(t('pk.name_label')) ?></label>
                <input type="text" id="passkeyName" maxlength="60" placeholder="<?= e(t('pk.name_ph')) ?>" autocomplete="off">
                <?php if (!$is_google_only): ?>
                    <label for="passkeyPw"><?= e(t('pk.pw_label')) ?></label>
                    <input type="password" id="passkeyPw" autocomplete="current-password">
                <?php elseif (!$reauth_ok): ?>
                    <p class="passkey-empty"><?= e(t('pk.need_reauth')) ?></p>
                <?php endif; ?>
                <p class="passkey-msg alert error" role="alert" hidden></p>
                <div class="form-actions"><button type="button" class="btn" id="passkeyAddBtn"><?= e(t('pk.add_btn')) ?></button></div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ================= KUJTESAT ================= -->
        <?php if ($reminders_available): ?>
        <form method="POST" class="settings-card" id="reminders" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reminders">
            <div class="settings-head">
                <h2><?= e(t('rem.title')) ?></h2>
                <p><?= e(t('rem.desc')) ?></p>
            </div>
            <?php if (!empty($errors['reminders'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['reminders'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label class="check-row">
                <input type="checkbox" name="reminders_enabled" value="1" <?= $reminders_on ? 'checked' : '' ?>>
                <span><?= e(t('rem.label')) ?></span>
            </label>
            <div class="form-actions"><button type="submit" class="btn"><?= e(t('rem.save')) ?></button></div>
        </form>
        <?php endif; ?>

        <!-- ================= EMAIL =================
        <form method="POST" class="settings-card" id="email-card" novalidate>
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="email">

            <div class="settings-head">
                <h2>Email</h2>
                <p>Emaili aktual: <strong><?= e($user['email']) ?></strong>. Per ta ndryshuar, te dergojme nje link konfirmimi ne emailin e ri.</p>
            </div>

            <?php if (!empty($errors['email'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['email'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($is_google_only): ?>
                <small class="hint">Hyn me Google dhe nuk ke password. Vendos nje password ne seksionin me poshte per te mundesuar ndryshimin e emailit.</small>
            <?php else: ?>
                <label for="newEmailInput">Email i ri</label>
                <input type="email" id="newEmailInput" name="new_email"
                       value="<?= e($old_new_email) ?>" placeholder="name@example.com"
                       autocomplete="email" required>

                <label for="emailPasswordInput">Password aktual</label>
                <input type="password" id="emailPasswordInput" name="email_password"
                       placeholder="••••••••" autocomplete="current-password" required>

                <div class="form-actions">
                    <button type="submit" class="btn">Dergo linkun e konfirmimit</button>
                </div>
            <?php endif; ?>
        </form>

        <!-- ================= VERIFIKIMI ME GOOGLE (vetem llogari pa password) ================= -->
        <?php if ($is_google_only): ?>
            <form method="POST" action="google-callback.php" class="settings-card" id="reauth" novalidate>
                <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reauth">

                <div class="settings-head">
                    <h2>Verifiko identitetin</h2>
                    <?php if ($reauth_ok): ?>
                        <p>Identiteti yt eshte verifikuar. Ke disa minuta per te vendosur password ose per te fshire llogarine.</p>
                    <?php else: ?>
                        <p>Per te vendosur password ose per te fshire llogarine, fillimisht verifiko me Google (vlen 5 minuta).</p>
                    <?php endif; ?>
                </div>

                <?php if (!$reauth_ok): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn-outline">Verifiko me Google</button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>

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
                    <input type="password" name="password" id="password" maxlength="72"
                           placeholder="••••••••" autocomplete="new-password" required>
                    <div class="strength"><span id="strengthBar"></span></div>
                    <small class="hint" id="pwHint">Min 8 karaktere, shkronje dhe numer.</small>
                </div>
                <div>
                    <label for="cpassword">Konfirmo password-in</label>
                    <input type="password" name="cpassword" id="cpassword" maxlength="72"
                           placeholder="••••••••" autocomplete="new-password" required>
                    <small class="hint" id="matchHint"></small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn"><?= $is_google_only ? 'Vendos password' : 'Ndrysho password' ?></button>
            </div>
        </form>

        <!-- ================= SESIONET ================= -->
        <form method="POST" class="settings-card" id="sessions" novalidate>
            <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="logout_others">

            <div class="settings-head">
                <h2>Sesionet</h2>
                <p>Je kyqur ne nje pajisje tjeter qe nuk e njeh, ose harrove te dilje diku? Mbyll sesionet ne te gjitha pajisjet e tjera. Kjo pajisje mbetet e kyqur. Ndryshimi i passwordit i mbyll sesionet e tjera automatikisht.</p>
            </div>

            <?php if (!empty($errors['sessions'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['sessions'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn-outline">Mbyll sesionet e tjera</button>
            </div>
        </form>

        <!-- ================= KYQJA ME GOOGLE ================= -->
        <section class="settings-card" id="google">
            <div class="settings-head">
                <h2>Kyqja me Google</h2>
                <p>Lidh Google per t'u kyqur me nje klik, ose vazhdo me email dhe password. Te dyja menyrat hyjne ne te njejten llogari.</p>
            </div>

            <?php if (!empty($errors['google'])): ?>
                <div class="alert error">
                    <?php foreach ($errors['google'] as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="connect-row">
                <div class="connect-info">
                    <span class="connect-logo" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 48 48">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                        </svg>
                    </span>
                    <div>
                        <div class="connect-name">Google</div>
                        <div class="connect-status <?= $has_google ? 'on' : '' ?>"><?= $has_google ? 'I lidhur' : 'I palidhur' ?></div>
                    </div>
                </div>

            </div>

            <?php if (!$has_google): ?>
                <p class="connect-note">Kur te lidhesh Google, emaili i llogarise (<strong><?= e($user['email']) ?></strong>) zevendesohet me emailin e verifikuar te Google dhe emaili i vjeter njoftohet (me mundesi per ta anuluar ndryshimin).</p>

                <form method="POST" action="google-callback.php" class="connect-unlink" novalidate>
                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="link">

                    <label for="link_password">Per ta lidhur, konfirmo me password-in tend</label>
                    <input type="password" id="link_password" name="link_password"
                           placeholder="••••••••" autocomplete="current-password" required>

                    <div class="form-actions">
                        <button type="submit" class="btn-google">Lidh Google</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($has_google && $is_google_only): ?>
                <p class="connect-note">Google eshte tani menyra e vetme per t'u kyqur. Vendos nje password me siper nese deshiron ta shkeputesh.</p>

            <?php elseif ($has_google): ?>
                <form method="POST" class="connect-unlink" novalidate>
                    <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="unlink">

                    <label for="unlink_password">Per ta shkeputur, konfirmo me password-in tend</label>
                    <input type="password" id="unlink_password" name="unlink_password"
                           placeholder="••••••••" autocomplete="current-password" required>

                    <div class="form-actions">
                        <button type="submit" class="btn-outline">Shkput Google</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <!-- ================= FSHI LLOGARINE ================= -->
        <form method="POST" class="settings-card danger" id="delete" novalidate
              data-confirm="Je i sigurt? Llogaria dhe te gjitha detyrat do te fshihen pergjithmone.">
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
<script src="assets/passkey.js"></script>
</body>
</html>