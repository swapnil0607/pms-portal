<?php

declare(strict_types=1);

/**
 * Zoho Projects -> this PMS, one-time migration.
 *
 * Reads the two extracted Zoho exports (timesheet = work_logs, tasks = the
 * project/phase/task-list/task structure), wipes the example Clients/
 * Projects/Tasks/WorkLogs/Users in the target database, and imports the
 * Zoho data in dependency order: Users -> Clients -> Projects -> Phases ->
 * Task Lists -> Tasks -> parent-task links -> Work Logs.
 *
 * Wrapped in one DB transaction: any failure rolls back to the pre-run
 * state, nothing partial is ever left behind.
 *
 * Usage: php migration/run_import.php --confirm-local
 * (Deliberately has no "run against production" mode. That is a separate,
 * explicit step for later, not something this script does by accident.)
 */

chdir(__DIR__);
require __DIR__ . '/lib/xlsx_reader.php';
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

if (!in_array('--confirm-local', $argv, true)) {
    fwrite(STDERR, "Refusing to run without --confirm-local.\n");
    fwrite(STDERR, "This WIPES Clients/Projects/Tasks/Phases/TaskLists/WorkLogs and all\n");
    fwrite(STDERR, "Users except the admin account, then imports the Zoho exports.\n\n");
    fwrite(STDERR, "  php migration/run_import.php --confirm-local\n");
    exit(1);
}

$dbConfig = config('database');
echo "Target database: {$dbConfig['host']}/{$dbConfig['database']} (user: {$dbConfig['username']})\n";
echo "(read from config/database.php - the local XAMPP database as currently configured)\n\n";

$db = Database::connection();

const ADMIN_EMAIL = 'swapnilg@eduriser.com';
const ADMIN_NAME = 'Swapnil Gaonkar';

$STATUS_MAP = [
    'closed' => 'completed',
    'open' => 'open',
    'in progress' => 'in_progress',
    'in review' => 'review',
    'on hold' => 'blocked',
    'delayed' => 'open',
    'cancelled' => 'completed',
    'to be tested' => 'review',
];
$PRIORITY_MAP = ['medium' => 'medium', 'high' => 'high', 'low' => 'low', 'none' => 'medium'];
$PROJECT_STATUS_MAP = [
    'active' => 'active',
    'in progress' => 'active',
    'cancelled' => 'cancelled',
    'completed' => 'completed',
    'on hold' => 'on_hold',
];
$CLIENT_NAME_ALIASES = ['Ungrouped Project' => 'Ungrouped Projects'];

$warnings = [];
function warn(array &$bag, string $msg): void
{
    $bag[] = $msg;
}

// ---------- 1. Read source files ----------
echo "Reading timesheet export...\n";
$ts = read_xlsx_sheets(__DIR__ . '/zoho-exports/_extracted/timesheet', 1);
echo '  ' . count($ts['rows']) . " time log rows\n";

echo "Reading task export...\n";
$tk = read_xlsx_sheets(__DIR__ . '/zoho-exports/_extracted/tasks', 7);
echo '  ' . count($tk['rows']) . " task rows\n\n";

// ---------- 2. Name -> email map (from the timesheet's User/Log User Mailid columns) ----------
$nameToEmail = [];
foreach ($ts['rows'] as $row) {
    $name = trim($row['User'] ?? '');
    $email = strtolower(trim($row['Log User Mailid'] ?? ''));
    if ($name !== '' && str_contains($email, '@')) {
        $nameToEmail[$name] = $email;
    }
}
echo 'Resolved ' . count($nameToEmail) . " team members:\n";
foreach ($nameToEmail as $n => $e) {
    echo "  $n -> $e\n";
}
echo "\n";

