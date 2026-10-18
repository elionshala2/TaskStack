<?php
// privacy.php - Politika e Privatesise

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme
require_once __DIR__ . '/includes/legal-config.php'; // emri, emaili, data

session_start(); // navbar-i ka nevoje per sesionin

// gjuha: ne EN shfaqim tekstin anglisht nga includes/legal/*_en.php
$en = function_exists('pref_lang') && pref_lang() === 'en';
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $en ? 'Privacy Policy' : 'Politika e Privatesise' ?> - <?= e($legal_site) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="legal">

    <header class="legal-header">
        <p class="eyebrow"><?= $en ? 'Legal' : 'Ligjore' ?></p>
        <h1><?= $en ? 'Privacy Policy' : 'Politika e Privatesise' ?></h1>
        <p class="legal-meta"><?php if ($en): ?>Last updated: <?= e($legal_updated_en ?? $legal_updated) ?><?php else: ?>Perditesuar se fundmi: <?= e($legal_updated) ?><?php endif; ?></p>
    </header>

    <div class="legal-layout">

<?php if ($en): require __DIR__ . '/includes/legal/privacy_en.php'; else: ?>
        <nav class="legal-toc" aria-label="Permbajtja">
            <a href="#hyrje">1. Hyrje</a>
            <a href="#te-dhenat">2. Te dhenat qe mbledhim</a>
            <a href="#perdorimi">3. Si i perdorim</a>
            <a href="#emailet">4. Emailet qe te dergojme</a>
            <a href="#cookies">5. Cookies dhe sesionet</a>
            <a href="#te-treta">6. Sherbime te trete</a>
            <a href="#siguria">7. Siguria</a>
            <a href="#ruajtja">8. Ruajtja dhe fshirja</a>
            <a href="#te-drejtat">9. Te drejtat e tua</a>
            <a href="#femijet">10. Mosha minimale</a>
            <a href="#ndryshimet">11. Ndryshimet</a>
            <a href="#kontakt">12. Kontakt</a>
        </nav>

        <article class="legal-content">

            <h2 id="hyrje">1. Hyrje</h2>
            <p><?= e($legal_site) ?> eshte nje aplikacion falas per menaxhimin e detyrave, i menaxhuar nga <?= e($legal_owner) ?> (kontrolluesi i te dhenave). Kjo politike shpjegon cfare te dhenash mbledhim kur e perdor, pse i mbledhim dhe cfare kontrolli ke mbi to.</p>
            <p>Duke krijuar llogari ose duke u kyqur, konfirmon qe e ke lexuar kete politike. Per rregullat e perdorimit, shiko edhe <a href="terms.php">Kushtet e Sherbimit</a>.</p>

            <h2 id="te-dhenat">2. Te dhenat qe mbledhim</h2>
            <p><strong>Te dhenat e llogarise.</strong> Kur regjistrohesh, ruajme:</p>
            <ul>
                <li>username-in dhe emailin tend, si dhe nese emaili eshte verifikuar;</li>
                <li>passwordin, i cili ruhet vetem i enkriptuar (hash) dhe nuk mund te lexohet nga ne;</li>
                <li>menyren si hyn (email dhe password, Google, ose te dyja);</li>
                <li>daten kur eshte krijuar llogaria.</li>
            </ul>

            <p><strong>Kyqja me Google.</strong> Nese zgjedh "Vazhdo me Google" ose e lidh Google nga Settings, marrim nga Google emailin tend (dhe nese eshte i verifikuar), emrin dhe nje identifikues unik te llogarise Google. Nuk e marrim dhe nuk e shohim passwordin e Google. Emri perdoret vetem per te propozuar username-in fillestar. Kur e lidh Google me nje llogari ekzistuese, kerkohet passwordi yt aktual, emaili i llogarise zevendesohet me emailin e verifikuar te Google dhe emaili i vjeter njoftohet (me mundesi per ta anuluar ndryshimin). Per llogarite qe hyjne vetem me Google, e perdorim Google edhe per te verifikuar perseri identitetin tend para veprimeve te ndjeshme (vendosja e passwordit ose fshirja e llogarise).</p>

            <p><strong>Permbajtja jote.</strong> Detyrat dhe kategorite qe krijon (titulli, pershkrimi, prioriteti, afati, statusi dhe ngjyra e kategorise).</p>

            <p><strong>Te dhena teknike.</strong></p>
            <ul>
                <li>Adresen IP, oren e tentativave dhe nje identifikues te llogarise (hash i emailit ose numri i llogarise) per tentativat per kyqje, per konfirmim passwordi ne Settings dhe per kerkesat per email (verifikim, reset password). I perdorim vetem per te kufizuar tentativat e tepruara dhe per te mbrojtur llogarite, dhe nuk perdoren per profilizim.</li>
                <li>Nje cookie sesioni dhe nje numer versioni te sesionit te ruajtur ne llogarine tende (shiko seksionin Cookies dhe sesionet), si dhe, per llogarite vetem me Google, nje shenje e perkohshme ne sesion (5 minuta) qe tregon se e ke verifikuar identitetin me Google.</li>
                <li>Tokene te perkohshem per linqet ne email (verifikim, reset password, ndryshim emaili, anulim ndryshimi). Ruhet vetem hash-i i tyre, jo tokeni vete, dhe skadojne brenda 1 ore deri 24 ore, sipas llojit.</li>
            </ul>

            <h2 id="perdorimi">3. Si i perdorim te dhenat</h2>
            <ul>
                <li>per te krijuar llogarine tende dhe per te te kyqur;</li>
                <li>per te ruajtur dhe shfaqur detyrat dhe kategorite e tua, vetem per ty;</li>
                <li>per te verifikuar emailin tend dhe per te te derguar email sigurie (shiko seksionin tjeter);</li>
                <li>per te mbrojtur sherbimin nga abuzimi dhe tentativat e paautorizuara;</li>
                <li>per te rregulluar gabime dhe per te permiresuar aplikacionin.</li>
            </ul>
            <p>Nuk i shesim te dhenat e tua, nuk shfaqim reklama dhe nuk ndertojme profile marketingu. Mund t'i ndajme te dhenat vetem me ofruesit teknike qe e mbajne aplikacionin ne funksion (hostimi, databaza dhe sherbimi i emailit), ose kur na kerkohet me ligj.</p>

            <h2 id="emailet">4. Emailet qe te dergojme</h2>
            <p>Dergojme vetem email te nevojshem per llogarine, jo email marketingu:</p>
            <ul>
                <li>email verifikimi kur krijon llogari;</li>
                <li>email per vendosje te passwordit te ri, kur e kerkon ti;</li>
                <li>email konfirmimi kur ndryshon emailin;</li>
                <li>njoftim sigurie kur ndryshon passwordi ose emaili i llogarise (perfshire kur lidhja e Google e ndryshon emailin), me nje link per ta anuluar ndryshimin brenda 12 oreve nese nuk e ke bere ti;</li>
                <li>njoftim kur dikush provon te regjistrohet me emailin tend qe ka tashme llogari (mund ta injorosh nese nuk ke qene ti).</li>
            </ul>
            <p>Emailet dergohen permes nje ofruesi te sherbimit SMTP, i cili i perpunon adresen tende te emailit vetem per dergimin e tyre.</p>

            <h2 id="cookies">5. Cookies dhe sesionet</h2>
            <p>Perdorim vetem nje cookie sesioni, qe te mban te kyqur dhe mbron formularet nga sulmet CSRF. Eshte e domosdoshme per punen e faqes dhe nuk perdorim cookies reklamash ose analitike.</p>
            <ul>
                <li>Cookie-ja nuk lexohet nga JavaScript (HttpOnly), nuk dergohet nga faqe te huaja ne kerkesa qe ndryshojne te dhena (SameSite) dhe, kur faqja hapet permes HTTPS, dergohet vetem permes HTTPS (Secure).</li>
                <li>Zhduket kur mbyll shfletuesin ose kur del nga llogaria.</li>
                <li>Mund te mbyllesh sesionet ne te gjitha pajisjet e tjera nga Settings, ne seksionin "Sesionet". Sesionet e tjera mbyllen automatikisht kur ndryshon ose rivendos passwordin.</li>
            </ul>

            <h2 id="te-treta">6. Sherbime te trete</h2>
            <ul>
                <li><strong>Google Sign-In.</strong> Kur kyqesh me Google, perpunimi nga ana e Google rregullohet nga politika e tyre e privatesise.</li>
                <li><strong>Google Fonts.</strong> Fontet e faqes ngarkohen nga serverat e Google, prandaj Google mund te marre adresen IP te shfletuesit tend gjate ngarkimit.</li>
                <li><strong>Ofruesi i emailit (SMTP).</strong> Perdoret per dergimin e emaileve te pershkruar ne seksionin 4.</li>
            </ul>

            <h2 id="siguria">7. Siguria</h2>
            <p>Marrim masa te arsyeshme per te mbrojtur te dhenat: passwordet ruhen te enkriptuara, kerkesat ndaj databazes jane te parametrizuara, formularet kane mbrojtje nga CSRF, tentativat e kyqjes jane te kufizuara, dhe faqet dergojne header-a sigurie (p.sh. Content-Security-Policy). Asnje sistem nuk eshte plotesisht i sigurt, prandaj perdor nje password te forte, mos e ndaj me askend dhe mbyll sesionet e tjera nese dyshon per perdorim te paautorizuar.</p>

            <h2 id="ruajtja">8. Ruajtja dhe fshirja e te dhenave</h2>
            <p>I ruajme te dhenat sa kohe e ke llogarine aktive. Mund ta fshish vete llogarine ne cdo kohe nga Settings, ne seksionin "Fshi llogarine". Fshirja eshte e menjehershme dhe e pakthyeshme: hiqen llogaria, te gjitha detyrat dhe kategorite, si dhe tokenet e lidhur me te. Te dhenat teknike per kufizimin e tentativave (adresa IP, ora dhe identifikuesi i llogarise) perdoren per kufizim vetem brenda dritares kohore (rreth 15 minuta) dhe fshihen automatikisht pas rreth 1 dite. Llogarite qe nuk e verifikojne emailin brenda 3 diteve fshihen automatikisht, njesoj si tokenet e skaduar.</p>

            <h2 id="te-drejtat">9. Te drejtat e tua</h2>
            <p>Sipas ligjit te zbatueshem per mbrojtjen e te dhenave personale, mund te kesh te drejten te:</p>
            <ul>
                <li>kerkosh qasje ne te dhenat qe mbajme per ty;</li>
                <li>korrigjosh te dhenat e pasakta (username, email dhe password mund t'i ndryshosh vete nga Settings);</li>
                <li>kerkosh fshirjen e te dhenave te tua (mund ta besh vete nga Settings);</li>
                <li>kerkosh nje kopje te te dhenave;</li>
                <li>kundershtosh ose kufizosh perpunimin e tyre;</li>
                <li>depozitosh ankese prane autoritetit kompetent per mbrojtjen e te dhenave.</li>
            </ul>
            <p>Per te ushtruar nje te drejte qe nuk mund ta besh vete ne aplikacion, na shkruaj ne <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

            <h2 id="femijet">10. Mosha minimale</h2>
            <p><?= e($legal_site) ?> nuk eshte i destinuar per femije nen <?= (int)$legal_min_age ?> vjec. Nese mendon se nje femije nen kete moshe ka krijuar llogari, na kontakto dhe do ta fshijme.</p>

            <h2 id="ndryshimet">11. Ndryshimet e kesaj politike</h2>
            <p>Mund ta perditesojme kete politike here pas here. Data ne krye te faqes tregon versionin e fundit. Nese ndryshimet jane te rendesishme, do ta bejme te ditur ne aplikacion.</p>

            <h2 id="kontakt">12. Kontakt</h2>
            <p>Per cdo pyetje rreth privatesise: <a href="mailto:<?= e($legal_email) ?>"><?= e($legal_email) ?></a>.</p>

        </article>
<?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>