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
?>
<nav class="navbar">
    <div class="nav-left">
    <a href="<?= $prefix ?>index.php" class="logo">
        <img src="<?= $prefix ?>assets/logo.png" alt="TaskStack Logo" class="navbar-logo-img" style="height: 32px; width: auto; object-fit: contain; display: block;">
        <span>TaskStack</span>
    </a>

        <div class="nav-main">
            <a href="<?= $prefix ?>index.php"
               class="btn-outline <?= $current === 'index.php' ? 'btn-active' : '' ?>">Home</a>

            <?php if ($isLoggedIn): ?> <!-- nese useri eshte i kyqur i tregon edhe linqet e dashboardit edhe te kategorive -->
                <a href="<?= $prefix ?>dashboard.php"
                   class="btn-outline <?= in_array($current, $task_pages) ? 'btn-active' : '' ?>">Dashboard</a>

                <a href="<?= $prefix ?>categories.php"
                   class="btn-outline <?= in_array($current, $cat_pages) ? 'btn-active' : '' ?>">Kategorite</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="nav-links">
        <?php if ($isLoggedIn): ?> <!-- nese useri eshte i kyqur i tregon edhe linqet e dashboardit edhe te kategorive -->
            <span class="nav-user">Pershendetje, <strong><?= e($username) ?></strong></span><!--nese perdoruesi eshte i kyqur e tregon emrin e tij ketu-->
            <a href="<?= $prefix ?>settings.php"
               class="btn-outline <?= $current === 'settings.php' ? 'btn-active' : '' ?>">Settings</a>
            <a href="<?= $prefix ?>logout.php" class="btn-outline">Logout</a>
        <?php else: ?>
            <a href="<?= $prefix ?>login.php"
               class="btn-outline <?= $current === 'login.php' ? 'btn-active' : '' ?>">Login</a>
            <a href="<?= $prefix ?>signup.php"
               class="btn-outline <?= $current === 'signup.php' ? 'btn-active' : '' ?>">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>