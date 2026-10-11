<?php
// index.php - Faqja kryesore

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF

$isLoggedIn = !empty($_SESSION['user_id']); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php
$username   = $_SESSION['username'] ?? ''; // ruan username nese useri eshte i kyqur, ose e ben bosh nese jo
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="To-Do List - Organizohu, fokusohu, arri me shume.">
    <title>To-Do List - Organizohu me lehtesi</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="landing">

    <!-- ================= HERO ================= -->
    <section class="hero">
        <div class="hero-text">
            <div class="hero-badge">I shpejte. I sigurt. Falas.</div>

            <?php if ($isLoggedIn): ?>
                <h1>Mire se u ktheve, <em><?= e($username) ?></em></h1>
                <p class="hero-sub">
                    Detyrat e tua te presin. Hap dashboard-in dhe vazhdo aty ku e le.
                </p>
                <div class="hero-actions">
                    <a href="dashboard.php" class="btn btn-large">Shko ne Dashboard</a>
                    <a href="tasks/add_task.php" class="btn-outline btn-large">+ Shto detyre</a>
                </div>
            <?php else: ?>
                <h1>Mos harro asgje. <em>Perfundo gjithcka.</em></h1>
                <p class="hero-sub">
                    Nje aplikacion i thjeshte per detyrat e perditshme. Cakto prioritetin,
                    afatin dhe kategorine, pastaj shenoji ato si te kryera.
                </p>
                <div class="hero-actions">
                    <a href="signup.php" class="btn btn-large">Krijo llogari falas</a>
                    <a href="login.php" class="btn-outline btn-large">Ke llogari? Hyr</a>
                </div>
                <p class="hero-note">Pa kartele, pa reklama. Vetem detyrat e tua.</p>
            <?php endif; ?>
        </div>

        <!-- pamje shembull e dashboard-it (vetem dekor) -->
        <div class="preview" aria-hidden="true">
            <div class="preview-top">
                <i></i><i></i><i></i>
                <span>Detyrat e mia</span>
            </div>

            <div class="preview-progress">
                <div class="progress"><span style="width: 50%"></span></div>
                <span><strong>50%</strong> e kryer</span>
            </div>

            <div class="mock-task done" style="--c: var(--text-dim)">
                <span class="mock-check">&#10003;</span>
                <span class="mock-text">Bej detyrat e matematikes</span>
                <span class="priority priority-low">Ulet</span>
            </div>
            <div class="mock-task" style="--c: var(--red)">
                <span class="mock-check"></span>
                <span class="mock-text">Projekti i informatikes</span>
                <span class="priority priority-high">Larte</span>
            </div>
            <div class="mock-task" style="--c: var(--yellow)">
                <span class="mock-check"></span>
                <span class="mock-text">Lexo kapitullin 4</span>
                <span class="priority priority-medium">Mesatar</span>
            </div>
            <div class="mock-task done" style="--c: var(--text-dim)">
                <span class="mock-check">&#10003;</span>
                <span class="mock-text">Trajnimi i ores 18:00</span>
                <span class="priority priority-low">Ulet</span>
            </div>
        </div>
    </section>

    <!-- ================= STRIPI ================= -->
    <section class="strip">
        <div><strong>&lt; 2s</strong><span>koha e hapjes</span></div>
        <div><strong>3</strong><span>nivele prioriteti</span></div>
        <div><strong>100%</strong><span>responsive</span></div>
        <div><strong>0 &euro;</strong><span>cmimi</span></div>
    </section>

    <!-- ================= SI FUNKSIONON ================= -->
    <section class="section">
        <h2 class="section-title">Si funksionon?</h2>
        <p class="section-sub">Tre hapa dhe je gati. Nuk ka asgje te komplikuar.</p>

        <div class="steps">
            <div class="step">
                <h3>Krijo llogari</h3>
                <p>Vetem username, email dhe password. Nje minute dhe je brenda.</p>
            </div>
            <div class="step">
                <h3>Shto detyrat</h3>
                <p>Vendos titullin, prioritetin, afatin dhe kategorine per cdo detyre.</p>
            </div>
            <div class="step">
                <h3>Ndiq progresin</h3>
                <p>Shenoji si te kryera dhe shiko perqindjen qe ke bere cdo dite.</p>
            </div>
        </div>
    </section>

    <!-- ================= FEATURES ================= -->
    <section class="section">
        <h2 class="section-title">Pse te zgjedhesh ne?</h2>
        <p class="section-sub">Gjithe ajo qe te duhet per produktivitet, ne nje vend.</p>

        <div class="features-grid">

            <div class="feature-card">
                <div class="feature-icon">&#9889;</div>
                <h3>I shpejte</h3>
                <p>Faqja hapet ne me pak se 2 sekonda. Gjithmone.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">&#128274;</div>
                <h3>I sigurt</h3>
                <p>Password te enkriptuara, mbrojtje nga SQLi, XSS dhe CSRF. Askush tjeter nuk i sheh detyrat e tua.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">&#128241;</div>
                <h3>Responsive</h3>
                <p>Punon ne laptop, tablet dhe telefon pa problem.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">&#127919;</div>
                <h3>Prioritete</h3>
                <p>Cakto prioritetin: low, medium ose high. Me te rendesishmet dalin gjithmone lart.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">&#128193;</div>
                <h3>Kategori me ngjyra</h3>
                <p>Ndaj detyrat ne shkolle, sport, projekte dhe zgjidh ngjyren qe te pelqen.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">&#128197;</div>
                <h3>Afate kohore</h3>
                <p>Detyrat e vonuara shfaqen me te kuqe, qe te mos te iku asgje.</p>
            </div>

        </div>
    </section>

    <!-- ================= CTA ================= -->
    <section class="cta-section">
        <h2>Gati per t'u organizuar?</h2>
        <p>Fillo sot dhe merr kontrollin e dites tende.</p>
        <?php if ($isLoggedIn): ?>
            <a href="dashboard.php" class="btn btn-large">Shko ne Dashboard</a>
        <?php else: ?>
            <a href="signup.php" class="btn btn-large">Fillo tani - eshte falas</a>
        <?php endif; ?>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
