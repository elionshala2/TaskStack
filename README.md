# TaskStack

Nje web aplikacion per menaxhimin e detyrave me autentifikim perdoruesi, i ndertuar me HTML, CSS, JavaScript, PHP dhe MySQL.

## Si ta nisesh

1. Hap XAMPP (Apache + MySQL). Duhet PHP 8.0 ose me i ri.
2. Vendose folderin e projektit ne `htdocs` me emrin `TaskStack` (kujdes shkronjat e medha, duhet te perputhen me `APP_URL`).
3. Krijo nje databaze bosh `todolist` (utf8mb4_unicode_ci) ne phpMyAdmin dhe importo `database.sql` ne te (krijon vetem tabelat, pa te dhena).
   Ke tashme nje databaze nga versioni i vjeter? Mos e importo `database.sql`: bej backup dhe importo `migration.sql` (shiko "Perditesimi i nje instalimi ekzistues").
4. Instalo Composer: https://getcomposer.org/
5. Hap terminalin ne folderin e projektit dhe shkruaj `composer install`.
6. Kopjo `.env.example` si `.env` dhe vendos te dhenat (shiko "Konfigurimi" me poshte).
7. Hap `http://localhost/TaskStack/`.

## Konfigurimi (`.env`)

| Variabla | Pershkrim |
| --- | --- |
| `DB_SERVER`, `DB_USER`, `DB_PASS`, `DB_DATABASE` | Lidhja me databazen. Ne XAMPP: `localhost`, `root`, passwordi bosh, `todolist`. |
| `APP_URL` | URL e aplikacionit, pa `/` ne fund (p.sh. `http://localhost/TaskStack`). Perdoret per linqet ne emaile. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Per kyqjen me Google (opsionale). Krijo OAuth client ne Google Cloud Console dhe regjistro `GOOGLE_REDIRECT_URI` saktesisht si ne `.env`. |
| `APP_DEBUG` | `0` (parazgjedhja) = gabimet e PHP nuk shfaqen ne faqe, vetem ne log. Vendos `1` vetem ne zhvillim lokal. |
| `TRUSTED_PROXIES` | Opsionale. IP/CIDR te proxy-ve te besuara (p.sh. Cloudflare ose load balancer-i i hostit), te ndara me presje. Vetem kur kerkesa vjen nga nje prej tyre, `X-Forwarded-For` perdoret per IP e klientit (rate limiting). Lere bosh nese nuk ke proxy. |
| `APP_TIMEZONE` | Opsionale. Zona kohore per afatet dhe kujtesat (parazgjedhja `Europe/Belgrade`). |
| `CRON_KEY` | Opsionale. Te pakten 20 karaktere te rastesishme; aktivizon thirrjen e kujtesave me URL kur hosti nuk ka PHP CLI (shiko "Kujtesat e afateve"). Lere bosh nese perdor cron me CLI. |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `SMTP_FROM_NAME` | Per emailet (verifikim, reset password, njoftime sigurie). Me Gmail perdor "App Password" dhe port `587` me `SMTP_SECURE=tls` (ose `465` me `ssl`). |

Mos e commit-o `.env` kurre (eshte ne `.gitignore`). Nese e ke ndare me dike, ndrysho sekretet.

## Kerkesat

- Faqja Kryesore `index.php`, qe e fton perdoruesin me kriju llogari
- Faqja Login dhe Signup
- Faqja Signup kerkon username, email, password, cpassword
- Passwordet enkriptohen para se te ruhen ne databaze
- Rigjenerimi i session ID ne secilin login
- Per krijimin e taskut te ri kerkohet Titulli, Pershkrimi (opsional), Data (opsionale), Prioriteti (low, medium, high)
- Tasku eshte i lidhur te logged-in user (permes user_id qelesi i jashtem)
- Siguria kundrejt SQLi (prepared statements)
- Perdorimi i htmlspecialchars() ne te gjitha outputet (kundrejt XSS)
- Siguria qe useri eshte pronari i taskes para shikimit/editimit/fshirjes
- Faqja loadohet shpejte (<2s)
- Responsive ne laptop, tablet dhe telefon
- Nuk lejohet krijimi i me shume se 1 llogarie me 1 email
- Useri 1 nuk mund ti shoh taskat e Userit 2
- Ne deshtim mesazh i pergjithshem "Kredencialet e pavlefshme"
- Rate-Limiting max 5 tentativa per IP (15 minuta)

## Gjera shtese

