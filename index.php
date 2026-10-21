<?php
// index.php - Faqja kryesore

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme
require_once __DIR__ . '/includes/seo.php'; // SEO: meta, hreflang, JSON-LD

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
validate_session($conn); // sesion i vjeter (password i ndryshuar, "mbyll sesionet e tjera") hiqet

$isLoggedIn = !empty($_SESSION['user_id']); // kontrollon nese useri eshte i kyqur
$username   = $_SESSION['username'] ?? ''; // ruan username nese useri eshte i kyqur, ose e ben bosh nese jo

// $L('shqip', 'english'): teksti ne gjuhen aktive (?lang=en ose cookie)
$L = static fn(string $sq, string $en): string => pref_lang() === 'en' ? $en : $sq;

// ---------- FAQ: nje burim i vetem per HTML-in e dukshem dhe per JSON-LD (FAQPage) ----------
$faq = [
    [
        $L('A është TaskStack falas?', 'Is TaskStack free?'),
        $L(
            'Po. TaskStack është plotësisht falas: nuk ke nevojë për kartelë bankare dhe nuk ka reklama.',
            'Yes. TaskStack is completely free: no credit card is needed and there are no ads.'
        ),
    ],
    [
        $L('Çfarë është TaskStack?', 'What is TaskStack?'),
        $L(
            'TaskStack është një aplikacion web falas për menaxhimin e detyrave (to-do list). Shton detyra, u cakton prioritet, kategori dhe afat, dhe merr përkujtime me email para se të vonohesh.',
            'TaskStack is a free web app for managing tasks (a to-do list). You add tasks, give them a priority, category and deadline, and get email reminders before you fall behind.'
        ),
    ],
    [
        $L('A ka TaskStack përkujtime për detyrat?', 'Does TaskStack have task reminders?'),
        $L(
            'Po. Kur përkujtimet janë aktive, merr një email përmbledhës në ditë me detyrat e vonuara, ato me afat sot dhe ato me afat nesër. Mund t’i aktivizosh ose çaktivizosh te Settings.',
            'Yes. When reminders are on, you get one summary email a day listing overdue tasks, tasks due today and tasks due tomorrow. You can turn them on or off in Settings.'
        ),
    ],
    [
        $L('A mund të hyj me Google?', 'Can I sign in with Google?'),
        $L(
            'Po. Mund të krijosh llogari ose të hysh me një klikim me Google, pa password të ri. Nëse preferon, mund të regjistrohesh edhe me email dhe password.',
            'Yes. You can create an account or sign in with one click using Google, with no new password. If you prefer, you can also register with an email and password.'
        ),
    ],
    [
        $L('Çfarë është passkey dhe a e mbështet TaskStack?', 'What is a passkey, and does TaskStack support it?'),
        $L(
            'Passkey është hyrje pa password me gjurmë gishti, fytyrë ose PIN të pajisjes. TaskStack e mbështet dhe e shton te Settings. Ekziston edhe verifikimi në 2 hapa (2FA) me kod në email.',
            'A passkey lets you sign in without a password using your fingerprint, face or device PIN. TaskStack supports passkeys, which you add in Settings. Two-step verification (2FA) with an email code is also available.'
        ),
    ],
    [
        $L('A janë detyrat e mia të sigurta?', 'Are my tasks safe and private?'),
        $L(
            'Po. Password-et ruhen të enkriptuara dhe formularët kanë mbrojtje nga sulmet e zakonshme (SQLi, XSS dhe CSRF). Detyrat i sheh vetëm ti.',
            'Yes. Passwords are stored encrypted and forms are protected against common attacks (SQLi, XSS and CSRF). Only you can see your tasks.'
        ),
    ],
    [
        $L('Si i ndaj dhe i gjej detyrat?', 'How do I organize and find my tasks?'),
        $L(
            'Çdo detyrë ka kategori me ngjyrë, prioritet (i ulët, mesatar ose i lartë) dhe afat. Në dashboard mund të kërkosh dhe të filtrosh sipas statusit ose kategorisë.',
            'Every task has a colored category, a priority (low, medium or high) and a deadline. In the dashboard you can search and filter by status or category.'
        ),
    ],
    [
        $L('Çfarë ndodh me detyrat e vonuara?', 'What happens to overdue tasks?'),
        $L(
            'Kur afati kalon, detyra shfaqet me të kuqe dhe në krye të dashboard-it, që të dish menjëherë çfarë ka mbetur prapa. Ato përfshihen edhe në emailin e përkujtimeve.',
            'When a deadline passes, the task turns red and appears in a banner at the top of the dashboard so you see right away what fell behind. Overdue tasks are also included in the reminder email.'
        ),
    ],
    [
        $L('A funksionon TaskStack në telefon dhe në shqip?', 'Does TaskStack work on phones, and in English and Albanian?'),
        $L(
            'Po. Faqja është responsive dhe funksionon në laptop, tablet dhe telefon, në çdo shfletues modern. Ndërfaqja është në shqip dhe anglisht, me temë të errët ose të çelët.',
            'Yes. The site is responsive and works on laptops, tablets and phones in any modern browser. The interface is available in Albanian and English, with a dark or light theme.'
        ),
    ],
    [
        $L('Si e fshij llogarinë dhe të dhënat e mia?', 'How do I delete my account and data?'),
        $L(
            'Te Settings mund të ndryshosh username, email dhe password, ose të fshish llogarinë tënde në çdo kohë.',
            'In Settings you can change your username, email and password, or delete your account at any time.'
        ),
    ],
];

