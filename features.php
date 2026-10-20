<?php
// features.php - Veçoritë e TaskStack (faqe publike, e indeksueshme)

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme
require_once __DIR__ . '/includes/seo.php'; // SEO: meta, hreflang, JSON-LD

session_start(); // navbar-i ka nevoje per sesionin
validate_session($conn);

$isLoggedIn = !empty($_SESSION['user_id']);

// $L('shqip', 'english'): teksti ne gjuhen aktive (?lang=en ose cookie)
$L = static fn(string $sq, string $en): string => pref_lang() === 'en' ? $en : $sq;

// [ikona, titulli, paragrafi, pikat]
$sections = [
    [
        '&#9989;',
        $L('Detyra dhe lista detyrash', 'Tasks and to-do lists'),
        $L('Në TaskStack krijon një detyrë në pak sekonda: vendos titullin, një përshkrim opsional, afatin dhe prioritetin. Kur e përfundon, e shënon si të kryer dhe shirit progresi në dashboard mbushet vetë.',
           'In TaskStack you create a task in seconds: add a title, an optional description, a deadline and a priority. When you finish it, you check it off and the progress bar on your dashboard fills automatically.'),
        [
            $L('Shto, edito dhe fshi detyra kur të duash', 'Add, edit and delete tasks whenever you like'),
            $L('Përshkrim opsional për detajet e detyrës', 'Optional description for task details'),
            $L('Shënoji si të kryera me një klikim', 'Mark tasks as completed with one click'),
        ],
    ],
    [
        '&#128308;',
        $L('Prioritete: e ulët, mesatare, e lartë', 'Priorities: low, medium, high'),
        $L('Jo çdo detyrë ka të njëjtën rëndësi. Me tre nivele prioriteti e di menjëherë çfarë duhet bërë së pari dhe çfarë mund të presë.',
           'Not every task is equally important. With three priority levels you know right away what to do first and what can wait.'),
        [
            $L('Tre nivele prioriteti me etiketa me ngjyra', 'Three priority levels with color-coded labels'),
            $L('Fokusohu te detyrat e rëndësishme fillimisht', 'Focus on the important tasks first'),
        ],
    ],
    [
        '&#127912;',
        $L('Kategori me ngjyra', 'Colored categories'),
        $L('Ndaji detyrat sipas fushës së jetës: shkollë, punë, sport, projekte ose çdo kategori tjetër që krijon vetë. Çdo kategori ka ngjyrën e vet dhe numrin e detyrave.',
           'Group tasks by area of life: school, work, fitness, projects or any category you create yourself. Each category has its own color and a task count.'),
        [
            $L('Shto, edito dhe fshi kategori', 'Add, edit and delete categories'),
            $L('Filtro dashboard-in sipas kategorisë me një klikim', 'Filter the dashboard by category with one click'),
            $L('Kategoria parazgjedhur “Inbox” për detyrat pa kategori', 'A default “Inbox” category for uncategorized tasks'),
        ],
    ],
    [
        '&#128197;',
        $L('Afate dhe detyra të vonuara', 'Deadlines and overdue tasks'),
        $L('Çdo detyrë mund të ketë afat. Kur afati kalon, detyra theksohet me të kuqe dhe shfaqet në një banner në krye të dashboard-it. Detyrat për sot dhe për nesër kanë etiketa të veçanta.',
           'Any task can have a deadline. When it passes, the task is highlighted in red and appears in a banner at the top of the dashboard. Tasks due today and tomorrow get their own labels.'),
        [
            $L('Banner dhe filtër “Të vonuara”', 'An “Overdue” banner and filter'),
            $L('Rreshta të theksuar për detyrat e vonuara dhe ato për sot', 'Highlighted rows for overdue tasks and tasks due today'),
            $L('Renditje sipas afatit, nga më afër te më larg ose anasjelltas', 'Sort by deadline, closest first or furthest first'),
        ],
    ],
    [
        '&#128236;',
        $L('Përkujtime me email për afatet', 'Email reminders for deadlines'),
        $L('Kur përkujtimet janë aktive, TaskStack të dërgon një email përmbledhës në ditë me detyrat e vonuara (deri në 7 ditë pas afatit), detyrat me afat sot dhe ato me afat nesër. Nuk ka spam: maksimumi një email në ditë.',
           'When reminders are on, TaskStack sends you one summary email a day with overdue tasks (up to 7 days past the deadline), tasks due today and tasks due tomorrow. No spam: at most one email a day.'),
        [
            $L('Aktivizohen ose çaktivizohen te Settings', 'Turn them on or off in Settings'),
            $L('Emaili vjen në gjuhën që ke zgjedhur në faqe (shqip ose anglisht)', 'The email arrives in the language you chose on the site (Albanian or English)'),
            $L('Dërgohen vetëm te emailet e verifikuara', 'Sent only to verified email addresses'),
        ],
    ],
    [
        '&#128269;',
        $L('Kërkim dhe filtra', 'Search and filters'),
        $L('Kur lista rritet, gjen çdo detyrë shpejt: kërko sipas titullit, filtro sipas statusit (aktive, të kryera, të vonuara) ose sipas kategorisë dhe rendit sipas afatit.',
           'As your list grows you can still find anything fast: search by title, filter by status (active, completed, overdue) or category, and sort by deadline.'),
        [
            $L('Kërkim në të gjitha detyrat', 'Search across all tasks'),
            $L('Filtra sipas statusit dhe kategorisë', 'Filters by status and category'),
            $L('Statistika: gjithsej, aktive, të kryera, të vonuara dhe përqindja e kryer', 'Stats: total, active, completed, overdue and percent done'),
        ],
    ],
    [
        '&#128273;',
        $L('Hyrje e sigurt: Google, passkey dhe 2FA', 'Secure sign-in: Google, passkeys and 2FA'),
        $L('Mund të hysh me email dhe password, me një klikim me Google, ose pa password fare me passkey (gjurmë gishti, fytyrë ose PIN i pajisjes). Për siguri shtesë aktivizo verifikimin në 2 hapa me kod në email.',
           'Sign in with an email and password, with one click using Google, or with no password at all using a passkey (fingerprint, face or device PIN). For extra security, turn on two-step verification with an email code.'),
        [
            $L('Password-et ruhen të enkriptuara', 'Passwords are stored encrypted'),
            $L('Mbrojtje nga SQLi, XSS dhe CSRF, dhe kufizim tentativash hyrjeje', 'Protection against SQLi, XSS and CSRF, plus login rate limiting'),
            $L('Mund t’i mbyllësh sesionet në pajisjet e tjera nga Settings', 'You can sign out other devices from Settings'),
        ],
    ],
    [
        '&#127763;',
        $L('Temë e errët ose e çelët, shqip ose anglisht', 'Dark or light theme, Albanian or English'),
        $L('Ndërfaqja përshtatet me shijen tënde: ndërro mes temës së errët dhe të çelët dhe mes shqipes dhe anglishtes në çdo kohë. Faqja është responsive dhe funksionon në laptop, tablet dhe telefon.',
           'The interface adapts to you: switch between dark and light theme and between Albanian and English at any time. The site is responsive and works on laptops, tablets and phones.'),
        [
            $L('Zgjedhja ruhet për herën tjetër', 'Your choice is remembered for next time'),
            $L('Pa reklama dhe pa gjurmim reklamash', 'No ads and no ad tracking'),
        ],
    ],
];

