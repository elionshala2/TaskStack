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
            <p>Krijo hapësirë për atë që ka rëndësi</p>
        </div>

        <div class="footer-links">
            <a href="<?= $f_prefix ?>index.php">Home</a>
            <?php if ($f_loggedIn): ?>
                <a href="<?= $f_prefix ?>dashboard.php">Dashboard</a>
                <a href="<?= $f_prefix ?>categories.php">Kategorite</a>
                <a href="<?= $f_prefix ?>privacy.php">Privacy Policy</a>
                <a href="<?= $f_prefix ?>terms.php">Terms of Service</a>
                <form method="POST" action="<?= $f_prefix ?>logout.php" class="logout-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a href="<?= $f_prefix ?>login.php">Login</a>
                <a href="<?= $f_prefix ?>signup.php">Sign Up</a>
                <a href="<?= $f_prefix ?>privacy.php">Privacy Policy</a>
                <a href="<?= $f_prefix ?>terms.php">Terms of Service</a>
            <?php endif; ?>
        </div>

        <div class="footer-copy">
            &copy; <?= date('Y') ?> TaskStack. Të gjitha të drejtat e rezervuara. <!--perdor php per vitin-->
            Elion Shala
        </div>
    </div>
</footer>
<script src="<?= $f_prefix ?>assets/script.js"></script>
