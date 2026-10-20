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
<nav class="navbar" id="siteNav" aria-label="Main">
    <div class="nav-bar">
        <a href="<?= $prefix ?>index.php" class="logo">
            <img src="<?= $prefix ?>assets/logo.png" alt="TaskStack" class="navbar-logo-img">
            <span>TaskStack</span>
        </a>
        <button type="button" class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="navMenu"
            aria-label="Menu">
            <span class="nav-toggle-bars" aria-hidden="true"></span>
        </button>
    </div>

    <div class="nav-menu" id="navMenu">
        <div class="nav-main">
            <a href="<?= $prefix ?>index.php"
                class="btn-outline <?= $current === 'index.php' ? 'btn-active' : '' ?>"><?= e(t('nav.home')) ?></a>
            <?php if ($isLoggedIn): ?>
                <a href="<?= $prefix ?>dashboard.php"
                    class="btn-outline <?= in_array($current, $task_pages) ? 'btn-active' : '' ?>"><?= e(t('nav.dashboard')) ?></a>
                <a href="<?= $prefix ?>categories.php"
                    class="btn-outline <?= in_array($current, $cat_pages) ? 'btn-active' : '' ?>"><?= e(t('nav.categories')) ?></a>
            <?php endif; ?>
        </div>

        <div class="nav-links">
            <div class="pref-controls">
                <form method="POST" action="<?= $prefix ?>set_pref.php" class="pref-form theme-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="type" value="theme">
                    <input type="hidden" name="value" value="<?= $cur_theme === 'dark' ? 'light' : 'dark' ?>">
                    <input type="hidden" name="next" value="<?= e($pref_next) ?>">
                    <button type="submit" class="theme-switch" role="switch" aria-checked="<?= $cur_theme === 'dark' ? 'true' : 'false' ?>"
                        title="<?= e($cur_theme === 'dark' ? t('nav.theme_light') : t('nav.theme_dark')) ?>"
                        aria-label="<?= e($cur_theme === 'dark' ? t('nav.theme_light') : t('nav.theme_dark')) ?>">
                        <span class="ts-track" aria-hidden="true">
                            <span class="ts-deco"></span>
                            <span class="ts-knob">
                                <svg class="ts-sun" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                    <circle cx="12" cy="12" r="4.2" fill="currentColor" />
                                    <path d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M18.7 5.3l-1.6 1.6M6.9 17.1l-1.6 1.6" />
                                </svg>
                                <svg class="ts-moon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                    <path d="M20.3 14.6A8.5 8.5 0 0 1 9.4 3.7a.6.6 0 0 0-.8-.7A9.5 9.5 0 1 0 21 15.4a.6.6 0 0 0-.7-.8z" />
                                </svg>
                            </span>
                        </span>
                    </button>
                </form>
                <form method="POST" action="<?= $prefix ?>set_pref.php" class="pref-form lang-form" data-lang="<?= e($cur_lang) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="type" value="lang">
                    <input type="hidden" name="next" value="<?= e($pref_next) ?>">
                    <button type="submit" name="value" value="sq" class="pref-btn <?= $cur_lang === 'sq' ? 'is-on' : '' ?>" title="<?= e(t('nav.lang')) ?>">AL</button>
                    <button type="submit" name="value" value="en" class="pref-btn <?= $cur_lang === 'en' ? 'is-on' : '' ?>" title="<?= e(t('nav.lang')) ?>">EN</button>
                </form>
            </div>

            <?php if ($isLoggedIn): ?>
                <span class="nav-user"><?= e(t('nav.hello')) ?>, <strong><?= e($username) ?></strong></span>
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
    </div>
</nav>