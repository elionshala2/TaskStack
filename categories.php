<?php
// categories.php

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id  = $_SESSION['user_id']; // merr user_id nga sessioni
$username = $_SESSION['username']; // merr username nga sessioni

// Merr te gjitha kategorite e userit
$sql = "SELECT id, name, color, is_default, created_at
        FROM categories
        WHERE user_id = ?
        ORDER BY is_default DESC, created_at DESC";

$categories = []; // array per te ruajtur kategorite e userit

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Categories error: " . $e->getMessage());
    $categories = [];
}

// Merr detyrat e userit qe kane kategori (nje query per te gjitha, grupohen sipas category_id)
$tasks_by_category = [];

if (!empty($categories)) {
    try {
        $stmt = $conn->prepare(
            "SELECT id, category_id, title, description, priority, due_date, is_completed
             FROM tasks
             WHERE user_id = ? AND category_id IS NOT NULL
             ORDER BY id DESC"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $tasks_by_category[(int)$row['category_id']][] = $row;
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log("Category tasks error: " . $e->getMessage());
        $tasks_by_category = [];
    }
}

$priority_labels = [
    'low'    => 'Ulet',
    'medium' => 'Mesatar',
    'high'   => 'Larte',
];

// flash nga veprimet (add/edit/delete)
$flash = '';
$flash_error = '';
if (!empty($_SESSION['flash_success'])) {
    $flash = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorite - To-Do List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<main class="dashboard">

    <div class="dashboard-header">
        <div>
            <p class="eyebrow">Organizimi</p>
            <h1>Kategorite e mia</h1>
        </div>
        <a href="tasks/add_category.php" class="btn">+ Shto kategori</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="alert error"><p><?= e($flash_error) ?></p></div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="empty-state">
            <p class="empty-icon">&#128193;</p>
            <p>Nuk ka kategori ende.</p>
            <p><a href="tasks/add_category.php">Shto kategorine e pare</a></p>
        </div>
    <?php else: ?>
        <ul class="cat-list">
            <?php foreach ($categories as $i => $category):
                $cid   = (int)$category['id'];
                $color = safe_color($category['color'] ?? null);

                $cat_tasks = $tasks_by_category[$cid] ?? [];
                // te pakryerat fillimisht
                usort($cat_tasks, fn($a, $b) => (int)$a['is_completed'] <=> (int)$b['is_completed']);
                $count = count($cat_tasks);
                $isDefault = (int)$category['is_default'] === 1;
            ?>
                <li class="cat-card" style="--cat: <?= e($color) ?>; animation-delay: <?= min($i, 5) * 0.04 ?>s;">

                    <div class="cat-head" role="button" tabindex="0"
                         aria-expanded="false" aria-controls="cat-panel-<?= $cid ?>">
                        <span class="cat-chev"></span>

                        <div class="cat-main">
                            <span class="category-badge">
                                <span class="dot"></span>
                                <span class="name"><?= e($category['name']) ?></span>
                            </span>
                            <div class="cat-meta">
                                <span>&#128338; <?= e(date('d.m.Y', strtotime($category['created_at']))) ?></span>
                                <?php if ($isDefault): ?><span>Default</span><?php endif; ?>
                            </div>
                        </div>

                        <span class="cat-count"><?= $count ?> <?= $count === 1 ? 'detyre' : 'detyra' ?></span>

                        <div class="cat-actions">
                            <a href="tasks/edit_category.php?id=<?= $cid ?>" class="icon-btn" title="Edito">&#9999;&#65039;</a>

                            <?php if (!$isDefault): ?>
                                <form action="tasks/delete_category.php" method="POST" class="inline-form"
                                      onsubmit="return confirm('Fshij kete kategori? Detyrat do mbeten pa kategori.');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id"   value="<?= $cid ?>">
                                    <button type="submit" class="icon-btn" title="Fshij">&#128465;&#65039;</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cat-panel" id="cat-panel-<?= $cid ?>">
                        <div class="cat-panel-inner">
                            <?php if ($count === 0): ?>
                                <div class="cat-empty"><span>Nuk ka detyra ne kete kategori.</span></div>
                            <?php else: ?>
                                <ul class="cat-tasks">
                                    <?php foreach ($cat_tasks as $t):
                                        $done     = (int)$t['is_completed'] === 1;
                                        $desc     = trim((string)($t['description'] ?? ''));
                                        $priority = strtolower((string)($t['priority'] ?? ''));
                                        $due      = $t['due_date'] ?? null;
                                        $overdue  = false;
                                        $due_txt  = '';
                                        if (!empty($due) && strtotime($due) !== false) {
                                            $due_txt = date('d.m.Y', strtotime($due));
                                            $overdue = !$done && strtotime($due) < strtotime('today');
                                        }
                                    ?>
                                        <li class="cat-task<?= $done ? ' done' : '' ?>">
                                            <span class="cat-task-check">&#10003;</span>
                                            <div class="cat-task-body">
                                                <div class="cat-task-title"><?= e($t['title']) ?></div>
                                                <?php if ($desc !== ''): ?>
                                                    <div class="cat-task-desc"><?= e($desc) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="cat-task-side">
                                                <?php if ($due_txt !== ''): ?>
                                                    <span class="<?= $overdue ? 'overdue' : '' ?>">&#128197; <?= e($due_txt) ?></span>
                                                <?php endif; ?>
                                                <?php if (isset($priority_labels[$priority])): ?>
                                                    <span class="priority priority-<?= e($priority) ?>"><?= e($priority_labels[$priority]) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
