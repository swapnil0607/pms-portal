<?php

declare(strict_types=1);

/**
 * One-time fix-up: links existing work_logs rows that have no task_id (i.e.
 * were logged through the plain Daily Log form, before it started
 * find-or-creating a Task on save) to a real Task, using the exact same
 * find-or-create logic Task::findOrCreateForLog() now runs on every new
 * Daily Log entry - so these old rows end up exactly as if they'd always
 * gone through today's flow.
 *
 * Strictly additive: the only thing ever changed on an existing row is
 * setting work_logs.task_id from NULL to a real id. Nothing is deleted,
 * and no other column on work_logs (notes, hours, dates, project_group,
 * ...) is ever touched. New rows may be created in project_phases,
 * task_lists, and tasks where none matched yet - never in projects.
 *
 * Web-runnable (no SSH needed): upload this file, visit it once in a
 * browser with the token below appended, read the report, then DELETE
 * this file from the server. Safe to run more than once if you don't -
 * already-linked rows are skipped, so a second run just does nothing.
 *
 * Usage:
 *   https://yourdomain.com/pms/migration/backfill_task_links.php?token=a3eccf450b41536257055e43cd42063f9494e9bafce86a25
 */

const BACKFILL_TOKEN = 'a3eccf450b41536257055e43cd42063f9494e9bafce86a25';

if (($_GET['token'] ?? '') !== BACKFILL_TOKEN) {
    http_response_code(403);
    exit('Forbidden.');
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Models\Task;

header('Content-Type: text/plain; charset=utf-8');

$db = Database::connection();

// A system user to attribute newly-created tasks to when we can't tell who
// logged the original entry any other way - but we can: work_logs.user_id.
$stmt = $db->query(
    "SELECT id, project_group, phase, module_name, notes, user_id, log_date
     FROM work_logs
     WHERE task_id IS NULL
     ORDER BY id"
);
$rows = $stmt->fetchAll();

echo 'Found ' . count($rows) . " work log(s) without a task link.\n\n";

$linked = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $row) {
    try {
        $taskId = Task::findOrCreateForLog(
            (string) $row['project_group'],
            (string) $row['phase'],
            (string) $row['module_name'],
            (string) $row['notes'],
            (int) $row['user_id']
        );

        if ($taskId === null) {
            $skipped++;
            echo "SKIP  work_log #{$row['id']} ({$row['log_date']}): no project named '{$row['project_group']}' found.\n";
            continue;
        }

        $db->prepare('UPDATE work_logs SET task_id = ? WHERE id = ?')->execute([$taskId, $row['id']]);
        $linked++;
        echo "LINK  work_log #{$row['id']} ({$row['log_date']}) -> task #{$taskId}\n";
    } catch (\Throwable $e) {
        $errors++;
        echo "ERROR work_log #{$row['id']}: {$e->getMessage()}\n";
    }
}

echo "\nDone. Linked: {$linked}, skipped (no matching project): {$skipped}, errors: {$errors}.\n";
echo "Delete this file from the server now that it's run.\n";