- Kategori me ngjyra (shto, edito, fshi)
- Filtra sipas statusit dhe kategorise
- Kerkim ne detyra
- Statistika dhe progress bar ne dashboard
- Password strength ne signup
- Kyqje dhe regjistrim me Google, lidhje/shkputje e Google nga Settings
- Verifikim emaili, reset password, ndryshim emaili me konfirmim, dhe njoftim sigurie me link "anulo ndryshimin" (12 ore)
- Settings: ndrysho username, email, password, fshi llogarine
- Faqet Privacy Policy dhe Terms of Service
- **Passkeys (WebAuthn):** hyrje pa password me gjurme gishti, fytyre ose PIN te pajisjes. Shtohen/fshihen te Settings. Hyrja me passkey kerkon verifikim te userit ne pajisje, ndaj nuk kerkon kod 2FA nga emaili.
- **Kujtesat e afateve:** nje email permbledhes ne dite me detyrat e vonuara (deri 7 dite pas afatit), ato me afat sot dhe nesër. Aktivizohen/çaktivizohen te Settings.
- **Detyrat e vonuara:** banner ne krye te Dashboard-it, filtri "Te vonuara", rreshta te theksuar per te vonuarat/sot dhe etiketa "nesër".
- Verifikim ne 2 hapa (2FA) me kod ne email, tema e erret/e çelet, gjuha SQ/EN, njoftimi per cookie

## Siguria

