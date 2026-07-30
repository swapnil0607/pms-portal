<?php

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Core\View;
use App\Models\Client;
use App\Models\CustomField;
use App\Models\Dashboard;
use App\Models\Project;
use App\Models\ProjectFieldSetting;
use App\Models\Task;
use App\Models\TaskListTemplate;
use App\Models\TimesheetXlsxExporter;
use App\Models\User;
use App\Models\WorkLog;

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

if ($path === '/login') {
    if (Auth::check()) {
        redirect('/');
    }

    $error = null;
    if (is_post()) {
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::attempt($email, $password)) {
            redirect('/');
        }

        $error = 'Invalid email or password.';
    }

    View::render('login', ['title' => 'Login', 'error' => $error], 'auth_layout');
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    redirect('/login');
}

Auth::requireLogin();

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
        'categories' => WorkLog::CATEGORIES,
    ]);
    exit;
}

if ($path === '/my-work') {
    redirect('/');
}

if ($path === '/projects') {
    $projects = Project::all();
    View::render('projects/index', [
        'title' => 'Projects',
        'projects' => $projects,
        'projectFields' => ProjectFieldSetting::visible(),
        'customFields' => CustomField::visible(),
        'customValues' => CustomField::valuesForProjects(array_column($projects, 'id')),
    ]);
    exit;
}

if ($path === '/projects/quick-update' && is_post()) {
    verify_csrf();
    Permissions::require(Permissions::MANAGER_ROLES);
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
    $projectId = ($_GET['project_id'] ?? '') !== '' ? (int) $_GET['project_id'] : null;
    $assigneeId = ($_GET['assignee_id'] ?? '') !== '' ? (int) $_GET['assignee_id'] : null;

    View::render('kanban/index', [
        'title' => 'Kanban',
        'columns' => Task::kanban($projectId, $assigneeId),
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
    [$fromDate, $toDate, $dates] = WorkLog::dateRange($_GET['from_date'] ?? null, $_GET['to_date'] ?? null);
    $view = ($_GET['view'] ?? 'user') === 'client' ? 'client' : 'user';
    $rows = $view === 'client'
        ? WorkLog::timeLogByClient($fromDate, $toDate)
        : WorkLog::timeLogByUser($fromDate, $toDate);

    View::render('time_logs/index', [
        'title' => 'Time Logs',
        'view' => $view,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'dates' => $dates,
        'rows' => $rows,
    ]);
    exit;
}

if ($path === '/work-logs') {
    View::render('work_logs/index', [
        'title' => 'Daily Log',
        'categories' => WorkLog::CATEGORIES,
        'recentLogs' => WorkLog::recentForUser((int) $_SESSION['user_id']),
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

    $hours = $rangeHours ?? (float) ($_POST['hours'] ?? 0);
    if ($hours <= 0) {
        $hasMissing = true;
    }

    if (!$hasMissing && array_key_exists($_POST['task_category'], WorkLog::CATEGORIES)) {
        WorkLog::create([
            'task_id' => ($_POST['task_id'] ?? '') !== '' ? (int) $_POST['task_id'] : null,
            'project_group' => trim($_POST['project_group']),
            'phase' => trim($_POST['phase']),
            'module_name' => trim($_POST['module_name']),
            'task_category' => $_POST['task_category'],
            'notes' => trim($_POST['notes']),
            'daily_log' => $timeRange ?: null,
            'time_period' => $timeRange ?: null,
            'hours' => max(0, $hours),
            'log_date' => $_POST['log_date'],
            'billing_type' => $_POST['billing_type'] === 'Non-Billable' ? 'Non-Billable' : 'Billable',
            'user_id' => (int) $_SESSION['user_id'],
        ]);
    }

    redirect(($_POST['return_to'] ?? '') === 'home' ? '/' : '/work-logs');
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

        if (trim($_POST['notes'] ?? '') === '' || $hours <= 0 || !array_key_exists($_POST['task_category'] ?? '', WorkLog::CATEGORIES)) {
            $error = 'Please fill in Task Category, Activity, and a valid number of hours.';
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
        'categories' => WorkLog::CATEGORIES,
        'returnTo' => $returnTo,
        'error' => $error,
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
    Permissions::require(Permissions::MANAGER_ROLES);
    View::render('clients/index', [
        'title' => 'Clients',
        'clients' => Client::all(),
    ]);
    exit;
}

if ($path === '/clients/create') {
    Permissions::require(Permissions::MANAGER_ROLES);
    $error = null;

    if (is_post()) {
        verify_csrf();
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Client name is required.';
        } else {
            Client::create([
                'name' => $name,
                'notes' => trim($_POST['notes'] ?? '') ?: null,
                'status' => $_POST['status'] ?? 'active',
            ]);
            redirect('/clients');
        }
    }

    View::render('clients/create', ['title' => 'New Client', 'error' => $error]);
    exit;
}

if ($path === '/clients/edit') {
    Permissions::require(Permissions::MANAGER_ROLES);
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
            Client::update($clientId, [
                'name' => $name,
                'notes' => trim($_POST['notes'] ?? '') ?: null,
                'status' => $_POST['status'] ?? 'active',
            ]);
            redirect('/clients');
        }
    }

    View::render('clients/edit', ['title' => 'Edit Client', 'client' => $client, 'error' => $error]);
    exit;
}

if ($path === '/projects/create') {
    Permissions::require(Permissions::MANAGER_ROLES);
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
        'error' => $error,
    ]);
    exit;
}

