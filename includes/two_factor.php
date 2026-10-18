<?php
// includes/two_factor.php - 2FA me kod 6-shifror ne email

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/prefs.php';

const TWO_FA_TTL_MINUTES = 10;   // kodi vlen 10 minuta
const TWO_FA_MAX_ATTEMPTS = 5;   // max tentativa per kod
const TWO_FA_COOLDOWN_SECONDS = 60;
const TWO_FA_PENDING_MINUTES = 10; // sa zgjat hapi "pres kodin" pas passwordit

function two_fa_hash(int $user_id, string $code): string {
    return hash('sha256', $user_id . ':' . $code);
}

// Sa sekonda kane kaluar nga kodi i fundit (null nese nuk ka)
function two_fa_seconds_since_last($conn, int $user_id): ?int {
    $stmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS s FROM two_factor_codes WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['s'] !== null) ? (int)$row['s'] : null;
}

// Krijon kodin e ri (fshin te vjetrit) dhe e dergon ne email. Kthen true nese emaili u dergua.
function two_fa_send_code($conn, int $user_id, string $email, bool $ignore_cooldown = false): bool|string {
    if (!$ignore_cooldown) {
        $since = two_fa_seconds_since_last($conn, $user_id);
        if ($since !== null && $since < TWO_FA_COOLDOWN_SECONDS) {
            return 'wait';
        }
    }

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = two_fa_hash($user_id, $code);

    $stmt = $conn->prepare("DELETE FROM two_factor_codes WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $ttl  = (int)TWO_FA_TTL_MINUTES;
    $stmt = $conn->prepare("INSERT INTO two_factor_codes (user_id, code_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL $ttl MINUTE)");
    $stmt->bind_param("is", $user_id, $hash);
    $stmt->execute();
    $stmt->close();

    $subject = t('2fa.mail_subject');
    $intro   = t('2fa.mail_body');
    $note    = t('2fa.mail_note');
    $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px">'
          . '<h2 style="margin:0 0 12px">TaskStack</h2>'
          . '<p>' . e($intro) . '</p>'
          . '<p style="font-size:32px;font-weight:700;letter-spacing:8px;margin:16px 0">' . e($code) . '</p>'
          . '<p style="color:#666;font-size:13px">' . e($note) . '</p></div>';
    $text = $intro . "\n\n" . $code . "\n\n" . $note;

    return send_mail($email, $subject, $html, $text);
}

// Kontrollon kodin. Kthen 'ok' | 'bad' | 'locked'
function two_fa_check_code($conn, int $user_id, string $input): string {
    $input = preg_replace('/\s+/', '', $input);

    $stmt = $conn->prepare("SELECT id, code_hash, attempts, expires_at > NOW() AS valid FROM two_factor_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !(int)$row['valid']) {
        return 'bad';
    }
    if ((int)$row['attempts'] >= TWO_FA_MAX_ATTEMPTS) {
        return 'locked';
    }

    if (preg_match('/^\d{6}$/', $input) && hash_equals($row['code_hash'], two_fa_hash($user_id, $input))) {
        $stmt = $conn->prepare("DELETE FROM two_factor_codes WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        return 'ok';
    }

    $stmt = $conn->prepare("UPDATE two_factor_codes SET attempts = attempts + 1 WHERE id = ?");
    $stmt->bind_param("i", $row['id']);
    $stmt->execute();
    $stmt->close();
    return 'bad';
}
