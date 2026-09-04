<?php

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Permissions;
use App\Core\View;
use App\Models\Client;
use App\Models\ClientBatch;
use App\Models\ClientRenewalsXlsxExporter;
use App\Models\CustomField;
use App\Models\Dashboard;
use App\Models\Project;
use App\Models\ProjectFieldSetting;
use App\Models\Suggestions;
use App\Models\Task;
use App\Models\TaskListTemplate;
use App\Models\TimesheetXlsxExporter;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\Msg91Service;
use App\Services\NotificationService;

function suggestions_data(bool $withHierarchy = false): array
{
    if ($withHierarchy) {
        return [
            'hierarchy' => Suggestions::hierarchy(),
            'tasks' => Suggestions::tasks(),
        ];
    }

    // Wire key stays 'clients' to match the `data-autosuggest="clients"` attribute app.js reads.
    return [
        'clients' => Suggestions::projectNames(),
        'projectsOnly' => Suggestions::projectsOnly(),
        'clientsOnly' => Suggestions::clientNames(),
    ];
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = app_base_path();
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath)) ?: '/';
}
if ($path === '/index.php') {
    $path = '/';
}

function time_range_from_post(): array
{
    $from = trim($_POST['time_from'] ?? '');
    $to = trim($_POST['time_to'] ?? '');
    if ($from === '' || $to === '') {
        return ['', null];
    }

    $fromTime = strtotime('2000-01-01 ' . $from);
    $toTime = strtotime('2000-01-01 ' . $to);
    if (!$fromTime || !$toTime) {
        return ['', null];
    }

    if ($toTime < $fromTime) {
        $toTime = strtotime('+1 day', $toTime);
    }

    $hours = max(0, ($toTime - $fromTime) / 3600);
    $label = date('h:i A', $fromTime) . ' - ' . date('h:i A', $toTime);
    return [$label, $hours];
}

// The Central Management System's SSO form posts straight to the app root
// (https://eduriserck.com/pms/index.php, which normalizes to '/' below) -
// handle it wherever it lands, using the same POST-based-SSO logic as the
// dedicated /login route.
if ($path === '/login' || ($path === '/' && is_post() && isset($_POST['email']))) {
    if (Auth::check()) {
        redirect('/');
    }

    $error = null;
    $prefillEmail = '';

    if (is_post()) {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($email !== '' && $password === '') {
            // POST-based SSO from the master app: no password field at all,
            // so there's no session-bound form to CSRF-check against - the
            // email-present/password-blank shape is what stands in for it.
            if (Auth::ssoLogin($email)) {
                redirect('/');
            }

            $prefillEmail = $email;
            $error = 'No active user was found for this SSO email.';
        } else {
            verify_csrf();

            if (Auth::attempt($email, $password)) {
                redirect('/');
            }

            $prefillEmail = $email;
            $error = 'Invalid email or password.';
        }
    }

    View::render('login', ['title' => 'Login', 'error' => $error, 'prefillEmail' => $prefillEmail], 'auth_layout');
    exit;
}

if ($path === '/forgot-password') {
    if (Auth::check()) {
        redirect('/');
    }

    $error = null;
    $success = null;
    $prefillEmail = '';

    if (is_post()) {
        verify_csrf();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $prefillEmail = $email;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $user = User::findByEmail($email);
            if ($user && $user['status'] === 'active') {
                $rawToken = Msg91Service::createPasswordResetToken($email);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'eduriserck.com';
                $resetUrl = $scheme . '://' . $host . url('/reset-password?token=' . urlencode($rawToken));

                $result = Msg91Service::sendPasswordResetEmail($user['email'], $user['name'], $resetUrl);
                if ($result['success']) {
                    $success = 'A password reset link has been sent to ' . htmlspecialchars($email) . '. Please check your email inbox.';
                } else {
                    $error = 'Could not send reset email: ' . $result['message'];
                }
            } else {
                $success = 'If an active account exists for ' . htmlspecialchars($email) . ', a password reset link has been sent.';
            }
        }
    }

    View::render('forgot_password', [
        'title' => 'Forgot Password',
        'error' => $error,
        'success' => $success,
        'prefillEmail' => $prefillEmail,
    ], 'auth_layout');
    exit;
}

if ($path === '/reset-password') {
    if (Auth::check()) {
        redirect('/');
    }

    $rawToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $tokenRecord = Msg91Service::validateResetToken($rawToken);

    if (!$tokenRecord) {
        View::render('forgot_password', [
            'title' => 'Invalid or Expired Link',
            'error' => 'This password reset link is invalid or has expired. Please request a new link below.',
            'success' => null,
            'prefillEmail' => '',
        ], 'auth_layout');
        exit;
    }

    $error = null;
    $email = $tokenRecord['email'];

    if (is_post()) {
        verify_csrf();
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match. Please re-enter.';
        } else {
            $user = User::findByEmail($email);
            if ($user) {
                $db = \App\Core\Database::connection();
                $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);

                Msg91Service::markTokenUsed($rawToken);

                View::render('login', [
                    'title' => 'Login',
                    'error' => null,
                    'success' => 'Your password has been reset successfully! Please sign in with your new password.',
                    'prefillEmail' => $email,
                ], 'auth_layout');
                exit;
            } else {
                $error = 'Associated user account was not found.';
            }
        }
    }

    View::render('reset_password', [
        'title' => 'Set New Password',
        'token' => $rawToken,
        'email' => $email,
        'error' => $error,
    ], 'auth_layout');
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    redirect('/login');
}

