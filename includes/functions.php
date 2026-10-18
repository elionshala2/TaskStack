<?php
// includes/functions.php

// Escape Output -- mbrojtja nga sulmet XSS
function e($str) { // teksti nga useri shikohet nga htmlspecialchars per XSS
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF tokeni -- mbrojtja nga sulmet CSRF - Cross Site Request Forgery
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf($token) { // verifikimi i tokenit te CSRF me lart ^^^
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ?? '');
}

// redirect userin ne URL te caktuar edhe e bon scriptin exit
function redirect($url) {
    header("Location: $url");
    exit;
}

// Kontrollon nese useri osht i kyqur, nese jo e perdor funksionin redirect per me kthy ne login
function require_login() {
    global $conn;
    validate_session($conn); // sesion i vjeter (pas ndryshimit te passwordit / "mbyll sesionet e tjera") hiqet ketu
    if (empty($_SESSION['user_id'])) {
        $prefix = str_contains($_SERVER['SCRIPT_NAME'], '/tasks/') ? '../' : '';
        redirect($prefix . 'login.php');
    }
}

// =================================================================
// Sesionet: cdo user ka nje "session_version" ne databaze. Cdo sesion ruan kopjen e vet ($_SESSION['sv']).
// Kur versioni rritet (ndryshim/reset password, "Mbyll sesionet e tjera"), te gjitha sesionet me version
// te vjeter pushojne se vlejne dhe useri duhet te kyqet perseri.
// =================================================================

