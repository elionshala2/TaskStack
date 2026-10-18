<?php
// cron/send_reminders.php - dergon kujtesat e afateve (1 here ne dite, p.sh. ora 08:00).
//
// Opsioni 1 (rekomandohet) - cron me PHP CLI:
//     0 8 * * *  php /rruga/e/projektit/cron/send_reminders.php
// Opsioni 2 - hosti pa CLI: vendos CRON_KEY (te pakten 20 karaktere) ne .env dhe thirre me URL:
//     0 8 * * *  curl -fsS "https://domaini-yt.com/cron/send_reminders.php?key=CRON_KEY"

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/reminders.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    $key = (string)($_ENV['CRON_KEY'] ?? '');
    if (strlen($key) < 20) {
        http_response_code(403);
        exit("Disabled\n");
    }
    if (!hash_equals($key, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

try {
    $r = reminders_run($conn);
} catch (Throwable $e) {
    error_log('Reminders error: ' . $e->getMessage());
    http_response_code(500);
    exit("Error\n");
}
echo $r === null ? "Already running\n" : sprintf("users=%d sent=%d failed=%d\n", $r['users'], $r['sent'], $r['failed']);
