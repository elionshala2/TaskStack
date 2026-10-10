# To-Do-List
Nje web aplikacion per menaxhimin e detyrave me autentifikim perdoruesi, i ndertuar me HTML, CSS, Javascript, PHP dhe MySQL.

## Si ta nisesh
1. Hap XAMPP (Apache + MySQL)
2. Importo `database.sql` ne phpMyAdmin (krijon databazen `todolist` dhe tabelat)
3. Vendose folderin e projektit ne `htdocs`
4. Hap `http://localhost/to-do-list/`

## Kerkesat
- Faqja Kryesore index.php, qe e fton perdoruesin me kriju llogari
- Faqja Login dhe Signup
- Faqja Signup kerkon username, email, password, cpassword
- Passwordet enkriptohen para se te ruhen ne databaze
- Rigjenerimi i session ID ne secilin login
- Per krijimin e taskut te ri kerkohet Titulli, Pershkrimi (opsional), Data (opsionale), Prioriteti (low, medium, high)
- Tasku eshte i lidhur te logged-in user (permes user_id qelesi i jashtem)
- Siguria kundrejt SQLi (prepared statements)
- Perdorimi i htmlspecialchars() ne te gjitha outputet (kundrejt XSS)
- Siguria qe useri eshte pronari i taskes para shikimit/editit/fshirjes
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

## Folderat

### /includes
- `functions.php` - funksione gjenerale (escape, CSRF, redirect, rate limiting etj.)
- `navbar.php` - navbari
- `footer.php` - footer-i

### /tasks
Filet per menaxhimin e detyrave dhe kategorive: shtimi, fshirja, editimi dhe toggle.

### /assets
- `style.css` - gjithe stili i faqes
- `script.js` - JS i vogel (password strength, kategorite qe hapen)

## Files
- `config.php` - lidhja me databaze
- `database.sql` - struktura e databazes
- `dashboard.php` - faqja ku dergohet useri pas login
- `categories.php` - lista e kategorive
- `index.php` - faqja kryesore
- `login.php` / `logout.php` / `signup.php` - autentifikimi
- `LICENSE` - licenca e perdorimit

## Per ne te ardhmen
- Login/Signup me Google, Apple, Passkey
