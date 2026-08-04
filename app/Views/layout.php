<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pageTitle = $title ?? 'Dashboard';
$breadcrumbs = [['label' => 'Home', 'href' => '/']];

if ($currentPath === '/') {
    $breadcrumbs = [['label' => 'Home', 'href' => null]];
} elseif (str_starts_with($currentPath, '/projects/create')) {
    $breadcrumbs[] = ['label' => 'Projects', 'href' => '/projects'];
    $breadcrumbs[] = ['label' => 'New Project', 'href' => null];
} elseif (str_starts_with($currentPath, '/projects/edit')) {
    $breadcrumbs[] = ['label' => 'Projects', 'href' => '/projects'];
    $breadcrumbs[] = ['label' => 'Edit Project', 'href' => null];
} elseif (str_starts_with($currentPath, '/projects/show')) {
    $breadcrumbs[] = ['label' => 'Projects', 'href' => '/projects'];
    $breadcrumbs[] = ['label' => $pageTitle, 'href' => null];
} elseif (str_starts_with($currentPath, '/projects')) {
    $breadcrumbs[] = ['label' => 'Projects', 'href' => null];
} elseif (str_starts_with($currentPath, '/tasks/show')) {
    $breadcrumbs[] = ['label' => 'Projects', 'href' => '/projects'];
    $breadcrumbs[] = ['label' => $pageTitle, 'href' => null];
} elseif (str_starts_with($currentPath, '/kanban')) {
    $breadcrumbs[] = ['label' => 'Kanban', 'href' => null];
} elseif (str_starts_with($currentPath, '/work-logs')) {
    $breadcrumbs[] = ['label' => 'Daily Log', 'href' => null];
} elseif (str_starts_with($currentPath, '/time-logs')) {
    $breadcrumbs[] = ['label' => 'Time Logs', 'href' => null];
} elseif (str_starts_with($currentPath, '/reports')) {
    $breadcrumbs[] = ['label' => 'Reports', 'href' => null];
} elseif (str_starts_with($currentPath, '/clients/create')) {
    $breadcrumbs[] = ['label' => 'Clients', 'href' => '/clients'];
    $breadcrumbs[] = ['label' => 'New Client', 'href' => null];
} elseif (str_starts_with($currentPath, '/clients/edit')) {
    $breadcrumbs[] = ['label' => 'Clients', 'href' => '/clients'];
    $breadcrumbs[] = ['label' => 'Edit Client', 'href' => null];
} elseif (str_starts_with($currentPath, '/clients')) {
    $breadcrumbs[] = ['label' => 'Clients', 'href' => null];
} elseif (str_starts_with($currentPath, '/users/create')) {
    $breadcrumbs[] = ['label' => 'Users', 'href' => '/users'];
    $breadcrumbs[] = ['label' => 'New User', 'href' => null];
} elseif (str_starts_with($currentPath, '/users/edit')) {
    $breadcrumbs[] = ['label' => 'Users', 'href' => '/users'];
    $breadcrumbs[] = ['label' => 'Edit User', 'href' => null];
} elseif (str_starts_with($currentPath, '/users')) {
    $breadcrumbs[] = ['label' => 'Users', 'href' => null];
} elseif (str_starts_with($currentPath, '/settings') || str_starts_with($currentPath, '/templates')) {
    $breadcrumbs[] = ['label' => 'Settings', 'href' => null];
} elseif (str_starts_with($currentPath, '/profile')) {
    $breadcrumbs[] = ['label' => 'My Profile', 'href' => null];
} else {
    $breadcrumbs[] = ['label' => $pageTitle, 'href' => null];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('app.name')) ?> | <?= e(config('app.name')) ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-base-path" content="<?= e(app_base_path()) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/public/assets/css/app.css')) ?>">
    <script defer src="<?= e(asset_url('/public/assets/js/app.js')) ?>"></script>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <a href="/">
                    <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser" class="brand-logo">
                </a>
            </div>
            <nav class="nav">
                <a class="<?= $currentPath === '/' || str_starts_with($currentPath, '/my-work') ? 'active' : '' ?>" href="/">Home</a>
                <?php if (\App\Core\Permissions::canAccessPage('projects')): ?>
                    <a class="<?= str_starts_with($currentPath, '/projects') ? 'active' : '' ?>" href="/projects">Projects</a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('clients')): ?>
                    <a class="<?= str_starts_with($currentPath, '/clients') ? 'active' : '' ?>" href="/clients">Clients</a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('kanban')): ?>
                    <a class="<?= str_starts_with($currentPath, '/kanban') ? 'active' : '' ?>" href="/kanban">Kanban</a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('work_logs')): ?>
                    <a class="<?= str_starts_with($currentPath, '/work-logs') ? 'active' : '' ?>" href="/work-logs">Daily Log</a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('time_logs')): ?>
                    <a class="<?= str_starts_with($currentPath, '/time-logs') ? 'active' : '' ?>" href="/time-logs">Time Logs</a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('reports')): ?>
                    <a class="<?= str_starts_with($currentPath, '/reports') ? 'active' : '' ?>" href="/reports">Reports</a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('users') || \App\Core\Permissions::isManager()): ?>
                    <a class="<?= str_starts_with($currentPath, '/users') ? 'active' : '' ?>" href="/users">Users</a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('settings')): ?>
                    <a class="<?= str_starts_with($currentPath, '/settings') || str_starts_with($currentPath, '/templates') ? 'active' : '' ?>" href="/settings">Settings</a>
                <?php endif; ?>
            </nav>
        </aside>

        <main class="main">
            <div class="page-nav">
                <button type="button" class="back-button" onclick="history.length > 1 ? history.back() : window.location.href='<?= e(url('/')) ?>'">Back</button>
                <nav class="breadcrumbs" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php if ($index > 0): ?>
                            <span aria-hidden="true">/</span>
                        <?php endif; ?>
                        <?php if (!empty($crumb['href'])): ?>
                            <a href="<?= e($crumb['href']) ?>"><?= e($crumb['label']) ?></a>
                        <?php else: ?>
                            <strong><?= e($crumb['label']) ?></strong>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
            <header class="topbar">
                <div>
                    <h1><?= e($pageTitle) ?></h1>
                    <p><?= e(date('l, d M Y')) ?></p>
                </div>
                <div class="user-chip">
                    <a class="user-chip-profile" href="/profile">
                        <span class="avatar avatar-sm">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <img src="<?= e(avatar_url($_SESSION['user_avatar'])) ?>" alt="">
                            <?php else: ?>
                                <?= e(user_initials($_SESSION['user_name'] ?? 'User')) ?>
                            <?php endif; ?>
                        </span>
                        <span><?= e($_SESSION['user_name'] ?? 'User') ?></span>
                    </a>
                    <a href="/logout">Logout</a>
                </div>
            </header>

            <?php require __DIR__ . '/partials/flash.php'; ?>
            <?= $content ?>
        </main>
    </div>
</body>
</html>
