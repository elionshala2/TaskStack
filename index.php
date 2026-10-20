<?php
// index.php - Faqja kryesore

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
validate_session($conn); // sesion i vjeter (password i ndryshuar, "mbyll sesionet e tjera") hiqet

$isLoggedIn = !empty($_SESSION['user_id']); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php
$username   = $_SESSION['username'] ?? ''; // ruan username nese useri eshte i kyqur, ose e ben bosh nese jo
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TaskStack - Organizohu, fokusohu, arri me shume.">
    <title>TaskStack - Organizohu me lehtesi</title>

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
                <div class="hero-badge">I shpejtë. I sigurtë. Falas.</div>

                <?php if ($isLoggedIn): ?>
                    <h1>Mirë se u ktheve, <em><?= e($username) ?></em></h1>
                    <p class="hero-sub">
                        Detyrat e tua të presin. Hap dashboard-in dhe vazhdo aty ke mbetur.
                    </p>
                    <div class="hero-actions">
                        <a href="dashboard.php" class="btn btn-large">Shko ne Dashboard</a>
                        <a href="tasks/add_task.php" class="btn-outline btn-large">+ Shto detyre</a>
                    </div>
                <?php else: ?>
                    <h1>Transformo idetë e tua <em>në rezultate.</em></h1>
                    <p class="hero-sub">
                        Planifikimi nuk merr kohë, ai të jep kohë. Organizo projektet, cakto prioritete të qarta dhe ndërto ditë më produktive.
                    </p>
                    <div class="hero-actions">
                        <a href="signup.php" class="btn btn-large">Krijo llogari falas</a>
                        <a href="login.php" class="btn-outline btn-large">Ke llogari? Hyr</a>
                    </div>
                    <p class="hero-note">Krijo llogarinë falas dhe fillo menjëherë.</p>
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
                    <span class="mock-text">Përfundo detyrat e matematikes</span>
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
                    <span class="mock-text">Trajnimi i orës 18:00</span>
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

        <!-- ================= SHOWCASE ================= -->
        <section class="section showcase">
            <div class="showcase-text">
                <p class="eyebrow">Brenda aplikacionit</p>
                <h2 class="section-title">Gjithcka ne nje pamje</h2>
                <p class="section-sub">Dashboard-i te tregon statistikat, progresin dhe detyrat e tua ne nje vend, pa dritare shtese.</p>

                <ul class="check-list">
                    <li>Statistika per detyrat e kryera, ne pritje dhe te vonuara</li>
                    <li>Shirit progresi qe mbushet sa here qe perfundon nje detyre</li>
                    <li>Kategori me ngjyra dhe filtra te shpejte</li>
                    <li>Kerkim ne te gjitha detyrat</li>
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
                        <div class="stat-label">Gjithsej</div>
                    </div>
                    <div class="stat">
                        <div class="stat-num">5</div>
                        <div class="stat-label">Në pritje</div>
                    </div>
                    <div class="stat is-danger">
                        <div class="stat-num">2</div>
                        <div class="stat-label">Të vonuara</div>
                    </div>
                    <div class="stat is-good">
                        <div class="stat-num">7</div>
                        <div class="stat-label">Të kryera</div>
                    </div>
                </div>

                <div class="preview-progress">
                    <div class="progress"><span style="width: 58%"></span></div>
                    <span><strong>58%</strong> e kryer</span>
                </div>

                <div class="mock-pills">
                    <span class="mock-pill" style="--cat: #7c3aed"><i></i>Shkollë <b>5</b></span>
                    <span class="mock-pill" style="--cat: #22c55e"><i></i>Sport <b>3</b></span>
                    <span class="mock-pill" style="--cat: #06b6d4"><i></i>Projekte <b>3</b></span>
                    <span class="mock-pill" style="--cat: #f59e0b"><i></i>Inbox <b>1</b></span>
                </div>

                <div class="mock-task" style="--c: var(--red)">
                    <span class="mock-check"></span>
                    <span class="mock-text">Projekti i informatikes</span>
                    <span class="mock-due late">Vonuar</span>
                    <span class="priority priority-high">Larte</span>
                </div>
                <div class="mock-task" style="--c: var(--yellow)">
                    <span class="mock-check"></span>
                    <span class="mock-text">Lexo kapitullin 4</span>
                    <span class="mock-due">Neser</span>
                    <span class="priority priority-medium">Mesatar</span>
                </div>
                <div class="mock-task done" style="--c: var(--text-dim)">
                    <span class="mock-check">&#10003;</span>
                    <span class="mock-text">Bej detyrat e matematikes</span>
                    <span class="mock-due">Sot</span>
                    <span class="priority priority-low">Ulet</span>
                </div>
            </div>
        </section>

        <!-- ================= SI FUNKSIONON ================= -->
        <section class="section">
            <h2 class="section-title">Si funksionon?</h2>
            <p class="section-sub">Tre hapa dhe je gati. Nuk ka asgje të komplikuar.</p>

            <div class="steps">
                <div class="step">
                    <h3>Krijo llogari</h3>
                    <p>Vetëm username, email dhe password. Nje minutë dhe je brenda.</p>
                </div>
                <div class="step">
                    <h3>Shto detyrat</h3>
                    <p>Vendos titullin, prioritetin, afatin dhe kategorine per çdo detyre.</p>
                </div>
                <div class="step">
                    <h3>Ndiq progresin</h3>
                    <p>Shenoji si te kryera dhe shiko perqindjen qe ke bere çdo dite.</p>
                </div>
            </div>
        </section>

        <!-- ================= PER KE ESHTE ================= -->
        <section class="section">
            <h2 class="section-title">Për këdo që punon</h2>
            <p class="section-sub">Nga shkolla te puna dhe jeta e përditshme, TaskStack përshtatet me ty.</p>

            <div class="features-grid">

                <div class="feature-card">
                    <div class="feature-icon">&#127891;</div>
                    <h3>Për nxënësit dhe studentët</h3>
                    <p>Mbaj gjurme të detyrave, projekteve dhe provimeve para se të afrohet afati.</p>
                    <ul class="usecase-tasks">
                        <li><i style="--cat: #7c3aed"></i>Detyra e matematikes</li>
                        <li><i style="--cat: #06b6d4"></i>Projekti i informatikes</li>
                        <li><i style="--cat: #f59e0b"></i>Lexo kapitullin 4</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">&#128188;</div>
                    <h3>Për punë</h3>
                    <p>Organizo takimet, afatet dhe punët që nuk duhet të presin.</p>
                    <ul class="usecase-tasks">
                        <li><i style="--cat: #ef4444"></i>Dergo raportin javor</li>
                        <li><i style="--cat: #06b6d4"></i>Takim me ekipin</li>
                        <li><i style="--cat: #f59e0b"></i>Pergjigju email-eve</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">&#127968;</div>
                    <h3>Për jetën e përditshme</h3>
                    <p>Blerjet, sporti dhe pagesat e muajit, në një liste që nuk harrohet.</p>
                    <ul class="usecase-tasks">
                        <li><i style="--cat: #22c55e"></i>Trajnimi i ores 18:00</li>
                        <li><i style="--cat: #f59e0b"></i>Paguaj faturen e internetit</li>
                        <li><i style="--cat: #7c3aed"></i>Blej ushqime</li>
                    </ul>
                </div>

            </div>
        </section>

        <!-- ================= FAQ ================= -->
        <section class="section">
            <h2 class="section-title">Pyetje te shpeshta</h2>
            <p class="section-sub">Gjithcka që duhet të dish para se të fillosh.</p>

            <div class="faq">

                <details class="faq-item">
                    <summary>A eshte TaskStack falas?</summary>
                    <p>Po. Nuk ke nevoje për kartelë dhe nuk ka reklama.</p>
                </details>

                <details class="faq-item">
                    <summary>A mund te hyj me Google?</summary>
                    <p>Po. Mund te krijosh llogari ose te hysh me nje click me Google, pa pasur nevojë për password te ri. Nese preferon, mund te regjistrohesh edhe me email dhe password.</p>
                </details>

                <details class="faq-item">
                    <summary>A janë detyrat e mia të sigurta?</summary>
                    <p>Password-et ruhen të enkriptuara dhe formularet kane mbrojtje nga sulmet e zakonshme (SQLi, XSS dhe CSRF). Detyrat i sheh vetem ti.</p>
                </details>

                <details class="faq-item">
                    <summary>Si i ndaj dhe i gjej detyrat?</summary>
                    <p>Cdo detyre ka kategori me ngjyre, prioritet (low, medium ose high) dhe afat. Ne dashboard mund te kerkosh dhe te filtrosh sipas statusit ose kategorise.</p>
                </details>

                <details class="faq-item">
                    <summary>Cfare ndodh me detyrat e vonuara?</summary>
                    <p>Kur afati kalon, detyra shfaqet me te kuqe, qe te dish menjehere cfare ka mbetur prapa.</p>
                </details>

            </div>
        </section>

        <!-- ================= CTA ================= -->
        <section class="cta-section">
            <h2>Gati për t'u organizuar?</h2>
            <p>Fillo sot dhe merr kontrollin e dites tënde.</p>
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-large">Shko në Dashboard</a>
            <?php else: ?>
                <a href="signup.php" class="btn btn-large">Fillo tani - është falas</a>
            <?php endif; ?>
        </section>

    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>