- **Cookie e sesionit:** `HttpOnly`, `SameSite=Lax`, `Secure` kur faqja hapet me HTTPS, `use_strict_mode`, session ID vetem ne cookie. Cilesimet jane ne `includes/session.php`.
- **Headers e sigurise:** Content-Security-Policy (pa JS inline), `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, dhe HSTS ne HTTPS.
- **Mbyllja e sesioneve ne pajisje te tjera:** secili user ka `session_version` ne databaze. Kur passwordi ndryshohet ose rivendoset (ose anulohet ndryshimi nga emaili), ose kur useri shtyp "Mbyll sesionet e tjera" ne Settings, versioni rritet dhe te gjitha sesionet e tjera bien. Sesioni aktual mbetet i kyqur.
- **Rate limiting:** max 5 tentativa te gabuara per IP dhe max 10 per llogari ne 15 minuta. Kur faqja eshte pas proxy-t, vendos `TRUSTED_PROXIES` (perndryshe te gjithe userat shihen me te njejten IP).
- **Veprime te ndjeshme:** lidhja e Google, ndryshimi i emailit, shkeputja e Google dhe fshirja e llogarise kerkojne passwordin aktual; llogarite vetem me Google verifikojne perseri me Google (5 minuta) para se te vendosin password ose te fshijne llogarine. Logout eshte POST + CSRF.
- **Regjistrimi:** faqja nuk zbulon nese nje email ka llogari (pronari merr njoftim ne inbox). Llogarite e paverifikuara fshihen pas 3 diteve; nese dikush kyqet me Google me nje email te paverifikuar, passwordi i vendosur me pare behet i pavlefshem.
- CSRF token ne cdo formular POST, prepared statements kudo, tokenat e emailit ruhen vetem si hash SHA-256.

Shenim per zhvilluesit: mos shto `onclick`/`onsubmit` inline ose `<script>` inline, sepse CSP i bllokon. Per konfirmim para nje veprimi, vendos `data-confirm="Mesazhi"` ne `<form>` (e trajton `assets/script.js`).

## Passkeys

- Perdoret biblioteka `lbuchs/WebAuthn` v2.2.0 (MIT), e ngjitur ne `includes/lib/WebAuthn/`, ndaj nuk kerkon `composer`. Kerkon PHP 8.0+ me extension `openssl` dhe `mbstring`.
- Passkey-t lidhen me domenin e `APP_URL` (rpId). Ne produksion `APP_URL` duhet te jete domeni i vertete me HTTPS; nese e ndryshon domenin, passkey-t e vjetra nuk punojne me. Ne `localhost` punon edhe pa HTTPS.
- Ruhet vetem celesi publik (tabela `passkeys`). Shtimi dhe fshirja kerkojne passwordin aktual (llogarite vetem me Google verifikojne perseri me Google). Pas shtimit dergohet email njoftimi.
- Mbrojtjet: sfida (challenge) njehereshe me skadim 5 minuta, kontrollimi i origjines dhe rpId, verifikim i detyrueshem i userit, kontrolli i signature counter (kunder klonimit), rate limiting si te login me password, email i paverifikuar nuk hyn.
- Login pa shkruar email: browseri tregon passkey-t e ruajtura per faqen (discoverable credentials).

## Kujtesat e afateve (cron)

Emailet nuk dergohen vete: duhet nje cron job qe e ekzekuton `cron/send_reminders.php` **nje here ne dite** (p.sh. ora 08:00).

- Me PHP CLI (rekomandohet): `0 8 * * * php /rruga/e/projektit/cron/send_reminders.php`
- Pa CLI: vendos `CRON_KEY` ne `.env` dhe cakto cron me `curl -fsS "https://domaini-yt.com/cron/send_reminders.php?key=CRON_KEY"`. Pa `CRON_KEY` (ose me celes te gabuar) URL kthen 403.
- Cdo user merr max 1 email ne dite; dergohet vetem per userat me email te verifikuar qe i kane te aktivizuara kujtesat. Nese SMTP deshton, provohet perseri ne ekzekutimin e radhes. Max 100 email per ekzekutim. Gjuha e emailit eshte gjuha qe useri ka zgjedhur ne faqe.

## Perditesimi i nje instalimi ekzistues

1. Bej backup te databazes (Export ne phpMyAdmin) dhe te file-ve.
2. Ngarko file-t e rinj/ndryshuar. Nese perdor `composer`, nuk ka pakete te reja.
3. Importo `migration.sql` ne databazen e aplikacionit. Mund te ekzekutohet disa here pa problem. Shton `two_factor_codes`, `passkeys`, kolonat e kujtesave dhe gjuhes, rregullon `created_at` (nuk ndryshon me me cdo perditesim), shton `UNIQUE` te `google_id`, fshin kategorite jetime dhe shton çelesat e huaj qe mungonin.
4. Cakto cron-in per kujtesat (shiko me siper) dhe, nese perdor URL, shto `CRON_KEY` ne `.env`.
5. Provo: hyr, shto nje passkey te Settings, dil dhe hyr me passkey.

## Folderat

### /includes
- `session.php` - cilesimet e sigurta te sesionit dhe headers e sigurise (ngarkohet nga `config.php`)
- `functions.php` - funksione gjenerale (escape, CSRF, redirect, rate limiting, menaxhimi i sesioneve etj.)
- `mailer.php` - dergimi i emaileve (PHPMailer) dhe tokenat e emailit
- `navbar.php` - navbari
- `footer.php` - footer-i
- `auth-side.php` - paneli me preview ne faqet login/signup
- `legal-config.php` - emri, emaili dhe data per Privacy Policy dhe Terms of Service
- `passkeys.php` - helperat e passkeys (rpId, base64url, lista) dhe ngarkimi i bibliotekes
- `reminders.php` - mbledhja e detyrave me afat, ndertimi i emailit dhe ekzekutimi i kujtesave
- `lib/WebAuthn/` - biblioteka `lbuchs/WebAuthn` v2.2.0 (MIT)

### /cron
- `send_reminders.php` - dergon kujtesat e afateve (CLI ose URL me `CRON_KEY`)

### /tasks
Filet per menaxhimin e detyrave dhe kategorive: shtimi, fshirja, editimi dhe toggle (`add_task.php`, `edit_task.php`, `delete_task.php`, `toggle_task.php`, `add_category.php`, `edit_category.php`, `delete_category.php`).

### /assets
- `style.css` - gjithe stili i faqes
- `script.js` - JS i vogel (password strength, kategorite qe hapen, konfirmimet `data-confirm`, alert qe zhduket)
- `passkey.js` - login dhe regjistrim me passkey (WebAuthn), pa JS inline
- `logo.png` - logoja

## Files
- `config.php` - lidhja me databaze dhe leximi i `.env`
- `database.sql` - struktura e databazes per instalim te ri (pa te dhena)
- `migration.sql` - perditeson nje databaze ekzistuese ne strukturen e re (idempotent)
- `passkey_api.php` - endpoint JSON per passkeys (regjistrim dhe login)
- `dashboard.php` - faqja ku dergohet useri pas login
- `categories.php` - lista e kategorive
- `index.php` - faqja kryesore
- `login.php` / `logout.php` / `signup.php` - autentifikimi
- `google-callback.php` - kyqja/regjistrimi/lidhja me Google
- `verify.php` / `resend_verification.php` - verifikimi i emailit
- `forgot_password.php` / `reset_password.php` - rivendosja e passwordit
- `confirm_email.php` - konfirmimi i emailit te ri
- `revert.php` - anulimi i ndryshimit te emailit ose passwordit
- `settings.php` - cilesimet e llogarise
- `privacy.php` / `terms.php` - Politika e Privatesise dhe Kushtet e Sherbimit
- `LICENSE` - licenca (GNU GPL v3)

## Per ne te ardhmen
- Login/Signup me Apple
- Passkey gjate regjistrimit (tani shtohet te Settings pas krijimit te llogarise)
- 2FA me aplikacion authenticator (TOTP), pervec kodit ne email
- Detyra qe perseriten, eksport/import i detyrave