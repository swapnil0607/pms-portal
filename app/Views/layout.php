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
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title><?= e($title ?? config('app.name')) ?> | <?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('/public/assets/img/favicon.svg')) ?>">
    <link rel="alternate icon" href="<?= e(asset_url('/public/favicon.svg')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset_url('/public/assets/img/favicon.svg')) ?>">
    <meta name="theme-color" content="#007a70">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="EduRiser PMS">
    <link rel="manifest" href="<?= e(asset_url('/public/manifest.json')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-base-path" content="<?= e(app_base_path()) ?>">
    <script>
        // Apply saved sidebar preference immediately before paint to prevent flicker
        (function() {
            try {
                if (localStorage.getItem('pms_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-is-collapsed');
                }
            } catch(e) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset_url('/public/assets/css/app.css')) ?>">
    <script defer src="<?= e(asset_url('/public/assets/js/app.js')) ?>"></script>
</head>
<body>
    <div class="app-shell" id="appShell">
        <aside class="sidebar" id="appSidebar">
            <div class="brand">
                <a href="/" class="brand-link" title="EduRiser PMS Home">
                    <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser" class="brand-logo">
                    <span class="brand-collapsed-mark" aria-hidden="true">E</span>
                </a>
                <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation menu" title="Toggle sidebar">
                    <svg class="hamburger-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
            <nav class="nav" aria-label="Main Navigation">
                <a class="<?= $currentPath === '/' || str_starts_with($currentPath, '/my-work') ? 'active' : '' ?>" href="/" title="Home" data-nav-item>
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <span class="nav-label">Home</span>
                </a>
                <?php if (\App\Core\Permissions::canAccessPage('projects')): ?>
                    <a class="<?= str_starts_with($currentPath, '/projects') ? 'active' : '' ?>" href="/projects" title="Projects" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        <span class="nav-label">Projects</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('clients')): ?>
                    <a class="<?= str_starts_with($currentPath, '/clients') ? 'active' : '' ?>" href="/clients" title="Clients" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <span class="nav-label">Clients</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('client_renewals')): ?>
                    <a class="<?= str_starts_with($currentPath, '/client-renewals') ? 'active' : '' ?>" href="/client-renewals" title="Client Renewals" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><polyline points="9 16 12 19 16 14"></polyline></svg>
                        <span class="nav-label">Client Renewals</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('kanban')): ?>
                    <a class="<?= str_starts_with($currentPath, '/kanban') ? 'active' : '' ?>" href="/kanban" title="Kanban" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="5" height="18" rx="1"></rect><rect x="10" y="3" width="5" height="12" rx="1"></rect><rect x="17" y="3" width="5" height="15" rx="1"></rect></svg>
                        <span class="nav-label">Kanban</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('work_logs')): ?>
                    <a class="<?= str_starts_with($currentPath, '/work-logs') ? 'active' : '' ?>" href="/work-logs" title="Daily Log" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        <span class="nav-label">Daily Log</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('time_logs')): ?>
                    <a class="<?= str_starts_with($currentPath, '/time-logs') ? 'active' : '' ?>" href="/time-logs" title="Time Logs" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span class="nav-label">Time Logs</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('reports')): ?>
                    <a class="<?= str_starts_with($currentPath, '/reports') ? 'active' : '' ?>" href="/reports" title="Reports" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                        <span class="nav-label">Reports</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('users') || \App\Core\Permissions::isManager()): ?>
                    <a class="<?= str_starts_with($currentPath, '/users') ? 'active' : '' ?>" href="/users" title="Users" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <span class="nav-label">Users</span>
                    </a>
                <?php endif; ?>
                <?php if (\App\Core\Permissions::canAccessPage('settings')): ?>
                    <a class="<?= str_starts_with($currentPath, '/settings') || str_starts_with($currentPath, '/templates') ? 'active' : '' ?>" href="/settings" title="Settings" data-nav-item>
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        <span class="nav-label">Settings</span>
                    </a>
                <?php endif; ?>

                <div class="sidebar-section-divider" style="height: 1px; background: rgba(255, 255, 255, 0.08); margin: 12px 10px 8px;"></div>
                <div class="sidebar-section-label" style="padding: 6px 14px 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); letter-spacing: 0.6px;">Central Links</div>
                <a href="https://eduriserck.com/ckadminmasterkey/adminmasterkeyfinal.php" target="_blank" rel="noopener noreferrer" title="Employee Central" data-nav-item class="nav-link-external">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <span class="nav-label">Employee Central</span>
                    <svg class="nav-ext-arrow" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto; opacity: 0.5;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                </a>
                <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Feduriserck.com%2Fclientele%2Fadmin%2Flogin.php" target="_blank" rel="noopener noreferrer" title="Eduriser Clientele" data-nav-item class="nav-link-external">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span class="nav-label">Clientele</span>
                    <svg class="nav-ext-arrow" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto; opacity: 0.5;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                </a>
                <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Foutlook.cloud.microsoft%2Fmail%2F" target="_blank" rel="noopener noreferrer" title="Eduriser E-mail (Outlook)" data-nav-item class="nav-link-external">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                    <span class="nav-label">Outlook Mail</span>
                    <svg class="nav-ext-arrow" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto; opacity: 0.5;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                </a>
            </nav>
        </aside>

        <main class="main">
            <!-- Mobile App Header (Visible on Mobile Viewports) -->
            <header class="mobile-app-header" id="mobileAppHeader">
                <a href="/" class="mobile-brand-link" title="EduRiser PMS">
                    <img src="<?= e(asset_url('/public/assets/img/eduriser-logo.svg')) ?>" alt="EduRiser" class="mobile-brand-logo" style="height: 32px; max-height: 32px; width: auto; max-width: 140px; object-fit: contain;">
                </a>
                <div class="mobile-header-center">
                    <span class="mobile-header-title"><?= e($pageTitle) ?></span>
                </div>
                <div class="mobile-header-actions">
                    <!-- Mobile Quick Links / App Switcher -->
                    <div class="quick-links-wrapper" data-quick-links-wrapper>
                        <button type="button" class="quick-links-trigger-btn mobile-quick-links-btn" data-quick-links-trigger aria-label="Employee Central Quick Links" title="Employee Central Apps">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="5" cy="5" r="1.5"></circle>
                                <circle cx="12" cy="5" r="1.5"></circle>
                                <circle cx="19" cy="5" r="1.5"></circle>
                                <circle cx="5" cy="12" r="1.5"></circle>
                                <circle cx="12" cy="12" r="1.5"></circle>
                                <circle cx="19" cy="12" r="1.5"></circle>
                                <circle cx="5" cy="19" r="1.5"></circle>
                                <circle cx="12" cy="19" r="1.5"></circle>
                                <circle cx="19" cy="19" r="1.5"></circle>
                            </svg>
                        </button>
                        <div class="quick-links-dropdown" data-quick-links-dropdown style="display: none;">
                            <div class="quick-links-dropdown-header">
                                <span style="font-weight: 700; font-size: 12.5px; color: #1e293b;">EduRiser Ecosystem</span>
                                <span class="badge secondary" style="font-size: 10px; padding: 2px 6px;">Central SSO</span>
                            </div>
                            <div class="quick-links-grid">
                                <a href="https://eduriserck.com/ckadminmasterkey/adminmasterkeyfinal.php" target="_blank" rel="noopener noreferrer" class="quick-link-card">
                                    <div class="quick-link-icon-wrap" style="background: #eff6ff; color: #2563eb;">
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    </div>
                                    <div class="quick-link-text">
                                        <span class="quick-link-title">Employee Central</span>
                                        <span class="quick-link-desc">Master Admin Portal</span>
                                    </div>
                                    <svg class="quick-link-ext" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>

                                <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Feduriserck.com%2Fclientele%2Fadmin%2Flogin.php" target="_blank" rel="noopener noreferrer" class="quick-link-card">
                                    <div class="quick-link-icon-wrap" style="background: #f0fdf4; color: #16a34a;">
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    </div>
                                    <div class="quick-link-text">
                                        <span class="quick-link-title">Eduriser Clientele</span>
                                        <span class="quick-link-desc">Client Management</span>
                                    </div>
                                    <svg class="quick-link-ext" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>

                                <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Foutlook.cloud.microsoft%2Fmail%2F" target="_blank" rel="noopener noreferrer" class="quick-link-card">
                                    <div class="quick-link-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                                    </div>
                                    <div class="quick-link-text">
                                        <span class="quick-link-title">Eduriser E-mail</span>
                                        <span class="quick-link-desc">Outlook Webmail</span>
                                    </div>
                                    <svg class="quick-link-ext" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Notification Bell Component -->
                    <div class="notifications-wrapper" data-notifications-wrapper>
                        <button type="button" class="notifications-trigger-btn mobile-notifications-btn" data-notifications-trigger aria-label="View notifications" title="Notifications">
                            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span class="notifications-badge" data-notifications-badge style="display: none;">0</span>
                        </button>
                        <div class="notifications-dropdown" data-notifications-dropdown style="display: none;">
                            <div class="notifications-dropdown-header">
                                <div class="notifications-title-wrap">
                                    <h3>Notifications</h3>
                                    <span class="notifications-count-pill" data-notifications-count-pill>0</span>
                                </div>
                                <button type="button" class="notifications-mark-all-btn" data-notifications-mark-all-btn>Mark all read</button>
                            </div>
                            <div class="notifications-list" data-notifications-list>
                                <div class="notifications-empty">Loading notifications...</div>
                            </div>
                        </div>
                    </div>

                    <a class="mobile-user-avatar" href="/profile" title="My Profile">
                        <span class="avatar avatar-xs" style="width: 26px; height: 26px; min-width: 26px; min-height: 26px; max-width: 26px; max-height: 26px; font-size: 10.5px;">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <img src="<?= e(avatar_url($_SESSION['user_avatar'])) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <?= e(user_initials($_SESSION['user_name'] ?? 'User')) ?>
                            <?php endif; ?>
                        </span>
                    </a>
                    <button type="button" class="mobile-menu-trigger" id="mobileMenuBtn" aria-label="Open Navigation Menu">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </header>

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
                <div class="topbar-right-group">
                    <!-- Desktop Quick Links / App Switcher -->
                    <div class="quick-links-wrapper" data-quick-links-wrapper>
                        <button type="button" class="quick-links-trigger-btn" data-quick-links-trigger aria-label="Employee Central Quick Links" title="Quick Links / Employee Central">
                            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="5" cy="5" r="1.5"></circle>
                                <circle cx="12" cy="5" r="1.5"></circle>
                                <circle cx="19" cy="5" r="1.5"></circle>
                                <circle cx="5" cy="12" r="1.5"></circle>
                                <circle cx="12" cy="12" r="1.5"></circle>
                                <circle cx="19" cy="12" r="1.5"></circle>
                                <circle cx="5" cy="19" r="1.5"></circle>
                                <circle cx="12" cy="19" r="1.5"></circle>
                                <circle cx="19" cy="19" r="1.5"></circle>
                            </svg>
                        </button>
                        <div class="quick-links-dropdown" data-quick-links-dropdown style="display: none;">
                            <div class="quick-links-dropdown-header">
                                <span style="font-weight: 700; font-size: 13px; color: #1e293b;">EduRiser Ecosystem</span>
                                <span class="badge secondary" style="font-size: 10.5px; padding: 2px 6px;">Central SSO</span>
                            </div>
                            <div class="quick-links-grid">
                                <a href="https://eduriserck.com/ckadminmasterkey/adminmasterkeyfinal.php" target="_blank" rel="noopener noreferrer" class="quick-link-card">
                                    <div class="quick-link-icon-wrap" style="background: #eff6ff; color: #2563eb;">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    </div>
                                    <div class="quick-link-text">
                                        <span class="quick-link-title">Employee Central</span>
                                        <span class="quick-link-desc">Master Admin Portal</span>
                                    </div>
                                    <svg class="quick-link-ext" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>

                                <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Feduriserck.com%2Fclientele%2Fadmin%2Flogin.php" target="_blank" rel="noopener noreferrer" class="quick-link-card">
                                    <div class="quick-link-icon-wrap" style="background: #f0fdf4; color: #16a34a;">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    </div>
                                    <div class="quick-link-text">
                                        <span class="quick-link-title">Eduriser Clientele</span>
                                        <span class="quick-link-desc">Client Management & SSO</span>
                                    </div>
                                    <svg class="quick-link-ext" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>

                                <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Foutlook.cloud.microsoft%2Fmail%2F" target="_blank" rel="noopener noreferrer" class="quick-link-card">
                                    <div class="quick-link-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                                    </div>
                                    <div class="quick-link-text">
                                        <span class="quick-link-title">Eduriser E-mail</span>
                                        <span class="quick-link-desc">Microsoft 365 Outlook</span>
                                    </div>
                                    <svg class="quick-link-ext" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>

                                <div class="quick-link-card active-app">
                                    <div class="quick-link-icon-wrap" style="background: #007a70; color: #ffffff;">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                                    </div>
                                    <div class="quick-link-text">
                                        <span class="quick-link-title">EduRiser PMS</span>
                                        <span class="quick-link-desc"><strong style="color: #007a70;">Current Application</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Notification Bell Component -->
                    <div class="notifications-wrapper" data-notifications-wrapper>
                        <button type="button" class="notifications-trigger-btn" data-notifications-trigger aria-label="View notifications" title="Notifications">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span class="notifications-badge" data-notifications-badge style="display: none;">0</span>
                        </button>
                        <div class="notifications-dropdown" data-notifications-dropdown style="display: none;">
                            <div class="notifications-dropdown-header">
                                <div class="notifications-title-wrap">
                                    <h3>Notifications</h3>
                                    <span class="notifications-count-pill" data-notifications-count-pill>0</span>
                                </div>
                                <button type="button" class="notifications-mark-all-btn" data-notifications-mark-all-btn>Mark all read</button>
                            </div>
                            <div class="notifications-list" data-notifications-list>
                                <div class="notifications-empty">Loading notifications...</div>
                            </div>
                        </div>
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
                </div>
            </header>

            <?php require __DIR__ . '/partials/flash.php'; ?>
            <?= $content ?>
        </main>
    </div>

    <!-- Mobile Slide-Over Drawer Backdrop & Container -->
    <div class="mobile-drawer-backdrop" id="mobileDrawerBackdrop" hidden></div>
    <aside class="mobile-drawer" id="mobileDrawer" aria-label="Mobile Navigation Drawer">
        <div class="mobile-drawer-header">
            <div class="mobile-drawer-user">
                <span class="avatar avatar-md" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px; max-width: 40px; max-height: 40px; font-size: 15px;">
                    <?php if (!empty($_SESSION['user_avatar'])): ?>
                        <img src="<?= e(avatar_url($_SESSION['user_avatar'])) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?= e(user_initials($_SESSION['user_name'] ?? 'User')) ?>
                    <?php endif; ?>
                </span>
                <div class="mobile-drawer-user-info">
                    <strong><?= e($_SESSION['user_name'] ?? 'User') ?></strong>
                    <small class="muted"><?= e($_SESSION['user_email'] ?? '') ?></small>
                </div>
            </div>
            <button type="button" class="mobile-drawer-close" id="mobileDrawerClose" aria-label="Close Menu">&times;</button>
        </div>
        <nav class="mobile-drawer-nav">
            <div class="mobile-drawer-section-label">Core Modules</div>
            <a class="<?= $currentPath === '/' || str_starts_with($currentPath, '/my-work') ? 'active' : '' ?>" href="/">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Home (My Work)</span>
            </a>
            <?php if (\App\Core\Permissions::canAccessPage('projects')): ?>
                <a class="<?= str_starts_with($currentPath, '/projects') ? 'active' : '' ?>" href="/projects">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    <span>Projects</span>
                </a>
            <?php endif; ?>
            <?php if (\App\Core\Permissions::canAccessPage('kanban')): ?>
                <a class="<?= str_starts_with($currentPath, '/kanban') ? 'active' : '' ?>" href="/kanban">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="5" height="18" rx="1"></rect><rect x="10" y="3" width="5" height="12" rx="1"></rect><rect x="17" y="3" width="5" height="15" rx="1"></rect></svg>
                    <span>Kanban Board</span>
                </a>
            <?php endif; ?>
            <?php if (\App\Core\Permissions::canAccessPage('work_logs')): ?>
                <a class="<?= str_starts_with($currentPath, '/work-logs') ? 'active' : '' ?>" href="/work-logs">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    <span>Daily Work Log</span>
                </a>
            <?php endif; ?>
            <div class="mobile-drawer-section-label">Clients & Management</div>
            <?php if (\App\Core\Permissions::canAccessPage('client_renewals')): ?>
                <a class="<?= str_starts_with($currentPath, '/client-renewals') ? 'active' : '' ?>" href="/client-renewals">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><polyline points="9 16 12 19 16 14"></polyline></svg>
                    <span>Client Renewals</span>
                </a>
            <?php endif; ?>
            <?php if (\App\Core\Permissions::canAccessPage('clients')): ?>
                <a class="<?= str_starts_with($currentPath, '/clients') ? 'active' : '' ?>" href="/clients">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Clients</span>
                </a>
            <?php endif; ?>
            <?php if (\App\Core\Permissions::canAccessPage('time_logs')): ?>
                <a class="<?= str_starts_with($currentPath, '/time-logs') ? 'active' : '' ?>" href="/time-logs">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Time Logs</span>
                </a>
            <?php endif; ?>
            <?php if (\App\Core\Permissions::canAccessPage('reports')): ?>
                <a class="<?= str_starts_with($currentPath, '/reports') ? 'active' : '' ?>" href="/reports">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    <span>Reports & Billing</span>
                </a>
            <?php endif; ?>
            <?php if (\App\Core\Permissions::canAccessPage('users') || \App\Core\Permissions::isManager()): ?>
                <a class="<?= str_starts_with($currentPath, '/users') ? 'active' : '' ?>" href="/users">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span>Users</span>
                </a>
            <?php endif; ?>
            <?php if (\App\Core\Permissions::canAccessPage('settings')): ?>
                <a class="<?= str_starts_with($currentPath, '/settings') || str_starts_with($currentPath, '/templates') ? 'active' : '' ?>" href="/settings">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span>Settings</span>
                </a>
            <?php endif; ?>

            <div class="mobile-drawer-section-label">Employee Central Links</div>
            <a href="https://eduriserck.com/ckadminmasterkey/adminmasterkeyfinal.php" target="_blank" rel="noopener noreferrer" class="mobile-drawer-link-ext">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Employee Central</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto; opacity: 0.6;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
            </a>
            <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Feduriserck.com%2Fclientele%2Fadmin%2Flogin.php" target="_blank" rel="noopener noreferrer" class="mobile-drawer-link-ext">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Eduriser Clientele</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto; opacity: 0.6;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
            </a>
            <a href="https://eduriserck.com/ckadminmasterkey/sso_relay.php?url=https%3A%2F%2Foutlook.cloud.microsoft%2Fmail%2F" target="_blank" rel="noopener noreferrer" class="mobile-drawer-link-ext">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                <span>Eduriser E-mail (Outlook)</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto; opacity: 0.6;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
            </a>
            <div class="mobile-drawer-footer">
                <a href="/profile" class="mobile-drawer-btn secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span>My Profile</span>
                </a>
                <a href="/logout" class="mobile-drawer-btn danger">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Mobile Fixed Bottom Navigation Bar -->
    <nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Mobile Navigation Bar">
        <a class="mobile-nav-tab <?= $currentPath === '/' || str_starts_with($currentPath, '/my-work') ? 'active' : '' ?>" href="/">
            <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span class="tab-label">Home</span>
        </a>
        <?php if (\App\Core\Permissions::canAccessPage('projects')): ?>
            <a class="mobile-nav-tab <?= str_starts_with($currentPath, '/projects') ? 'active' : '' ?>" href="/projects">
                <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                <span class="tab-label">Projects</span>
            </a>
        <?php endif; ?>
        <?php if (\App\Core\Permissions::canAccessPage('work_logs')): ?>
            <a class="mobile-nav-tab mobile-nav-highlight <?= str_starts_with($currentPath, '/work-logs') ? 'active' : '' ?>" href="/work-logs" title="Daily Work Log">
                <div class="mobile-nav-fab-icon" style="width: 48px; height: 48px; min-width: 48px; min-height: 48px; border-radius: 50%; aspect-ratio: 1/1; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width: 22px; height: 22px; min-width: 22px; min-height: 22px; flex-shrink: 0;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </div>
                <span class="tab-label">Log Time</span>
            </a>
        <?php endif; ?>
        <?php if (\App\Core\Permissions::canAccessPage('kanban')): ?>
            <a class="mobile-nav-tab <?= str_starts_with($currentPath, '/kanban') ? 'active' : '' ?>" href="/kanban">
                <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="5" height="18" rx="1"></rect><rect x="10" y="3" width="5" height="12" rx="1"></rect><rect x="17" y="3" width="5" height="15" rx="1"></rect></svg>
                <span class="tab-label">Kanban</span>
            </a>
        <?php endif; ?>
        <button type="button" class="mobile-nav-tab" id="mobileBottomMoreBtn" aria-label="Open More Menu">
            <svg class="tab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            <span class="tab-label">More</span>
        </button>
    </nav>
</body>
</html>