$site       = seo_site_url();
$page_title = $L('Veçoritë e TaskStack: detyra, përkujtime dhe afate', 'TaskStack Features: Tasks, Reminders & Deadlines');
$page_desc  = $L(
    'Zbulo veçoritë e TaskStack: lista detyrash, prioritete, kategori me ngjyra, afate, përkujtime me email, kërkim, hyrje me Google dhe passkey. Plotësisht falas.',
    'Explore TaskStack features: to-do lists, priorities, colored categories, deadlines, email reminders, search, Google sign-in and passkeys. Completely free.'
);

$schema = [
    [
        '@type'       => 'WebPage',
        '@id'         => $site . '/features.php#webpage',
        'url'         => $site . '/features.php',
        'name'        => $page_title,
        'description' => $page_desc,
        'inLanguage'  => pref_lang(),
        'isPartOf'    => ['@id' => $site . '/#website'],
        'about'       => ['@id' => $site . '/#app'],
        'breadcrumb'  => ['@id' => $site . '/features.php#breadcrumb'],
    ],
    [
        '@type'           => 'BreadcrumbList',
        '@id'             => $site . '/features.php#breadcrumb',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'TaskStack', 'item' => $site . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $L('Veçoritë', 'Features'), 'item' => $site . '/features.php'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php seo_head(['title' => $page_title, 'desc' => $page_desc, 'path' => 'features.php', 'schema' => $schema]); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/assets/style.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="landing">

        <section class="section">
            <p class="eyebrow"><?= e($L('Veçoritë', 'Features')) ?></p>
            <h1 class="section-title"><?= e($L('Veçoritë e TaskStack: detyra, përkujtime dhe afate', 'TaskStack features: tasks, reminders and deadlines')) ?></h1>
            <p class="section-sub">
                <?= e($L(
                    'TaskStack është një aplikacion falas për menaxhimin e detyrave. Këtu është gjithçka që të ofron: nga lista e thjeshtë e detyrave deri te përkujtimet me email dhe hyrja pa password.',
                    'TaskStack is a free app for managing tasks. Here is everything it offers, from a simple to-do list to email reminders and password-free sign-in.'
                )) ?>
            </p>
        </section>

        <?php foreach ($sections as $s): ?>
            <section class="section">
                <div class="feature-card">
                    <div class="feature-icon"><?= $s[0] ?></div>
                    <h2><?= e($s[1]) ?></h2>
                    <p><?= e($s[2]) ?></p>
                    <ul class="check-list">
                        <?php foreach ($s[3] as $point): ?>
                            <li><?= e($point) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="cta-section">
            <h2><?= e($L('Provo TaskStack falas', 'Try TaskStack for free')) ?></h2>
            <p><?= e($L('Krijo llogari në një minutë. Pa kartelë bankare, pa reklama.', 'Create an account in one minute. No credit card, no ads.')) ?></p>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-large"><?= e($L('Shko te Dashboard', 'Go to Dashboard')) ?></a>
            <?php else: ?>
                <a href="signup.php" class="btn btn-large"><?= e($L('Krijo llogari falas', 'Create a free account')) ?></a>
            <?php endif; ?>
            <p><a href="index.php"><?= e($L('← Kthehu te faqja kryesore', '← Back to the home page')) ?></a></p>
        </section>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>
