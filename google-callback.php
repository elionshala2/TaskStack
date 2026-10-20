<?php
// google-callback.php
// Ky file ben dy gjera:
//   1) pa ?code  -> e con userin te Google per autorizim
//   2) me ?code  -> merr te dhenat e userit, e kyq ose e regjistron automatikisht

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php'; // njoftimi "anulo ndryshimin" kur lidhja me Google ndryshon emailin

session_start();
validate_session($conn); // sesion i vjeter (password i ndryshuar, "mbyll sesionet e tjera") hiqet para se te lejohet lidhja

// Funksion ndihmes: ruan gabimin dhe kthen userin ne login
function google_fail(string $msg): void
{
    $dest = 'login.php';
    // nese useri eshte i kyqur dhe po lidh Google nga settings, kthehu atje
    if (!empty($_SESSION['user_id']) && (!empty($_SESSION['google_link']) || !empty($_SESSION['google_reauth']))) {
        $dest = 'settings.php';
        unset($_SESSION['google_link'], $_SESSION['google_reauth']);
    }
    $_SESSION['flash_error'] = $msg;
    redirect($dest);
    exit;
}

// Funksion ndihmes: kerkese HTTP me cURL, kthen array nga JSON ose null
function google_http(string $url, ?array $post = null, ?string $bearer = null): ?array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    if ($bearer !== null) {
        $opts[CURLOPT_HTTPHEADER] = ['Authorization: Bearer ' . $bearer];
    }
    curl_setopt_array($ch, $opts);

    $body = curl_exec($ch);
    if ($body === false) {
        error_log('Google cURL error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

// Funksion ndihmes: gjeneron username unik (3-20 karaktere, vetem [a-zA-Z0-9_])
function google_make_username(mysqli $conn, string $name, string $email): string
{
    $base = $name !== '' ? $name : (string)strstr($email, '@', true);
    $base = preg_replace('/[^a-zA-Z0-9_]/', '', $base);
    if (strlen($base) < 3) {
        $base = 'user' . $base;
    }
    $base = substr($base, 0, 14); // lemë vend per numrat ne fund

    $candidate = $base;
    for ($i = 0; $i < 20; $i++) {
        $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $candidate);
        $stmt->execute();
        $stmt->store_result();
        $taken = $stmt->num_rows > 0;
        $stmt->close();

        if (!$taken) {
            return $candidate;
        }
        $candidate = $base . random_int(100, 99999);
    }
    return 'user' . bin2hex(random_bytes(4));
}

// Nese tashme i kyqur: lejohet vetem per te LIDHUR Google ose per te VERIFIKUAR identitetin (reauth) nga settings.php
if (!empty($_SESSION['user_id'])) {
    $uid    = (int)$_SESSION['user_id'];
    $action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['action'] ?? '') : '';
    $start  = in_array($action, ['link', 'reauth'], true) ? $action : '';

    if ($start !== '') {
        // Fillimi vetem me POST + CSRF, qe nje faqe e huaj te mos mund te lidhe Google-in e dikujt tjeter me llogarine tende.
        if (!verify_csrf($_POST['csrf'] ?? '')) {
            $_SESSION['flash_error'] = 'Kerkese e pavlefshme. Provo perseri.';
            redirect('settings.php');
        }

        if ($start === 'link') {
            // Lidhja kerkon passwordin aktual (si ndryshimi i emailit dhe shkeputja e Google),
            // qe nje sesion i vjedhur te mos mund te lidhe Google-in e dikujt tjeter.
            $stmt = $conn->prepare("SELECT id, password FROM regjistrimi WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $acct = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $pw_err = null;
            if (!$acct || !check_current_password($conn, $acct, (string)($_POST['link_password'] ?? ''), $pw_err)) {
                $_SESSION['flash_error'] = $pw_err ?? 'Konfirmo me password-in tend per te lidhur Google.';
                redirect('settings.php');
            }
        }

        $_SESSION['google_link']   = $start === 'link'   ? $uid : 0;
        $_SESSION['google_reauth'] = $start === 'reauth' ? $uid : 0;
    } elseif ((int)($_SESSION['google_link'] ?? 0) !== $uid && (int)($_SESSION['google_reauth'] ?? 0) !== $uid) {
        redirect('index.php'); // i kyqur dhe jo ne link/reauth mode
        exit;
    } elseif (!isset($_GET['code']) && !isset($_GET['error'])) {
        unset($_SESSION['google_link'], $_SESSION['google_reauth']); // mode i vjeter, pastroje
        redirect('settings.php');
    }
} else {
    unset($_SESSION['google_link'], $_SESSION['google_reauth']);
}

// > 0 kur po lidhim Google me llogarine e kyqur (jo kyqje/signup)
$linking_user = !empty($_SESSION['user_id']) ? (int)($_SESSION['google_link'] ?? 0) : 0;
// > 0 kur po verifikojme identitetin e userit te kyqur me Google
$reauth_user  = !empty($_SESSION['user_id']) ? (int)($_SESSION['google_reauth'] ?? 0) : 0;

// Useri e anuloi ose Google ktheu gabim
if (isset($_GET['error'])) {
    google_fail('Kyqja me Google u anulua.');
}

/* ---------- HAPI 1: dergo userin te Google ---------- */
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_state'] = $state; // mbrojtje CSRF per OAuth

    $params = http_build_query([
        'client_id'     => $google_client_id,
        'redirect_uri'  => $google_redirect_uri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => $reauth_user > 0 ? 'login' : 'select_account', // reauth: Google kerkon kyqje te re
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

/* ---------- HAPI 2: callback nga Google ---------- */

// Verifiko state
$expected = $_SESSION['google_state'] ?? '';
unset($_SESSION['google_state']);
if ($expected === '' || !hash_equals($expected, (string)($_GET['state'] ?? ''))) {
    google_fail('Kerkese e pavlefshme. Provo perseri.');
}

// Shkembe code per access token
$token = google_http('https://oauth2.googleapis.com/token', [
    'code'          => $_GET['code'],
    'client_id'     => $google_client_id,
    'client_secret' => $google_client_secret,
    'redirect_uri'  => $google_redirect_uri,
    'grant_type'    => 'authorization_code',
]);

if (!$token || empty($token['access_token'])) {
    error_log('Google token error: ' . json_encode($token));
    google_fail('Nuk u arrit kyqja me Google. Provo perseri.');
}

// Merr te dhenat e userit
$info = google_http('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']);

$email = strtolower(trim($info['email'] ?? ''));
$name  = trim($info['name'] ?? '');
$gid   = trim($info['sub'] ?? ''); // ID unike e Google per kete user

if ($gid === '' || $email === '' || empty($info['email_verified'])) {
    google_fail('Emaili i Google nuk eshte i verifikuar.');
}

/* ---------- REAUTH: useri i kyqur verifikon identitetin me Google ---------- */
if ($reauth_user > 0) {
    unset($_SESSION['google_reauth']);

    try {
        $stmt = $conn->prepare("SELECT google_id FROM regjistrimi WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $reauth_user);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['google_id']) && hash_equals((string)$row['google_id'], $gid)) {
            $_SESSION['reauth_until']  = time() + 300; // 5 minuta
            $_SESSION['flash_success'] = 'Identiteti u verifikua. Ke 5 minuta per te vendosur password ose per te fshire llogarine.';
        } else {
            $_SESSION['flash_error'] = 'Ky nuk eshte Google-i i lidhur me kete llogari.';
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Google reauth error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Diqka shkoi gabim. Provo perseri.';
    }

    redirect('settings.php');
    exit;
}

/* ---------- LIDHJE: useri i kyqur po lidh Google nga settings ---------- */
if ($linking_user > 0) {
    unset($_SESSION['google_link']);

    try {
        // Llogaria duhet te kete emailin e verifikuar para se te lidhet Google
        $stmt = $conn->prepare("SELECT email_verified, email, username FROM regjistrimi WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $linking_user);
        $stmt->execute();
        $me = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$me || !(int)$me['email_verified']) {
            $_SESSION['flash_error'] = 'Verifiko emailin e llogarise para se te lidhesh Google.';
            redirect('settings.php');
            exit;
        }

        // A eshte ky Google ID i lidhur me nje llogari?
        $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE google_id = ? LIMIT 1");
        $stmt->bind_param("s", $gid);
        $stmt->execute();
        $other = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($other && (int)$other['id'] !== $linking_user) {
            $_SESSION['flash_error'] = 'Ky Google eshte i lidhur me nje llogari tjeter TaskStack.';
        } elseif ($other) {
            $_SESSION['flash_success'] = 'Google eshte tashme i lidhur me llogarine tende.';
        } else {
            // Emaili i llogarise behet emaili i verifikuar i Google (nuk mbetet nje email i rremë).
            // Nese ky email i perket nje llogarie tjeter TaskStack, nuk lidhet.
            $stmt = $conn->prepare("SELECT id FROM regjistrimi WHERE email = ? AND id <> ? LIMIT 1");
            $stmt->bind_param("si", $email, $linking_user);
            $stmt->execute();
            $emailTaken = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($emailTaken) {
                $_SESSION['flash_error'] = 'Emaili i ketij Google (' . $email . ') perdoret nga nje llogari tjeter TaskStack.';
            } else {
                // lidh vetem nese llogaria nuk ka tashme nje Google te lidhur
                $stmt = $conn->prepare(
                    "UPDATE regjistrimi SET google_id = ?, email = ?
                     WHERE id = ? AND (google_id IS NULL OR google_id = '')"
                );
                $stmt->bind_param("ssi", $gid, $email, $linking_user);
                $stmt->execute();
                $linked = $stmt->affected_rows > 0;
                $stmt->close();

                if ($linked) {
                    // emaili i vjeter njoftohet (me link per ta anuluar ndryshimin) nese emaili ndryshoi
                    if (strcasecmp((string)$me['email'], $email) !== 0) {
                        send_email_changed_notice($conn, $linking_user, (string)$me['email'], $email, (string)$me['username']);
                    }
                    $_SESSION['flash_success'] = 'Google u lidh me sukses! Emaili i llogarise tani eshte ' . $email . '.';
                } else {
                    $_SESSION['flash_error'] = 'Llogaria jote ka tashme nje Google te lidhur.';
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log('Google link error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Diqka shkoi gabim. Provo perseri.';
    }

    redirect('settings.php');
    exit;
}

try {
    // 1) Kerko me google_id (kyqje e njohur me Google)
    $stmt = $conn->prepare("SELECT id, username FROM regjistrimi WHERE google_id = ? LIMIT 1");
    $stmt->bind_param("s", $gid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2) Nese nuk u gjet, kerko me email (llogari ekzistuese 'local')
    if (!$user) {
        $stmt = $conn->prepare("SELECT id, username, google_id, email_verified FROM regjistrimi WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $byEmail = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($byEmail) {
            // Emaili i perket nje Google ID tjeter -> mos e lidh
            if (!empty($byEmail['google_id']) && $byEmail['google_id'] !== $gid) {
                google_fail('Ky email eshte i lidhur me nje llogari tjeter Google.');
            }

            if (!(int)$byEmail['email_verified'] && empty($byEmail['google_id'])) {
                // Llogari lokale e paverifikuar me kete email: Google sapo vertetoi qe emaili eshte i ketij useri,
                // ndaj llogaria i kalon atij dhe passwordi i vendosur nga dikush tjeter (ai qe e regjistroi pa e zoteruar
                // emailin) behet i pavlefshem; sesionet dhe linqet e verifikimit te vjetra mbyllen.
                $void_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "UPDATE regjistrimi SET password = ?, provider = 'google', google_id = ?, email_verified = 1,
                            session_version = session_version + 1
                     WHERE id = ?"
                );
                $stmt->bind_param("ssi", $void_hash, $gid, $byEmail['id']);
                $stmt->execute();
                $stmt->close();
                invalidate_user_tokens($conn, (int)$byEmail['id'], 'verify');
            } else {
                // Lidh Google ID me llogarine ekzistuese (e verifikuar)
                $stmt = $conn->prepare("UPDATE regjistrimi SET google_id = ? WHERE id = ?");
                $stmt->bind_param("si", $gid, $byEmail['id']);
                $stmt->execute();
                $stmt->close();
            }

            $user = ['id' => $byEmail['id'], 'username' => $byEmail['username']];
        }
    }

    // 3) Nuk ekziston fare -> signup automatik me Google
    if (!$user) {
        $username = google_make_username($conn, $name, $email);

        // Password i rastesishem (useri nuk e di), qe kyqja me password te mos funksionoje
        $hash     = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $provider = 'google';

        $stmt = $conn->prepare(
            "INSERT INTO regjistrimi (username, email, password, provider, google_id, email_verified) VALUES (?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param("sssss", $username, $email, $hash, $provider, $gid);
        $stmt->execute();
        $stmt->close();

        $new_id = $conn->insert_id;
        ensure_default_category($conn, $new_id);

        $user = ['id' => $new_id, 'username' => $username];
    }

    // Kyq userin (rigjeneron session ID + ruan versionin e sesionit)
    login_user($conn, (int)$user['id'], $user['username']);

    redirect('index.php');
    exit;
} catch (mysqli_sql_exception $e) {
    error_log('Google login error: ' . $e->getMessage());
    google_fail('Diqka shkoi gabim. Provo perseri.');
}