try {
    $db->beginTransaction();

    // ---------- 3. Wipe example data (children before parents; users last) ----------
    echo "Wiping example Clients/Projects/Tasks/WorkLogs/Users...\n";
    foreach (['work_logs', 'task_comments', 'task_attachments', 'tasks', 'project_custom_field_values', 'project_members', 'task_lists', 'project_phases', 'projects', 'clients'] as $table) {
        $db->exec("DELETE FROM $table");
    }

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([ADMIN_EMAIL]);
    $adminRow = $stmt->fetch();
    if ($adminRow) {
        $adminId = (int) $adminRow['id'];
        $db->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([ADMIN_NAME, $adminId]);
    } else {
        // Repurpose the seed admin row (id 1) so its password keeps working.
        $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = 1')->execute([ADMIN_NAME, ADMIN_EMAIL]);
        $adminId = 1;
    }
    $db->prepare('DELETE FROM users WHERE id <> ?')->execute([$adminId]);

    // ---------- 4. Users (the other 7 team members) ----------
    echo "Importing Users...\n";
    $emailToUserId = [strtolower(ADMIN_EMAIL) => $adminId];
    $generatedPasswords = [];
    $insertUser = $db->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    foreach ($nameToEmail as $name => $email) {
        if ($email === strtolower(ADMIN_EMAIL)) {
            continue;
        }
        $tempPassword = bin2hex(random_bytes(5));
        $insertUser->execute([$name, $email, password_hash($tempPassword, PASSWORD_DEFAULT), 'member', 'active']);
        $emailToUserId[$email] = (int) $db->lastInsertId();
        $generatedPasswords[$email] = $tempPassword;
    }
    echo '  ' . count($emailToUserId) . " users total (1 admin + " . count($generatedPasswords) . " imported)\n\n";

    $resolveUserId = function (string $name) use ($nameToEmail, $emailToUserId): ?int {
        $name = trim($name);
        if ($name === '' || $name === 'Unassigned User') {
            return null;
        }
        $email = $nameToEmail[$name] ?? null;
        return $email ? ($emailToUserId[$email] ?? null) : null;
    };

    // ---------- 5. Clients (Project Group, from both exports) ----------
    echo "Importing Clients...\n";
    $clientNames = [];
    foreach ([$ts['rows'], $tk['rows']] as $rows) {
        foreach ($rows as $row) {
            $name = trim($row['Project Group'] ?? '');
            if ($name === '') {
                continue;
            }
            $name = $CLIENT_NAME_ALIASES[$name] ?? $name;
            $clientNames[$name] = true;
        }
    }
    $clientNameToId = [];
    $insertClient = $db->prepare('INSERT INTO clients (name, status) VALUES (?, "active") ON DUPLICATE KEY UPDATE name = VALUES(name)');
    foreach (array_keys($clientNames) as $name) {
        $insertClient->execute([$name]);
        $idStmt = $db->prepare('SELECT id FROM clients WHERE name = ?');
        $idStmt->execute([$name]);
        $clientNameToId[$name] = (int) $idStmt->fetchColumn();
    }
    echo '  ' . count($clientNameToId) . " clients\n\n";

    // ---------- 6. Projects, Phases, Task Lists (derived from the task export) ----------
    echo "Importing Projects, Phases, and Task Lists...\n";
    $projectMeta = []; // projectKey => ['name'=>,'code'=>,'group'=>,'status'=>,'minStart'=>,'maxDue'=>]
    foreach ($tk['rows'] as $row) {
        $name = trim($row['Project Name'] ?? '');
        if ($name === '') {
            continue;
        }
        $code = trim($row['Project ID'] ?? '');
        $key = $name . '|' . $code;
        if (!isset($projectMeta[$key])) {
            $projectMeta[$key] = [
                'name' => $name,
                'code' => $code ?: null,
                'group' => $CLIENT_NAME_ALIASES[trim($row['Project Group'] ?? '')] ?? trim($row['Project Group'] ?? ''),
                'status' => $PROJECT_STATUS_MAP[strtolower(trim($row['Project Status'] ?? ''))] ?? 'planned',
                'minStart' => null,
                'maxDue' => null,
            ];
        }
        foreach (['Phase Start Date' => 'minStart', 'Phase End Date' => 'maxDue'] as $col => $key2) {
            $d = zoho_date($row[$col] ?? null);
            if ($d !== null) {
                if ($projectMeta[$key][$key2] === null || ($key2 === 'minStart' ? $d < $projectMeta[$key][$key2] : $d > $projectMeta[$key][$key2])) {
                    $projectMeta[$key][$key2] = $d;
                }
            }
        }
    }

    $projectKeyToId = [];
    $insertProject = $db->prepare(
        'INSERT INTO projects (name, code, client_id, project_group, owner_id, status, priority, start_date, due_date)
         VALUES (:name, :code, :client_id, :project_group, :owner_id, :status, "medium", :start_date, :due_date)'
    );
    foreach ($projectMeta as $key => $meta) {
        $clientId = $clientNameToId[$meta['group']] ?? null;
        $insertProject->execute([
            'name' => $meta['name'],
            'code' => $meta['code'],
            'client_id' => $clientId,
            'project_group' => $meta['group'] ?: null,
            'owner_id' => $adminId,
            'status' => $meta['status'],
            'start_date' => $meta['minStart'],
            'due_date' => $meta['maxDue'],
        ]);
        $projectKeyToId[$key] = (int) $db->lastInsertId();
        // Owner starts as the project's owner by role convention; add them as a project member too.
        $db->prepare('INSERT INTO project_members (project_id, user_id, project_role) VALUES (?, ?, "owner")')
            ->execute([$projectKeyToId[$key], $adminId]);
    }
    echo '  ' . count($projectKeyToId) . " projects\n";

    $phaseKeyToId = [];  // "projectKey|phaseName" => id
    $insertPhase = $db->prepare('INSERT INTO project_phases (project_id, name, sort_order) VALUES (?, ?, ?)');
    $phaseSortCounters = [];
    $taskListKeyToId = []; // "projectKey|taskListName" => id
    $insertTaskList = $db->prepare('INSERT INTO task_lists (project_id, phase_id, name, sort_order) VALUES (?, ?, ?, ?)');
    $taskListSortCounters = [];

    foreach ($tk['rows'] as $row) {
        $projName = trim($row['Project Name'] ?? '');
        if ($projName === '') {
            continue;
        }
        $projectKey = $projName . '|' . trim($row['Project ID'] ?? '');
        $projectId = $projectKeyToId[$projectKey] ?? null;
        if ($projectId === null) {
            continue;
        }

        $phaseName = trim($row['Phase Name'] ?? '');
        $phaseId = null;
        if ($phaseName !== '') {
            $phaseKey = $projectKey . '|' . $phaseName;
            if (!isset($phaseKeyToId[$phaseKey])) {
                $sort = ($phaseSortCounters[$projectKey] ?? 0) + 1;
                $phaseSortCounters[$projectKey] = $sort;
                $insertPhase->execute([$projectId, $phaseName, $sort]);
                $phaseKeyToId[$phaseKey] = (int) $db->lastInsertId();
            }
            $phaseId = $phaseKeyToId[$phaseKey];
        }

        $taskListName = trim($row['Task List Name'] ?? '');
        if ($taskListName !== '') {
            $taskListKey = $projectKey . '|' . $taskListName;
            if (!isset($taskListKeyToId[$taskListKey])) {
                $sort = ($taskListSortCounters[$projectKey] ?? 0) + 1;
                $taskListSortCounters[$projectKey] = $sort;
                $insertTaskList->execute([$projectId, $phaseId, $taskListName, $sort]);
                $taskListKeyToId[$taskListKey] = (int) $db->lastInsertId();
            }
        }
    }
    echo '  ' . count($phaseKeyToId) . " phases, " . count($taskListKeyToId) . " task lists\n\n";

    // ---------- 7. Tasks ----------
    echo "Importing Tasks...\n";
    $insertTask = $db->prepare(
        'INSERT INTO tasks (project_id, phase_id, task_list_id, task_code, title, description, assigned_to, status, priority, start_date, due_date, estimated_hours, progress, sort_order, created_by)
         VALUES (:project_id, :phase_id, :task_list_id, :task_code, :title, :description, :assigned_to, :status, :priority, :start_date, :due_date, :estimated_hours, :progress, :sort_order, :created_by)'
    );
    $taskKeyToId = []; // "projectKey|Task ID" => new task id
    $taskSortCounters = [];
    $pendingParents = []; // [projectKey, zohoTaskId, ourNewTaskId, zohoParentTaskId]
    $skippedTasks = 0;

    foreach ($tk['rows'] as $row) {
        $projName = trim($row['Project Name'] ?? '');
        $title = trim($row['Task Name'] ?? '');
        if ($projName === '' || $title === '') {
            $skippedTasks++;
            continue;
        }
        $projectKey = $projName . '|' . trim($row['Project ID'] ?? '');
        $projectId = $projectKeyToId[$projectKey] ?? null;
        if ($projectId === null) {
            $skippedTasks++;
            continue;
        }

        $phaseName = trim($row['Phase Name'] ?? '');
        $phaseId = $phaseName !== '' ? ($phaseKeyToId[$projectKey . '|' . $phaseName] ?? null) : null;
        $taskListName = trim($row['Task List Name'] ?? '');
        $taskListId = $taskListName !== '' ? ($taskListKeyToId[$projectKey . '|' . $taskListName] ?? null) : null;

        $owners = array_filter(array_map('trim', explode(',', $row['Owner'] ?? '')), fn ($n) => $n !== '' && $n !== 'Unassigned User');
        $primaryOwner = $owners ? reset($owners) : null;
        $assignedTo = $primaryOwner ? $resolveUserId($primaryOwner) : null;
        $description = trim($row['Task Description'] ?? '');
        if (count($owners) > 1) {
            $description = trim($description . "\n\n(Zoho owners: " . implode(', ', $owners) . ')');
        }

        $status = $STATUS_MAP[strtolower(trim($row['Custom Status'] ?? ''))] ?? 'open';
        $priority = $PRIORITY_MAP[strtolower(trim($row['Priority'] ?? ''))] ?? 'medium';
        $progress = $status === 'completed' ? 100 : max(0, min(100, (int) trim($row['% Completed'] ?? '0')));
        $createdBy = $resolveUserId(trim($row['Created By'] ?? '')) ?? $adminId;
        $estimatedHours = zoho_hhmm_to_hours($row['Work Hours Per Owner'] ?? null) ?? zoho_hhmm_to_hours($row['Work hours'] ?? null);

        $sort = ($taskSortCounters[$projectKey] ?? 0) + 1;
        $taskSortCounters[$projectKey] = $sort;

        $insertTask->execute([
            'project_id' => $projectId,
            'phase_id' => $phaseId,
            'task_list_id' => $taskListId,
            'task_code' => trim($row['Task ID'] ?? '') ?: null,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'assigned_to' => $assignedTo,
            'status' => $status,
            'priority' => $priority,
            'start_date' => zoho_date($row['Start Date'] ?? null),
            'due_date' => zoho_date($row['Due Date'] ?? null),
            'estimated_hours' => $estimatedHours,
            'progress' => $progress,
            'sort_order' => $sort,
            'created_by' => $createdBy,
        ]);
        $newId = (int) $db->lastInsertId();

        $zohoTaskId = trim($row['Task ID'] ?? '');
        if ($zohoTaskId !== '') {
            $taskKeyToId[$projectKey . '|' . $zohoTaskId] = $newId;
        }

        $parentZohoId = trim($row['Parent Task ID'] ?? '');
        if ($parentZohoId !== '' && $parentZohoId !== '-') {
            $pendingParents[] = [$projectKey, $newId, $parentZohoId];
        }
    }
    echo '  ' . count($taskKeyToId) . ' tasks imported (' . $skippedTasks . " skipped: missing project/title)\n";

    // ---------- 8. Parent-task links (second pass) ----------
    $updateParent = $db->prepare('UPDATE tasks SET parent_task_id = ? WHERE id = ?');
    $parentsLinked = 0;
    foreach ($pendingParents as [$projectKey, $childId, $parentZohoId]) {
        $parentId = $taskKeyToId[$projectKey . '|' . $parentZohoId] ?? null;
        if ($parentId !== null) {
            $updateParent->execute([$parentId, $childId]);
            $parentsLinked++;
        }
    }
    echo "  $parentsLinked parent-task links resolved (of " . count($pendingParents) . " referenced)\n\n";

    // ---------- 9. Work Logs ----------
    echo "Importing Work Logs...\n";
    $insertLog = $db->prepare(
        'INSERT INTO work_logs (task_id, project_group, phase, module_name, task_category, notes, daily_log, time_period, hours, log_date, billing_type, user_id)
         VALUES (:task_id, :project_group, :phase, :module_name, :task_category, :notes, :daily_log, :time_period, :hours, :log_date, :billing_type, :user_id)'
    );
    $skippedLogs = 0;
    $logsImported = 0;
    foreach ($ts['rows'] as $row) {
        $email = strtolower(trim($row['Log User Mailid'] ?? ''));
        $userId = $emailToUserId[$email] ?? null;
        $logDate = zoho_date($row['Date'] ?? null);
        if ($userId === null || $logDate === null) {
            $skippedLogs++;
            continue;
        }

        $projectKey = trim($row['Project Name'] ?? '') . '|' . trim($row['Project ID'] ?? '');
        $zohoTaskId = trim($row['Task/Issue ID'] ?? '');
        $taskId = $zohoTaskId !== '' ? ($taskKeyToId[$projectKey . '|' . $zohoTaskId] ?? null) : null;

        $billingRaw = strtolower(trim($row['Billing Type'] ?? ''));
        $billingType = $billingRaw === 'non billable' ? 'Non-Billable' : 'Billable';

        $projectGroup = trim($row['Project Group'] ?? '');
        $projectGroup = $CLIENT_NAME_ALIASES[$projectGroup] ?? $projectGroup;

        $dailyLog = trim($row['Daily Log'] ?? '');
        $timePeriod = trim($row['Time Period'] ?? '');

        $insertLog->execute([
            'task_id' => $taskId,
            'project_group' => $projectGroup !== '' ? $projectGroup : 'Ungrouped Projects',
            'phase' => trim($row['phase'] ?? '') ?: 'No Phase',
            'module_name' => trim($row['Task List/Module'] ?? '') ?: 'General',
            'task_category' => trim($row['Task/General/Issue'] ?? '') ?: 'General',
            'notes' => trim($row['Notes'] ?? ''),
            'daily_log' => ($dailyLog !== '' && $dailyLog !== '-') ? $dailyLog : null,
            'time_period' => ($timePeriod !== '' && $timePeriod !== '-') ? $timePeriod : null,
            'hours' => (float) ($row['Hours(For Calculation)'] ?? 0),
            'log_date' => $logDate,
            'billing_type' => $billingType,
            'user_id' => $userId,
        ]);
        $logsImported++;
    }
    echo "  $logsImported work logs imported ($skippedLogs skipped: unresolved user/date)\n\n";

    $db->commit();
    echo "=== DONE - transaction committed ===\n\n";

    echo "Generated temporary passwords (LOCAL only - not for production use as-is):\n";
    foreach ($generatedPasswords as $email => $pw) {
        echo "  $email : $pw\n";
    }
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "FAILED - transaction rolled back, database unchanged.\n");
    fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
