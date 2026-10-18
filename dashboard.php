<?php
// dashboard.php

require_once __DIR__ . '/config.php'; // lidhja me databazen
require_once __DIR__ . '/includes/functions.php'; // funksionet e pergjithshme

session_start(); // fillohet sessioni per te ruajtur user_id dhe tokenin CSRF
require_login(); // kontrollon nese useri eshte i kyqur, nese jo e kthen ne login.php

$user_id  = $_SESSION['user_id']; // ruan user_id nga sessioni
$username = $_SESSION['username']; // ruan username nga sessioni

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day')); // ruan daten aktuale per kalendar per detyrat e vonuara ose te sotme, me format YYYY-MM-DD

// URL per filtrat (status + kategori + kerkim)
function dash_url(string $filter, string $cat, string $q = '', ?string $sortOverride = null): string { 
    global $sort; // renditja aktuale ruhet ne cdo link
    $params = []; // array per te ruajtur parametrat e URL
    $s = $sortOverride ?? ($sort ?? 'default');
    if ($s !== 'default')  $params['sort']   = $s;
    if ($filter !== 'all') $params['filter'] = $filter; 
    if ($cat !== 'all')    $params['cat']    = $cat;
    if ($q !== '')         $params['q']      = $q;
    return 'dashboard.php' . ($params ? '?' . http_build_query($params) : '');
}

