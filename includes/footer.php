<?php
// includes/footer.php

// footer-i perdoret edhe nga faqet ne /tasks, prandaj duhet prefix
$f_inTasks   = str_contains($_SERVER['PHP_SELF'], '/tasks/'); // e shikon nese perdoruesi eshte ne folderin /tasks per me ndrru direktorin ne linqet e footerit
$f_prefix    = $f_inTasks ? '../' : ''; //  operator ternar per prefix, nese perdoruesi eshte ne /tasks prefixi osht ../ nese jo osht that
$f_loggedIn  = !empty($_SESSION['user_id']); // shikon neser perdoruesi eshte i kyqur ose jo
// nese perdoruesi osht i kyqur i tregon edhe linqet e dashboardit, kategorise edhe te logoutit nese jo i tregon linqet e loginit edhe te regjistrimit
?> 
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <a href="<?= $f_prefix ?>index.php" class="logo">TaskStack</a>
            <p><?= e(t('footer.tagline')) ?></p>
        </div>

        <div class="footer-links">
            <a href="<?= $f_prefix ?>index.php">Home</a>
            <?php if ($f_loggedIn): ?>
                <a href="<?= $f_prefix ?>dashboard.php"><?= e(t('nav.dashboard')) ?></a>
                <a href="<?= $f_prefix ?>categories.php"><?= e(t('nav.categories')) ?></a>
                <a href="<?= $f_prefix ?>privacy.php"><?= e(t('footer.privacy')) ?></a>
                <a href="<?= $f_prefix ?>terms.php"><?= e(t('footer.terms')) ?></a>
                <form method="POST" action="<?= $f_prefix ?>logout.php" class="logout-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit"><?= e(t('nav.logout')) ?></button>
                </form>
            <?php else: ?>
                <a href="<?= $f_prefix ?>login.php"><?= e(t('nav.login')) ?></a>
                <a href="<?= $f_prefix ?>signup.php"><?= e(t('nav.signup')) ?></a>
                <a href="<?= $f_prefix ?>privacy.php"><?= e(t('footer.privacy')) ?></a>
                <a href="<?= $f_prefix ?>terms.php"><?= e(t('footer.terms')) ?></a>
            <?php endif; ?>
        </div>

        <div class="footer-copy">
            &copy; <?= date('Y') ?> TaskStack. <?= e(t('footer.rights')) ?> <!--perdor php per vitin-->
            Elion Shala
        </div>
    </div>
</footer>
<?php if (empty($_COOKIE['ts_cookie_ok'])): ?>
<div class="cookie-banner" role="dialog" aria-live="polite" aria-label="Cookies">
    <p><?= e(t('cookie.text')) ?> <a href="<?= $f_prefix ?>privacy.php"><?= e(t('cookie.more')) ?></a></p>
    <form method="POST" action="<?= $f_prefix ?>set_pref.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="type" value="cookie_consent">
        <input type="hidden" name="next" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
        <button type="submit" class="btn"><?= e(t('cookie.accept')) ?></button>
    </form>
</div>
<?php endif; ?>
<script src="<?= $f_prefix ?>assets/script.js"></script>
