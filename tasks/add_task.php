<?php
// add_task.php

require_once __DIR__ . '/../config.php'; // lidhja me databazen dhe konfigurimet e tjera
require_once __DIR__ . '/../includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillimi i sesionit per session id
require_login(); // nese perdoruesi nuk eshte i kyqur e bon redirect ne faqen e login.php

$user_id = $_SESSION['user_id']; // merr id e perdoruesit aktual nga sesioni
$errors  = [];
$old = [ // vlerat e vjetra te formit per me i mbush formet nese ka gabime
    'title'       => '',
    'description' => '',
    'priority'    => 'medium',
    'due_date'    => '',
    'category_id' => '',
];

// Ngarko kategorite e userit
$categories = [];
try {
    $stmt = $conn->prepare(
        "SELECT id, name, color FROM categories WHERE user_id = ? ORDER BY name ASC" // merr kategorite e userit aktual nga databaza
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Load categories error: " . $e->getMessage()); // nese ka nje gabim ne query, e logon gabimin ne error_log
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $errors[] = 'Kerkese e pavlefshme. Provo perseri.'; // nese tokeni CSRF nuk eshte valid, e shton nje gabim ne array
    } else {
        $old['title']       = trim($_POST['title'] ?? '');
        $old['description'] = trim($_POST['description'] ?? '');
        $old['priority']    = $_POST['priority'] ?? 'medium';
        $old['due_date']    = trim($_POST['due_date'] ?? '');
        $old['category_id'] = $_POST['category_id'] ?? '';

        // Validime
        if ($old['title'] === '') {
            $errors[] = 'Titulli eshte i detyrueshem.';
        } elseif (mb_strlen($old['title']) > 255) {
            $errors[] = 'Titulli nuk duhet te kaloje 255 karaktere.';
        }

        if (!in_array($old['priority'], ['low', 'medium', 'high'], true)) {
            $errors[] = 'Prioritet i pavlefshem.';
        }

        if ($old['due_date'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $old['due_date']);
            if (!$d || $d->format('Y-m-d') !== $old['due_date']) {
                $errors[] = 'Date e pavlefshme.';
            }
        }

        // Kategoria duhet te jete e userit aktual (jo e dikujt tjeter)
        $category_id = !empty($old['category_id']) ? (int)$old['category_id'] : null;
        if ($category_id !== null && !in_array($category_id, array_map('intval', array_column($categories, 'id')), true)) {
            $errors[] = 'Kategori e pavlefshme.';
        }

        if (empty($errors)) {
            try {
                $due = $old['due_date'] === '' ? null : $old['due_date'];

                $stmt = $conn->prepare(
                    "INSERT INTO tasks (user_id, title, description, priority, due_date, category_id)
                     VALUES (?, ?, ?, ?, ?, ?)" // shton nje detyre te re per userin aktual
                );

                $stmt->bind_param(
                    "issssi",
                    $user_id,
                    $old['title'],
                    $old['description'],
                    $old['priority'],
                    $due,
                    $category_id
                );

                $stmt->execute();
                $stmt->close();

                $_SESSION['flash_success'] = 'Detyra u shtua me sukses!';
                redirect('../dashboard.php');

            } catch (mysqli_sql_exception $e) {
                error_log("Add task error: " . $e->getMessage());
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
    <meta name="description" content="TaskStack - Organizohu, fokusohu, arri me shume.">
    <title>Shto detyre - TaskStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="page-center">
    <div class="page-header">
        <p class="eyebrow">Detyre e re</p>
        <h1>Shto detyre te re</h1>
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

        <label>Titulli * <span class="counter" id="titleCount"></span></label>
        <input type="text" name="title" maxlength="255"
               value="<?= e($old['title']) ?>" required autofocus>

        <label>Pershkrimi (opsional)</label>
        <textarea name="description" rows="4"><?= e($old['description']) ?></textarea>

        <div class="form-row">
            <div>
                <label>Prioriteti</label>
                <select name="priority">
                    <option value="low"    <?= $old['priority'] === 'low'    ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $old['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high"   <?= $old['priority'] === 'high'   ? 'selected' : '' ?>>High</option>
                </select>
            </div>
            <div>
                <label>Data (opsionale)</label>
                <input type="date" name="due_date" value="<?= e($old['due_date']) ?>">
            </div>
        </div>

        <label>Kategoria</label>
        <select name="category_id">
            <option value="">-- Pa kategori --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= (string)$old['category_id'] === (string)$cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="form-actions">
            <a href="../dashboard.php" class="btn-outline">Anulo</a>
            <button type="submit" class="btn">Ruaj</button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
