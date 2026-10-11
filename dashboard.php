<?php
// dashboard.php

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id  = $_SESSION['user_id']; // ruan user_id nga sessioni
$username = $_SESSION['username']; // ruan username nga sessioni

$today = date('Y-m-d'); // ruan daten aktuale per kalendar per detyrat e vonuara ose te sotme, me format YYYY-MM-DD

// URL per filtrat (status + kategori + kerkim)
function dash_url(string $filter, string $cat, string $q = ''): string { 
    $params = []; // array per te ruajtur parametrat e URL
    if ($filter !== 'all') $params['filter'] = $filter; 
    if ($cat !== 'all')    $params['cat']    = $cat;
    if ($q !== '')         $params['q']      = $q;
    return 'dashboard.php' . ($params ? '?' . http_build_query($params) : '');
}

$priority_labels = [ // array me etiketat e prioritetit
    'low'    => 'Ulet',
    'medium' => 'Mesatar',
    'high'   => 'Larte',
];

// ---------- Kategorite e userit ----------
$categories = [];

try {
    $stmt = $conn->prepare(
        "SELECT id, name, color
         FROM categories
         WHERE user_id = ?
         ORDER BY name ASC"
    ); // prepare nje query per te marre kategorite e userit nga databaza, te renditura sipas emrit
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { // merr secilen kategori dhe e shton ne array
        $row['color'] = safe_color($row['color'] ?? null);
        $categories[(int)$row['id']] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) { // nese ka gabim ne query, e logon gabimin dhe e ben array bosh
    error_log("Dashboard categories error: " . $e->getMessage());
    $categories = [];
}

// ---------- Filtrat nga URL ----------
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'active', 'completed'], true)) {
    $filter = 'all';
}

$cat_filter = 'all';   // 'all' | 'none' | '<id>'
$cat_raw = (string)($_GET['cat'] ?? 'all');
if ($cat_raw === 'none') {
    $cat_filter = 'none';
} elseif (ctype_digit($cat_raw) && isset($categories[(int)$cat_raw])) {
    $cat_filter = (string)(int)$cat_raw;
}

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

// ---------- Statistikat ----------
$stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0];
$count_by_cat = [];
$count_none   = 0;