// Scheduled Cron Job endpoint (Allows cPanel scheduled runs without interactive session)
if ($path === '/cron/check-renewals') {
    header('Content-Type: application/json');
    $alertsSent = NotificationService::checkRenewalAlerts();
    echo json_encode([
        'success' => true,
        'alerts_sent' => $alertsSent,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

Auth::requireLogin();

if ($path === '/api/notifications') {
    header('Content-Type: application/json');
    $userId = (int) $_SESSION['user_id'];
    $count = NotificationService::getUnreadCount($userId);
    $notifications = NotificationService::getRecent($userId, 20);
    echo json_encode(['count' => $count, 'notifications' => $notifications]);
    exit;
}

if ($path === '/api/notifications/mark-read' && is_post()) {
    header('Content-Type: application/json');
    $userId = (int) $_SESSION['user_id'];
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        NotificationService::markAsRead($id, $userId);
    }
    $count = NotificationService::getUnreadCount($userId);
    echo json_encode(['success' => true, 'count' => $count]);
    exit;
}

if ($path === '/api/notifications/mark-all-read' && is_post()) {
    header('Content-Type: application/json');
    $userId = (int) $_SESSION['user_id'];
    NotificationService::markAllAsRead($userId);
    echo json_encode(['success' => true, 'count' => 0]);
    exit;
}

if ($path === '/') {
    $userId = (int) $_SESSION['user_id'];
    View::render('dashboard', [
        'title' => 'Home',
        'stats' => Dashboard::stats($userId),
        'recentTasks' => Dashboard::recentTasks(),
        'myTasks' => Dashboard::myTasks($userId),
        'myRecentLogs' => Dashboard::myRecentLogs($userId),
        'myLogSummary' => Dashboard::myLogSummary($userId),
        'labels' => Task::statusLabels(),
        'suggestions' => suggestions_data(true),
        'taskFilterOptions' => Task::filterOptions(),
    ]);
    exit;
}

if ($path === '/my-work') {
    redirect('/');
}

if ($path === '/tasks/search') {
    $results = Task::search([
        'q' => $_GET['q'] ?? '',
        'client_id' => $_GET['client_id'] ?? 0,
        'project_id' => $_GET['project_id'] ?? 0,
        'phase_id' => $_GET['phase_id'] ?? 0,
        'task_list_id' => $_GET['task_list_id'] ?? 0,
    ]);

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

if ($path === '/tasks/daily-log-search') {
    $results = Task::dailyLogSearch(
        (string) ($_GET['project'] ?? ''),
        (string) ($_GET['q'] ?? ''),
        (string) ($_GET['phase'] ?? ''),
        (string) ($_GET['task_list'] ?? '')
    );

    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

if ($path === '/profile') {
    $currentUser = User::find((int) $_SESSION['user_id']);
    $error = null;

    if (is_post()) {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $data = [
            'name' => $name,
            'designation' => trim($_POST['designation'] ?? '') ?: null,
            'department' => trim($_POST['department'] ?? '') ?: null,
        ];

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($name === '') {
            $error = 'Name is required.';
        } elseif ($newPassword !== '' && !password_verify($currentPassword, $currentUser['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        }

        $avatarFile = $_FILES['avatar'] ?? null;
        if (!$error && $avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $imageInfo = @getimagesize($avatarFile['tmp_name']);
            $mime = $imageInfo['mime'] ?? '';

            if ($avatarFile['size'] > 3 * 1024 * 1024) {
                $error = 'Profile picture must be smaller than 3MB.';
            } elseif (!isset($allowedTypes[$mime])) {
                $error = 'Profile picture must be a JPG, PNG, or WEBP image.';
            } else {
                $uploadDir = __DIR__ . '/assets/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $filename = $currentUser['id'] . '_' . time() . '.' . $allowedTypes[$mime];
                $target = $uploadDir . '/' . $filename;

                if (move_uploaded_file($avatarFile['tmp_name'], $target)) {
                    if (!empty($currentUser['avatar_path'])) {
                        $old = $uploadDir . '/' . $currentUser['avatar_path'];
                        if (is_file($old)) {
                            unlink($old);
                        }
                    }
                    $data['avatar_path'] = $filename;
                } else {
                    $error = 'Could not save the uploaded picture. Please try again.';
                }
            }
        }

        if (!$error) {
            if ($newPassword !== '') {
                $data['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            User::updateSelfProfile((int) $currentUser['id'], $data);
            $_SESSION['user_name'] = $name;
            if (array_key_exists('avatar_path', $data)) {
                $_SESSION['user_avatar'] = $data['avatar_path'];
            }
            $_SESSION['flash_success'] = 'Profile updated.';
            redirect('/profile');
        }

        $currentUser = array_merge($currentUser, $data);
    }

    View::render('users/profile', ['title' => 'My Profile', 'profileUser' => $currentUser, 'error' => $error]);
    exit;
}

if ($path === '/projects') {
    Permissions::requirePage('projects');
    $showArchived = ($_GET['archived'] ?? '') === '1';
    $projects = Project::all($showArchived);
    View::render('projects/index', [
        'title' => 'Projects',
        'projects' => $projects,
        'showArchived' => $showArchived,
        'archivedCount' => Project::archivedCount(),
        'projectFields' => ProjectFieldSetting::visible(),
        'customFields' => CustomField::visible(),
        'customValues' => CustomField::valuesForProjects(array_column($projects, 'id')),
    ]);
    exit;
}

if ($path === '/projects/archive' && is_post()) {
    verify_csrf();
    Permissions::requireProjectActions();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    if ($projectId) {
        Project::archive($projectId);
    }
    redirect('/projects');
}

if ($path === '/projects/unarchive' && is_post()) {
    verify_csrf();
    Permissions::requireProjectActions();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    if ($projectId) {
        Project::unarchive($projectId);
    }
    redirect('/projects?archived=1');
}

if ($path === '/projects/delete' && is_post()) {
    verify_csrf();
    Permissions::require(['admin', 'manager']);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $wasArchived = ($_POST['was_archived'] ?? '') === '1';
    if ($projectId) {
        Project::delete($projectId);
    }
    redirect($wasArchived ? '/projects?archived=1' : '/projects');
}

if ($path === '/projects/quick-update' && is_post()) {
    verify_csrf();
    Permissions::requireProjectActions();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $field = (string) ($_POST['field'] ?? '');
    $value = $_POST['value'] ?? '';

    if ($projectId && $field !== '') {
        if (str_starts_with($field, 'custom:')) {
            CustomField::quickUpdateValue($projectId, substr($field, 7), $value);
        } else {
            Project::quickUpdate($projectId, $field, $value);
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/kanban') {
    Permissions::requirePage('kanban');
    $projectId = ($_GET['project_id'] ?? '') !== '' ? (int) $_GET['project_id'] : null;
    $assigneeId = ($_GET['assignee_id'] ?? '') !== '' ? (int) $_GET['assignee_id'] : null;
    $limitParam = $_GET['completed_limit'] ?? '25';
    $completedLimit = ($limitParam === 'all' || $limitParam === '-1') ? null : max(10, (int) $limitParam);
    if ($limitParam !== 'all' && $limitParam !== '-1' && $completedLimit === null) {
        $completedLimit = 25;
    }

    $kanbanData = Task::kanban($projectId, $assigneeId, $completedLimit);

    View::render('kanban/index', [
        'title' => 'Kanban',
        'columns' => $kanbanData['columns'],
        'counts' => $kanbanData['counts'],
        'completedTotal' => $kanbanData['completed_total'],
        'completedLimit' => $limitParam,
        'statuses' => Task::STATUSES,
        'labels' => Task::statusLabels(),
        'projects' => Project::all(),
        'users' => User::allActive(),
        'selectedProject' => $projectId,
        'selectedAssignee' => $assigneeId,
    ]);
    exit;
}

if ($path === '/time-logs') {
    Permissions::requirePage('time_logs');
    WorkLog::cleanupOrphanedLogs();
    $month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
    $monthStart = $month . '-01';
    [$fromDate, $toDate, $dates] = WorkLog::dateRange($monthStart, date('Y-m-t', strtotime($monthStart)));

    $view = ($_GET['view'] ?? 'user') === 'client' ? 'client' : 'user';
    $rows = $view === 'client'
        ? WorkLog::timeLogByClient($fromDate, $toDate)
        : WorkLog::timeLogByUser($fromDate, $toDate);
    $breakdown = WorkLog::breakdown($view, $fromDate, $toDate);

    View::render('time_logs/index', [
        'title' => 'Time Logs',
        'view' => $view,
        'month' => $month,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'dates' => $dates,
        'rows' => $rows,
        'breakdown' => $breakdown['tree'],
        'breakdownTaskIds' => $breakdown['taskIds'],
    ]);
    exit;
}

if ($path === '/work-logs') {
    Permissions::requirePage('work_logs');
    View::render('work_logs/index', [
        'title' => 'Daily Log',
        'recentLogs' => WorkLog::recentForUser((int) $_SESSION['user_id']),
        'suggestions' => suggestions_data(true),
    ]);
    exit;
}

if ($path === '/work-logs/create' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();

    [$timeRange, $rangeHours] = time_range_from_post();
    $required = ['project_group', 'phase', 'module_name', 'task_category', 'notes', 'log_date', 'billing_type'];
    $hasMissing = false;
    foreach ($required as $field) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            $hasMissing = true;
            break;
        }
    }

    $logDate = trim((string) ($_POST['log_date'] ?? ''));
    if ($logDate !== '' && $logDate > date('Y-m-d')) {
        $_SESSION['flash_error'] = 'Time log date cannot be in the future.';
        redirect(($_POST['return_to'] ?? '') === 'home' ? '/' : '/work-logs');
    }

    $hours = $rangeHours ?? (float) ($_POST['hours'] ?? 0);
    if ($hours <= 0) {
        $hasMissing = true;
    }

    if (!$hasMissing) {
        $userId = (int) $_SESSION['user_id'];
        $taskId = ($_POST['task_id'] ?? '') !== '' ? (int) $_POST['task_id'] : null;
        $selectedTask = $taskId ? Task::find($taskId) : null;

        // Daily Log forms now require an actual task. Keep the legacy
        // fallback only for an older page still open during deployment.
        if (($_POST['task_selection_required'] ?? '') === '1' && !$selectedTask) {
            $hasMissing = true;
        }

        if ($selectedTask) {
            $taskId = (int) $selectedTask['id'];
            $projectName = (string) $selectedTask['project_name'];
            $phaseName = (string) ($selectedTask['phase_name'] ?: 'No Phase');
            $taskListName = (string) ($selectedTask['task_list_name'] ?: 'General');
            $taskCategory = (string) $selectedTask['title'];
        } else {
            $projectName = trim($_POST['project_group']);
            $phaseName = trim($_POST['phase']);
            $taskListName = trim($_POST['module_name']);
            $taskCategory = $_POST['task_category'];
        }

        if ($hasMissing) {
            redirect(($_POST['return_to'] ?? '') === 'home' ? '/' : '/work-logs');
        }

        // Daily Log doesn't have a task picker of its own - find-or-create a
        // real Task behind the scenes so the entry is clickable from Time
        // Logs, same as logging time straight from a task's own page already
        // was. Silently stays unlinked if the typed project name doesn't
        // match a real project - never blocks saving the log.
        $taskId ??= Task::findOrCreateForLog(
            $projectName,
            $phaseName,
            $taskListName,
            trim($_POST['notes']),
            $userId
        );

        WorkLog::create([
            'task_id' => $taskId,
            'project_group' => $projectName,
            'phase' => $phaseName,
            'module_name' => $taskListName,
            'task_category' => $taskCategory,
            'notes' => trim($_POST['notes']),
            'daily_log' => $timeRange ?: null,
            'time_period' => $timeRange ?: null,
            'hours' => max(0, $hours),
            'log_date' => $_POST['log_date'],
            'billing_type' => $_POST['billing_type'] === 'Non-Billable' ? 'Non-Billable' : 'Billable',
            'user_id' => $userId,
        ]);
    }

    redirect(($_POST['return_to'] ?? '') === 'home' ? '/' : '/work-logs');
}

function project_color_from_post(): ?string
{
    if (!empty($_POST['color_clear'])) {
        return null;
    }

    $color = trim($_POST['color'] ?? '');
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : null;
}

function work_log_return_url(string $returnTo, ?int $taskId): string
{
    if ($returnTo === 'task' && $taskId) {
        return '/tasks/show?id=' . $taskId;
    }

    return '/work-logs';
}

if ($path === '/work-logs/edit') {
    $logId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $log = WorkLog::find($logId);
    if (!$log) {
        http_response_code(404);
        exit('Time log not found.');
    }

    Permissions::requireEditWorkLog($log);
    $returnTo = $_GET['return_to'] ?? $_POST['return_to'] ?? 'work-logs';
    $error = null;

    if (is_post()) {
        verify_csrf();
        [$timeRange, $rangeHours] = time_range_from_post();
        $hours = $rangeHours ?? (float) ($_POST['hours'] ?? 0);

        $logDate = trim((string) ($_POST['log_date'] ?? ''));
        if ($logDate !== '' && $logDate > date('Y-m-d')) {
            $error = 'Time log date cannot be in the future.';
        } elseif (trim($_POST['notes'] ?? '') === '' || $hours <= 0) {
            $error = 'Please fill in Activity and a valid number of hours.';
        } else {
            WorkLog::update($logId, [
                'project_group' => trim($_POST['project_group'] ?? ''),
                'phase' => trim($_POST['phase'] ?? ''),
                'module_name' => trim($_POST['module_name'] ?? ''),
                'task_category' => $_POST['task_category'],
                'notes' => trim($_POST['notes']),
                'daily_log' => $timeRange ?: ($log['daily_log'] ?? null),
                'time_period' => $timeRange ?: ($log['time_period'] ?? null),
                'hours' => max(0, $hours),
                'log_date' => $_POST['log_date'] ?: $log['log_date'],
                'billing_type' => $_POST['billing_type'] === 'Non-Billable' ? 'Non-Billable' : 'Billable',
            ]);
            $_SESSION['flash_success'] = 'Time log updated.';
            redirect(work_log_return_url($returnTo, $log['task_id'] ? (int) $log['task_id'] : null));
        }
    }

    View::render('work_logs/edit', [
        'title' => 'Edit Time Log',
        'log' => $log,
        'returnTo' => $returnTo,
        'error' => $error,
        'suggestions' => suggestions_data(true),
    ]);
    exit;
}

if ($path === '/work-logs/delete' && is_post()) {
    verify_csrf();
    $logId = (int) ($_POST['id'] ?? 0);
    $log = WorkLog::find($logId);
    if (!$log) {
        http_response_code(404);
        exit('Time log not found.');
    }

    Permissions::requireEditWorkLog($log);
    $taskId = $log['task_id'] ? (int) $log['task_id'] : null;
    WorkLog::delete($logId);
    $_SESSION['flash_success'] = 'Time log deleted.';
    redirect(work_log_return_url($_POST['return_to'] ?? 'work-logs', $taskId));
}

if ($path === '/clients') {
    Permissions::requirePage('clients');
    $projectsByClient = [];
    foreach (Project::all() as $project) {
        if ($project['client_id']) {
            $projectsByClient[(int) $project['client_id']][] = $project;
        }
    }

    View::render('clients/index', [
        'title' => 'Clients',
        'clients' => Client::all(),
        'projectsByClient' => $projectsByClient,
    ]);
    exit;
}

if ($path === '/clients/create') {
    Permissions::requirePage('clients');
    $error = null;

    if (is_post()) {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Client name is required.';
        } else {
            try {
                Client::create([
                    'name' => $name,
                    'notes' => trim($_POST['notes'] ?? '') ?: null,
                    'status' => $_POST['status'] ?? 'active',
                ]);
                redirect('/clients');
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                    $error = 'A client with this name already exists.';
                } else {
                    $error = 'Database error creating client: ' . $e->getMessage();
                }
            }
        }
    }

    View::render('clients/create', ['title' => 'New Client', 'error' => $error]);
    exit;
}

if ($path === '/clients/edit') {
    Permissions::requirePage('clients');
    $clientId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $client = Client::find($clientId);
    if (!$client) {
        http_response_code(404);
        exit('Client not found.');
    }

    $error = null;
    if (is_post()) {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Client name is required.';
        } else {
            try {
                Client::update($clientId, [
                    'name' => $name,
                    'notes' => trim($_POST['notes'] ?? '') ?: null,
                    'status' => $_POST['status'] ?? 'active',
                ]);
                redirect('/clients');
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                    $error = 'A client with this name already exists.';
                } else {
                    $error = 'Database error updating client: ' . $e->getMessage();
                }
            }
        }
    }

    View::render('clients/edit', ['title' => 'Edit Client', 'client' => $client, 'error' => $error]);
    exit;
}

if ($path === '/client-renewals') {
    Permissions::requirePage('client_renewals');
    $filters = [
        'client_id' => $_GET['client_id'] ?? '',
        'project_id' => $_GET['project_id'] ?? '',
        'urgency' => $_GET['urgency'] ?? '',
        'status' => $_GET['status'] ?? 'active',
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'q' => trim($_GET['q'] ?? ''),
    ];
    $viewMode = $_GET['view'] ?? 'hierarchy';
    if (!in_array($viewMode, ['hierarchy', 'platform_hierarchy', 'flat', 'statement'], true)) {
        $viewMode = 'hierarchy';
    }

    $scheduleMode = ($viewMode === 'platform_hierarchy') ? 'platform' : 'contract';

    $clientId = !empty($filters['client_id']) ? (int) $filters['client_id'] : null;
    $projectId = !empty($filters['project_id']) ? (int) $filters['project_id'] : null;

    View::render('client_renewals/index', [
        'title' => 'Client Renewals',
        'stats' => ClientBatch::stats($filters, $scheduleMode),
        'hierarchy' => ClientBatch::hierarchy($filters, $scheduleMode),
        'batches' => ClientBatch::all($filters, $scheduleMode),
        'yearComparison' => ClientBatch::yearComparison($clientId, $projectId, $scheduleMode),
        'historyLogs' => ClientBatch::allHistory($filters, $scheduleMode),
        'clientsWithProjects' => ClientBatch::allActiveClientsWithProjects(),
        'filters' => $filters,
        'viewMode' => $viewMode,
        'scheduleMode' => $scheduleMode,
        'canEdit' => Permissions::canWrite(),
    ]);
    exit;
}

if ($path === '/client-renewals/check-alerts' && is_post()) {
    Permissions::requirePage('client_renewals');
    $alertsSent = NotificationService::checkRenewalAlerts();
    flash_set("Renewal alert check completed. Sent {$alertsSent} notification(s) & email(s).", 'success');
    redirect('/client-renewals');
}

if ($path === '/client-renewals/batch-history-api') {
    Permissions::requirePage('client_renewals');
    $batchId = (int) ($_GET['batch_id'] ?? 0);
    $history = $batchId ? ClientBatch::historyForBatch($batchId) : [];

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($path === '/client-renewals/export') {
    Permissions::requirePage('client_renewals');
    Permissions::requireExportAccess();
    $filters = [
        'client_id' => $_GET['client_id'] ?? '',
        'project_id' => $_GET['project_id'] ?? '',
        'urgency' => $_GET['urgency'] ?? '',
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'q' => trim($_GET['q'] ?? ''),
    ];

    $scheduleMode = in_array($_GET['schedule_mode'] ?? '', ['contract', 'platform'], true)
        ? $_GET['schedule_mode']
        : (($_GET['view'] ?? '') === 'platform_hierarchy' ? 'platform' : 'contract');

    $clientId = !empty($filters['client_id']) ? (int) $filters['client_id'] : null;
    $projectId = !empty($filters['project_id']) ? (int) $filters['project_id'] : null;

    $client = $clientId ? Client::find($clientId) : null;
    $project = $projectId ? Project::find($projectId) : null;

    $stats = ClientBatch::stats($filters, $scheduleMode);
    $yearComparison = ClientBatch::yearComparison($clientId, $projectId, $scheduleMode);
    $activeBatches = ClientBatch::all($filters, $scheduleMode);
    $historyLogs = ClientBatch::allHistory($filters, $scheduleMode);

    $options = [
        'include_summary' => !empty($_GET['include_summary']),
        'include_active' => !empty($_GET['include_active']),
        'include_history' => !empty($_GET['include_history']),
        'include_notes' => !empty($_GET['include_notes']),
        'from_date' => $filters['from_date'] ?: null,
        'to_date' => $filters['to_date'] ?: null,
        'exported_by' => $_SESSION['user_name'] ?? 'EduRiser User',
        'schedule_mode' => $scheduleMode,
    ];

    $excelBinary = ClientRenewalsXlsxExporter::build(
        $client,
        $project,
        $stats,
        $yearComparison,
        $activeBatches,
        $historyLogs,
        $options
    );

    $filenamePrefix = $client ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $client['name']) : 'all_clients';
    $schedulePrefix = ($scheduleMode === 'platform') ? 'platform_licences_statement' : 'contract_licences_statement';
    $filename = "{$schedulePrefix}_{$filenamePrefix}_" . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($excelBinary));
    echo $excelBinary;
    exit;
}

if ($path === '/client-renewals/projects-api') {
    Permissions::requirePage('client_renewals');
    $clientId = (int) ($_GET['client_id'] ?? 0);
    $projects = $clientId ? ClientBatch::projectsByClient($clientId) : [];

    header('Content-Type: application/json');
    echo json_encode($projects);
    exit;
}

if ($path === '/client-renewals/create' && is_post()) {
    verify_csrf();
    Permissions::requirePage('client_renewals');
    Permissions::requireWriteAccess();

    $clientId = (int) ($_POST['client_id'] ?? 0);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $batchName = trim($_POST['batch_name'] ?? '');
    $licenceCount = (int) ($_POST['licence_count'] ?? 0);
    $creationDate = $_POST['creation_date'] ?? '';
    $renewalDate = $_POST['renewal_date'] ?? '';
    $platformStartDate = !empty($_POST['platform_start_date']) ? $_POST['platform_start_date'] : null;
    $platformRenewalDate = !empty($_POST['platform_renewal_date']) ? $_POST['platform_renewal_date'] : null;

    if ($clientId && $projectId && $batchName !== '' && $creationDate !== '' && $renewalDate !== '') {
        ClientBatch::create([
            'client_id' => $clientId,
            'project_id' => $projectId,
            'batch_name' => $batchName,
            'region_department' => trim($_POST['region_department'] ?? ''),
            'licence_count' => $licenceCount,
            'creation_date' => $creationDate,
            'renewal_date' => $renewalDate,
            'platform_start_date' => $platformStartDate,
            'platform_renewal_date' => $platformRenewalDate,
            'given_by' => trim($_POST['given_by'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => 'active',
            'created_by' => (int) $_SESSION['user_id'],
        ]);
        $_SESSION['flash_success'] = 'Batch added successfully.';
    } else {
        $_SESSION['flash_error'] = 'Please fill in all required fields (Client, Project, Batch Name, Dates, Licences).';
    }

    redirect('/client-renewals');
}

if ($path === '/client-renewals/update' && is_post()) {
    verify_csrf();
    Permissions::requirePage('client_renewals');
    Permissions::requireWriteAccess();

    $id = (int) ($_POST['id'] ?? 0);
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $batchName = trim($_POST['batch_name'] ?? '');
    $licenceCount = (int) ($_POST['licence_count'] ?? 0);
    $creationDate = $_POST['creation_date'] ?? '';
    $renewalDate = $_POST['renewal_date'] ?? '';
    $platformStartDate = !empty($_POST['platform_start_date']) ? $_POST['platform_start_date'] : null;
    $platformRenewalDate = !empty($_POST['platform_renewal_date']) ? $_POST['platform_renewal_date'] : null;

    if ($id && $clientId && $projectId && $batchName !== '' && $creationDate !== '' && $renewalDate !== '') {
        ClientBatch::update($id, [
            'client_id' => $clientId,
            'project_id' => $projectId,
            'batch_name' => $batchName,
            'region_department' => trim($_POST['region_department'] ?? ''),
            'licence_count' => $licenceCount,
            'creation_date' => $creationDate,
            'renewal_date' => $renewalDate,
            'platform_start_date' => $platformStartDate,
            'platform_renewal_date' => $platformRenewalDate,
            'given_by' => trim($_POST['given_by'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
        ]);
        $_SESSION['flash_success'] = 'Batch updated successfully.';
    } else {
        $_SESSION['flash_error'] = 'Could not update batch. Required fields were missing.';
    }

    redirect('/client-renewals');
}

if ($path === '/client-renewals/renew' && is_post()) {
    verify_csrf();
    Permissions::requirePage('client_renewals');
    Permissions::requireWriteAccess();

    $id = (int) ($_POST['id'] ?? 0);
    $renewalDate = $_POST['renewal_date'] ?? '';
    $periodStart = $_POST['period_start'] ?? date('Y-m-d');
    $licenceCount = ($_POST['licence_count'] ?? '') !== '' ? (int) $_POST['licence_count'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $givenBy = trim($_POST['given_by'] ?? '');
    $contractHours = ($_POST['contract_hours'] ?? '') !== '' ? (float) $_POST['contract_hours'] : null;
    $markPreviousBilled = !empty($_POST['mark_previous_billed']);
    $invoiceReference = trim((string) ($_POST['invoice_reference'] ?? '')) ?: null;
    $platformPeriodStart = !empty($_POST['platform_period_start']) ? $_POST['platform_period_start'] : null;
    $platformRenewalDate = !empty($_POST['platform_renewal_date']) ? $_POST['platform_renewal_date'] : null;

    if ($id && $renewalDate !== '') {
        ClientBatch::renew(
            $id,
            $renewalDate,
            $licenceCount,
            $periodStart,
            $notes ?: null,
            $givenBy ?: null,
            (int) $_SESSION['user_id'],
            $contractHours,
            $markPreviousBilled,
            $invoiceReference,
            $platformPeriodStart,
            $platformRenewalDate
        );
        $_SESSION['flash_success'] = 'Batch renewed successfully and cycle logged in audit history.';
    }

    redirect('/client-renewals');
}

if ($path === '/client-renewals/extend' && is_post()) {
    verify_csrf();
    Permissions::requirePage('client_renewals');
    Permissions::requireWriteAccess();

    $id = (int) ($_POST['id'] ?? 0);
    $extendedDate = $_POST['extended_date'] ?? '';
    $periodStart = $_POST['period_start'] ?? date('Y-m-d');
    $licenceCount = ($_POST['licence_count'] ?? '') !== '' ? (int) $_POST['licence_count'] : null;
    $reason = trim($_POST['extension_reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $givenBy = trim($_POST['given_by'] ?? '');
    $platformPeriodStart = !empty($_POST['platform_period_start']) ? $_POST['platform_period_start'] : null;
    $platformExtendedDate = !empty($_POST['platform_extended_date']) ? $_POST['platform_extended_date'] : null;

    if ($id && $extendedDate !== '') {
        ClientBatch::extend(
            $id,
            $extendedDate,
            $licenceCount,
            $periodStart,
            $reason ?: 'Courtesy / Grace Period Extension',
            $givenBy ?: null,
            (int) $_SESSION['user_id'],
            $notes ?: null,
            $platformPeriodStart,
            $platformExtendedDate
        );
        $_SESSION['flash_success'] = 'Batch extension granted and recorded in audit history.';
    }

    redirect('/client-renewals');
}

if ($path === '/client-renewals/delete' && is_post()) {
    verify_csrf();
    Permissions::requirePage('client_renewals');
    Permissions::requireWriteAccess();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        ClientBatch::delete($id);
        $_SESSION['flash_success'] = 'Batch deleted.';
    }

    redirect('/client-renewals');
}

if ($path === '/client-renewals/archive' && is_post()) {
    verify_csrf();
    Permissions::requirePage('client_renewals');
    Permissions::requireWriteAccess();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        ClientBatch::archive($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = 'Batch archived.';
    }

    redirect('/client-renewals');
}

if ($path === '/client-renewals/unarchive' && is_post()) {
    verify_csrf();
    Permissions::requirePage('client_renewals');
    Permissions::requireWriteAccess();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        ClientBatch::unarchive($id, (int) $_SESSION['user_id']);
        $_SESSION['flash_success'] = 'Batch unarchived and restored to active list.';
    }

    redirect('/client-renewals');
}

if ($path === '/clients/reassign-project' && is_post()) {
    verify_csrf();
    Permissions::requirePage('clients');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $moved = $projectId && $clientId ? Project::reassignClient($projectId, $clientId) : false;

    header('Content-Type: application/json');
    echo json_encode(['ok' => $moved]);
    exit;
}

if ($path === '/projects/create') {
    Permissions::requireProjectActions();
    $users = User::allActive();
    $clients = Client::allActive();
    $customFields = CustomField::all();
    $error = null;

    if (is_post()) {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Project name is required.';
        } else {
            $projectId = Project::create([
                'name' => $name,
                'code' => trim($_POST['code'] ?? '') ?: null,
                'client_id' => ($_POST['client_id'] ?? '') !== '' ? (int) $_POST['client_id'] : null,
                'project_group' => trim($_POST['project_group'] ?? '') ?: null,
                'color' => project_color_from_post(),
                'description' => trim($_POST['description'] ?? '') ?: null,
                'owner_id' => (int) ($_POST['owner_id'] ?? $_SESSION['user_id']),
                'status' => $_POST['status'] ?? 'planned',
                'priority' => $_POST['priority'] ?? 'medium',
                'billed_learners' => ($_POST['billed_learners'] ?? '') !== '' ? (int) $_POST['billed_learners'] : null,
                'learners_on_platform' => ($_POST['learners_on_platform'] ?? '') !== '' ? (int) $_POST['learners_on_platform'] : null,
                'learners_connected' => ($_POST['learners_connected'] ?? '') !== '' ? (int) $_POST['learners_connected'] : null,
                'started_with_courses' => ($_POST['started_with_courses'] ?? '') !== '' ? (int) $_POST['started_with_courses'] : null,
                'total_time' => ($_POST['total_time'] ?? '') !== '' ? (float) $_POST['total_time'] : null,
                'average_time_per_learner' => ($_POST['average_time_per_learner'] ?? '') !== '' ? (float) $_POST['average_time_per_learner'] : null,
                'adoption_percent' => ($_POST['adoption_percent'] ?? '') !== '' ? (float) $_POST['adoption_percent'] : null,
                'build_hours' => ($_POST['build_hours'] ?? '') !== '' ? (float) $_POST['build_hours'] : 0.00,
                'run_hours' => ($_POST['run_hours'] ?? '') !== '' ? (float) $_POST['run_hours'] : 0.00,
                'is_open_po' => !empty($_POST['is_open_po']) ? 1 : 0,
                'start_date' => $_POST['start_date'] ?: null,
                'due_date' => $_POST['due_date'] ?: null,
            ]);
            CustomField::saveValues($projectId, $_POST['custom'] ?? []);

            redirect('/projects/show?id=' . $projectId);
        }
    }

    View::render('projects/create', [
        'title' => 'New Project',
        'users' => $users,
        'clients' => $clients,
        'customFields' => $customFields,
        'customValues' => [],
        'projectFieldSettings' => ProjectFieldSetting::getMap(),
        'error' => $error,
        'suggestions' => suggestions_data(),
    ]);
    exit;
}

if ($path === '/projects/edit') {
    Permissions::requireProjectActions();
    $projectId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $project = Project::find($projectId);
    if (!$project) {
        http_response_code(404);
        exit('Project not found.');
    }

    $users = User::allActive();
    $clients = Client::allActive();
    $customFields = CustomField::all();
    $error = null;

    if (is_post()) {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Project name is required.';
        } else {
            Project::update($projectId, [
                'name' => $name,
                'code' => trim($_POST['code'] ?? '') ?: null,
                'client_id' => ($_POST['client_id'] ?? '') !== '' ? (int) $_POST['client_id'] : null,
                'project_group' => trim($_POST['project_group'] ?? '') ?: null,
                'color' => project_color_from_post(),
                'description' => trim($_POST['description'] ?? '') ?: null,
                'owner_id' => (int) ($_POST['owner_id'] ?? $_SESSION['user_id']),
                'status' => $_POST['status'] ?? 'planned',
                'priority' => $_POST['priority'] ?? 'medium',
                'billed_learners' => ($_POST['billed_learners'] ?? '') !== '' ? (int) $_POST['billed_learners'] : null,
                'learners_on_platform' => ($_POST['learners_on_platform'] ?? '') !== '' ? (int) $_POST['learners_on_platform'] : null,
                'learners_connected' => ($_POST['learners_connected'] ?? '') !== '' ? (int) $_POST['learners_connected'] : null,
                'started_with_courses' => ($_POST['started_with_courses'] ?? '') !== '' ? (int) $_POST['started_with_courses'] : null,
                'total_time' => ($_POST['total_time'] ?? '') !== '' ? (float) $_POST['total_time'] : null,
                'average_time_per_learner' => ($_POST['average_time_per_learner'] ?? '') !== '' ? (float) $_POST['average_time_per_learner'] : null,
                'adoption_percent' => ($_POST['adoption_percent'] ?? '') !== '' ? (float) $_POST['adoption_percent'] : null,
                'build_hours' => ($_POST['build_hours'] ?? '') !== '' ? (float) $_POST['build_hours'] : 0.00,
                'run_hours' => ($_POST['run_hours'] ?? '') !== '' ? (float) $_POST['run_hours'] : 0.00,
                'is_open_po' => !empty($_POST['is_open_po']) ? 1 : 0,
                'start_date' => $_POST['start_date'] ?: null,
                'due_date' => $_POST['due_date'] ?: null,
            ]);
            CustomField::saveValues($projectId, $_POST['custom'] ?? []);

            redirect('/projects/show?id=' . $projectId . '&tab=details');
        }
    }

    View::render('projects/edit', [
        'title' => 'Edit Project',
        'project' => $project,
        'users' => $users,
        'clients' => $clients,
        'customFields' => $customFields,
        'customValues' => CustomField::valuesForProject($projectId),
        'projectFieldSettings' => ProjectFieldSetting::getMap(),
        'error' => $error,
        'suggestions' => suggestions_data(),
    ]);
    exit;
}

if ($path === '/projects/show') {
    Permissions::requirePage('projects');
    $projectId = (int) ($_GET['id'] ?? 0);
    $project = Project::find($projectId);
    if (!$project) {
        http_response_code(404);
        exit('Project not found.');
    }

    $statusFilter = $_GET['status'] ?? 'all_open';
    $statusFilter = in_array($statusFilter, array_merge(['all', 'all_open'], Task::STATUSES), true) ? $statusFilter : 'all_open';
    $groupBy = ($_GET['group_by'] ?? 'task_list') === 'phase' ? 'phase' : 'task_list';
    $activeTab = $_GET['tab'] ?? 'tasks';
    $activeTab = in_array($activeTab, ['tasks', 'details', 'reports', 'time-logs'], true) ? $activeTab : 'tasks';
    [$fromDate, $toDate, $dates] = WorkLog::dateRange($_GET['from_date'] ?? null, $_GET['to_date'] ?? null);
    $projectGroup = $project['project_group'] ?: $project['name'];
    $projectLogFilters = [
        'project_group' => $projectGroup,
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ];

    // Auto-heal / recover any unassigned tasks whose work logs retain module names
    try {
        $db = \App\Core\Database::connection();
        $modulesStmt = $db->prepare("
            SELECT DISTINCT TRIM(wl.module_name) AS mod_name
            FROM work_logs wl 
            JOIN tasks t ON t.id = wl.task_id 
            WHERE t.project_id = ? AND (t.task_list_id IS NULL OR t.task_list_id = 0)
              AND wl.module_name IS NOT NULL 
              AND TRIM(wl.module_name) != '' 
              AND TRIM(wl.module_name) NOT IN ('None', 'General', 'No Module', 'N/A')
        ");
        $modulesStmt->execute([$projectId]);
        $modules = $modulesStmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($modules as $modName) {
            $modName = trim((string) $modName);
            if ($modName === '') continue;

            $tlStmt = $db->prepare("SELECT id FROM task_lists WHERE project_id = ? AND name = ? LIMIT 1");
            $tlStmt->execute([$projectId, $modName]);
            $tlId = $tlStmt->fetchColumn();

            if (!$tlId) {
                $order = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_lists WHERE project_id = ?");
                $order->execute([$projectId]);
                $db->prepare("INSERT INTO task_lists (project_id, name, sort_order) VALUES (?, ?, ?)")
                   ->execute([$projectId, $modName, (int) $order->fetchColumn()]);
                $tlId = (int) $db->lastInsertId();
            }

            if ($tlId) {
                $updateStmt = $db->prepare("
                    UPDATE tasks t 
                    JOIN work_logs wl ON wl.task_id = t.id 
                    SET t.task_list_id = ? 
                    WHERE t.project_id = ? 
                      AND TRIM(wl.module_name) = ? 
                      AND (t.task_list_id IS NULL OR t.task_list_id = 0)
                ");
                $updateStmt->execute([(int) $tlId, $projectId, $modName]);
            }
        }
    } catch (\Throwable $e) {
        error_log('Auto-heal task lists error: ' . $e->getMessage());
    }

    View::render('projects/show', [
        'title' => $project['name'],
        'project' => $project,
        'tasks' => Task::forProject($projectId, $statusFilter),
        'members' => Project::members($projectId),
        'phases' => Project::phases($projectId),
        'taskLists' => Project::taskLists($projectId),
        'templates' => TaskListTemplate::all(),
        'users' => User::allActive(),
        'labels' => Task::statusLabels(),
        'statuses' => Task::STATUSES,
        'statusFilter' => $statusFilter,
        'groupBy' => $groupBy,
        'activeTab' => $activeTab,
        'canManageProject' => Permissions::canManageProjectActions(),
        'customFields' => CustomField::visible(),
        'customValues' => CustomField::valuesForProject($projectId),
        'projectFieldSettings' => ProjectFieldSetting::getMap(),
        'projectReportRows' => WorkLog::reportByProject($projectLogFilters),
        'projectSummaryRows' => WorkLog::summaryByProject($projectLogFilters),
        'projectTimeRows' => WorkLog::timeLogByClient($fromDate, $toDate, $projectGroup),
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'dates' => $dates,
    ]);
    exit;
}

if ($path === '/projects/task-list-templates/apply' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $phaseId = ($_POST['phase_id'] ?? '') !== '' ? (int) $_POST['phase_id'] : null;
    $templateId = (int) ($_POST['template_id'] ?? 0);

    if ($projectId && $templateId) {
        TaskListTemplate::applyToProject($projectId, $phaseId, $templateId, (int) $_SESSION['user_id']);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/phases/create' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($projectId && $name !== '') {
        Project::createPhase($projectId, $name);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/task-lists/create' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $phaseId = ($_POST['phase_id'] ?? '') !== '' ? (int) $_POST['phase_id'] : null;
    $name = trim($_POST['name'] ?? '');

    if ($projectId && $name !== '') {
        Project::createTaskList($projectId, $phaseId, $name);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/phases/delete' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $phaseId = (int) ($_POST['phase_id'] ?? 0);

    if ($projectId && $phaseId) {
        Project::deletePhase($projectId, $phaseId);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/phases/update-name' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $phaseId = (int) ($_POST['phase_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($projectId && $phaseId && $name !== '') {
        Project::renamePhase($projectId, $phaseId, $name);
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/projects/phases/move' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $phaseId = (int) ($_POST['phase_id'] ?? 0);
    $beforePhaseId = ($_POST['before_phase_id'] ?? '') !== '' ? (int) $_POST['before_phase_id'] : null;

    if ($projectId && $phaseId) {
        Project::movePhaseBefore($projectId, $phaseId, $beforePhaseId);
    }

    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/task-lists/delete' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskListId = (int) ($_POST['task_list_id'] ?? 0);

    if ($projectId && $taskListId) {
        Project::deleteTaskList($projectId, $taskListId);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/task-lists/update-name' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskListId = (int) ($_POST['task_list_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($projectId && $taskListId && $name !== '') {
        Project::renameTaskList($projectId, $taskListId, $name);
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/projects/task-lists/move' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskListId = (int) ($_POST['task_list_id'] ?? 0);
    $phaseId = ($_POST['phase_id'] ?? '') !== '' ? (int) $_POST['phase_id'] : null;
    $beforeTaskListId = ($_POST['before_task_list_id'] ?? '') !== '' ? (int) $_POST['before_task_list_id'] : null;

    if ($projectId && $taskListId) {
        Project::moveTaskList($projectId, $taskListId, $phaseId, $beforeTaskListId);
    }

    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/members' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $role = $_POST['project_role'] ?? 'member';

    if ($projectId && $userId && in_array($role, ['owner', 'manager', 'member', 'viewer'], true)) {
        Project::addMember($projectId, $userId, $role);
    }

    redirect('/projects/show?id=' . $projectId . '&tab=details');
}

if ($path === '/projects/members/remove' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($projectId && $userId) {
        Project::removeMember($projectId, $userId);
    }

    redirect('/projects/show?id=' . $projectId . '&tab=details');
}

if ($path === '/users') {
    // Managers can always reach the list to use their edit-access right,
    // even if the admin-only "Users" page checkbox isn't ticked for them.
    if (!Permissions::canAccessPage('users') && !Permissions::isManager()) {
        http_response_code(403);
        View::render('403', ['title' => 'Access Denied']);
        exit;
    }

    // Auto-clean any residual UAT test users, clients, or batches
    try {
        Database::connection()->exec("DELETE FROM users WHERE email LIKE 'uat.%' OR name LIKE 'UAT %'");
        Database::connection()->exec("DELETE FROM clients WHERE name LIKE 'UAT %'");
        Database::connection()->exec("DELETE FROM client_batches WHERE batch_name LIKE 'UAT %'");
    } catch (\Throwable $e) {
        // Ignore if already deleted
    }

    View::render('users/index', [
        'title' => 'Users',
        'users' => User::all(),
    ]);
    exit;
}

if ($path === '/users/delete' && is_post()) {
    verify_csrf();
    Permissions::require(['admin']);
    $userId = (int) ($_POST['id'] ?? 0);
    $currentAdminId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId === $currentAdminId) {
        $_SESSION['flash_error'] = 'You cannot delete your own logged-in account.';
    } elseif ($userId > 0) {
        try {
            User::delete($userId, $currentAdminId);
            $_SESSION['flash_success'] = 'User permanently deleted.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Could not delete user: ' . $e->getMessage();
        }
    }
    redirect('/users');
}

if ($path === '/audit-logs') {
    Permissions::requirePage('settings');

    $filters = [
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'user_id' => $_GET['user_id'] ?? '',
        'entity_type' => $_GET['entity_type'] ?? '',
        'action' => $_GET['action'] ?? '',
        'q' => $_GET['q'] ?? '',
    ];

    $perPageParam = $_GET['per_page'] ?? '50';
    $isAll = $perPageParam === 'all';
    $perPage = $isAll ? 999999 : max(10, min(500, (int) $perPageParam));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $totalCount = \App\Services\AuditService::count($filters);
    $totalPages = $isAll ? 1 : (int) ceil($totalCount / $perPage);
    $logs = \App\Services\AuditService::query($filters, $perPage, $offset);

    $filters['per_page'] = $perPageParam;

    View::render('audit_logs/index', [
        'title' => 'Audit & Change Logs',
        'activeTab' => 'audit_logs',
        'logs' => $logs,
        'filters' => $filters,
        'totalCount' => $totalCount,
        'page' => $page,
        'perPage' => $perPageParam,
        'totalPages' => $totalPages,
        'users' => User::all(),
    ]);
    exit;
}

if ($path === '/settings') {
    Permissions::requirePage('settings');
    $activeTab = in_array($_GET['tab'] ?? '', ['fields', 'templates'], true) ? $_GET['tab'] : 'fields';
    View::render('settings', [
        'title' => 'Settings',
        'activeTab' => $activeTab,
        'projectFields' => ProjectFieldSetting::all(),
        'customFields' => CustomField::all(),
        'templates' => TaskListTemplate::allWithTasks(),
    ]);
    exit;
}

if ($path === '/settings/project-fields' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    ProjectFieldSetting::save($_POST['labels'] ?? [], $_POST['visible'] ?? [], $_POST['field_order'] ?? []);
    $_SESSION['flash_success'] = 'Project dashboard fields updated.';
    redirect('/settings?tab=fields');
    exit;
}

if ($path === '/settings/custom-fields/create' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    $label = trim($_POST['label'] ?? '');

    if ($label === '') {
        $_SESSION['flash_error'] = 'Field label is required.';
    } else {
        CustomField::create($label, $_POST['field_type'] ?? 'text');
        $_SESSION['flash_success'] = 'Custom field added.';
    }

    redirect('/settings?tab=fields');
}

if ($path === '/settings/custom-fields/update' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    $fieldId = (int) ($_POST['field_id'] ?? 0);
    $label = trim($_POST['label'] ?? '');

    if (!$fieldId || $label === '') {
        $_SESSION['flash_error'] = 'Field label is required.';
    } else {
        CustomField::update($fieldId, $label, $_POST['field_type'] ?? 'text', !empty($_POST['visible']));
        $_SESSION['flash_success'] = 'Custom field updated.';
    }

    redirect('/settings?tab=fields');
}

if ($path === '/settings/custom-fields/delete' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    $fieldId = (int) ($_POST['field_id'] ?? 0);
    if ($fieldId) {
        CustomField::delete($fieldId);
        $_SESSION['flash_success'] = 'Custom field deleted.';
    }

    redirect('/settings?tab=fields');
}

if ($path === '/settings/custom-fields/reorder' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    $orderedIds = array_map('intval', $_POST['custom_field_order'] ?? []);
    if (!empty($orderedIds)) {
        CustomField::updateSortOrders($orderedIds);
        $_SESSION['flash_success'] = 'Custom field order updated.';
    }
    redirect('/settings?tab=fields');
}

if ($path === '/settings/templates/create' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    $name = trim($_POST['name'] ?? '');
    $taskLines = preg_split('/\r\n|\r|\n/', trim($_POST['tasks'] ?? '')) ?: [];

    if ($name === '' || trim(implode('', $taskLines)) === '') {
        $_SESSION['flash_error'] = 'Template name and at least one task are required.';
    } else {
        TaskListTemplate::create([
            'name' => $name,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'created_by' => (int) $_SESSION['user_id'],
        ], $taskLines);
        $_SESSION['flash_success'] = 'Task list template created.';
    }

    redirect('/settings?tab=templates');
}

if ($path === '/settings/templates/update' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    $templateId = (int) ($_POST['template_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $taskLines = preg_split('/\r\n|\r|\n/', trim($_POST['tasks'] ?? '')) ?: [];

    if (!$templateId || $name === '' || trim(implode('', $taskLines)) === '') {
        $_SESSION['flash_error'] = 'Template name and at least one task are required.';
    } else {
        TaskListTemplate::update($templateId, [
            'name' => $name,
            'description' => trim($_POST['description'] ?? '') ?: null,
        ], $taskLines);
        $_SESSION['flash_success'] = 'Task list template updated.';
    }

    redirect('/settings?tab=templates');
}

if ($path === '/settings/templates/delete' && is_post()) {
    verify_csrf();
    Permissions::requirePage('settings');
    $templateId = (int) ($_POST['template_id'] ?? 0);
    if ($templateId) {
        TaskListTemplate::delete($templateId);
        $_SESSION['flash_success'] = 'Task list template deleted.';
    }

    redirect('/settings?tab=templates');
}

if ($path === '/templates') {
    redirect('/settings');
    exit;
}

function pages_from_post(): array
{
    $submitted = $_POST['pages'] ?? [];
    if (!is_array($submitted)) {
        return [];
    }

    return array_values(array_intersect($submitted, array_keys(Permissions::PAGES)));
}

function user_permissions_from_post(): array
{
    $permissions = pages_from_post();
    if (($_POST['project_actions'] ?? '') === '1') {
        $permissions[] = Permissions::PROJECT_ACTIONS_PERMISSION;
    }

    $rights = $_POST['rights'] ?? null;
    if (is_array($rights)) {
        foreach ([Permissions::RIGHT_READ, Permissions::RIGHT_WRITE, Permissions::RIGHT_EXPORT] as $r) {
            if (in_array($r, $rights, true)) {
                $permissions[] = $r;
            }
        }
    } else {
        $permissions[] = Permissions::RIGHT_READ;
        $permissions[] = Permissions::RIGHT_WRITE;
        $permissions[] = Permissions::RIGHT_EXPORT;
    }

    return array_values(array_unique($permissions));
}

if ($path === '/users/create') {
    Permissions::requirePage('users');
    $error = null;

    if (is_post()) {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Name, email, and password are required.';
        } elseif (User::findByEmail($email)) {
            $error = 'A user with this email already exists.';
        } else {
            User::create([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $_POST['role'] ?? 'member',
                'permissions' => json_encode(user_permissions_from_post()),
                'designation' => trim($_POST['designation'] ?? '') ?: null,
                'department' => trim($_POST['department'] ?? '') ?: null,
                'status' => $_POST['status'] ?? 'active',
            ]);

            redirect('/users');
        }
    }

    View::render('users/create', ['title' => 'New User', 'error' => $error]);
    exit;
}

if ($path === '/users/edit') {
    Permissions::require(['admin', 'manager']);
    $userId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $editUser = $userId ? User::find($userId) : null;
    if (!$editUser) {
        http_response_code(404);
        exit('User not found.');
    }

    $isAdminEditor = Permissions::isAdmin();
    if (!$isAdminEditor && $editUser['role'] === 'admin') {
        http_response_code(403);
        View::render('403', ['title' => 'Access Denied']);
        exit;
    }

    $error = null;
    if (is_post()) {
        verify_csrf();

        if ($isAdminEditor) {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $existing = $email !== '' ? User::findByEmail($email) : null;

            if ($name === '' || $email === '') {
                $error = 'Name and email are required.';
            } elseif ($existing && (int) $existing['id'] !== $userId) {
                $error = 'A user with this email already exists.';
            } else {
                $password = $_POST['password'] ?? '';
                User::update($userId, [
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
                    'role' => $_POST['role'] ?? $editUser['role'],
                    'permissions' => json_encode(user_permissions_from_post()),
                    'designation' => trim($_POST['designation'] ?? '') ?: null,
                    'department' => trim($_POST['department'] ?? '') ?: null,
                    'status' => $_POST['status'] ?? $editUser['status'],
                ]);
                redirect('/users');
            }
        } else {
            // Manager: access + rights + status.
            $allowedPages = array_diff(pages_from_post(), ['users', 'settings']);
            $rights = $_POST['rights'] ?? [Permissions::RIGHT_READ, Permissions::RIGHT_WRITE, Permissions::RIGHT_EXPORT];
            if (is_array($rights)) {
                foreach ([Permissions::RIGHT_READ, Permissions::RIGHT_WRITE, Permissions::RIGHT_EXPORT] as $r) {
                    if (in_array($r, $rights, true)) {
                        $allowedPages[] = $r;
                    }
                }
            }
            $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
            User::updateAccess($userId, array_values(array_unique($allowedPages)), $status);
            redirect('/users');
        }
    }

    View::render('users/edit', [
        'title' => 'Edit User',
        'editUser' => $editUser,
        'isAdminEditor' => $isAdminEditor,
        'error' => $error,
    ]);
    exit;
}

if ($path === '/users/quick-permissions-update' && is_post()) {
    Permissions::require(['admin', 'manager']);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $permKey = trim((string) ($_POST['permission'] ?? ''));
    $enabled = (int) ($_POST['enabled'] ?? 0) === 1;

    $user = $userId ? User::find($userId) : null;
    if (!$user) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'User not found.']);
        exit;
    }

    $isAdmin = Permissions::isAdmin();
    // Non-admins cannot toggle 'users' or 'settings' pages, or edit admins
    if (!$isAdmin) {
        if ($user['role'] === 'admin') {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Cannot edit administrator permissions.']);
            exit;
        }
        if (in_array($permKey, ['users', 'settings'], true)) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Only administrators can grant Users or Settings.']);
            exit;
        }
    }

    $currentPerms = User::permissionsFor($user);

    if ($enabled) {
        if (!in_array($permKey, $currentPerms, true)) {
            $currentPerms[] = $permKey;
        }
    } else {
        $currentPerms = array_values(array_diff($currentPerms, [$permKey]));
    }

    User::updatePermissions($userId, $currentPerms);

    if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
        $_SESSION['user_pages'] = $currentPerms;
    }

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'permissions' => $currentPerms]);
    exit;
}

if ($path === '/tasks/create' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');

    $assigneeIds = Task::normalizeAssigneeIds($_POST['assignee_ids'] ?? []);

    if ($projectId && $title !== '') {
        Task::create([
            'project_id' => $projectId,
            'phase_id' => ($_POST['phase_id'] ?? '') !== '' ? (int) $_POST['phase_id'] : null,
            'task_list_id' => ($_POST['task_list_id'] ?? '') !== '' ? (int) $_POST['task_list_id'] : null,
            'task_code' => trim($_POST['task_code'] ?? '') ?: null,
            'title' => $title,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'assigned_to' => $assigneeIds[0] ?? null,
            'assignee_ids' => $assigneeIds,
            'status' => $_POST['status'] ?? 'open',
            'priority' => $_POST['priority'] ?? 'medium',
            'start_date' => $_POST['start_date'] ?: null,
            'due_date' => $_POST['due_date'] ?: null,
            'estimated_hours' => ($_POST['estimated_hours'] ?? '') !== '' ? (float) $_POST['estimated_hours'] : null,
            'created_by' => (int) $_SESSION['user_id'],
        ]);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/tasks/bulk' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskIds = $_POST['task_ids'] ?? [];
    $action = $_POST['bulk_action'] ?? '';

    if (!is_array($taskIds)) {
        $taskIds = [];
    }

    if ($projectId && $action === 'trash') {
        Task::deleteMany($projectId, $taskIds);
    }

    if ($projectId && $action === 'move') {
        $target = $_POST['target_task_list_id'] ?? '';
        $phaseId = null;
        $taskListId = null;

        if ($target !== '') {
            $parts = explode(':', $target);
            $phaseId = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : null;
            $taskListId = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : null;
        }

        Task::moveMany($projectId, $taskIds, $phaseId, $taskListId);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/tasks/move' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $phaseId = ($_POST['phase_id'] ?? '') !== '' ? (int) $_POST['phase_id'] : null;
    $taskListId = ($_POST['task_list_id'] ?? '') !== '' ? (int) $_POST['task_list_id'] : null;
    $beforeTaskId = ($_POST['before_task_id'] ?? '') !== '' ? (int) $_POST['before_task_id'] : null;

    if ($projectId && $taskId) {
        Task::moveBefore($projectId, $taskId, $phaseId, $taskListId, $beforeTaskId);
    }

    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/tasks/quick-update' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    if ($projectId && $taskId) {
        Task::quickUpdate($projectId, $taskId, $field, $value);
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/tasks/workload') {
    $userId = (int) ($_GET['user_id'] ?? 0);
    $fromDate = $_GET['from_date'] ?? date('Y-m-d');
    $toDate = $_GET['to_date'] ?? date('Y-m-d', strtotime('+13 days'));
    $excludeTaskId = ($_GET['exclude_task_id'] ?? '') !== '' ? (int) $_GET['exclude_task_id'] : null;

    $load = $userId ? Task::workloadForUser($userId, $fromDate, $toDate, $excludeTaskId) : [];

    header('Content-Type: application/json');
    echo json_encode($load);
    exit;
}

if ($path === '/tasks/show') {
    $taskId = (int) ($_GET['id'] ?? 0);
    $task = Task::find($taskId);

    if (!$task) {
        http_response_code(404);
        exit('Task not found.');
    }

    View::render('tasks/show', [
        'title' => $task['title'],
        'task' => $task,
        'taskAssigneeIds' => Task::assigneeIds($taskId),
        'users' => User::allActive(),
        'statuses' => Task::STATUSES,
        'labels' => Task::statusLabels(),
        'comments' => Task::comments($taskId),
        'attachments' => Task::attachments($taskId),
        'workLogs' => WorkLog::forTask($taskId),
        'categories' => WorkLog::CATEGORIES,
    ]);
    exit;
}

if ($path === '/tasks/work-logs/create' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $task = $taskId ? Task::find($taskId) : null;
    [$timeRange, $rangeHours] = time_range_from_post();
    $dailyLog = $timeRange ?: '';
    $hoursValue = trim($_POST['hours'] ?? '');
    $hours = $rangeHours ?? ($hoursValue !== '' ? (float) $hoursValue : null);

    if ($hours === null && preg_match('/^(\d{1,2}):([0-5]\d)$/', trim($_POST['daily_log'] ?? ''), $matches)) {
        $hours = (int) $matches[1] + ((int) $matches[2] / 60);
    }

    if ($hours === null && is_numeric(trim($_POST['daily_log'] ?? ''))) {
        $hours = (float) trim($_POST['daily_log'] ?? '');
    }

    $selectedUserId = (int) ($_POST['user_id'] ?? $_SESSION['user_id']);
    $selectedUser = $selectedUserId ? User::find($selectedUserId) : null;

    $logDate = trim((string) ($_POST['log_date'] ?? ''));
    if ($logDate !== '' && $logDate > date('Y-m-d')) {
        $_SESSION['flash_error'] = 'Time log date cannot be in the future.';
        redirect('/tasks/show?id=' . $taskId);
    }

    if ($task && trim($_POST['notes'] ?? '') !== '' && $hours !== null) {
        WorkLog::create([
            'task_id' => $taskId,
            'project_group' => $task['project_group'] ?: $task['project_name'],
            'phase' => $task['phase_name'] ?: 'No Phase',
            'module_name' => $task['task_list_name'] ?: 'General',
            'task_category' => $task['title'],
            'notes' => trim($_POST['notes']),
            'daily_log' => $dailyLog ?: null,
            'time_period' => $timeRange ?: (trim($_POST['time_period'] ?? '') ?: null),
            'hours' => max(0, $hours),
            'log_date' => $_POST['log_date'] ?: date('Y-m-d'),
            'billing_type' => ($_POST['billing_type'] ?? '') === 'Non-Billable' ? 'Non-Billable' : 'Billable',
            'user_id' => $selectedUser ? $selectedUserId : (int) $_SESSION['user_id'],
        ]);
        $_SESSION['flash_success'] = 'Time log added.';
    } else {
        $_SESSION['flash_error'] = 'Time log was not saved. Please enter Daily Log Hours and Notes.';
    }

    redirect('/tasks/show?id=' . $taskId);
}

if ($path === '/tasks/update' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $task = $taskId ? Task::find($taskId) : null;

    $assigneeIds = Task::normalizeAssigneeIds($_POST['assignee_ids'] ?? []);

    if ($task) {
        Task::update($taskId, [
            'title' => trim($_POST['title'] ?? $task['title']),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'assigned_to' => $assigneeIds[0] ?? null,
            'assignee_ids' => $assigneeIds,
            'status' => $_POST['status'] ?? $task['status'],
            'priority' => $_POST['priority'] ?? $task['priority'],
            'start_date' => $_POST['start_date'] ?: null,
            'due_date' => $_POST['due_date'] ?: null,
            'estimated_hours' => ($_POST['estimated_hours'] ?? '') !== '' ? (float) $_POST['estimated_hours'] : null,
            'progress' => ($_POST['status'] ?? $task['status']) === 'completed' ? 100 : max(0, min(100, (int) ($_POST['progress'] ?? $task['progress']))),
        ]);
    }

    redirect('/tasks/show?id=' . $taskId);
}

if ($path === '/tasks/status' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $status = $_POST['status'] ?? 'open';
    $existingTask = $taskId ? Task::find($taskId) : null;
    $progress = ($_POST['progress'] ?? '') !== ''
        ? (int) $_POST['progress']
        : (int) ($existingTask['progress'] ?? 0);

    if ($taskId && in_array($status, ['open', 'in_progress', 'review', 'completed', 'blocked'], true)) {
        Task::updateStatus($taskId, $status, max(0, min(100, $progress)));
    }

    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if (($_POST['return_to'] ?? '') === 'kanban') {
        $query = [];
        if (($_POST['filter_project_id'] ?? '') !== '') {
            $query['project_id'] = (int) $_POST['filter_project_id'];
        }
        if (($_POST['filter_assignee_id'] ?? '') !== '') {
            $query['assignee_id'] = (int) $_POST['filter_assignee_id'];
        }
        if (($_POST['filter_completed_limit'] ?? '') !== '' && $_POST['filter_completed_limit'] !== '25') {
            $query['completed_limit'] = $_POST['filter_completed_limit'];
        }
        redirect('/kanban' . ($query ? '?' . http_build_query($query) : ''));
    }

    if (($_POST['return_to'] ?? '') === 'task') {
        redirect('/tasks/show?id=' . $taskId);
    }

    if (($_POST['return_to'] ?? '') === 'my-work') {
        redirect('/');
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/tasks/comment' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($taskId && $comment !== '') {
        Task::addComment($taskId, (int) $_SESSION['user_id'], $comment);
    }

    if (($_POST['return_to'] ?? '') === 'task') {
        redirect('/tasks/show?id=' . $taskId);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/tasks/attachment' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $file = $_FILES['attachment'] ?? null;

    if ($taskId && $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../storage/uploads/tasks/' . $taskId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $originalName = basename((string) $file['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedName = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');
        $target = $uploadDir . '/' . $storedName;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            Task::addAttachment([
                'task_id' => $taskId,
                'user_id' => (int) $_SESSION['user_id'],
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'mime_type' => $file['type'] ?: null,
                'file_size' => (int) $file['size'],
            ]);
        }
    }

    if (($_POST['return_to'] ?? '') === 'task') {
        redirect('/tasks/show?id=' . $taskId);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/attachments/download') {
    $attachment = Task::attachment((int) ($_GET['id'] ?? 0));
    if (!$attachment) {
        http_response_code(404);
        exit('Attachment not found.');
    }

    $filePath = __DIR__ . '/../storage/uploads/tasks/' . $attachment['task_id'] . '/' . $attachment['stored_name'];
    if (!is_file($filePath)) {
        http_response_code(404);
        exit('File missing.');
    }

    header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $attachment['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

if ($path === '/reports') {
    Permissions::requirePage('reports');
    $reportType = $_GET['type'] ?? 'detailed';
    if ($reportType === 'project') {
        $reportType = 'project_billing';
    }
    $reportType = in_array($reportType, ['detailed', 'service_hours', 'project_billing', 'phase_billing', 'customer'], true) ? $reportType : 'detailed';
    $filters = [
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'user_id' => $_GET['user_id'] ?? '',
        'client_id' => $_GET['client_id'] ?? '',
        'project_group' => trim($_GET['project_group'] ?? ''),
        'phase' => trim($_GET['phase'] ?? ''),
        'module_name' => trim($_GET['module_name'] ?? ''),
        'billing_status' => trim($_GET['billing_status'] ?? ''),
        'contract_model' => trim($_GET['contract_model'] ?? ''),
        'per_page' => $_GET['per_page'] ?? '50',
    ];

    $pagination = null;
    if ($reportType === 'detailed') {
        $perPageParam = $_GET['per_page'] ?? '50';
        $isAll = $perPageParam === 'all' || $perPageParam === '-1';
        $perPage = $isAll ? 0 : max(10, min(500, (int) $perPageParam));
        if (!$isAll && $perPage === 0) {
            $perPage = 50;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $totalItems = WorkLog::reportCount($filters);

        if ($perPage > 0) {
            $totalPages = max(1, (int) ceil($totalItems / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;
            $workLogs = WorkLog::report($filters, $perPage, $offset);
        } else {
            $totalPages = 1;
            $page = 1;
            $workLogs = WorkLog::report($filters);
        }

        $pagination = [
            'page' => $page,
            'per_page' => $isAll ? 'all' : (string) $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'from_item' => $totalItems > 0 ? (($page - 1) * ($perPage ?: $totalItems) + 1) : 0,
            'to_item' => $totalItems > 0 ? min($totalItems, $page * ($perPage ?: $totalItems)) : 0,
        ];
    } else {
        $workLogs = [];
    }

    View::render('reports/index', [
        'title' => 'Reports',
        'reportType' => $reportType,
        'projectBilling' => WorkLog::billingSummaryByProject($filters),
        'phaseBilling' => WorkLog::billingSummaryByPhase($filters),
        'workLogs' => $workLogs,
        'pagination' => $pagination,
        'serviceHours' => WorkLog::serviceHoursReport($filters),
        'customerSummary' => WorkLog::summaryByCustomer($filters),
        'billingStats' => WorkLog::billingStats($filters),
        'users' => User::allActive(),
        'clients' => Client::allActive(),
        'distinctPhases' => WorkLog::distinctPhases($filters['project_group'] ?: null),
        'distinctModules' => WorkLog::distinctModules($filters['project_group'] ?: null),
        'filters' => $filters,
        'suggestions' => suggestions_data(),
        'allProjectsWithClients' => Database::connection()->query(
            "SELECT p.id, p.name, p.client_id, c.name AS client_name
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE p.archived_at IS NULL
             ORDER BY c.name ASC, p.name ASC"
        )->fetchAll(),
    ]);
    exit;
}

if ($path === '/billing/mark-project' && is_post()) {
    verify_csrf();
    Permissions::requirePage('reports');
    Permissions::requireWriteAccess();

    $projectId = (int) ($_POST['project_id'] ?? 0);
    $action = trim($_POST['action'] ?? 'billed');
    $invoiceRef = trim($_POST['invoice_reference'] ?? '');

    if ($projectId > 0) {
        if ($action === 'unbilled') {
            WorkLog::markProjectUnbilled($projectId);
            flash_set('Project time logs marked as Unbilled.', 'success');
        } else {
            WorkLog::markProjectBilled($projectId, $invoiceRef ?: null, (int) ($_SESSION['user_id'] ?? 0));
            flash_set('Project time logs marked as Billed' . ($invoiceRef ? " (Invoice: {$invoiceRef})" : '') . '.', 'success');
        }
    }
    redirect_back('/reports?type=project_billing');
}

if ($path === '/billing/mark-phase' && is_post()) {
    verify_csrf();
    Permissions::requirePage('reports');
    Permissions::requireWriteAccess();

    $phaseId = (int) ($_POST['phase_id'] ?? 0);
    $action = trim($_POST['action'] ?? 'billed');
    $invoiceRef = trim($_POST['invoice_reference'] ?? '');

    if ($phaseId > 0) {
        if ($action === 'unbilled') {
            WorkLog::markPhaseUnbilled($phaseId);
            flash_set('Phase time logs marked as Unbilled.', 'success');
        } else {
            WorkLog::markPhaseBilled($phaseId, $invoiceRef ?: null, (int) ($_SESSION['user_id'] ?? 0));
            flash_set('Phase time logs marked as Billed' . ($invoiceRef ? " (Invoice: {$invoiceRef})" : '') . '.', 'success');
        }
    }
    redirect_back('/reports?type=phase_billing');
}

if ($path === '/work-logs/mark-billed' && is_post()) {
    verify_csrf();
    Permissions::requirePage('reports');
    Permissions::requireWriteAccess();

    $ids = !empty($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : (!empty($_POST['id']) ? [(int)$_POST['id']] : []);
    $invoiceRef = trim($_POST['invoice_reference'] ?? '');

    if (!empty($ids)) {
        WorkLog::markBilled($ids, $invoiceRef ?: null, (int) ($_SESSION['user_id'] ?? 0));
        flash_set(count($ids) . ' time log' . (count($ids) > 1 ? 's' : '') . ' marked as Billed' . ($invoiceRef ? " (Invoice: {$invoiceRef})" : '') . '.', 'success');
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    redirect_back('/reports?type=detailed');
}

if ($path === '/work-logs/mark-unbilled' && is_post()) {
    verify_csrf();
    Permissions::requirePage('reports');
    Permissions::requireWriteAccess();

    $ids = !empty($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : (!empty($_POST['id']) ? [(int)$_POST['id']] : []);

    if (!empty($ids)) {
        WorkLog::markUnbilled($ids);
        flash_set(count($ids) . ' time log' . (count($ids) > 1 ? 's' : '') . ' marked as Unbilled.', 'success');
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    redirect_back('/reports?type=detailed');
}

if ($path === '/projects/quick-service-hours' && is_post()) {
    verify_csrf();
    Permissions::requirePage('projects');
    Permissions::requireWriteAccess();

    $projectId = (int) ($_POST['project_id'] ?? 0);
    $isOpenPo = !empty($_POST['is_open_po']);
    $buildHours = $isOpenPo ? 0.00 : (float) ($_POST['build_hours'] ?? 0);
    $runHours = $isOpenPo ? 0.00 : (float) ($_POST['run_hours'] ?? 0);

    if ($projectId > 0) {
        Project::updateServiceHours($projectId, $buildHours, $runHours, $isOpenPo);
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'is_open_po' => $isOpenPo,
            'build_hours' => $buildHours,
            'run_hours' => $runHours,
            'total_allocated_hours' => $buildHours + $runHours,
        ]);
        exit;
    }

    flash_set($isOpenPo ? 'Project set to Open PO (Hourly billing).' : 'Project service hours updated.', 'success');
    redirect_back('/reports?type=service_hours');
}

if ($path === '/reports/export') {
    Permissions::requirePage('reports');
    Permissions::requireExportAccess();

    $reportType = $_GET['type'] ?? 'detailed';
    if ($reportType === 'project') {
        $reportType = 'project_billing';
    }

    $filters = [
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'user_id' => $_GET['user_id'] ?? '',
        'client_id' => $_GET['client_id'] ?? '',
        'project_group' => trim($_GET['project_group'] ?? ''),
        'phase' => trim($_GET['phase'] ?? ''),
        'module_name' => trim($_GET['module_name'] ?? ''),
        'billing_status' => trim($_GET['billing_status'] ?? ''),
        'contract_model' => trim($_GET['contract_model'] ?? ''),
    ];

    $currentUser = Auth::user() ?? [];

    if ($reportType === 'project_billing') {
        $data = WorkLog::billingSummaryByProject($filters);
        $headers = ['Project', 'Client', 'Contract Model', 'Billing Cycle / Period', 'Total Logged (Hrs)', 'Billable (Hrs)', 'Billed / Invoiced (Hrs)', 'Unbilled (Hrs)', 'Contract Balance', 'Billing Progress / Status'];
        $rows = [];
        foreach ($data as $d) {
            $contractModel = $d['contract_model_label'] ?? (($d['is_open_po'] ?? false) ? 'Open PO (Hourly)' : (($d['total_allocated_hours'] ?? 0) > 0 ? 'Contract Cap' : 'Internal / Non-Billable'));
            $cycle = $d['billing_cycle_label'] ?? 'All-Time';
            $progress = $d['billing_status_label'] ?? ($d['billing_status'] ?? '-');
            $rows[] = [
                $d['project_name'] ?? '',
                $d['client_name'] ?? 'Internal',
                $contractModel,
                $cycle,
                number_format((float) ($d['total_logged'] ?? $d['total_logged_hours'] ?? 0), 2),
                number_format((float) ($d['total_billable'] ?? $d['billable_hours'] ?? 0), 2),
                number_format((float) ($d['billed_hours'] ?? 0), 2),
                number_format((float) ($d['unbilled_hours'] ?? 0), 2),
                ($d['is_open_po'] ?? false) ? 'Open PO (No Cap)' : ($d['contract_balance_label'] ?? '-'),
                $progress,
            ];
        }
        $workbook = TimesheetXlsxExporter::buildSimpleTable('Project-wise Billing', $headers, $rows, $filters, $currentUser);
        $filename = 'project_billing_' . date('Ymd_His') . '.xlsx';
    } elseif ($reportType === 'service_hours') {
        $data = WorkLog::serviceHoursReport($filters);
        $headers = ['Project', 'Client', 'Contract Type', 'Contract / Cap (Hrs)', 'Total Logged (Hrs)', 'Billed (Hrs)', 'Unbilled (Hrs)', 'Remaining (Hrs)', 'Consumption %'];
        $rows = [];
        foreach ($data as $d) {
            $contractType = $d['contract_model_label'] ?? (($d['is_open_po'] ?? false) ? 'Open PO (Hourly)' : (($d['total_allocated_hours'] ?? 0) > 0 ? 'Contract Cap' : 'Internal / Non-Billable'));
            $cap = ($d['is_open_po'] ?? false) ? 'Open PO' : number_format((float) ($d['total_allocated_hours'] ?? 0), 2);
            $rem = ($d['remaining_hours'] !== null) ? number_format((float) $d['remaining_hours'], 2) : 'N/A';
            $rows[] = [
                $d['project_name'] ?? '',
                $d['client_name'] ?? 'Internal',
                $contractType,
                $cap,
                number_format((float) ($d['logged_hours'] ?? $d['total_logged_hours'] ?? 0), 2),
                number_format((float) ($d['billed_hours'] ?? 0), 2),
                number_format((float) ($d['unbilled_hours'] ?? 0), 2),
                $rem,
                ($d['consumption_percent'] ?? 0) . '%',
            ];
        }
        $workbook = TimesheetXlsxExporter::buildSimpleTable('Service Hours & Capacity', $headers, $rows, $filters, $currentUser);
        $filename = 'service_hours_' . date('Ymd_His') . '.xlsx';
    } elseif ($reportType === 'phase_billing') {
        $data = WorkLog::billingSummaryByPhase($filters);
        $headers = ['Project', 'Client', 'Phase', 'Logged Hours', 'Billable Hours', 'Billed Hours', 'Unbilled Hours', 'Status'];
        $rows = [];
        foreach ($data as $d) {
            $rows[] = [
                $d['project_name'] ?? '',
                $d['client_name'] ?? 'Internal',
                $d['phase_name'] ?? '',
                number_format((float) ($d['logged_hours'] ?? 0), 2),
                number_format((float) ($d['billable_hours'] ?? 0), 2),
                number_format((float) ($d['billed_hours'] ?? 0), 2),
                number_format((float) ($d['unbilled_hours'] ?? 0), 2),
                $d['billing_status'] ?? 'unbilled',
            ];
        }
        $workbook = TimesheetXlsxExporter::buildSimpleTable('Phase-wise Billing', $headers, $rows, $filters, $currentUser);
        $filename = 'phase_billing_' . date('Ymd_His') . '.xlsx';
    } elseif ($reportType === 'customer') {
        $data = WorkLog::summaryByCustomer($filters);
        $headers = ['Client / Customer', 'Total Logged (Hrs)', 'Billable (Hrs)', 'Non-Billable (Hrs)'];
        $rows = [];
        foreach ($data as $d) {
            $rows[] = [
                $d['group_name'] ?? '',
                number_format((float) ($d['logged_hours'] ?? 0), 2),
                number_format((float) ($d['billable_hours'] ?? 0), 2),
                number_format((float) ($d['non_billable_hours'] ?? 0), 2),
            ];
        }
        $workbook = TimesheetXlsxExporter::buildSimpleTable('Customer Summary', $headers, $rows, $filters, $currentUser);
        $filename = 'customer_report_' . date('Ymd_His') . '.xlsx';
    } else {
        // Detailed Work Log
        $rows = WorkLog::report($filters);
        $reportProjectName = trim((string) ($filters['project_group'] ?: ($rows[0]['resolved_project_name'] ?? $rows[0]['project_group'] ?? '')));
        if ($reportProjectName !== '') {
            foreach (Project::all(null) as $project) {
                if ($reportProjectName === (string) $project['name'] || $reportProjectName === (string) $project['project_group']) {
                    $filters['export_project_name'] = $project['name'];
                    $filters['export_project_id'] = $project['code'] ?: '-';
                    break;
                }
            }
        }
        $groupBy = in_array($_GET['group_by'] ?? '', ['user', 'client', 'phase', 'tasklist'], true) ? $_GET['group_by'] : 'user';
        $workbook = TimesheetXlsxExporter::build($rows, $filters, User::allActive(), $currentUser, $groupBy);
        $filename = 'detailed_work_log_' . date('Ymd_His') . '.xlsx';
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($workbook));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $workbook;
    exit;
}

http_response_code(404);
View::render('404', ['title' => 'Not Found']);
