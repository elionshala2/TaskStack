<?php
// add_category.php

require_once __DIR__ . '/../config.php'; // lidhja me databazen
require_once __DIR__ . '/../includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id = $_SESSION['user_id']; // merr user_id nga sessioni
$errors  = []; // array per te ruajtur gabimet e validimit
$colors  = category_colors(); // array me ngjyrat e lejuara per kategorite
$old = [ // array per te ruajtur vlerat e derguara nga useri ne rast gabimi
    'name'  => '',
    'color' => $colors[0],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // kontrollon nese forma eshte derguar me POST

    if (!verify_csrf($_POST['csrf'] ?? '')) { // kontrollon nese tokeni CSRF eshte valid, nese jo e shton nje gabim ne array
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $old['name']  = trim($_POST['name'] ?? '');
        $old['color'] = $_POST['color'] ?? $colors[0];

        // Validime
        if ($old['name'] === '') { // nese emri i kategorise eshte bosh, e shton nje gabim ne array
            $errors[] = 'Emri i kategorise eshte i detyrueshem.';
        } elseif (mb_strlen($old['name']) > 50) { // nese emri i kategorise eshte me i gjate se 50 karaktere, e shton nje gabim ne array
            $errors[] = 'Emri nuk duhet te kaloje 50 karaktere.';
        }

        if (!in_array($old['color'], $colors, true)) {
            $errors[] = 'Ngjyre e pavlefshme.'; // nese ngjyra e zgjedhur nuk eshte ne array me ngjyrat e lejuara, e shton nje gabim ne array
        }

        // Mos lejo dy kategori me te njejtin emer per te njejtin user
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare(
                    "SELECT id FROM categories WHERE user_id = ? AND name = ? LIMIT 1"
                );
                $stmt->bind_param("is", $user_id, $old['name']); // kontrollon nese ekziston nje kategori me te njejtin emer per te njejtin user
                $stmt->execute(); // ekzekuton query-n
                $stmt->store_result(); // ruan rezultatin e query-t ne memorie per te kontrolluar numrin e rreshtave
                if ($stmt->num_rows > 0) { // nese ekziston nje kategori me te njejtin emer per te njejtin user, e shton nje gabim ne array
                    $errors[] = 'Ke tashme nje kategori me kete emer.'; // nese ekziston nje kategori me te njejtin emer per te njejtin user, e shton nje gabim ne array
                }
                $stmt->close(); // mbyll statementin
            } catch (mysqli_sql_exception $e) { // nese ka nje gabim ne query, e shton nje gabim ne array dhe e logon gabimin ne error_log
                error_log("Check category error: " . $e->getMessage()); // nese ka nje gabim ne query, e shton nje gabim ne array dhe e logon gabimin ne error_log
                $errors[] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)" // shton nje kategori te re per userin aktual
                );
                $stmt->bind_param("iss", $user_id, $old['name'], $old['color']);
                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Kategoria u shtua me sukses!';
                redirect('../categories.php');
            } catch (mysqli_sql_exception $e) {
                error_log("Add category error: " . $e->getMessage());
                $errors[] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shto kategori - TaskStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?> <!-- e perfshin navbarin nga includes/navbar.php -->

    <main class="page-center">
        <div class="page-header">
            <p class="eyebrow">Kategori e re</p>
            <h1>Shto kategori</h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $err): ?>
                    <p><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="task-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <label>Emri *</label>
            <input type="text" name="name" maxlength="50"
                value="<?= e($old['name']) ?>" required autofocus>

            <label>Ngjyra</label>
            <div class="color-picker">
                <?php foreach ($colors as $c): ?>
                    <label class="color-opt" style="--c: <?= e($c) ?>;">
                        <input type="radio" name="color" value="<?= e($c) ?>" <?= $old['color'] === $c ? 'checked' : '' ?>>
                        <span></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <a href="../categories.php" class="btn-outline">Anulo</a>
                <button type="submit" class="btn">Ruaj</button>
            </div>
        </form>
    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?> <!-- e perfshin footerin nga includes/footer.php -->
</body>

</html>