<?php
// includes/footer.php

// footer-i perdoret edhe nga faqet ne /tasks, prandaj duhet prefix
$f_inTasks  = str_contains($_SERVER['PHP_SELF'], '/tasks/');
$f_prefix   = $f_inTasks ? '../' : '';
$f_loggedIn = !empty($_SESSION['user_id']);
?>
<footer class="site-footer">
    <div class="footer-glow" aria-hidden="true"></div>
    <div class="footer-inner">
        <div class="footer-brand">
            <a href="<?= $f_prefix ?>index.php" class="logo">
                <img src="<?= $f_prefix ?>assets/logo.png" alt="" class="navbar-logo-img">
                <span>TaskStack</span>
            </a>
            <p><?= e(t('footer.tagline')) ?></p>
        </div>

        <nav class="footer-col" aria-label="<?= e(t('footer.product')) ?>">
            <h4><?= e(t('footer.product')) ?></h4>
            <a href="<?= $f_prefix ?>index.php"><?= e(t('nav.home')) ?></a>
            <a href="<?= $f_prefix ?>features.php"><?= e(t('footer.features')) ?></a>
            <?php if ($f_loggedIn): ?>
                <a href="<?= $f_prefix ?>dashboard.php"><?= e(t('nav.dashboard')) ?></a>
                <a href="<?= $f_prefix ?>categories.php"><?= e(t('nav.categories')) ?></a>
            <?php endif; ?>
        </nav>

        <nav class="footer-col" aria-label="<?= e(t('footer.account')) ?>">
            <h4><?= e(t('footer.account')) ?></h4>
            <?php if ($f_loggedIn): ?>
                <a href="<?= $f_prefix ?>settings.php"><?= e(t('nav.settings')) ?></a>
                <form method="POST" action="<?= $f_prefix ?>logout.php" class="logout-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit"><?= e(t('nav.logout')) ?></button>
                </form>
            <?php else: ?>
                <a href="<?= $f_prefix ?>login.php"><?= e(t('nav.login')) ?></a>
                <a href="<?= $f_prefix ?>signup.php"><?= e(t('nav.signup')) ?></a>
            <?php endif; ?>
        </nav>

        <nav class="footer-col" aria-label="<?= e(t('footer.legal')) ?>">
            <h4><?= e(t('footer.legal')) ?></h4>
            <a href="<?= $f_prefix ?>privacy.php"><?= e(t('footer.privacy')) ?></a>
            <a href="<?= $f_prefix ?>terms.php"><?= e(t('footer.terms')) ?></a>
            <h4 style="margin-top:10px"><?= e(t('footer.language')) ?></h4>
            <a href="<?= $f_prefix ?>index.php?lang=sq" hreflang="sq" lang="sq">Shqip</a>
            <a href="<?= $f_prefix ?>index.php?lang=en" hreflang="en" lang="en">English</a>
        </nav>

        <div class="footer-copy">
            <span>&copy; <?= date('Y') ?> TaskStack. <?= e(t('footer.rights')) ?></span>
            <span>Elion Shala</span>
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