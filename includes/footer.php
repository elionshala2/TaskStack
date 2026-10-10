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
            <a href="<?= $f_prefix ?>index.php" class="logo">To-Do List</a>
            <p>Organizohu. Fokusohu. Arri me shume.</p>
        </div>

        <div class="footer-links">
            <a href="<?= $f_prefix ?>index.php">Home</a>
            <?php if ($f_loggedIn): ?>
                <a href="<?= $f_prefix ?>dashboard.php">Dashboard</a>
                <a href="<?= $f_prefix ?>categories.php">Kategorite</a>
                <a href="<?= $f_prefix ?>logout.php">Logout</a>
            <?php else: ?>
                <a href="<?= $f_prefix ?>login.php">Login</a>
                <a href="<?= $f_prefix ?>signup.php">Sign Up</a>
            <?php endif; ?>
        </div>

        <div class="footer-copy">
            &copy; <?= date('Y') ?> To-Do List. Te gjitha te drejtat e rezervuara. <!--perdor php per vitin-->
            Elion Shala
        </div>
    </div>
</footer>
<script src="<?= $f_prefix ?>assets/script.js"></script>
