<?php
// passkey_api.php - endpoint JSON per passkeys (regjistrim + login)
// Veprimet: register_options, register_verify (te kyqur) | login_options, login_verify (anonim)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/passkeys.php';

session_start();
// JSON nuk duhet te kaloje nga filtri i perkthimit te HTML-se (prefs_html_filter) - mund ta prishte base64
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pk_out(array $data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function pk_fail(string $msg, int $code = 400)
{
    pk_out(['ok' => false, 'error' => $msg], $code);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pk_fail('Method not allowed', 405);
}
if (!verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    pk_fail(t('login.invalid_req'), 403);
}
$raw = file_get_contents('php://input', false, null, 0, 65536);
$in  = json_decode((string)$raw, true);
if (!is_array($in)) {
    pk_fail(t('login.invalid_req'));
}
$action = (string)($in['action'] ?? '');

// ---------- veprimet per user te kyqur ----------
if (in_array($action, ['register_options', 'register_verify'], true)) {
    require_login();
    $uid = (int)$_SESSION['user_id'];

    try {
        $stmt = $conn->prepare("SELECT id, username, email, password, provider FROM regjistrimi WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) {
            pk_fail(t('login.invalid_req'), 401);
        }

        if ($action === 'register_options') {
            // Siguri: si te veprimet e tjera te ndjeshme, kerkohet password (ose verifikim i fresket me Google)
            if (($user['provider'] ?? 'local') === 'google') {
                $reauth = !empty($_SESSION['reauth_until']) && (int)$_SESSION['reauth_until'] >= time();
                if (!$reauth) {
                    pk_fail(t('pk.need_reauth'), 403);
                }
            } else {
                $err = null;
                if (!check_current_password($conn, $user, (string)($in['password'] ?? ''), $err)) {
                    pk_fail((string)$err, 403);
                }
            }

            $existing = passkeys_for_user($conn, $uid);
            if ($existing === null) {
                pk_fail(t('pk.unavailable'), 503);
            }
            if (count($existing) >= PASSKEY_MAX_PER_USER) {
                pk_fail(t('pk.limit'));
            }
            $exclude = [];
            foreach ($existing as $p) {
                $bin = pk_b64u_decode($p['credential_id']);
                if ($bin !== null) {
                    $exclude[] = $bin;
                }
            }

            $wa   = passkey_webauthn();
            $args = $wa->getCreateArgs(
                passkey_user_handle($uid),
                $user['email'],
                $user['username'],
                60,
                'required',
                'required',
                null,
                $exclude
            );
            $_SESSION['pk_reg'] = [
                'challenge' => $wa->getChallenge()->getBinaryString(),
                'uid'       => $uid,
                'name'      => passkey_clean_name((string)($in['name'] ?? ''), t('pk.default_name')),
                'at'        => time(),
            ];
            pk_out(['ok' => true, 'options' => $args]);
        }

        // register_verify
        $st = $_SESSION['pk_reg'] ?? null;
        unset($_SESSION['pk_reg']); // sfida perdoret vetem nje here
        if (!is_array($st) || (int)$st['uid'] !== $uid || (time() - (int)$st['at']) > PASSKEY_CEREMONY_TTL) {
            pk_fail(t('pk.expired'));
        }
        $cdj = pk_b64u_decode($in['clientDataJSON'] ?? null);
        $att = pk_b64u_decode($in['attestationObject'] ?? null);
        if ($cdj === null || $att === null) {
            pk_fail(t('login.invalid_req'));
        }

        try {
            $data = passkey_webauthn()->processCreate($cdj, $att, $st['challenge'], true, true, false);
        } catch (Throwable $e) {
            error_log('Passkey register error: ' . $e->getMessage());
            pk_fail(t('pk.failed'));
        }

        $credBin = (string)$data->credentialId;
        $credId = pk_b64u_encode($credBin);
        $hash   = hash('sha256', $credBin);
        $pem    = (string)$data->credentialPublicKey;
        $count  = (int)($data->signatureCounter ?? 0);
        $name   = (string)$st['name'];
        $tr     = [];
        foreach ((array)($in['transports'] ?? []) as $x) {
            if (is_string($x) && in_array($x, PASSKEY_KNOWN_TRANSPORTS, true)) {
                $tr[] = $x;
            }
        }
        $transports = $tr ? implode(',', $tr) : null;

        try {
            $stmt = $conn->prepare(
                "INSERT INTO passkeys (user_id, credential_id, credential_hash, public_key, sign_count, name, transports)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isssiss", $uid, $credId, $hash, $pem, $count, $name, $transports);
            $stmt->execute();
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                pk_fail(t('pk.duplicate'));
            }
            throw $e;
        }

        unset($_SESSION['reauth_until']); // verifikimi me Google vlen vetem per nje veprim
        $_SESSION['flash_success'] = t('pk.added');

        // njoftim sigurie (gabimi i emailit nuk e prish regjistrimin)
        send_mail(
            $user['email'],
            t('pk.mail_subject'),
            email_template(t('pk.mail_subject'), t('pk.mail_body', ['name' => $name]), t('pk.mail_btn'), app_url() . '/settings.php#passkeys', t('pk.mail_note')),
            t('pk.mail_body', ['name' => $name]) . "\n" . app_url() . '/settings.php#passkeys'
        );
        pk_out(['ok' => true]);
    } catch (mysqli_sql_exception $e) {
        error_log('Passkey API error: ' . $e->getMessage());
        pk_fail(t('pk.unavailable'), 500);
    }
}