// Merr versionin aktual te sesionit per nje user (null nese useri nuk ekziston me)
function get_session_version($conn, int $user_id): ?int {
    $stmt = $conn->prepare("SELECT session_version FROM regjistrimi WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['session_version'] : null;
}

// Rrit versionin => te gjitha sesionet ekzistuese te userit behen te pavlefshme. Kthen versionin e ri.
// Sesioni aktual duhet ta ruaje versionin e ri vete: $_SESSION['sv'] = bump_session_version(...)
function bump_session_version($conn, int $user_id): int {
    $stmt = $conn->prepare("UPDATE regjistrimi SET session_version = session_version + 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    return (int)get_session_version($conn, $user_id);
}

// Kyq userin: rigjeneron session ID (mbrojtje session fixation), rrotullon tokenin CSRF
// dhe ruan versionin aktual te sesionit.
function login_user($conn, int $user_id, string $username): void {
    $sv = get_session_version($conn, $user_id) ?? 1;
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['user_id']  = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['sv']       = $sv;
    // ruan gjuhen e zgjedhur (per emailet e kujtesave); nuk e prish login-in nese kolona mungon
    try {
        $lang = pref_lang();
        $stmt = $conn->prepare("UPDATE regjistrimi SET language = ? WHERE id = ?");
        $stmt->bind_param("si", $lang, $user_id);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Save language error: ' . $e->getMessage());
    }
    unset($_SESSION['reauth_until'], $_SESSION['google_reauth'], $_SESSION['google_link']);
}

// Kontrollon qe sesioni i kyqur eshte ende i vlefshem: useri ekziston dhe versioni perputhet.
// Nese jo, e c'kyq sesionin (pa e prekur session ID, qe te mund te thirret edhe pasi ka filluar output-i).
function validate_session($conn): void {
    static $done = false;
    if ($done || empty($_SESSION['user_id'])) {
        return;
    }
    $done  = true;
    $valid = false;

    try {
        $current = get_session_version($conn, (int)$_SESSION['user_id']);
        $valid   = $current !== null
            && isset($_SESSION['sv'])
            && (int)$_SESSION['sv'] === $current;
    } catch (mysqli_sql_exception $e) {
        error_log("Session validate error: " . $e->getMessage());
        $valid = false; // ne dyshim, mos e lejo sesionin
    }

    if (!$valid) {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['sv'], $_SESSION['google_link'], $_SESSION['google_reauth'], $_SESSION['reauth_until']);
        $_SESSION['flash_error'] = 'Sesioni yt perfundoi. Kyqu perseri.';
    }
}

// Siguron qe useri te ka kategorine default "Inbox"
function ensure_default_category($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM categories WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    // Nese nuk ka kategori, krijo Inbox default
    if ($row['count'] == 0) {
        $stmt = $conn->prepare(
            "INSERT INTO categories (user_id, name, color, is_default) VALUES (?, 'Inbox', '#3b82f6', 1)"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Kontrollon qe ngjyra eshte hex e vlefshme (#rrggbb)
function safe_color($c, $fallback = '#3b82f6') {
    return (is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c)) ? $c : $fallback;
}

// Ngjyrat qe mund te zgjedhe useri per kategorite
function category_colors() {
    return ['#3b82f6', '#7c3aed', '#06b6d4', '#22c55e', '#f59e0b', '#ef4444', '#ec4899', '#64748b'];
}

// =================================================================
// Rate limiting per login (max 5 tentativa per IP)
// Nese tabela login_attempts nuk ekziston, login-i vazhdon normalisht, nuk e krijon per arsyje sigurie
// =================================================================

define('MAX_LOGIN_ATTEMPTS', 5); // maksimum 5 tentative per IP, mund te ndryshohet
define('MAX_ACCOUNT_ATTEMPTS', 10); // maksimum 10 tentative te gabuara per llogari (nga cdo IP), qe sulmet e shperndara te mos kalojne
define('LOGIN_WINDOW_MINUTES', 15); // 15 minuta nuk mund te tentohet per IP

// Hash i rremë (bcrypt) qe perdoret kur emaili nuk ekziston, qe koha e pergjigjes te jete e njejte (nuk zbulohet cilat emaile ekzistojne)
define('LOGIN_DUMMY_HASH', '$2y$10$.vGA1O9wmRjrwAVXD98HNOgsNpDczlqm3Jq7KnEd1rVAGv3Fykk1a');

// A eshte IP-ja ne liste (IP te vecanta ose CIDR, te ndara me presje)?
function ip_in_list(string $ip, string $list): bool {
    $ipBin = @inet_pton($ip);
    if ($ipBin === false) {
        return false;
    }
    foreach (explode(',', $list) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }
        $parts  = explode('/', $entry, 2);
        $netBin = @inet_pton($parts[0]);
        if ($netBin === false || strlen($netBin) !== strlen($ipBin)) {
            continue;
        }
        $bits  = isset($parts[1]) ? (int)$parts[1] : strlen($netBin) * 8;
        $bytes = intdiv($bits, 8);
        $rem   = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            continue;
        }
        if ($rem > 0) {
            $mask = (0xFF << (8 - $rem)) & 0xFF;
            if ((ord($ipBin[$bytes]) & $mask) !== (ord($netBin[$bytes]) & $mask)) {
                continue;
            }
        }
        return true;
    }
    return false;
}

// IP e klientit. X-Forwarded-For merret parasysh VETEM nese kerkesa vjen nga nje proxy i listuar ne
// TRUSTED_PROXIES (.env); perndryshe kushdo mund ta falsifikoje. Pa proxy, perdoret REMOTE_ADDR.
function client_ip() {
    $remote  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = trim((string)($_ENV['TRUSTED_PROXIES'] ?? ''));
    $xff     = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

    if ($trusted === '' || $xff === '' || !ip_in_list($remote, $trusted)) {
        return $remote;
    }

    // nga e djathta: IP e pare qe nuk eshte proxy i besuar eshte klienti
    foreach (array_reverse(array_map('trim', explode(',', $xff))) as $candidate) {
        if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $remote;
        }
        if (!ip_in_list($candidate, $trusted)) {
            return $candidate;
        }
    }
    return $remote;
}

// Celesat e rate limiting per llogari (ruhen ne kolonen "ip", max 45 karaktere)
function account_key(string $email): string {
    return 'a:' . substr(hash('sha256', mb_strtolower(trim($email))), 0, 40);
}
function user_key(int $user_id): string {
    return 'u:' . $user_id;
}

function login_attempts_count($conn, $ip) { // kontrollon sa tentativa ka pasur IPja e dhene, nese ka me shume se 5 kthen 0, nese jo kthen numrin e tentativave
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM login_attempts
             WHERE ip = ? AND attempted_at > (NOW() - INTERVAL " . (int)LOGIN_WINDOW_MINUTES . " MINUTE)"
        );
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['c'] ?? 0);
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit count error: " . $e->getMessage());
        return 0;
    }
}