try {
    $stmt = $conn->prepare("SELECT category_id, is_completed, due_date FROM tasks WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $done = (int)$row['is_completed'] === 1;
        $stats['total']++;
        if ($done) {
            $stats['completed']++;
        } else {
            $stats['active']++;
            if (!empty($row['due_date']) && $row['due_date'] < $today) {
                $stats['overdue']++;
            }
        }
        if ($row['category_id'] === null) {
            $count_none++;
        } else {
            $cid = (int)$row['category_id'];
            $count_by_cat[$cid] = ($count_by_cat[$cid] ?? 0) + 1;
        }
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

$percent_done = $stats['total'] > 0 ? (int)round($stats['completed'] / $stats['total'] * 100) : 0;

// ---------- Lista e detyrave ----------
$sql = "SELECT t.id, t.title, t.description, t.priority, t.due_date, t.is_completed,
               t.created_at, t.category_id
        FROM tasks t
        WHERE t.user_id = ?";
$types  = "i";
$params = [$user_id];

if ($filter === 'active') {
    $sql .= " AND t.is_completed = 0";
} elseif ($filter === 'completed') {
    $sql .= " AND t.is_completed = 1";
}

if ($cat_filter === 'none') {
    $sql .= " AND t.category_id IS NULL";
} elseif ($cat_filter !== 'all') {
    $sql .= " AND t.category_id = ?";
    $types   .= "i";
    $params[] = (int)$cat_filter;
}

if ($q !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $types   .= "ss";
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY t.is_completed ASC,
                   FIELD(t.priority, 'high', 'medium', 'low'),
                   t.due_date IS NULL, t.due_date ASC,
                   t.created_at DESC";

$tasks = [];

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $tasks = [];
}

$filters_active = ($filter !== 'all' || $cat_filter !== 'all' || $q !== '');

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

// pershendetje sipas ores
$hour = (int)date('G');
if ($hour < 12)      { $greet = 'Miremengjes'; }
elseif ($hour < 18)  { $greet = 'Mirdita'; }
else                 { $greet = 'Mirembrema'; }
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TaskStack</title>
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
            <p class="eyebrow"><?= e($greet) ?>, <?= e($username) ?></p>
            <h1>Detyrat e mia</h1>
        </div>
        <a href="tasks/add_task.php" class="btn">+ Shto detyre</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="alert error"><p><?= e($flash_error) ?></p></div>
    <?php endif; ?>

    <?php if ($stats['total'] > 0): ?>
        <div class="stats">
            <div class="stat">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-label">Gjithsej</div>
            </div>
            <div class="stat">
                <div class="stat-num"><?= $stats['active'] ?></div>
                <div class="stat-label">Aktive</div>
            </div>
            <div class="stat <?= $stats['completed'] > 0 ? 'is-good' : '' ?>">
                <div class="stat-num"><?= $stats['completed'] ?></div>
                <div class="stat-label">Te kryera</div>
            </div>
            <div class="stat <?= $stats['overdue'] > 0 ? 'is-danger' : '' ?>">
                <div class="stat-num"><?= $stats['overdue'] ?></div>
                <div class="stat-label">Te vonuara</div>
            </div>
        </div>
        <div class="progress-wrap">
            <div class="progress"><span style="width: <?= $percent_done ?>%"></span></div>
            <span><strong><?= $percent_done ?>%</strong> e kryer</span>
        </div>
    <?php endif; ?>

    <div class="dash-toolbar">

        <form method="GET" action="dashboard.php" class="search-row">
            <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
            <?php if ($cat_filter !== 'all'): ?><input type="hidden" name="cat" value="<?= e($cat_filter) ?>"><?php endif; ?>
            <input type="text" name="q" class="search-input" maxlength="100"
                   placeholder="Kerko nje detyre..." value="<?= e($q) ?>">
        </form>

        <div class="toolbar-row">
            <span class="toolbar-label">Statusi</span>
            <div class="filters">
                <a href="<?= e(dash_url('all', $cat_filter, $q)) ?>"       class="<?= $filter === 'all'       ? 'active' : '' ?>">Te gjitha<span class="n"><?= $stats['total'] ?></span></a>
                <a href="<?= e(dash_url('active', $cat_filter, $q)) ?>"    class="<?= $filter === 'active'    ? 'active' : '' ?>">Aktive<span class="n"><?= $stats['active'] ?></span></a>
                <a href="<?= e(dash_url('completed', $cat_filter, $q)) ?>" class="<?= $filter === 'completed' ? 'active' : '' ?>">Te kryera<span class="n"><?= $stats['completed'] ?></span></a>
            </div>

            <?php if ($filters_active): ?>
                <a href="dashboard.php" class="clear-filters">&#10005; Pastro filtrat</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="toolbar-row">
                <span class="toolbar-label">Kategoria</span>
                <div class="cat-filters">
                    <a href="<?= e(dash_url($filter, 'all', $q)) ?>"
                       class="cat-pill <?= $cat_filter === 'all' ? 'active' : '' ?>">Te gjitha</a>

                    <?php foreach ($categories as $cid => $cat): ?>
                        <a href="<?= e(dash_url($filter, (string)$cid, $q)) ?>"
                           class="cat-pill <?= $cat_filter === (string)$cid ? 'active' : '' ?>"
                           style="--cat: <?= e($cat['color']) ?>;">
                            <span class="dot"></span>
                            <?= e($cat['name']) ?>
                            <span class="n"><?= (int)($count_by_cat[$cid] ?? 0) ?></span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($count_none > 0 || $cat_filter === 'none'): ?>
                        <a href="<?= e(dash_url($filter, 'none', $q)) ?>"
                           class="cat-pill <?= $cat_filter === 'none' ? 'active' : '' ?>">
                            <span class="dot"></span>
                            Pa kategori
                            <span class="n"><?= $count_none ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <?php if ($filters_active): ?>
                <p class="empty-icon">&#128269;</p>
                <p>Asnje detyre nuk perputhet me filtrat.</p>
                <p><a href="dashboard.php">Pastro filtrat</a></p>
            <?php else: ?>
                <p class="empty-icon">&#128237;</p>
                <p>Nuk ke asnje detyre ende.</p>
                <p><a href="tasks/add_task.php">Shto detyren e pare</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="task-list">
            <?php foreach ($tasks as $task): ?>
                <?php
                    $isDone = (int)$task['is_completed'] === 1;
                    $isOverdue = !$isDone
                        && !empty($task['due_date'])
                        && $task['due_date'] < $today;
                    $isToday = !$isDone
                        && !empty($task['due_date'])
                        && $task['due_date'] === $today;

                    $taskCat = null;
                    if ($task['category_id'] !== null && isset($categories[(int)$task['category_id']])) {
                        $taskCat = $categories[(int)$task['category_id']];
                    }

                    $prio = (string)$task['priority'];
                    $prioLabel = $priority_labels[$prio] ?? $prio;
                ?>
                <li class="task-item <?= $isDone ? 'completed' : '' ?>">

                    <form action="tasks/toggle_task.php" method="POST" class="task-toggle">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id"   value="<?= (int)$task['id'] ?>">
                        <button type="submit" class="check-btn" title="Ndrysho statusin">
                            <?= $isDone ? '&#10003;' : '' ?>
                        </button>
                    </form>

                    <div class="task-body">
                        <div class="task-title">
                            <?= e($task['title']) ?>
                            <span class="priority priority-<?= e($prio) ?>">
                                <?= e($prioLabel) ?>
                            </span>
                            <?php if ($taskCat): ?>
                                <a href="<?= e(dash_url($filter, (string)$taskCat['id'], $q)) ?>"
                                   class="task-cat"
                                   style="--cat: <?= e($taskCat['color']) ?>;"
                                   title="Shfaq vetem kete kategori">
                                    <span class="dot"></span><?= e($taskCat['name']) ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($task['description'])): ?>
                            <p class="task-desc"><?= e($task['description']) ?></p>
                        <?php endif; ?>

                        <div class="task-meta">
                            <?php if (!empty($task['due_date'])): ?>
                                <span class="<?= $isOverdue ? 'overdue' : ($isToday ? 'today' : '') ?>">
                                    &#128197; <?= e(date('d.m.Y', strtotime($task['due_date']))) ?>
                                    <?= $isOverdue ? ' (vonuar)' : ($isToday ? ' (sot)' : '') ?>
                                </span>
                            <?php endif; ?>
                            <span>&#128338; <?= e(date('d.m.Y H:i', strtotime($task['created_at']))) ?></span>
                        </div>
                    </div>

                    <div class="task-actions">
                        <a href="tasks/edit_task.php?id=<?= (int)$task['id'] ?>" class="icon-btn" title="Edito">&#9999;&#65039;</a>

                        <form action="tasks/delete_task.php" method="POST" class="inline-form"
                              data-confirm="Fshij kete detyre?">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id"   value="<?= (int)$task['id'] ?>">
                            <button type="submit" class="icon-btn" title="Fshij">&#128465;&#65039;</button>
                        </form>
                    </div>

                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>