// ---------- Veçoritë (kartat) ----------
$features = [
    [
        '&#9989;',
        $L('Detyra dhe lista detyrash', 'Tasks and to-do lists'),
        $L(
            'Shto, edito dhe shëno detyrat si të kryera në pak sekonda. Lista jote e detyrave është gjithmonë e rregullt.',
            'Add, edit and check off tasks in seconds. Your to-do list stays tidy and easy to scan.'
        )
    ],
    [
        '&#128308;',
        $L('Prioritete të qarta', 'Clear priorities'),
        $L(
            'Cakto prioritet të ulët, mesatar ose të lartë dhe fokusohu fillimisht te ajo që ka rëndësi.',
            'Set low, medium or high priority and focus on what matters first.'
        )
    ],
    [
        '&#127912;',
        $L('Kategori me ngjyra', 'Colored categories'),
        $L(
            'Ndaji detyrat në kategori si Shkollë, Punë ose Sport dhe filtroji me një klikim.',
            'Group tasks into categories such as School, Work or Fitness and filter them with one click.'
        )
    ],
    [
        '&#128197;',
        $L('Afate dhe detyra të vonuara', 'Deadlines and overdue tasks'),
        $L(
            'Çdo detyrë mund të ketë afat. Ato që kalojnë afatin theksohen me të kuqe në dashboard.',
            'Give any task a deadline. Anything past due is highlighted in red on your dashboard.'
        )
    ],
    [
        '&#128236;',
        $L('Përkujtime me email', 'Email reminders'),
        $L(
            'Një email në ditë me detyrat e vonuara, ato për sot dhe për nesër, që asgjë të mos harrohet.',
            'One email a day with overdue tasks and what is due today and tomorrow, so nothing gets forgotten.'
        )
    ],
    [
        '&#128273;',
        $L('Hyrje e sigurt', 'Secure sign-in'),
        $L(
            'Hyr me Google, me passkey (gjurmë gishti ose PIN) ose me verifikim në 2 hapa.',
            'Sign in with Google, a passkey (fingerprint or PIN) or two-step verification.'
        )
    ],
];