function record_login_attempt($conn, $ip) { // regjistron nje tentative per IP (ose celes llogarie) e dhene
    try {
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
        maybe_cleanup($conn);
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit insert error: " . $e->getMessage());
    }
}

function clear_login_attempts($conn, $ip) { // nese useri kyqet me sukses i fshin tentativat e IPse nga databaza
    try {
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Rate limit clear error: " . $e->getMessage());
    }
}

// Pastron te dhenat e vjetra (here pas here, ~1 nga 50 kerkesat qe shkruajne ne tabelat e rate limiting):
// tentativat/kerkesat e vjetra, tokenat e skaduar dhe llogarite e paverifikuara (>3 dite), qe emailet te mos zihen pafund
function maybe_cleanup($conn): void {
    if (random_int(1, 50) !== 1) {
        return;
    }
    try {
        $conn->query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
        $conn->query("DELETE FROM mail_requests WHERE requested_at < (NOW() - INTERVAL 1 DAY)");
        $conn->query("DELETE FROM email_tokens WHERE expires_at < (NOW() - INTERVAL 7 DAY) OR used_at < (NOW() - INTERVAL 7 DAY)");
        $conn->query("DELETE FROM regjistrimi
                      WHERE email_verified = 0 AND (google_id IS NULL OR google_id = '')
                        AND created_at < (NOW() - INTERVAL 3 DAY)");
    } catch (mysqli_sql_exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
    }
    try {
        $conn->query("DELETE FROM two_factor_codes WHERE expires_at < (NOW() - INTERVAL 1 DAY)");
    } catch (mysqli_sql_exception $e) {
        error_log("Cleanup 2FA error: " . $e->getMessage());
    }
}

// =================================================================
// Passwordi: rregullat (te njejta kudo) dhe verifikimi i passwordit aktual
// =================================================================

define('PASSWORD_MAX_LENGTH', 72); // bcrypt injoron cdo gje mbi 72 bajt

function password_errors(string $pw, string $cpw): array {
    $errs = [];
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        $errs[] = 'Password: min 8 karaktere, te pakten 1 shkronje dhe 1 numer.';
    }
    if (strlen($pw) > PASSWORD_MAX_LENGTH) {
        $errs[] = 'Password: maksimumi ' . PASSWORD_MAX_LENGTH . ' karaktere.';
    }
    if ($pw !== $cpw) {
        $errs[] = 'Password-et nuk perputhen.';
    }
    return $errs;
}

// Verifikon passwordin aktual te nje useri ($user duhet te ka 'id' dhe 'password'), me rate limiting
// per IP dhe per llogari (perdoret nga settings.php dhe google-callback.php)
function check_current_password(mysqli $conn, array $user, string $given, ?string &$err): bool {
    $ip   = client_ip();
    $ukey = user_key((int)$user['id']);

    if (login_attempts_count($conn, $ip) >= MAX_LOGIN_ATTEMPTS
        || login_attempts_count($conn, $ukey) >= MAX_ACCOUNT_ATTEMPTS) {
        $err = 'Shume tentativa te pasuksesshme. Provo perseri pas ' . LOGIN_WINDOW_MINUTES . ' minutash.';
        return false;
    }
    if ($given === '' || strlen($given) > 1024 || !password_verify($given, $user['password'])) {
        record_login_attempt($conn, $ip);
        record_login_attempt($conn, $ukey);
        $err = 'Password-i aktual eshte i gabuar.';
        return false;
    }
    // IP nuk pastrohet me sukses (qe kush ka llogari te vete te mos e rivendose limitin duke u kyqur ne te)
    clear_login_attempts($conn, $ukey);
    return true;
}