if ($path === '/projects/edit') {
    Permissions::require(Permissions::MANAGER_ROLES);
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
                'start_date' => $_POST['start_date'] ?: null,
                'due_date' => $_POST['due_date'] ?: null,
            ]);
            CustomField::saveValues($projectId, $_POST['custom'] ?? []);

            redirect('/projects');
        }
    }

    View::render('projects/edit', [
        'title' => 'Edit Project',
        'project' => $project,
        'users' => $users,
        'clients' => $clients,
        'customFields' => $customFields,
        'customValues' => CustomField::valuesForProject($projectId),
        'error' => $error,
    ]);
    exit;
}

if ($path === '/projects/show') {
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
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'project_group' => $projectGroup,
        'user_id' => '',
    ];

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
        'canManageProject' => Permissions::isManager(),
        'customFields' => CustomField::visible(),
        'customValues' => CustomField::valuesForProject($projectId),
        'projectReportRows' => WorkLog::report($projectLogFilters),
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
    Permissions::require(Permissions::MANAGER_ROLES);
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
    Permissions::require(Permissions::MANAGER_ROLES);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($projectId && $name !== '') {
        Project::createPhase($projectId, $name);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/task-lists/create' && is_post()) {
    verify_csrf();
    Permissions::require(Permissions::MANAGER_ROLES);
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
    Permissions::require(Permissions::MANAGER_ROLES);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $phaseId = (int) ($_POST['phase_id'] ?? 0);

    if ($projectId && $phaseId) {
        Project::deletePhase($projectId, $phaseId);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/phases/update-name' && is_post()) {
    verify_csrf();
    Permissions::require(Permissions::MANAGER_ROLES);
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
    Permissions::require(Permissions::MANAGER_ROLES);
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
    Permissions::require(Permissions::MANAGER_ROLES);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $taskListId = (int) ($_POST['task_list_id'] ?? 0);

    if ($projectId && $taskListId) {
        Project::deleteTaskList($projectId, $taskListId);
    }

    redirect('/projects/show?id=' . $projectId);
}

if ($path === '/projects/task-lists/update-name' && is_post()) {
    verify_csrf();
    Permissions::require(Permissions::MANAGER_ROLES);
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
    Permissions::require(Permissions::MANAGER_ROLES);
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
    Permissions::require(Permissions::MANAGER_ROLES);
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
    Permissions::require(Permissions::MANAGER_ROLES);
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($projectId && $userId) {
        Project::removeMember($projectId, $userId);
    }

    redirect('/projects/show?id=' . $projectId . '&tab=details');
}

if ($path === '/users') {
    Permissions::require(['admin']);
    View::render('users/index', [
        'title' => 'Users',
        'users' => User::all(),
    ]);
    exit;
}

if ($path === '/settings') {
    Permissions::require(['admin']);
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
    Permissions::require(['admin']);
    ProjectFieldSetting::save($_POST['labels'] ?? [], $_POST['visible'] ?? []);
    $_SESSION['flash_success'] = 'Project dashboard fields updated.';
    redirect('/settings?tab=fields');
    exit;
}

if ($path === '/settings/custom-fields/create' && is_post()) {
    verify_csrf();
    Permissions::require(['admin']);
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
    Permissions::require(['admin']);
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
    Permissions::require(['admin']);
    $fieldId = (int) ($_POST['field_id'] ?? 0);
    if ($fieldId) {
        CustomField::delete($fieldId);
        $_SESSION['flash_success'] = 'Custom field deleted.';
    }

    redirect('/settings?tab=fields');
}

if ($path === '/settings/templates/create' && is_post()) {
    verify_csrf();
    Permissions::require(['admin']);
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
    Permissions::require(['admin']);
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
    Permissions::require(['admin']);
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

if ($path === '/users/create') {
    Permissions::require(['admin']);
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

if ($path === '/tasks/create' && is_post()) {
    verify_csrf();
    Permissions::requireWriteAccess();
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');

    if ($projectId && $title !== '') {
        Task::create([
            'project_id' => $projectId,
            'phase_id' => ($_POST['phase_id'] ?? '') !== '' ? (int) $_POST['phase_id'] : null,
            'task_list_id' => ($_POST['task_list_id'] ?? '') !== '' ? (int) $_POST['task_list_id'] : null,
            'task_code' => trim($_POST['task_code'] ?? '') ?: null,
            'title' => $title,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'assigned_to' => ($_POST['assigned_to'] ?? '') !== '' ? (int) $_POST['assigned_to'] : null,
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

    if ($task) {
        Task::update($taskId, [
            'title' => trim($_POST['title'] ?? $task['title']),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'assigned_to' => ($_POST['assigned_to'] ?? '') !== '' ? (int) $_POST['assigned_to'] : null,
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

    if (($_POST['return_to'] ?? '') === 'kanban') {
        $query = [];
        if (($_POST['filter_project_id'] ?? '') !== '') {
            $query['project_id'] = (int) $_POST['filter_project_id'];
        }
        if (($_POST['filter_assignee_id'] ?? '') !== '') {
            $query['assignee_id'] = (int) $_POST['filter_assignee_id'];
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
    Permissions::require(Permissions::MANAGER_ROLES);
    $reportType = $_GET['type'] ?? 'detailed';
    $filters = [
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'user_id' => $_GET['user_id'] ?? '',
        'project_group' => trim($_GET['project_group'] ?? ''),
    ];

    View::render('reports/index', [
        'title' => 'Reports',
        'reportType' => in_array($reportType, ['detailed', 'project', 'customer'], true) ? $reportType : 'detailed',
        'workLogs' => WorkLog::report($filters),
        'projectSummary' => WorkLog::summaryByProject($filters),
        'customerSummary' => WorkLog::summaryByCustomer($filters),
        'users' => User::allActive(),
        'filters' => $filters,
    ]);
    exit;
}

if ($path === '/reports/export') {
    Permissions::require(Permissions::MANAGER_ROLES);
    $filters = [
        'from_date' => $_GET['from_date'] ?? '',
        'to_date' => $_GET['to_date'] ?? '',
        'user_id' => $_GET['user_id'] ?? '',
        'project_group' => trim($_GET['project_group'] ?? ''),
    ];

    $rows = WorkLog::report($filters);
    $reportProjectName = trim((string) ($filters['project_group'] ?: ($rows[0]['project_group'] ?? '')));
    if ($reportProjectName !== '') {
        foreach (Project::all() as $project) {
            if ($reportProjectName === (string) $project['name'] || $reportProjectName === (string) $project['project_group']) {
                $filters['export_project_name'] = $project['name'];
                $filters['export_project_id'] = $project['code'] ?: '-';
                break;
            }
        }
    }
    $groupBy = in_array($_GET['group_by'] ?? '', ['user', 'client', 'phase', 'tasklist'], true) ? $_GET['group_by'] : 'user';
    $workbook = TimesheetXlsxExporter::build($rows, $filters, User::allActive(), Auth::user() ?? [], $groupBy);
    $filename = 'timesheet_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($workbook));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $workbook;
    exit;
}

http_response_code(404);
View::render('404', ['title' => 'Not Found']);
