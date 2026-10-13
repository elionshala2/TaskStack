<?php
// includes/mailer.php
// Dergimi i emaileve (PHPMailer + SMTP) dhe tokenat per verifikim / reset password.
// Kerkon:  composer require phpmailer/phpmailer

require_once __DIR__ . '/functions.php';

use PHPMailer\PHPMailer\PHPMailer;

define('VERIFY_TTL_MINUTES', 1440);   // linku i verifikimit vlen 24 ore
define('RESET_TTL_MINUTES', 60);      // linku i reset password vlen 1 ore
define('EMAIL_CHANGE_TTL_MINUTES', 60); // linku per konfirmimin e emailit te ri vlen 1 ore
define('REVERT_TTL_MINUTES', 720);    // linku "anulo ndryshimin" vlen 12 ore
define('MAIL_COOLDOWN_SECONDS', 60);  // sa pritet para se te dergohet email i ri per te njejtin user
define('MAX_MAIL_REQUESTS', 5);       // max kerkesa per IP ...
define('MAIL_WINDOW_MINUTES', 15);    // ... brenda ketyre minutave

// Baza e URL-se per linqet ne email (merret nga .env, jo nga Host header qe te mos manipulohet)
function app_url(): string {
    return rtrim($_ENV['APP_URL'] ?? 'http://localhost/taskstack', '/');
}

