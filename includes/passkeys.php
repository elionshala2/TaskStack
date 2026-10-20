<?php
// includes/passkeys.php
// Passkeys (WebAuthn) - helperat e perbashketa per passkey_api.php dhe settings.php.
// Biblioteka: lbuchs/WebAuthn v2.2.0 (MIT), e ngjitur ne includes/lib/WebAuthn (nuk kerkon composer).
// Kerkon: PHP 8.0+, extension openssl dhe mbstring.

require_once __DIR__ . '/functions.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'lbuchs\\WebAuthn\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/lib/WebAuthn/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use lbuchs\WebAuthn\WebAuthn;

const PASSKEY_MAX_PER_USER   = 10;
const PASSKEY_CEREMONY_TTL   = 300; // sekonda: sa kohe vlen sfida (challenge) e ruajtur ne sesion
const PASSKEY_KNOWN_TRANSPORTS = ['usb', 'nfc', 'ble', 'hybrid', 'internal', 'smart-card'];

// Domeni (rpId) merret nga APP_URL ne .env, jo nga Host header. Passkey punon vetem per kete domen.
function passkey_rp_id(): string
{
    $host = parse_url((string)($_ENV['APP_URL'] ?? ''), PHP_URL_HOST);
    return is_string($host) && $host !== '' ? strtolower($host) : 'localhost';
}

// Vetem attestation "none": nuk na duhen certifikata te prodhuesit, vetem celesi publik.
function passkey_webauthn(): WebAuthn
{
    return new WebAuthn('TaskStack', passkey_rp_id(), ['none'], true);
}

function pk_b64u_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

// Kthen stringun binar, ose null nese hyrja nuk eshte base64url e vlefshme
function pk_b64u_decode($s): ?string
{
    if (!is_string($s) || $s === '' || strlen($s) > 4096 || !preg_match('/^[A-Za-z0-9_-]+$/', $s)) {
        return null;
    }
    $bin = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    return $bin === false ? null : $bin;
}

// ID e perdoruesit per authenticator-in: opake, pa email/username brenda (nuk eshte PII)
function passkey_user_handle(int $user_id): string
{
    return hash('sha256', 'taskstack-passkey-user:' . $user_id, true);
}

// Lista e passkey-ve te nje useri (pa public_key). Kthen null nese tabela nuk ekziston ende (migrimi s'eshte bere).
function passkeys_for_user(mysqli $conn, int $user_id): ?array
{
    try {
        $stmt = $conn->prepare(
            "SELECT id, credential_id, name, created_at, last_used_at
             FROM passkeys WHERE user_id = ? ORDER BY created_at ASC"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    } catch (mysqli_sql_exception $e) {
        error_log('Passkeys list error: ' . $e->getMessage());
        return null;
    }
}

// Emri qe useri i jep passkey-t: pa karaktere kontrolli, max 60 karaktere
function passkey_clean_name(string $name, string $fallback): string
{
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '');
    $name = mb_substr($name, 0, 60);
    return $name !== '' ? $name : $fallback;
}