// ---------- veprimet anonime: login me passkey ----------
if ($action === 'login_options') {
    $wa   = passkey_webauthn();
    // pa allowCredentials: browseri tregon passkey-t e ruajtura per kete faqe (login pa shkruar email)
    $args = $wa->getGetArgs([], 60, true, true, true, true, true, 'required');
    $_SESSION['pk_login'] = ['challenge' => $wa->getChallenge()->getBinaryString(), 'at' => time()];
    pk_out(['ok' => true, 'options' => $args]);
}

if ($action === 'login_verify') {
    $ip = client_ip();
    if (login_attempts_count($conn, $ip) >= MAX_LOGIN_ATTEMPTS) {
        pk_fail(t('pk.too_many'), 429);
    }
    $st = $_SESSION['pk_login'] ?? null;
    unset($_SESSION['pk_login']);

    $generic = function () use ($conn, $ip) {
        record_login_attempt($conn, $ip);
        pk_fail(t('pk.login_failed'), 401);
    };

    if (!is_array($st) || (time() - (int)$st['at']) > PASSKEY_CEREMONY_TTL) {
        pk_fail(t('pk.expired'));
    }
    $id   = pk_b64u_decode($in['id'] ?? null);
    $cdj  = pk_b64u_decode($in['clientDataJSON'] ?? null);
    $auth = pk_b64u_decode($in['authenticatorData'] ?? null);
    $sig  = pk_b64u_decode($in['signature'] ?? null);
    if ($id === null || $cdj === null || $auth === null || $sig === null) {
        $generic();
    }

    try {
        $stmt = $conn->prepare(
            "SELECT p.id AS pid, p.public_key, p.sign_count, u.id AS uid, u.username, u.email_verified, u.google_id
             FROM passkeys p JOIN regjistrimi u ON u.id = p.user_id
             WHERE p.credential_hash = ? LIMIT 1"
        );
        $hash = hash('sha256', $id);
        $stmt->bind_param("s", $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            $generic();
        }

        // userHandle (nese e dergon authenticator-i) duhet te perputhet me pronarin e passkey-t
        if (!empty($in['userHandle'])) {
            $uh = pk_b64u_decode($in['userHandle']);
            if ($uh === null || !hash_equals(passkey_user_handle((int)$row['uid']), $uh)) {
                $generic();
            }
        }

        $wa = passkey_webauthn();
        try {
            $wa->processGet($cdj, $auth, $sig, $row['public_key'], $st['challenge'], (int)$row['sign_count'], true, true);
        } catch (Throwable $e) {
            error_log('Passkey login error: ' . $e->getMessage());
            $generic();
        }

        // si te login me password: email i paverifikuar (pa Google) nuk hyn
        if (!(int)$row['email_verified'] && empty($row['google_id'])) {
            pk_fail(t('pk.verify_email'), 403);
        }

        $newCount = (int)($wa->getSignatureCounter() ?? 0);
        $stmt = $conn->prepare("UPDATE passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $newCount, $row['pid']);
        $stmt->execute();
        $stmt->close();

        unset($_SESSION['2fa_pending']);
        login_user($conn, (int)$row['uid'], $row['username']); // passkey me UV = 2 faktore, pa kod nga emaili
        pk_out(['ok' => true, 'redirect' => 'index.php']);
    } catch (mysqli_sql_exception $e) {
        error_log('Passkey login DB error: ' . $e->getMessage());
        pk_fail(t('pk.unavailable'), 500);
    }
}

pk_fail('Unknown action', 400);