// Dergon nje email. Kthen true/false (gabimi ruhet vetem ne log)
function send_mail(string $to, string $subject, string $html, string $text): bool {
    $host = $_ENV['SMTP_HOST'] ?? '';
    if ($host === '') {
        error_log('Mail: SMTP_HOST nuk eshte vendosur ne .env');
        return false;
    }

    try {
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host       = $host;
        $m->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);
        $m->SMTPAuth   = true;
        $m->Username   = $_ENV['SMTP_USER'] ?? '';
        $m->Password   = $_ENV['SMTP_PASS'] ?? '';
        $secure        = strtolower($_ENV['SMTP_SECURE'] ?? 'tls');
        $m->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $m->CharSet    = 'UTF-8';

        $from = $_ENV['SMTP_FROM'] ?? $m->Username;
        $m->setFrom($from, $_ENV['SMTP_FROM_NAME'] ?? 'TaskStack');
        $m->addAddress($to);

        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body    = $html;
        $m->AltBody = $text;
        $m->send();
        return true;
    } catch (\Throwable $ex) {
        error_log('Mail error: ' . $ex->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------
// Tokenat (ne DB ruhet vetem hash-i SHA-256, jo tokeni vete)
// ---------------------------------------------------------------

// Sa minuta vlen secili lloj tokeni
function token_ttl_minutes(string $type): int {
    switch ($type) {
        case 'verify':          return VERIFY_TTL_MINUTES;
        case 'reset':           return RESET_TTL_MINUTES;
        case 'email_change':    return EMAIL_CHANGE_TTL_MINUTES;
        case 'revert_email':
        case 'revert_password': return REVERT_TTL_MINUTES;
    }
    return 60;
}

// I ben te pavlefshem te gjithe tokenat e pa-perdorur te nje lloji per nje user (dhe fshin payload-in)
function invalidate_user_tokens($conn, int $user_id, string $type): void {
    $stmt = $conn->prepare("UPDATE email_tokens SET used_at = NOW(), payload = NULL WHERE user_id = ? AND type = ? AND used_at IS NULL");
    $stmt->bind_param("is", $user_id, $type);
    $stmt->execute();
    $stmt->close();
}

// Krijon token te ri dhe i ben te pavlefshem te vjetrit e pa-perdorur te se njejtit lloj.
// $payload = te dhena shtese (emaili i ri, emaili/password-i i vjeter per anulim)
function create_email_token($conn, int $user_id, string $type, ?string $payload = null): string {
    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $minutes = token_ttl_minutes($type);

    invalidate_user_tokens($conn, $user_id, $type);

    $stmt = $conn->prepare(
        "INSERT INTO email_tokens (user_id, type, token_hash, payload, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
    );
    $stmt->bind_param("isssi", $user_id, $type, $hash, $payload, $minutes);
    $stmt->execute();
    $stmt->close();

    return $token;
}

// Gjen token te vlefshem (jo i perdorur, jo i skaduar). Kthen ['id'=>, 'user_id'=>, 'payload'=>] ose null
function find_email_token($conn, string $token, string $type): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);

    $stmt = $conn->prepare(
        "SELECT id, user_id, payload FROM email_tokens
         WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("ss", $hash, $type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function mark_token_used($conn, int $token_id): void {
    $stmt = $conn->prepare("UPDATE email_tokens SET used_at = NOW(), payload = NULL WHERE id = ?");
    $stmt->bind_param("i", $token_id);
    $stmt->execute();
    $stmt->close();
}

// A eshte derguar email i ketij lloji per kete user se fundmi (cooldown)?
function recent_token_exists($conn, int $user_id, string $type): bool {
    $stmt = $conn->prepare(
        "SELECT 1 FROM email_tokens
         WHERE user_id = ? AND type = ? AND created_at > (NOW() - INTERVAL " . (int)MAIL_COOLDOWN_SECONDS . " SECOND)
         LIMIT 1"
    );
    $stmt->bind_param("is", $user_id, $type);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    return $found;
}

// ---------------------------------------------------------------
// Limit per IP (qe dikush te mos e perdore formen per te derguar spam)
// ---------------------------------------------------------------

function mail_requests_count($conn, string $ip): int {
    try {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM mail_requests
             WHERE ip = ? AND requested_at > (NOW() - INTERVAL " . (int)MAIL_WINDOW_MINUTES . " MINUTE)"
        );
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['c'] ?? 0);
    } catch (mysqli_sql_exception $ex) {
        error_log('Mail rate limit count error: ' . $ex->getMessage());
        return 0;
    }
}

function record_mail_request($conn, string $ip): void {
    try {
        $stmt = $conn->prepare("INSERT INTO mail_requests (ip) VALUES (?)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
        maybe_cleanup($conn);
    } catch (mysqli_sql_exception $ex) {
        error_log('Mail rate limit insert error: ' . $ex->getMessage());
    }
}

// ---------------------------------------------------------------
// Shabllonet e emaileve
// ---------------------------------------------------------------

function email_template(string $heading, string $intro, string $btn_text, string $link, string $note): string {
    $heading  = e($heading);
    $intro    = e($intro);
    $btn_text = e($btn_text);
    $note     = e($note);
    $href     = e($link);

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 12px;"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;padding:32px;">'
        . '<tr><td style="font-size:22px;font-weight:700;color:#111827;padding-bottom:6px;">TaskStack</td></tr>'
        . '<tr><td style="font-size:18px;font-weight:700;color:#111827;padding:18px 0 8px;">' . $heading . '</td></tr>'
        . '<tr><td style="font-size:15px;line-height:1.6;color:#374151;padding-bottom:22px;">' . $intro . '</td></tr>'
        . '<tr><td style="padding-bottom:22px;"><a href="' . $href . '" style="display:inline-block;background:#3b82f6;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 24px;border-radius:10px;">' . $btn_text . '</a></td></tr>'
        . '<tr><td style="font-size:12px;line-height:1.6;color:#6b7280;">Nëse butoni nuk punon, kopjo kete link ne shfletues:<br><span style="word-break:break-all;">' . $href . '</span></td></tr>'
        . '<tr><td style="font-size:12px;line-height:1.6;color:#6b7280;padding-top:16px;">' . $note . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function send_verification_email($conn, int $user_id, string $email, string $username): bool {
    try {
        $token = create_email_token($conn, $user_id, 'verify');
    } catch (mysqli_sql_exception $ex) {
        error_log('Verify token error: ' . $ex->getMessage());
        return false;
    }

    $link  = app_url() . '/verify.php?token=' . $token;
    $intro = "Përshëndetje $username, faleminderit që u regjistrove ne TaskStack. Kliko butonin për të verifikuar emailin tend.";
    $note  = 'Linku vlen për 24 orë. Nëse nuk e ke krijuar ti kete llogari, injoroje kete email.';

    $html = email_template('Verifiko emailin tënd', $intro, 'Verifiko emailin', $link, $note);
    $text = "$intro\n\n$link\n\n$note";

    return send_mail($email, 'Verifiko emailin - TaskStack', $html, $text);
}

// Kur dikush regjistrohet me nje email qe ka tashme llogari (nuk zbulohet ne faqe, vetem ne inbox te pronarit)
function send_account_exists_email(string $email, string $username): bool {
    $link  = app_url() . '/login.php';
    $intro = "Pershendetje $username, dikush u perpoq te regjistrohet ne TaskStack me kete email, por ti ke tashme nje llogari. Nese ke qene ti, kyqu ose rivendos passwordin nga faqja e login-it.";
    $note  = 'Nese nuk ke qene ti, injoroje kete email, asgje nuk ndryshon ne llogarine tende.';

    $html = email_template('Ke tashme nje llogari', $intro, 'Shko te Login', $link, $note);
    $text = "$intro\n\n$link\n\n$note";

    return send_mail($email, 'Ke tashme nje llogari - TaskStack', $html, $text);
}

function send_reset_email($conn, int $user_id, string $email, string $username): bool {
    try {
        $token = create_email_token($conn, $user_id, 'reset');
    } catch (mysqli_sql_exception $ex) {
        error_log('Reset token error: ' . $ex->getMessage());
        return false;
    }

    $link  = app_url() . '/reset_password.php?token=' . $token;
    $intro = "ërshëndetje $username, morëm një kërkesë për të ndryshuar passwordin e llogarise tënde në platformën TaskStack. Kliko butonin për të vendosur një password të ri.";
    $note  = 'Linku vlen për 1 orë dhe perdoret vetem një herë. Nëse nuk e ke kerkuar ti kete, injoroje kete email, passwordi yt nuk ndryshon.';

    $html = email_template('Rivendos passwordin', $intro, 'Vendos password te ri', $link, $note);
    $text = "$intro\n\n$link\n\n$note";

    return send_mail($email, 'Rivendos passwordin - TaskStack', $html, $text);
}

// ---------------------------------------------------------------
// Ndryshimi i emailit + njoftimet "anulo ndryshimin"
// ---------------------------------------------------------------

// ab***@gmail.com
function mask_email(string $email): string {
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }
    return mb_substr($parts[0], 0, 1) . '***@' . $parts[1];
}

// Dergon linkun e konfirmimit ne emailin e RI (emaili nuk ndryshon para konfirmimit)
function send_email_change_email($conn, int $user_id, string $new_email, string $username): bool {
    try {
        $token = create_email_token($conn, $user_id, 'email_change', $new_email);
    } catch (mysqli_sql_exception $ex) {
        error_log('Email change token error: ' . $ex->getMessage());
        return false;
    }

    $link  = app_url() . '/confirm_email.php?token=' . $token;
    $intro = "Përshëndetje $username, morëm një kërkesë për të ndryshuar emailin e llogarise tënde në platformën TaskStack në këtë adrese. Kliko butonin për të konfirmuar.";
    $note  = 'Linku vlen 1 ore. Nëse nuk e ke kerkuar ti kete, injoroje kete email, asgje nuk ndryshon.';

    $html = email_template('Konfirmo emailin e ri', $intro, 'Konfirmo emailin', $link, $note);
    $text = "$intro\n\n$link\n\n$note";

    return send_mail($new_email, 'Konfirmo emailin e ri - TaskStack', $html, $text);
}

// Njoftim te emaili i VJETER pasi emaili u ndryshua; linku e kthen emailin mbrapsht (12 ore)
function send_email_changed_notice($conn, int $user_id, string $old_email, string $new_email, string $username): bool {
    try {
        $token = create_email_token($conn, $user_id, 'revert_email', $old_email);
    } catch (mysqli_sql_exception $ex) {
        error_log('Revert email token error: ' . $ex->getMessage());
        return false;
    }

    $link  = app_url() . '/revert.php?token=' . $token;
    $when  = date('d.m.Y H:i');
    $intro = "Përshëndetje $username, më $when emaili i llogarise tende në platformën TaskStack u ndryshua nga kjo adrese ne " . mask_email($new_email) . ". "
           . "Nëse ke qene ti, nuk duhet te besh asgje. Nëse NUK ke qene ti, kliko butonin per ta kthyer emailin e meparshem.";
    $note  = 'Linku vlen 12 ore. Nëse nuk e ke bere ti kete ndryshim, ndrysho edhe passwordin menjehere.';

    $html = email_template('Emaili i llogarise u ndryshua', $intro, 'Anulo ndryshimin', $link, $note);
    $text = "$intro\n\n$link\n\n$note";

    return send_mail($old_email, 'Emaili i llogarise u ndryshua - TaskStack', $html, $text);
}

// Njoftim pas reset password; linku e kthen passwordin e meparshem (12 ore)
function send_password_changed_notice($conn, int $user_id, string $email, string $username, string $old_hash, string $old_provider): bool {
    $payload = json_encode(['hash' => $old_hash, 'provider' => $old_provider]);

    try {
        $token = create_email_token($conn, $user_id, 'revert_password', $payload);
    } catch (mysqli_sql_exception $ex) {
        error_log('Revert password token error: ' . $ex->getMessage());
        return false;
    }

    $link  = app_url() . '/revert.php?token=' . $token;
    $when  = date('d.m.Y H:i');
    $intro = "Pershendetje $username, me $when passwordi i llogarise tende TaskStack u ndryshua permes \"Harrova passwordin\". "
           . "Nëse ke qene ti, nuk duhet te besh asgje. Nëse NUK ke qene ti, kliko butonin per ta kthyer passwordin e meparshem.";
    $note  = 'Linku vlen 12 ore. Nëse nuk e ke bërë ti këtë ndryshim, ndrysho edhe passwordin e emailit tend.';

    $html = email_template('Passwordi u ndryshua', $intro, 'Anulo ndryshimin', $link, $note);
    $text = "$intro\n\n$link\n\n$note";

    return send_mail($email, 'Passwordi u ndryshua - TaskStack', $html, $text);
}