// ---------- JSON-LD specifik per kete faqe ----------
$site = seo_site_url();
$page_title = $L('TaskStack - Aplikacion falas për detyra dhe përkujtime', 'TaskStack - Free To-Do List & Task Manager with Reminders');
$page_desc  = $L(
    'TaskStack është aplikacioni falas për menaxhimin e detyrave: lista e detyrave, prioritete, kategori, afate dhe përkujtime me email. Krijo llogari në një minutë.',
    'TaskStack is a free online task manager and to-do list app. Organize tasks with priorities, categories and deadlines, and get daily email reminders.'
);

$faq_entities = [];
foreach ($faq as $item) {
    $faq_entities[] = [
        '@type' => 'Question',
        'name'  => $item[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item[1]],
    ];
}

$schema = [
    [
        '@type'      => 'WebPage',
        '@id'        => $site . '/#webpage',
        'url'        => $site . '/',
        'name'       => $page_title,
        'description' => $page_desc,
        'inLanguage' => pref_lang(),
        'isPartOf'   => ['@id' => $site . '/#website'],
        'about'      => ['@id' => $site . '/#app'],
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $site . '/assets/og-image.png'],
    ],
    [
        '@type'               => 'WebApplication',
        '@id'                 => $site . '/#app',
        'name'                => 'TaskStack',
        'url'                 => $site . '/',
        'description'         => $page_desc,
        'applicationCategory' => 'BusinessApplication',
        'applicationSubCategory' => 'Task management, to-do list, reminders',
        'operatingSystem'     => 'Any (web browser)',
        'isAccessibleForFree' => true,
        'inLanguage'          => ['sq', 'en'],
        'image'               => $site . '/assets/og-image.png',
        'creator'             => ['@id' => $site . '/#organization'],
        'publisher'           => ['@id' => $site . '/#organization'],
        'offers'              => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR'],
        'featureList'         => [
            'Tasks and to-do lists',
            'Low, medium and high priorities',
            'Colored categories',
            'Deadlines and overdue task highlighting',
            'Daily email reminders for overdue, today and tomorrow',
            'Search and filters by status and category',
            'Sign in with Google, passkeys and two-step verification',
            'Dark and light theme',
            'Albanian and English interface',
        ],
    ],
    [
        '@type'      => 'FAQPage',
        '@id'        => $site . '/#faq',
        'isPartOf'   => ['@id' => $site . '/#webpage'],
        'mainEntity' => $faq_entities,
    ],
];
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php seo_head(['title' => $page_title, 'desc' => $page_desc, 'path' => '', 'schema' => $schema]); ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/style.css?v=<?= (int)@filemtime(__DIR__ . '/assets/style.css') ?>">
</head>

