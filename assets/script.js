// assets/script.js
// JS i vogel per faqen: password strength, kategorite qe hapen, alert qe zhduket

document.addEventListener('DOMContentLoaded', function () {

    // ---------- 1. Signup: password strength + perputhja ----------
    var pw      = document.getElementById('password');
    var cpw     = document.getElementById('cpassword');
    var bar     = document.getElementById('strengthBar');
    var pwHint  = document.getElementById('pwHint');
    var mHint   = document.getElementById('matchHint');

    if (pw && bar) {
        pw.addEventListener('input', function () {
            var v = pw.value;
            var score = 0;

            if (v.length >= 8) score++;
            if (/[A-Za-z]/.test(v) && /\d/.test(v)) score++;
            if (v.length >= 12) score++;
            if (/[^A-Za-z0-9]/.test(v) && /[A-Z]/.test(v)) score++;

            var colors = ['#ef4444', '#f59e0b', '#22c55e', '#06b6d4'];
            var labels = ['Shume i dobet', 'Mesatar', 'I mire', 'Shume i forte'];

            if (v.length === 0) {
                bar.style.width = '0';
                pwHint.textContent = 'Min 8 karaktere, shkronje dhe numer.';
                return;
            }

            var i = Math.max(score - 1, 0);
            bar.style.width = (score * 25) + '%';
            bar.style.background = colors[i];
            pwHint.textContent = labels[i];
        });
    }

    if (pw && cpw && mHint) {
        function checkMatch() {
            if (cpw.value === '') {
                mHint.textContent = '';
                return;
            }
            if (pw.value === cpw.value) {
                mHint.textContent = 'Password-et perputhen';
                mHint.className = 'hint ok';
            } else {
                mHint.textContent = 'Password-et nuk perputhen';
                mHint.className = 'hint bad';
            }
        }
        pw.addEventListener('input', checkMatch);
        cpw.addEventListener('input', checkMatch);
    }

    // ---------- 2. Kategorite: hap / mbyll listen e detyrave ----------
    document.querySelectorAll('.cat-card').forEach(function (card) {
        var head = card.querySelector('.cat-head');
        if (!head) return;

        function toggle() {
            var open = card.classList.toggle('open');
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        head.addEventListener('click', function (e) {
            if (e.target.closest('.cat-actions')) return;
            toggle();
        });

        head.addEventListener('keydown', function (e) {
            if (e.target !== head) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    });

    // ---------- 3. Mesazhet e suksesit zhduken pas 4 sekondash ----------
    document.querySelectorAll('.alert.success').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s, transform 0.4s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-6px)';
            setTimeout(function () { el.remove(); }, 450);
        }, 4000);
    });

    // ---------- 4. Numeruesi i karaktereve per titullin ----------
    var title = document.querySelector('input[name="title"][maxlength]');
    var counter = document.getElementById('titleCount');
    if (title && counter) {
        function upd() { counter.textContent = title.value.length + '/' + title.maxLength; }
        title.addEventListener('input', upd);
        upd();
    }

    // ---------- 5. Konfirmim para veprimeve te rrezikshme (data-confirm) ----------
    // Zevendeson onsubmit="return confirm(...)" inline, qe Content-Security-Policy e bllokon.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // ---------- 6. Navbar mobile: hap / mbyll menune ----------
    var nav = document.getElementById('siteNav');
    var burger = document.getElementById('navToggle');
    if (nav && burger) {
        function setNav(open) {
            nav.classList.toggle('nav-open', open);
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('nav-locked', open);
        }
        burger.addEventListener('click', function () {
            setNav(!nav.classList.contains('nav-open'));
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setNav(false);
        });
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target)) setNav(false);
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) setNav(false);
        });
    }

    // ---------- 7. Theme switch: animacion para se te ruhet preferenca ----------
    document.querySelectorAll('form.theme-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var next = form.querySelector('input[name="value"]');
            if (!next || form.dataset.busy) return;
            e.preventDefault();
            form.dataset.busy = '1';
            var root = document.documentElement;
            root.classList.add('theme-anim');
            form.classList.add('is-switching');
            root.setAttribute('data-theme', next.value);
            var sw = form.querySelector('.theme-switch');
            if (sw) sw.setAttribute('aria-checked', next.value === 'dark' ? 'true' : 'false');
            setTimeout(function () { form.submit(); }, 650);
        });
    });

    // ---------- 8. Language switch: pill-i rrshqet para se te ruhet gjuha ----------
    document.querySelectorAll('form.lang-form').forEach(function (form) {
        form.querySelectorAll('button[name="value"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (form.dataset.busy) return;
                var lang = btn.value;
                if (form.getAttribute('data-lang') === lang) return;
                form.dataset.busy = '1';
                form.setAttribute('data-lang', lang);
                form.querySelectorAll('button[name="value"]').forEach(function (b) {
                    b.classList.toggle('is-on', b === btn);
                });
                document.body.classList.add('page-leaving');
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'value';
                hidden.value = lang;
                form.appendChild(hidden);
                setTimeout(function () { form.submit(); }, 380);
            });
        });
    });
});
