<?php
// google-callback.php
// Ky file ben dy gjera:
//   1) pa ?code  -> e con userin te Google per autorizim
//   2) me ?code  -> merr te dhenat e userit, e kyq ose e regjistron automatikisht

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_start();

// Funksion ndihmes: ruan gabimin dhe kthen userin ne login
function google_fail(string $msg): void
{
    $_SESSION['flash_error'] = $msg;
    redirect('login.php');
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

// Nese tashme i kyqur
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
    exit;
}

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
        'prompt'        => 'select_account',
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

try {
    // 1) Kerko me google_id (kyqje e njohur me Google)
    $stmt = $conn->prepare("SELECT id, username FROM regjistrimi WHERE google_id = ? LIMIT 1");
    $stmt->bind_param("s", $gid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2) Nese nuk u gjet, kerko me email (llogari ekzistuese 'local')
    if (!$user) {
        $stmt = $conn->prepare("SELECT id, username, google_id FROM regjistrimi WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $byEmail = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($byEmail) {
            // Emaili i perket nje Google ID tjeter -> mos e lidh
            if (!empty($byEmail['google_id']) && $byEmail['google_id'] !== $gid) {
                google_fail('Ky email eshte i lidhur me nje llogari tjeter Google.');
            }

            // Lidh Google ID me llogarine ekzistuese
            $stmt = $conn->prepare("UPDATE regjistrimi SET google_id = ? WHERE id = ?");
            $stmt->bind_param("si", $gid, $byEmail['id']);
            $stmt->execute();
            $stmt->close();

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
            "INSERT INTO regjistrimi (username, email, password, provider, google_id) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssss", $username, $email, $hash, $provider, $gid);
        $stmt->execute();
        $stmt->close();

        $new_id = $conn->insert_id;
        ensure_default_category($conn, $new_id);

        $user = ['id' => $new_id, 'username' => $username];
    }

    // Kyq userin
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];

    redirect('dashboard.php');
    exit;

} catch (mysqli_sql_exception $e) {
    error_log('Google login error: ' . $e->getMessage());
    google_fail('Diqka shkoi gabim. Provo perseri.');
}