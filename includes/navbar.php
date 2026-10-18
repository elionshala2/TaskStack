<?php
// includes/navbar.php

if (session_status() === PHP_SESSION_NONE) { // nese sesioni nuk eshte startuar, e starton
    session_start();
}
if (isset($conn) && function_exists('validate_session')) { // sesion i vjeter hiqet para se te vendoset $isLoggedIn
    validate_session($conn);
}
$isLoggedIn = !empty($_SESSION['user_id']); // shikon nese perdoruesi eshte i kyqur ose jo
$username   = $_SESSION['username'] ?? ''; // merr userin e perdoruesit
$current    = basename($_SERVER['PHP_SELF']); // merr emrin e faqes aktuale per me e bo active linkun ne navbar

// nese jemi brenda folderit /tasks duhet ../ per linqet
$inTasks = str_contains($_SERVER['PHP_SELF'], '/tasks/');
$prefix  = $inTasks ? '../' : ''; // prefixi i shtuar eshte ../ nese jo osht that, p.sh. index.php, ose ../edit_task.php

$cat_pages  = ['categories.php', 'add_category.php', 'edit_category.php']; // faqet e kategorive per me bo active linkun ne navbar
$task_pages = ['dashboard.php', 'add_task.php', 'edit_task.php']; // faqet e tasks per me bo active linkun ne navbar
$pref_next  = $_SERVER['REQUEST_URI'] ?? '/'; // ku kthehet useri pas ndryshimit te temes/gjuhes
$cur_theme  = function_exists('pref_theme') ? pref_theme() : 'dark';
$cur_lang   = function_exists('pref_lang') ? pref_lang() : 'sq';
?>
<nav class="navbar">
    <div class="nav-left">
    <a href="<?= $prefix ?>index.php" class="logo">
        <img src="<?= $prefix ?>assets/logo.png" alt="TaskStack Logo" class="navbar-logo-img" style="height: 32px; width: auto; object-fit: contain; display: block;">
        <span>TaskStack</span>
    </a>

        <div class="nav-main">
            <a href="<?= $prefix ?>index.php"
               class="btn-outline <?= $current === 'index.php' ? 'btn-active' : '' ?>"><?= e(t('nav.home')) ?></a>

            <?php if ($isLoggedIn): ?> <!-- nese useri eshte i kyqur i tregon edhe linqet e dashboardit edhe te kategorive -->
                <a href="<?= $prefix ?>dashboard.php"
                   class="btn-outline <?= in_array($current, $task_pages) ? 'btn-active' : '' ?>"><?= e(t('nav.dashboard')) ?></a>

                <a href="<?= $prefix ?>categories.php"
                   class="btn-outline <?= in_array($current, $cat_pages) ? 'btn-active' : '' ?>"><?= e(t('nav.categories')) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="nav-links">
        <div class="pref-controls">
            <form method="POST" action="<?= $prefix ?>set_pref.php" class="pref-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="type" value="theme">
                <input type="hidden" name="value" value="<?= $cur_theme === 'dark' ? 'light' : 'dark' ?>">
                <input type="hidden" name="next" value="<?= e($pref_next) ?>">
                <button type="submit" class="pref-btn" title="<?= e($cur_theme === 'dark' ? t('nav.theme_light') : t('nav.theme_dark')) ?>"
                        aria-label="<?= e($cur_theme === 'dark' ? t('nav.theme_light') : t('nav.theme_dark')) ?>"><?= $cur_theme === 'dark' ? '&#9728;&#65039;' : '&#127769;' ?></button>
            </form>
            <form method="POST" action="<?= $prefix ?>set_pref.php" class="pref-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="type" value="lang">
                <input type="hidden" name="next" value="<?= e($pref_next) ?>">
                <button type="submit" name="value" value="sq" class="pref-btn <?= $cur_lang === 'sq' ? 'is-on' : '' ?>" title="<?= e(t('nav.lang')) ?>">AL</button>
                <button type="submit" name="value" value="en" class="pref-btn <?= $cur_lang === 'en' ? 'is-on' : '' ?>" title="<?= e(t('nav.lang')) ?>">EN</button>
            </form>
        </div>
        <?php if ($isLoggedIn): ?> <!-- nese useri eshte i kyqur i tregon edhe linqet e dashboardit edhe te kategorive -->
            <span class="nav-user"><?= e(t('nav.hello')) ?>, <strong><?= e($username) ?></strong></span><!--nese perdoruesi eshte i kyqur e tregon emrin e tij ketu-->
            <a href="<?= $prefix ?>settings.php"
               class="btn-outline <?= $current === 'settings.php' ? 'btn-active' : '' ?>"><?= e(t('nav.settings')) ?></a>
            <form method="POST" action="<?= $prefix ?>logout.php" class="logout-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn-outline"><?= e(t('nav.logout')) ?></button>
            </form>
        <?php else: ?>
            <a href="<?= $prefix ?>login.php"
               class="btn-outline <?= $current === 'login.php' ? 'btn-active' : '' ?>"><?= e(t('nav.login')) ?></a>
            <a href="<?= $prefix ?>signup.php"
               class="btn-outline <?= $current === 'signup.php' ? 'btn-active' : '' ?>"><?= e(t('nav.signup')) ?></a>
        <?php endif; ?>
    </div>
</nav>