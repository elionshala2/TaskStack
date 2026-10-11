# TaskStack

Nje web aplikacion per menaxhimin e detyrave me autentifikim perdoruesi, i ndertuar me HTML, CSS, JavaScript, PHP dhe MySQL.

## Si ta nisesh

1. Hap XAMPP (Apache + MySQL). Duhet PHP 8.0 ose me i ri.
2. Vendose folderin e projektit ne `htdocs` me emrin `TaskStack` (kujdes shkronjat e medha, duhet te perputhen me `APP_URL`).
3. Importo `database.sql` ne phpMyAdmin (krijon databazen `todolist` dhe tabelat).
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
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `SMTP_FROM_NAME` | Per emailet (verifikim, reset password, njoftime sigurie). Me Gmail perdor "App Password" dhe port `587` me `SMTP_SECURE=tls` (ose `465` me `ssl`). |

Mos e commit-o `.env` kurre (eshte ne `.gitignore`). Nese e ke ndare me dike, ndrysho sekretet.

## Perditesim i nje instalimi ekzistues

Nese e ke databazen krijuar me nje version me te vjeter, ekzekuto kete ne phpMyAdmin (tab SQL), para se te ngarkosh kodin e ri:

```sql
USE todolist;
ALTER TABLE regjistrimi
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER google_id;
```

Pas perditesimit, te gjithe userat kyqen perseri nje here.

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

## Siguria

- **Cookie e sesionit:** `HttpOnly`, `SameSite=Lax`, `Secure` kur faqja hapet me HTTPS, `use_strict_mode`, session ID vetem ne cookie. Cilesimet jane ne `includes/session.php`.
- **Headers e sigurise:** Content-Security-Policy (pa JS inline), `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, dhe HSTS ne HTTPS.
- **Mbyllja e sesioneve ne pajisje te tjera:** secili user ka `session_version` ne databaze. Kur passwordi ndryshohet ose rivendoset (ose anulohet ndryshimi nga emaili), ose kur useri shtyp "Mbyll sesionet e tjera" ne Settings, versioni rritet dhe te gjitha sesionet e tjera bien. Sesioni aktual mbetet i kyqur.
- CSRF token ne cdo formular POST, prepared statements kudo, tokenat e emailit ruhen vetem si hash SHA-256.

Shenim per zhvilluesit: mos shto `onclick`/`onsubmit` inline ose `<script>` inline, sepse CSP i bllokon. Per konfirmim para nje veprimi, vendos `data-confirm="Mesazhi"` ne `<form>` (e trajton `assets/script.js`).

## Folderat

### /includes
- `session.php` - cilesimet e sigurta te sesionit dhe headers e sigurise (ngarkohet nga `config.php`)
- `functions.php` - funksione gjenerale (escape, CSRF, redirect, rate limiting, menaxhimi i sesioneve etj.)
- `mailer.php` - dergimi i emaileve (PHPMailer) dhe tokenat e emailit
- `navbar.php` - navbari
- `footer.php` - footer-i
- `auth-side.php` - paneli me preview ne faqet login/signup
- `legal-config.php` - emri, emaili dhe data per Privacy Policy dhe Terms of Service

### /tasks
Filet per menaxhimin e detyrave dhe kategorive: shtimi, fshirja, editimi dhe toggle (`add_task.php`, `edit_task.php`, `delete_task.php`, `toggle_task.php`, `add_category.php`, `edit_category.php`, `delete_category.php`).

### /assets
- `style.css` - gjithe stili i faqes
- `script.js` - JS i vogel (password strength, kategorite qe hapen, konfirmimet `data-confirm`, alert qe zhduket)
- `logo.png` - logoja

## Files
- `config.php` - lidhja me databaze dhe leximi i `.env`
- `database.sql` - struktura e databazes
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
- Login/Signup me Apple dhe Passkey
- Autentifikim me dy faktore (2FA)