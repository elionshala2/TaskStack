<?php
// includes/navbar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = !empty($_SESSION['user_id']);
$username   = $_SESSION['username'] ?? '';
$current    = basename($_SERVER['PHP_SELF']);

// nese jemi brenda folderit /tasks duhet ../ per linqet
$inTasks = str_contains($_SERVER['PHP_SELF'], '/tasks/');
$prefix  = $inTasks ? '../' : '';

$cat_pages  = ['categories.php', 'add_category.php', 'edit_category.php'];
$task_pages = ['dashboard.php', 'add_task.php', 'edit_task.php'];
?>
<nav class="navbar">
    <div class="nav-left">
        <a href="<?= $prefix ?>index.php" class="logo">To-Do List</a>

        <div class="nav-main">
            <a href="<?= $prefix ?>index.php"
               class="btn-outline <?= $current === 'index.php' ? 'btn-active' : '' ?>">Home</a>

            <?php if ($isLoggedIn): ?>
                <a href="<?= $prefix ?>dashboard.php"
                   class="btn-outline <?= in_array($current, $task_pages) ? 'btn-active' : '' ?>">Dashboard</a>

                <a href="<?= $prefix ?>categories.php"
                   class="btn-outline <?= in_array($current, $cat_pages) ? 'btn-active' : '' ?>">Kategorite</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="nav-links">
        <?php if ($isLoggedIn): ?>
            <span class="nav-user">Pershendetje, <strong><?= e($username) ?></strong></span>
            <a href="<?= $prefix ?>logout.php" class="btn-outline">Logout</a>
        <?php else: ?>
            <a href="<?= $prefix ?>login.php"
               class="btn-outline <?= $current === 'login.php' ? 'btn-active' : '' ?>">Login</a>
            <a href="<?= $prefix ?>signup.php"
               class="btn-outline <?= $current === 'signup.php' ? 'btn-active' : '' ?>">Sign Up</a>
        <?php endif; ?>
    </div>
</nav>
