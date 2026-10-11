<?php
// privacy.php - Politika e Privatesise

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme
require_once __DIR__ . '/includes/legal-config.php'; // emri, emaili, data

session_start(); // navbar-i ka nevoje per sesionin
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politika e Privatesise - <?= e($legal_site) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="legal">

    <header class="legal-header">
        <p class="eyebrow">Ligjore</p>
        <h1>Politika e Privatesise</h1>
        <p class="legal-meta">Perditesuar se fundmi: <?= e($legal_updated) ?></p>
    </header>

    <div class="legal-layout">

        <nav class="legal-toc" aria-label="Permbajtja">
            <a href="#hyrje">1. Hyrje</a>
            <a href="#te-dhenat">2. Te dhenat qe mbledhim</a>
            <a href="#perdorimi">3. Si i perdorim</a>
            <a href="#cookies">4. Cookies</a>
            <a href="#te-treta">5. Sherbime te trete</a>
            <a href="#siguria">6. Siguria</a>
            <a href="#ruajtja">7. Ruajtja e te dhenave</a>
            <a href="#te-drejtat">8. Te drejtat e tua</a>
            <a href="#femijet">9. Mosha minimale</a>
            <a href="#ndryshimet">10. Ndryshimet</a>
            <a href="#kontakt">11. Kontakt</a>
        </nav>

        <article class="legal-content">

            <h2 id="hyrje">1. Hyrje</h2>
            <p><?= e($legal_site) ?> eshte nje aplikacion falas per menaxhimin e detyrave. Kjo politike shpjegon cfare te dhenash mbledhim kur e perdor, pse i mbledhim dhe cfare kontrolli ke mbi to.</p>
            <p>Duke krijuar llogari ose duke u kyqur, konfirmon qe e ke lexuar kete politike. Per rregullat e perdorimit, shiko edhe <a href="terms.php">Kushtet e Sherbimit</a>.</p>

            <h2 id="te-dhenat">2. Te dhenat qe mbledhim</h2>
            <p><strong>Te dhenat e llogarise.</strong> Kur regjistrohesh, ruajme:</p>
            <ul>
                <li>username-in dhe emailin tend;</li>
                <li>passwordin, i cili ruhet vetem i enkriptuar (hash) dhe nuk mund te lexohet nga ne;</li>
                <li>daten kur eshte krijuar llogaria.</li>
            </ul>

            <p><strong>Kyqja me Google.</strong> Nese zgjedh "Vazhdo me Google", marrim nga Google emailin tend, emrin dhe nje identifikues unik te llogarise Google. Nuk e marrim dhe nuk e shohim passwordin e Google. Emri perdoret vetem per te propozuar username-in fillestar.</p>

            <p><strong>Permbajtja jote.</strong> Detyrat dhe kategorite qe krijon (titulli, pershkrimi, prioriteti, afati, statusi dhe ngjyra e kategorise).</p>

            <p><strong>Te dhena teknike.</strong> Adresen IP gjate tentativave per kyqje, qe e perdorim per te kufizuar tentativat e tepruara dhe per te mbrojtur llogarite. Gjithashtu perdorim nje cookie sesioni (shiko seksionin Cookies).</p>

            <h2 id="perdorimi">3. Si i perdorim te dhenat</h2>
            <ul>
                <li>per te krijuar llogarine tende dhe per te te kyqur;</li>
                <li>per te ruajtur dhe shfaqur detyrat dhe kategorite e tua, vetem per ty;</li>
                <li>per te mbrojtur sherbimin nga abuzimi dhe tentativat e paautorizuara;</li>
                <li>per te rregulluar gabime dhe per te permiresuar aplikacionin.</li>
            </ul>
            <p>Nuk i shesim te dhenat e tua, nuk shfaqim reklama dhe nuk ndertojme profile marketingu. Mund t'i ndajme te dhenat vetem me ofruesin e hostimit dhe te databazes qe e mban aplikacionin ne funksion, ose kur na kerkohet me ligj.</p>

            <h2 id="cookies">4. Cookies</h2>
            <p>Perdorim vetem nje cookie sesioni, qe te mban te kyqur dhe mbron formularet nga sulmet CSRF. Eshte e domosdoshme per punen e faqes. Nuk perdorim cookies reklamash ose analitike.</p>

            <h2 id="te-treta">5. Sherbime te trete</h2>
            <ul>
                <li><strong>Google Sign-In.</strong> Kur kyqesh me Google, perpunimi nga ana e Google rregullohet nga politika e tyre e privatesise.</li>
                <li><strong>Google Fonts.</strong> Fontet e faqes ngarkohen nga serverat e Google, prandaj Google mund te marre adresen IP te shfletuesit tend gjate ngarkimit.</li>
            </ul>

            <h2 id="siguria">6. Siguria</h2>
            <p>Marrim masa te arsyeshme per te mbrojtur te dhenat: passwordet ruhen te enkriptuara, kerkesat ndaj databazes jane te parametrizuara dhe formularet kane mbrojtje nga CSRF. Asnje sistem nuk eshte plotesisht i sigurt, prandaj perdor nje password te forte dhe mos e ndaj me askend.</p>

            <h2 id="ruajtja">7. Ruajtja e te dhenave</h2>
            <p>I ruajme te dhenat sa kohe e ke llogarine aktive. Mund te kerkosh fshirjen e llogarise dhe te te gjitha detyrave te lidhura me te duke na kontaktuar ne emailin me poshte.</p>

            <h2 id="te-drejtat">8. Te drejtat e tua</h2>
            <p>Sipas ligjit te zbatueshem per mbrojtjen e te dhenave personale, mund te kesh te drejten te:</p>
            <ul>
                <li>kerkosh qasje ne te dhenat qe mbajme per ty;</li>
                <li>korrigjosh te dhenat e pasakta;</li>
                <li>kerkosh fshirjen e te dhenave te tua;</li>
                <li>kerkosh nje kopje te te dhenave;</li>
                <li>kundershtosh ose kufizosh perpunimin e tyre;</li>
                <li>depozitosh ankese prane autoritetit kompetent per mbrojtjen e te dhenave.</li>
            </ul>
            <p>Per te ushtruar nje te drejte, na shkruaj ne <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

            <h2 id="femijet">9. Mosha minimale</h2>
            <p><?= e($legal_site) ?> nuk eshte i destinuar per femije nen <?= (int)$legal_min_age ?> vjec. Nese mendon se nje femije nen kete moshe ka krijuar llogari, na kontakto dhe do ta fshijme.</p>

            <h2 id="ndryshimet">10. Ndryshimet e kesaj politike</h2>
            <p>Mund ta perditesojme kete politike here pas here. Data ne krye te faqes tregon versionin e fundit. Nese ndryshimet jane te rendesishme, do ta bejme te ditur ne aplikacion.</p>

            <h2 id="kontakt">11. Kontakt</h2>
            <p>Per cdo pyetje rreth privatesise: <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

        </article>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>