<?php
// terms.php - Kushtet e Sherbimit

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
    <title>Kushtet e Sherbimit - <?= e($legal_site) ?></title>
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
        <h1>Kushtet e Sherbimit</h1>
        <p class="legal-meta">Perditesuar se fundmi: <?= e($legal_updated) ?></p>
    </header>

    <div class="legal-layout">

        <nav class="legal-toc" aria-label="Permbajtja">
            <a href="#pranimi">1. Pranimi i kushteve</a>
            <a href="#sherbimi">2. Sherbimi</a>
            <a href="#llogaria">3. Llogaria jote</a>
            <a href="#perdorimi">4. Perdorimi i pranueshem</a>
            <a href="#permbajtja">5. Permbajtja jote</a>
            <a href="#disponueshmeria">6. Disponueshmeria</a>
            <a href="#pergjegjesia">7. Kufizimi i pergjegjesise</a>
            <a href="#mbyllja">8. Mbyllja e llogarise</a>
            <a href="#privatesia">9. Privatesia</a>
            <a href="#ndryshimet">10. Ndryshimet</a>
            <a href="#ligji">11. Ligji i zbatueshem</a>
            <a href="#kontakt">12. Kontakt</a>
        </nav>

        <article class="legal-content">

            <h2 id="pranimi">1. Pranimi i kushteve</h2>
            <p>Duke krijuar llogari, duke u kyqur ose duke perdorur <?= e($legal_site) ?>, pranon keto Kushte te Sherbimit. Nese nuk pajtohesh me to, te lutemi mos e perdor sherbimin.</p>

            <h2 id="sherbimi">2. Sherbimi</h2>
            <p><?= e($legal_site) ?> eshte nje aplikacion falas per menaxhimin e detyrave: mund te krijosh detyra, kategori, prioritete dhe afate, dhe t'i shenosh si te kryera. Mund ta ndryshojme, zgjerojme ose ndalojme ndonje funksion ne cdo kohe.</p>

            <h2 id="llogaria">3. Llogaria jote</h2>
            <ul>
                <li>Duhet te jesh te pakten <?= (int)$legal_min_age ?> vjec per te krijuar llogari.</li>
                <li>Duhet te japesh te dhena te sakta dhe te mirembash llogarine tende te sigurt.</li>
                <li>Je pergjegjes per cdo veprim qe behet me llogarine tende, perfshire kyqjen me Google.</li>
                <li>Mos e ndaj passwordin me te tjere. Nese dyshon per perdorim te paautorizuar, na njofto menjehere.</li>
            </ul>

            <h2 id="perdorimi">4. Perdorimi i pranueshem</h2>
            <p>Bien dakord qe te mos:</p>
            <ul>
                <li>e perdoresh sherbimin per qellime te kundraligjshme ose te demshme;</li>
                <li>perpiqesh te hysh ne llogari ose te dhena qe nuk te takojne;</li>
                <li>pengosh ose ngarkosh teper serverin, ose te kryesh sulme (p.sh. brute force, SQL injection, XSS);</li>
                <li>perdoresh skripte automatike per te krijuar llogari ose per te nxjerre te dhena ne mase;</li>
                <li>ngarkosh permbajtje me virus, mashtruese ose qe shkel te drejtat e te tjereve.</li>
            </ul>

            <h2 id="permbajtja">5. Permbajtja jote</h2>
            <p>Detyrat dhe kategorite qe krijon jane te tuat. Na jep vetem lejen e nevojshme per t'i ruajtur dhe shfaqur ty brenda aplikacionit. Je pergjegjes per ate qe shkruan dhe nuk duhet te ruash permbajtje te kundraligjshme.</p>

            <h2 id="disponueshmeria">6. Disponueshmeria</h2>
            <p>Mundohemi ta mbajme sherbimin ne pune, por nuk garantojme qe do te jete gjithmone i disponueshem ose pa gabime. Mund te kete nderprerje per mirembajtje ose per arsye jashte kontrollit tone. Per detyra shume te rendesishme, mbaj nje kopje edhe diku tjeter.</p>

            <h2 id="pergjegjesia">7. Kufizimi i pergjegjesise</h2>
            <p>Sherbimi ofrohet "ashtu sic eshte", pa garanci te asnje lloji. Brenda kufijve qe lejon ligji, nuk mbajme pergjegjesi per humbje te te dhenave, afate te humbura ose dem te tjera te pasoje qe vijne nga perdorimi i sherbimit.</p>

            <h2 id="mbyllja">8. Mbyllja e llogarise</h2>
            <p>Mund te ndalosh perdorimin e <?= e($legal_site) ?> ne cdo kohe dhe te kerkosh fshirjen e llogarise duke na kontaktuar. Mund ta pezullojme ose mbyllim nje llogari qe shkel keto kushte ose qe perdoret ne menyre abuzive.</p>

            <h2 id="privatesia">9. Privatesia</h2>
            <p>Menyra si i trajtojme te dhenat e tua shpjegohet ne <a href="privacy.php">Politiken e Privatesise</a>, e cila eshte pjese e ketyre kushteve.</p>

            <h2 id="ndryshimet">10. Ndryshimet e kushteve</h2>
            <p>Mund t'i perditesojme keto kushte here pas here. Data ne krye te faqes tregon versionin e fundit. Nese vazhdon ta perdoresh sherbimin pas ndryshimeve, konsiderohet qe i ke pranuar.</p>

            <h2 id="ligji">11. Ligji i zbatueshem</h2>
            <p>Keto kushte rregullohen nga ligjet e <?= e($legal_country) ?>. Per mosmarreveshje do te kerkohet fillimisht zgjidhje miqesore, dhe nese nuk arrihet, ato i nenshtrohen gjykatave kompetente te ketij vendi.</p>

            <h2 id="kontakt">12. Kontakt</h2>
            <p>Per pyetje rreth ketyre kushteve: <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

        </article>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>