<body>

    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="landing">

        <!-- ================= HERO ================= -->
        <section class="hero">
            <div class="hero-text">
                <div class="hero-badge"><?= e($L('I shpejtë. I sigurtë. Falas.', 'Fast. Secure. Free.')) ?></div>

                <?php if ($isLoggedIn): ?>
                    <h1><?= e($L('Mirë se u ktheve,', 'Welcome back,')) ?> <em><?= e($username) ?></em></h1>
                    <p class="hero-sub">
                        <?= e($L('Detyrat e tua të presin. Hap dashboard-in dhe vazhdo aty ku mbete.', 'Your tasks are waiting. Open the dashboard and pick up where you left off.')) ?>
                    </p>
                    <div class="hero-actions">
                        <a href="dashboard.php" class="btn btn-large"><?= e($L('Shko te Dashboard', 'Go to Dashboard')) ?></a>
                        <a href="tasks/add_task.php" class="btn-outline btn-large"><?= e($L('+ Shto detyrë', '+ Add task')) ?></a>
                    </div>
                <?php else: ?>
                    <h1><?= e($L('Detyra dhe përkujtime', 'Tasks and reminders')) ?> <em><?= e($L('në një vend.', 'in one place.')) ?></em></h1>
                    <p class="hero-sub">
                        <?= e($L(
                            'TaskStack është menaxheri falas i detyrave: shto detyra, cakto prioritete dhe afate, dhe merr përkujtime me email para se të vonohesh.',
                            'TaskStack is the free task manager: add tasks, set priorities and deadlines, and get email reminders before you fall behind.'
                        )) ?>
                    </p>
                    <div class="hero-actions">
                        <a href="signup.php" class="btn btn-large"><?= e($L('Krijo llogari falas', 'Create a free account')) ?></a>
                        <a href="login.php" class="btn-outline btn-large"><?= e($L('Ke llogari? Hyr', 'Have an account? Log in')) ?></a>
                    </div>
                    <p class="hero-note"><?= e($L('Pa kartelë bankare. Pa reklama. Fillo menjëherë.', 'No credit card. No ads. Start right away.')) ?></p>
                <?php endif; ?>
            </div>

            <!-- pamje shembull e dashboard-it (vetem dekor) -->
            <div class="preview" aria-hidden="true">
                <div class="preview-top">
                    <i></i><i></i><i></i>
                    <span><?= e($L('Detyrat e mia', 'My tasks')) ?></span>
                </div>

                <div class="preview-progress">
                    <div class="progress"><span style="width: 50%"></span></div>
                    <span><strong>50%</strong> <?= e($L('e kryer', 'done')) ?></span>
                </div>

                <div class="mock-task done" style="--c: var(--text-dim)">
                    <span class="mock-check">&#10003;</span>
                    <span class="mock-text"><?= e($L('Përfundo detyrat e matematikës', 'Finish math homework')) ?></span>
                    <span class="priority priority-low"><?= e($L('Ulët', 'Low')) ?></span>
                </div>
                <div class="mock-task" style="--c: var(--red)">
                    <span class="mock-check"></span>
                    <span class="mock-text"><?= e($L('Projekti i informatikës', 'Computer science project')) ?></span>
                    <span class="priority priority-high"><?= e($L('Lartë', 'High')) ?></span>
                </div>
                <div class="mock-task" style="--c: var(--yellow)">
                    <span class="mock-check"></span>
                    <span class="mock-text"><?= e($L('Lexo kapitullin 4', 'Read chapter 4')) ?></span>
                    <span class="priority priority-medium"><?= e($L('Mesatar', 'Medium')) ?></span>
                </div>
                <div class="mock-task done" style="--c: var(--text-dim)">
                    <span class="mock-check">&#10003;</span>
                    <span class="mock-text"><?= e($L('Trajnimi i orës 18:00', '6 PM workout')) ?></span>
                    <span class="priority priority-low"><?= e($L('Ulët', 'Low')) ?></span>
                </div>
            </div>
        </section>

        <!-- ================= STRIPI ================= -->
        <section class="strip">
            <div><strong>&lt; 2s</strong><span><?= e($L('koha e hapjes', 'load time')) ?></span></div>
            <div><strong>3</strong><span><?= e($L('nivele prioriteti', 'priority levels')) ?></span></div>
            <div><strong>100%</strong><span>responsive</span></div>
            <div><strong>0 &euro;</strong><span><?= e($L('çmimi', 'price')) ?></span></div>
        </section>

        <!-- ================= CFARE ESHTE (pergjigje e drejtperdrejte per Google dhe AI) ================= -->
        <section class="section">
            <p class="eyebrow"><?= e($L('Rreth TaskStack', 'About TaskStack')) ?></p>
            <h2 class="section-title"><?= e($L('Çfarë është TaskStack?', 'What is TaskStack?')) ?></h2>
            <p class="section-sub">
                <?= e($L(
                    'TaskStack është një aplikacion web falas për detyra dhe përkujtime. Në vend të listave në letër ose në shënime të shpërndara, i mban të gjitha detyrat në një vend: me prioritet, kategori me ngjyrë dhe afat. Kur një afat afrohet ose kalon, TaskStack të dërgon një email përkujtimi. Funksionon në shfletues, në telefon dhe në kompjuter, në shqip dhe në anglisht.',
                    'TaskStack is a free web app for tasks and reminders. Instead of paper lists or scattered notes, you keep every task in one place with a priority, a colored category and a deadline. When a deadline approaches or passes, TaskStack sends you an email reminder. It works in the browser on phones and computers, in Albanian and English.'
                )) ?>
            </p>
            <ul class="check-list">
                <li><?= e($L('Çmimi: falas, pa reklama dhe pa kartelë bankare', 'Price: free, no ads and no credit card')) ?></li>
                <li><?= e($L('Platforma: çdo shfletues modern, në laptop, tablet dhe telefon', 'Platform: any modern browser on laptop, tablet and phone')) ?></li>
                <li><?= e($L('Gjuhët: shqip dhe anglisht, me temë të errët ose të çelët', 'Languages: Albanian and English, with dark or light theme')) ?></li>
                <li><?= e($L('Hyrja: email dhe password, Google, passkey ose 2FA', 'Sign-in: email and password, Google, passkey or 2FA')) ?></li>
            </ul>
            <p class="section-sub"><a href="features.php"><?= e($L('Shiko të gjitha veçoritë →', 'See all features →')) ?></a></p>
        </section>

        <!-- ================= SHOWCASE ================= -->
        <section class="section showcase">
            <div class="showcase-text">
                <p class="eyebrow"><?= e($L('Brenda aplikacionit', 'Inside the app')) ?></p>
                <h2 class="section-title"><?= e($L('Dashboard-i i detyrave: gjithçka në një pamje', 'Your task dashboard: everything at a glance')) ?></h2>
                <p class="section-sub"><?= e($L('Dashboard-i të tregon statistikat, progresin dhe detyrat e tua në një vend, pa dritare shtesë.', 'The dashboard shows your stats, progress and tasks in one place, with no extra windows.')) ?></p>

                <ul class="check-list">
                    <li><?= e($L('Statistika për detyrat e kryera, në pritje dhe të vonuara', 'Stats for completed, pending and overdue tasks')) ?></li>
                    <li><?= e($L('Shirit progresi që mbushet sa herë që përfundon një detyrë', 'A progress bar that fills each time you finish a task')) ?></li>
                    <li><?= e($L('Kategori me ngjyra dhe filtra të shpejtë', 'Colored categories and quick filters')) ?></li>
                    <li><?= e($L('Kërkim në të gjitha detyrat', 'Search across all your tasks')) ?></li>
                </ul>
            </div>

            <!-- pamje e madhe e dashboard-it (vetem dekor) -->
            <div class="preview showcase-mock" aria-hidden="true">
                <div class="preview-top">
                    <i></i><i></i><i></i>
                    <span>Dashboard</span>
                </div>

                <div class="mock-stats">
                    <div class="stat">
                        <div class="stat-num">12</div>
                        <div class="stat-label"><?= e($L('Gjithsej', 'Total')) ?></div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">5</div>
                        <div class="stat-label"><?= e($L('Në pritje', 'Pending')) ?></div>
                    </div>
                    <div class="stat is-danger">
                        <div class="stat-num">2</div>
                        <div class="stat-label"><?= e($L('Të vonuara', 'Overdue')) ?></div>
                    </div>
                    <div class="stat is-good">
                        <div class="stat-num">7</div>
                        <div class="stat-label"><?= e($L('Të kryera', 'Completed')) ?></div>
                    </div>
                </div>

                <div class="preview-progress">
                    <div class="progress"><span style="width: 58%"></span></div>
                    <span><strong>58%</strong> <?= e($L('e kryer', 'done')) ?></span>
                </div>

                <div class="mock-pills">
                    <span class="mock-pill" style="--cat: #7c3aed"><i></i><?= e($L('Shkollë', 'School')) ?> <b>5</b></span>
                    <span class="mock-pill" style="--cat: #22c55e"><i></i>Sport <b>3</b></span>
                    <span class="mock-pill" style="--cat: #06b6d4"><i></i><?= e($L('Projekte', 'Projects')) ?> <b>3</b></span>
                    <span class="mock-pill" style="--cat: #f59e0b"><i></i>Inbox <b>1</b></span>
                </div>

                <div class="mock-task" style="--c: var(--red)">
                    <span class="mock-check"></span>
                    <span class="mock-text"><?= e($L('Projekti i informatikës', 'Computer science project')) ?></span>
                    <span class="mock-due late"><?= e($L('Vonuar', 'Overdue')) ?></span>
                    <span class="priority priority-high"><?= e($L('Lartë', 'High')) ?></span>
                </div>
                <div class="mock-task" style="--c: var(--yellow)">
                    <span class="mock-check"></span>
                    <span class="mock-text"><?= e($L('Lexo kapitullin 4', 'Read chapter 4')) ?></span>
                    <span class="mock-due"><?= e($L('Nesër', 'Tomorrow')) ?></span>
                    <span class="priority priority-medium"><?= e($L('Mesatar', 'Medium')) ?></span>
                </div>
                <div class="mock-task done" style="--c: var(--text-dim)">
                    <span class="mock-check">&#10003;</span>
                    <span class="mock-text"><?= e($L('Bëj detyrat e matematikës', 'Do math homework')) ?></span>
                    <span class="mock-due"><?= e($L('Sot', 'Today')) ?></span>
                    <span class="priority priority-low"><?= e($L('Ulët', 'Low')) ?></span>
                </div>
            </div>
        </section>

        <!-- ================= VECORITE ================= -->
        <section class="section">
            <h2 class="section-title"><?= e($L('Veçoritë kryesore të menaxherit të detyrave', 'Key features of the task manager')) ?></h2>
            <p class="section-sub"><?= e($L('Gjithçka që të duhet për të organizuar detyrat dhe për të mos harruar afatet.', 'Everything you need to organize your tasks and never miss a deadline.')) ?></p>

            <div class="features-grid">
                <?php foreach ($features as $f): ?>
                    <div class="feature-card">
                        <div class="feature-icon"><?= $f[0] ?></div>
                        <h3><?= e($f[1]) ?></h3>
                        <p><?= e($f[2]) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ================= SI FUNKSIONON ================= -->
        <section class="section">
            <h2 class="section-title"><?= e($L('Si funksionon?', 'How does it work?')) ?></h2>
            <p class="section-sub"><?= e($L('Tre hapa dhe je gati. Nuk ka asgjë të komplikuar.', 'Three steps and you are ready. Nothing complicated.')) ?></p>

            <div class="steps">
                <div class="step">
                    <h3><?= e($L('Krijo llogari', 'Create an account')) ?></h3>
                    <p><?= e($L('Vetëm username, email dhe password, ose një klikim me Google. Një minutë dhe je brenda.', 'Just a username, email and password, or one click with Google. One minute and you are in.')) ?></p>
                </div>
                <div class="step">
                    <h3><?= e($L('Shto detyrat', 'Add your tasks')) ?></h3>
                    <p><?= e($L('Vendos titullin, prioritetin, afatin dhe kategorinë për çdo detyrë.', 'Set the title, priority, deadline and category for each task.')) ?></p>
                </div>
                <div class="step">
                    <h3><?= e($L('Ndiq progresin dhe përkujtimet', 'Track progress and reminders')) ?></h3>
                    <p><?= e($L('Shënoji si të kryera, shiko përqindjen që ke bërë çdo ditë dhe merr përkujtime me email.', 'Check tasks off, see your daily percentage done and get reminders by email.')) ?></p>
                </div>
            </div>
        </section>

        <!-- ================= PER KE ESHTE ================= -->
        <section class="section">
            <h2 class="section-title"><?= e($L('Aplikacion detyrash për këdo që punon', 'A task app for anyone who gets things done')) ?></h2>
            <p class="section-sub"><?= e($L('Nga shkolla te puna dhe jeta e përditshme, TaskStack përshtatet me ty.', 'From school to work to everyday life, TaskStack adapts to you.')) ?></p>

            <div class="features-grid">

                <div class="feature-card">
                    <div class="feature-icon">&#127891;</div>
                    <h3><?= e($L('Për nxënësit dhe studentët', 'For students')) ?></h3>
                    <p><?= e($L('Mbaj gjurmë të detyrave të shtëpisë, projekteve dhe provimeve para se të afrohet afati.', 'Keep track of homework, projects and exams before the deadline arrives.')) ?></p>
                    <ul class="usecase-tasks">
                        <li><i style="--cat: #7c3aed"></i><?= e($L('Detyra e matematikës', 'Math homework')) ?></li>
                        <li><i style="--cat: #06b6d4"></i><?= e($L('Projekti i informatikës', 'Computer science project')) ?></li>
                        <li><i style="--cat: #f59e0b"></i><?= e($L('Lexo kapitullin 4', 'Read chapter 4')) ?></li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">&#128188;</div>
                    <h3><?= e($L('Për punë', 'For work')) ?></h3>
                    <p><?= e($L('Organizo takimet, afatet dhe punët që nuk duhet të presin.', 'Organize meetings, deadlines and the work that cannot wait.')) ?></p>
                    <ul class="usecase-tasks">
                        <li><i style="--cat: #ef4444"></i><?= e($L('Dërgo raportin javor', 'Send the weekly report')) ?></li>
                        <li><i style="--cat: #06b6d4"></i><?= e($L('Takim me ekipin', 'Team meeting')) ?></li>
                        <li><i style="--cat: #f59e0b"></i><?= e($L('Përgjigju email-eve', 'Reply to emails')) ?></li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">&#127968;</div>
                    <h3><?= e($L('Për jetën e përditshme', 'For everyday life')) ?></h3>
                    <p><?= e($L('Blerjet, sporti dhe pagesat e muajit, në një listë që nuk harrohet.', 'Groceries, workouts and monthly bills, in a list you will not forget.')) ?></p>
                    <ul class="usecase-tasks">
                        <li><i style="--cat: #22c55e"></i><?= e($L('Trajnimi i orës 18:00', '6 PM workout')) ?></li>
                        <li><i style="--cat: #f59e0b"></i><?= e($L('Paguaj faturën e internetit', 'Pay the internet bill')) ?></li>
                        <li><i style="--cat: #7c3aed"></i><?= e($L('Blej ushqime', 'Buy groceries')) ?></li>
                    </ul>
                </div>

            </div>
        </section>

        <!-- ================= FAQ ================= -->
        <section class="section">
            <h2 class="section-title"><?= e($L('Pyetje të shpeshta', 'Frequently asked questions')) ?></h2>
            <p class="section-sub"><?= e($L('Gjithçka që duhet të dish para se të fillosh.', 'Everything you need to know before you start.')) ?></p>

            <div class="faq">
                <?php foreach ($faq as $item): ?>
                    <details class="faq-item">
                        <summary><?= e($item[0]) ?></summary>
                        <p><?= e($item[1]) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ================= CTA ================= -->
        <section class="cta-section">
            <h2><?= e($L('Gati për t’u organizuar?', 'Ready to get organized?')) ?></h2>
            <p><?= e($L('Fillo sot dhe merr kontrollin e ditës sate.', 'Start today and take control of your day.')) ?></p>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-large"><?= e($L('Shko te Dashboard', 'Go to Dashboard')) ?></a>
            <?php else: ?>
                <a href="signup.php" class="btn btn-large"><?= e($L('Fillo tani - është falas', 'Start now - it is free')) ?></a>
            <?php endif; ?>
        </section>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>