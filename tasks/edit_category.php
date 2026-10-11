<?php
// edit_category.php

require_once __DIR__ . '/../config.php'; // lidhja me databazen
require_once __DIR__ . '/../includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id = $_SESSION['user_id'];
$cat_id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors  = [];
$colors  = category_colors();

if ($cat_id <= 0) {
    $_SESSION['flash_error'] = 'Kategoria nuk u gjet.'; // nese id e kategorise eshte e pavlefshme, e shton nje gabim ne session dhe e kthen ne categories.php
    redirect('../categories.php'); // kthen ne categories.php
}

// Ngarko kategorine - VETEM nese i perket userit
$category = null;
try {
    $stmt = $conn->prepare(
        "SELECT id, name, color FROM categories WHERE id = ? AND user_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $cat_id, $user_id);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Load category error: " . $e->getMessage());
}

if (!$category) {
    $_SESSION['flash_error'] = 'Kategoria nuk u gjet.';
    redirect('../categories.php');
}

$old = [
    'name'  => $category['name'],
    'color' => safe_color($category['color']),
];

// Nese ngjyra aktuale nuk eshte ne liste (p.sh. Inbox) shtoje qe te mos humbasi
if (!in_array($old['color'], $colors, true)) {
    array_unshift($colors, $old['color']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.';
    } else {
        $old['name']  = trim($_POST['name'] ?? '');
        $old['color'] = $_POST['color'] ?? $old['color'];

        if ($old['name'] === '') {
            $errors[] = 'Emri i kategorise eshte i detyrueshem.';
        } elseif (mb_strlen($old['name']) > 50) {
            $errors[] = 'Emri nuk duhet te kaloje 50 karaktere.';
        }

        if (!in_array($old['color'], $colors, true)) {
            $errors[] = 'Ngjyre e pavlefshme.';
        }

        // Emri duhet te jete unik per userin (pervec kesaj kategorie)
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare(
                    "SELECT id FROM categories WHERE user_id = ? AND name = ? AND id <> ? LIMIT 1"
                );
                $stmt->bind_param("isi", $user_id, $old['name'], $cat_id);
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
                    "UPDATE categories SET name = ?, color = ? WHERE id = ? AND user_id = ?"
                );
                $stmt->bind_param("ssii", $old['name'], $old['color'], $cat_id, $user_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Kategoria u perditesua me sukses!';
                redirect('../categories.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Edit category error: " . $e->getMessage());
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
    <title>Edito kategorine - TaskStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="page-center">
    <div class="page-header">
        <p class="eyebrow">Ndryshime</p>
        <h1>Edito kategorine</h1>
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
        <input type="hidden" name="id"   value="<?= (int)$cat_id ?>">

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
            <button type="submit" class="btn">Ruaj ndryshimet</button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
