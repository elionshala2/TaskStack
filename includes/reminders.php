<?php
// includes/reminders.php
// Kujtesat e afateve: nje email permbledhes ne dite per detyrat e vonuara, per sot dhe per neser.
// Thirret nga cron/send_reminders.php (1 here ne dite).

require_once __DIR__ . '/mailer.php';

const REMINDER_OVERDUE_DAYS = 7;   // detyrat e vonuara perfshihen vetem per kete numer ditesh pas afatit
const REMINDER_MAX_ITEMS    = 15;  // max detyra te listuara per seksion ne email
const REMINDER_MAX_EMAILS   = 100; // max email per ekzekutim (mbrojtje nga limiti i SMTP)

function reminder_strings(string $lang): array
{
    $d = [
        'sq' => [
            'subject' => 'TaskStack: detyra që kërkojnë vëmendje',
            'heading' => 'Detyrat e tua kanë afate',
            'intro'   => 'Përshëndetje {name}, ja çfarë ka afat së shpejti:',
            'overdue' => 'Të vonuara',
            'today' => 'Afati është sot',
            'tomorrow' => 'Afati është nesër',
            'more'    => '… dhe {n} të tjera',
            'btn'     => 'Hap Dashboard-in',
            'note'    => 'Këtë email e merr sepse kujtesat janë aktive. Mund t’i çaktivizosh te Settings.',
        ],
        'en' => [
            'subject' => 'TaskStack: tasks that need attention',
            'heading' => 'Your tasks have deadlines',
            'intro'   => 'Hi {name}, here is what is due soon:',
            'overdue' => 'Overdue',
            'today' => 'Due today',
            'tomorrow' => 'Due tomorrow',
            'more'    => '… and {n} more',
            'btn'     => 'Open Dashboard',
            'note'    => 'You receive this email because reminders are turned on. You can turn them off in Settings.',
        ],
    ];
    return $d[$lang === 'en' ? 'en' : 'sq'];
}

// Kthen [user_id => ['user' => [...], 'overdue' => [...], 'today' => [...], 'tomorrow' => [...]]]
function reminders_collect(mysqli $conn, DateTimeImmutable $now): array
{
    $today    = $now->format('Y-m-d');
    $tomorrow = $now->modify('+1 day')->format('Y-m-d');
    $oldest   = $now->modify('-' . (int)REMINDER_OVERDUE_DAYS . ' days')->format('Y-m-d');

    $stmt = $conn->prepare(
        "SELECT u.id AS uid, u.username, u.email, u.language, t.title, t.due_date
         FROM regjistrimi u
         JOIN tasks t ON t.user_id = u.id
         WHERE u.reminders_enabled = 1
           AND (u.email_verified = 1 OR (u.google_id IS NOT NULL AND u.google_id <> ''))
           AND (u.last_reminder_on IS NULL OR u.last_reminder_on < ?)
           AND t.is_completed = 0
           AND t.due_date IS NOT NULL
           AND t.due_date >= ? AND t.due_date <= ?
         ORDER BY u.id, t.due_date ASC, FIELD(t.priority, 'high', 'medium', 'low'), t.id"
    );
    $stmt->bind_param("sss", $today, $oldest, $tomorrow);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($r = $res->fetch_assoc()) {
        $uid = (int)$r['uid'];
        if (!isset($out[$uid])) {
            $out[$uid] = [
                'user' => ['id' => $uid, 'username' => $r['username'], 'email' => $r['email'], 'language' => $r['language']],
                'overdue' => [],
                'today' => [],
                'tomorrow' => [],
            ];
        }
        $bucket = $r['due_date'] < $today ? 'overdue' : ($r['due_date'] === $today ? 'today' : 'tomorrow');
        $out[$uid][$bucket][] = ['title' => $r['title'], 'due' => $r['due_date']];
    }
    $stmt->close();
    return $out;
}

