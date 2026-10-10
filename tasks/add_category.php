<?php
// add_category.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
require_login();

$user_id = $_SESSION['user_id'];
$errors  = [];
$colors  = category_colors();
$old = [
    'name'  => '',
    'color' => $colors[0],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $old['name']  = trim($_POST['name'] ?? '');
        $old['color'] = $_POST['color'] ?? $colors[0];

        // Validime
        if ($old['name'] === '') {
            $errors[] = 'Emri i kategorise eshte i detyrueshem.';
        } elseif (mb_strlen($old['name']) > 50) {
            $errors[] = 'Emri nuk duhet te kaloje 50 karaktere.';
        }

        if (!in_array($old['color'], $colors, true)) {
            $errors[] = 'Ngjyre e pavlefshme.';
        }

        // Mos lejo dy kategori me te njejtin emer per te njejtin user
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare(
                    "SELECT id FROM categories WHERE user_id = ? AND name = ? LIMIT 1"
                );
                $stmt->bind_param("is", $user_id, $old['name']);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = 'Ke tashme nje kategori me kete emer.';
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                error_log("Check category error: " . $e->getMessage());
                $errors[] = 'Diqka shkoi gabim. Provo perseri.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)"
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
    <title>Shto kategori - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