$priority_labels = [ // array me etiketat e prioritetit
    'low'    => t('prio.low'),
    'medium' => t('prio.medium'),
    'high'   => t('prio.high'),
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
if (!in_array($filter, ['all', 'active', 'completed', 'overdue'], true)) {
    $filter = 'all';
}

// Renditja sipas afatit: default | due_asc (me afer -> me larg) | due_desc (me larg -> me afer)
$sort = (string)($_GET['sort'] ?? 'default');
if (!in_array($sort, ['default', 'due_asc', 'due_desc'], true)) {
    $sort = 'default';
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
$stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0, 'today' => 0];
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
            } elseif (!empty($row['due_date']) && $row['due_date'] === $today) {
                $stats['today']++;
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
} elseif ($filter === 'overdue') {
    $sql .= " AND t.is_completed = 0 AND t.due_date IS NOT NULL AND t.due_date < ?";
    $types   .= "s";
    $params[] = $today;
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

// detyrat e kryera gjithmone ne fund; detyrat pa afat gjithmone pas atyre me afat
if ($sort === 'due_asc') {
    $sql .= " ORDER BY t.is_completed ASC, t.due_date IS NULL, t.due_date ASC, t.created_at DESC";
} elseif ($sort === 'due_desc') {
    $sql .= " ORDER BY t.is_completed ASC, t.due_date IS NULL, t.due_date DESC, t.created_at DESC";
} else {
    $sql .= " ORDER BY t.is_completed ASC,
                   FIELD(t.priority, 'high', 'medium', 'low'),
                   t.due_date IS NULL, t.due_date ASC,
                   t.created_at DESC";
}

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

$filters_active = ($filter !== 'all' || $cat_filter !== 'all' || $q !== '' || $sort !== 'default');

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
if ($hour < 12)      { $greet = t('dash.morning'); }
elseif ($hour < 18)  { $greet = t('dash.afternoon'); }
else                 { $greet = t('dash.evening'); }
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
            <h1><?= e(t('dash.title')) ?></h1>
        </div>
        <a href="tasks/add_task.php" class="btn"><?= e(t('dash.add')) ?></a>
    </div>

    <?php if ($flash): ?>
        <div class="alert success"><p><?= e($flash) ?></p></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="alert error"><p><?= e($flash_error) ?></p></div>
    <?php endif; ?>

    <?php if ($stats['overdue'] > 0 || $stats['today'] > 0): ?>
        <div class="deadline-banner <?= $stats['overdue'] > 0 ? 'has-overdue' : '' ?>" role="status">
            <span class="deadline-text">
                <?php if ($stats['overdue'] > 0): ?><strong><?= (int)$stats['overdue'] ?></strong> <?= e(t('dash.alert_overdue')) ?><?php endif; ?>
                <?php if ($stats['overdue'] > 0 && $stats['today'] > 0): ?> &middot; <?php endif; ?>
                <?php if ($stats['today'] > 0): ?><strong><?= (int)$stats['today'] ?></strong> <?= e(t('dash.alert_today')) ?><?php endif; ?>
            </span>
            <?php if ($stats['overdue'] > 0 && $filter !== 'overdue'): ?>
                <a href="<?= e(dash_url('overdue', 'all')) ?>"><?= e(t('dash.alert_view')) ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($stats['total'] > 0): ?>
        <div class="stats">
            <div class="stat">
                <div class="stat-num"><?= $stats['total'] ?></div>
                <div class="stat-label"><?= e(t('dash.total')) ?></div>
            </div>
            <div class="stat">
                <div class="stat-num"><?= $stats['active'] ?></div>
                <div class="stat-label"><?= e(t('dash.active')) ?></div>
            </div>
            <div class="stat <?= $stats['completed'] > 0 ? 'is-good' : '' ?>">
                <div class="stat-num"><?= $stats['completed'] ?></div>
                <div class="stat-label"><?= e(t('dash.done')) ?></div>
            </div>
            <div class="stat <?= $stats['overdue'] > 0 ? 'is-danger' : '' ?>">
                <div class="stat-num"><?= $stats['overdue'] ?></div>
                <div class="stat-label"><?= e(t('dash.overdue')) ?></div>
            </div>
        </div>
        <div class="progress-wrap">
            <div class="progress"><span style="width: <?= $percent_done ?>%"></span></div>
            <span><strong><?= $percent_done ?>%</strong> <?= e(t('dash.percent')) ?></span>
        </div>
    <?php endif; ?>

    <div class="dash-toolbar">

        <form method="GET" action="dashboard.php" class="search-row">
            <?php if ($sort !== 'default'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
            <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
            <?php if ($cat_filter !== 'all'): ?><input type="hidden" name="cat" value="<?= e($cat_filter) ?>"><?php endif; ?>
            <input type="text" name="q" class="search-input" maxlength="100"
                   placeholder="<?= e(t('dash.search')) ?>" value="<?= e($q) ?>">
        </form>

        <div class="toolbar-row">
            <span class="toolbar-label"><?= e(t('dash.status')) ?></span>
            <div class="filters">
                <a href="<?= e(dash_url('all', $cat_filter, $q)) ?>"       class="<?= $filter === 'all'       ? 'active' : '' ?>"><?= e(t('dash.all')) ?><span class="n"><?= $stats['total'] ?></span></a>
                <a href="<?= e(dash_url('active', $cat_filter, $q)) ?>"    class="<?= $filter === 'active'    ? 'active' : '' ?>"><?= e(t('dash.active')) ?><span class="n"><?= $stats['active'] ?></span></a>
                <a href="<?= e(dash_url('completed', $cat_filter, $q)) ?>" class="<?= $filter === 'completed' ? 'active' : '' ?>"><?= e(t('dash.done')) ?><span class="n"><?= $stats['completed'] ?></span></a>
                <a href="<?= e(dash_url('overdue', $cat_filter, $q)) ?>"   class="filter-overdue <?= $filter === 'overdue'   ? 'active' : '' ?>"><?= e(t('dash.overdue')) ?><span class="n"><?= $stats['overdue'] ?></span></a>
            </div>

            <?php if ($filters_active): ?>
                <a href="dashboard.php" class="clear-filters">&#10005; <?= e(t('dash.clear')) ?></a>
            <?php endif; ?>
        </div>

        <div class="toolbar-row">
            <span class="toolbar-label"><?= e(t('dash.sort')) ?></span>
            <div class="filters">
                <a href="<?= e(dash_url($filter, $cat_filter, $q, 'default')) ?>"  class="<?= $sort === 'default'  ? 'active' : '' ?>"><?= e(t('dash.sort_default')) ?></a>
                <a href="<?= e(dash_url($filter, $cat_filter, $q, 'due_asc')) ?>"  class="<?= $sort === 'due_asc'  ? 'active' : '' ?>"><?= e(t('dash.sort_asc')) ?></a>
                <a href="<?= e(dash_url($filter, $cat_filter, $q, 'due_desc')) ?>" class="<?= $sort === 'due_desc' ? 'active' : '' ?>"><?= e(t('dash.sort_desc')) ?></a>
            </div>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="toolbar-row">
                <span class="toolbar-label"><?= e(t('dash.category')) ?></span>
                <div class="cat-filters">
                    <a href="<?= e(dash_url($filter, 'all', $q)) ?>"
                       class="cat-pill <?= $cat_filter === 'all' ? 'active' : '' ?>"><?= e(t('dash.all')) ?></a>

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
                            <?= e(t('dash.nocat')) ?>
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
                <p><?= e(t('dash.nomatch')) ?></p>
                <p><a href="dashboard.php"><?= e(t('dash.clear')) ?></a></p>
            <?php else: ?>
                <p class="empty-icon">&#128237;</p>
                <p><?= e(t('dash.empty')) ?></p>
                <p><a href="tasks/add_task.php"><?= e(t('dash.first')) ?></a></p>
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
                    $isTomorrow = !$isDone
                        && !empty($task['due_date'])
                        && $task['due_date'] === $tomorrow;

                    $taskCat = null;
                    if ($task['category_id'] !== null && isset($categories[(int)$task['category_id']])) {
                        $taskCat = $categories[(int)$task['category_id']];
                    }

                    $prio = (string)$task['priority'];
                    $prioLabel = $priority_labels[$prio] ?? $prio;
                ?>
                <li class="task-item <?= $isDone ? 'completed' : '' ?> <?= $isOverdue ? 'is-overdue' : '' ?> <?= $isToday ? 'is-today' : '' ?>">

                    <form action="tasks/toggle_task.php" method="POST" class="task-toggle">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id"   value="<?= (int)$task['id'] ?>">
                        <button type="submit" class="check-btn" title="<?= e(t('dash.toggle')) ?>">
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
                                   title="<?= e(t('dash.only_cat')) ?>">
                                    <span class="dot"></span><?= e($taskCat['name']) ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($task['description'])): ?>
                            <p class="task-desc"><?= e($task['description']) ?></p>
                        <?php endif; ?>

                        <div class="task-meta">
                            <?php if (!empty($task['due_date'])): ?>
                                <span class="<?= $isOverdue ? 'overdue' : ($isToday ? 'today' : ($isTomorrow ? 'tomorrow' : '')) ?>">
                                    &#128197; <?= e(date('d.m.Y', strtotime($task['due_date']))) ?>
                                    <?= e($isOverdue ? t('dash.late') : ($isToday ? t('dash.today') : ($isTomorrow ? t('dash.tomorrow') : ''))) ?>
                                </span>
                            <?php endif; ?>
                            <span>&#128338; <?= e(date('d.m.Y H:i', strtotime($task['created_at']))) ?></span>
                        </div>
                    </div>

                    <div class="task-actions">
                        <a href="tasks/edit_task.php?id=<?= (int)$task['id'] ?>" class="icon-btn" title="<?= e(t('dash.edit')) ?>">&#9999;&#65039;</a>

                        <form action="tasks/delete_task.php" method="POST" class="inline-form"
                              data-confirm="<?= e(t('dash.confirm_del')) ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id"   value="<?= (int)$task['id'] ?>">
                            <button type="submit" class="icon-btn" title="<?= e(t('dash.delete')) ?>">&#128465;&#65039;</button>
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