// Ndertimi i emailit (HTML + tekst). Gjithe teksti i userit kalon nga e().
function reminder_build(array $entry): array
{
    $lang = ($entry['user']['language'] ?? 'sq') === 'en' ? 'en' : 'sq';
    $s    = reminder_strings($lang);
    $link = app_url() . '/dashboard.php';
    $name = (string)$entry['user']['username'];

    $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 12px;"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:14px;padding:32px;">'
        . '<tr><td style="font-size:22px;font-weight:700;color:#111827;">TaskStack</td></tr>'
        . '<tr><td style="font-size:18px;font-weight:700;color:#111827;padding:18px 0 8px;">' . e($s['heading']) . '</td></tr>'
        . '<tr><td style="font-size:15px;line-height:1.6;color:#374151;padding-bottom:12px;">' . e(str_replace('{name}', $name, $s['intro'])) . '</td></tr>';
    $text = str_replace('{name}', $name, $s['intro']) . "\n";

    $colors = ['overdue' => '#dc2626', 'today' => '#d97706', 'tomorrow' => '#2563eb'];
    foreach (['overdue', 'today', 'tomorrow'] as $key) {
        $items = $entry[$key];
        if (!$items) continue;
        $html .= '<tr><td style="padding:10px 0 4px;font-size:14px;font-weight:700;color:' . $colors[$key] . ';">' . e($s[$key]) . ' (' . count($items) . ')</td></tr>';
        $text .= "\n" . $s[$key] . ' (' . count($items) . "):\n";
        foreach (array_slice($items, 0, REMINDER_MAX_ITEMS) as $it) {
            $d = date('d.m.Y', strtotime($it['due']));
            $html .= '<tr><td style="font-size:14px;line-height:1.5;color:#111827;padding:2px 0 2px 10px;">&bull; ' . e($it['title']) . ' <span style="color:#6b7280;">(' . e($d) . ')</span></td></tr>';
            $text .= '- ' . $it['title'] . ' (' . $d . ")\n";
        }
        if (count($items) > REMINDER_MAX_ITEMS) {
            $more = str_replace('{n}', (string)(count($items) - REMINDER_MAX_ITEMS), $s['more']);
            $html .= '<tr><td style="font-size:13px;color:#6b7280;padding:2px 0 2px 10px;">' . e($more) . '</td></tr>';
            $text .= $more . "\n";
        }
    }

    $html .= '<tr><td style="padding:22px 0;"><a href="' . e($link) . '" style="display:inline-block;background:#3b82f6;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 24px;border-radius:10px;">' . e($s['btn']) . '</a></td></tr>'
        . '<tr><td style="font-size:12px;line-height:1.6;color:#6b7280;">' . e($s['note']) . ' ' . e(app_url() . '/settings.php#reminders') . '</td></tr>'
        . '</table></td></tr></table></body></html>';
    $text .= "\n" . $link . "\n\n" . $s['note'] . ' ' . app_url() . "/settings.php#reminders\n";

    return [$s['subject'], $html, $text];
}

// Ekzekuton nje rrotullim. Kthen ['users' => n, 'sent' => n, 'failed' => n] ose null nese po punon nje instance tjeter.
function reminders_run(mysqli $conn, ?DateTimeImmutable $now = null): ?array
{
    $now = $now ?? new DateTimeImmutable('now');
    $lock = $conn->query("SELECT GET_LOCK('taskstack_reminders', 0) AS l")->fetch_assoc();
    if ((int)($lock['l'] ?? 0) !== 1) {
        return null; // nje ekzekutim tjeter eshte ne pune: mos dergo dyfish
    }
    $stats = ['users' => 0, 'sent' => 0, 'failed' => 0];
    try {
        $today = $now->format('Y-m-d');
        $all   = reminders_collect($conn, $now);
        $stats['users'] = count($all);
        foreach ($all as $uid => $entry) {
            if ($stats['sent'] >= REMINDER_MAX_EMAILS) break;
            [$subject, $html, $text] = reminder_build($entry);
            if (send_mail($entry['user']['email'], $subject, $html, $text)) {
                $stmt = $conn->prepare("UPDATE regjistrimi SET last_reminder_on = ? WHERE id = ?");
                $stmt->bind_param("si", $today, $uid);
                $stmt->execute();
                $stmt->close();
                $stats['sent']++;
            } else {
                $stats['failed']++; // last_reminder_on nuk preket: provohet perseri ne ekzekutimin e radhes
            }
        }
    } finally {
        $conn->query("SELECT RELEASE_LOCK('taskstack_reminders')");
    }
    return $stats;
}
