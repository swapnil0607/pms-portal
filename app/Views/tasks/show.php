<?php
$isViewer = \App\Core\Permissions::isViewer();
$formatTaskLogHours = static function (float $hours, ?string $dailyLog = null): string {
    if ($dailyLog !== null && trim($dailyLog) !== '') {
        return $dailyLog;
    }

    $minutes = (int) round($hours * 60);
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
};
?>

<section class="project-hero task-hero">
    <div>
        <a class="back-link" href="/projects/show?id=<?= e((string) $task['project_id']) ?>"><?= e($task['project_name']) ?></a>
        <h2><?= e($task['title']) ?></h2>
        <p><?= e($task['description'] ?: 'No description added yet.') ?></p>
    </div>
    <div class="project-meta">
        <span class="badge"><?= e($labels[$task['status']] ?? $task['status']) ?></span>
        <span>Assignee: <?= e($task['assignee'] ?: 'Unassigned') ?></span>
        <span>Due: <?= e($task['due_date'] ?: '-') ?></span>
    </div>
</section>

<section class="task-detail-grid">
    <form method="post" action="/tasks/update" class="panel form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
        <div class="span-2">
            <h2>Task Details</h2>
        </div>
        <label class="span-2">
            Title
            <input type="text" name="title" value="<?= e($task['title']) ?>" required>
        </label>
        <label>
            Assignee
            <select name="assigned_to">
                <option value="">Unassigned</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (int) $task['assigned_to'] === (int) $user['id'] ? 'selected' : '' ?>>
                        <?= e($user['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Status
            <select name="status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $task['status'] === $status ? 'selected' : '' ?>>
                        <?= e($labels[$status]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Priority
            <select name="priority">
                <?php foreach (['low', 'medium', 'high', 'critical'] as $priority): ?>
                    <option value="<?= e($priority) ?>" <?= $task['priority'] === $priority ? 'selected' : '' ?>>
                        <?= e(ucfirst($priority)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Progress
            <input type="number" name="progress" min="0" max="100" value="<?= e((string) $task['progress']) ?>">
        </label>
        <label>
            Start Date
            <input type="date" name="start_date" value="<?= e($task['start_date'] ?: '') ?>">
        </label>
        <label>
            Due Date
            <input type="date" name="due_date" value="<?= e($task['due_date'] ?: '') ?>">
        </label>
        <label>
            Estimated Hours
            <input type="number" name="estimated_hours" min="0" step="0.25" value="<?= e($task['estimated_hours'] ?: '') ?>">
        </label>
        <label class="span-2">
            Description
            <textarea name="description" rows="5"><?= e($task['description'] ?: '') ?></textarea>
        </label>
        <div class="form-actions span-2">
            <button type="submit" <?= $isViewer ? 'disabled' : '' ?>>Save Task</button>
        </div>
    </form>

    <aside class="panel task-summary">
        <h2>Summary</h2>
        <dl>
            <div>
                <dt>Project</dt>
                <dd><a class="table-link" href="/projects/show?id=<?= e((string) $task['project_id']) ?>"><?= e($task['project_name']) ?></a></dd>
            </div>
            <div>
                <dt>Created By</dt>
                <dd><?= e($task['creator_name']) ?></dd>
            </div>
            <div>
                <dt>Created</dt>
                <dd><?= e(date('d M Y, h:i A', strtotime($task['created_at']))) ?></dd>
            </div>
            <div>
                <dt>Updated</dt>
                <dd><?= e(date('d M Y, h:i A', strtotime($task['updated_at']))) ?></dd>
            </div>
        </dl>
        <div class="progress task-progress">
            <span style="width: <?= e((string) $task['progress']) ?>%"></span>
        </div>
        <p class="muted"><?= e((string) $task['progress']) ?>% complete</p>
    </aside>
</section>

<section class="task-detail-grid lower-grid">
    <div class="panel task-log-panel">
        <div class="task-log-header">
            <h2>Time Log Entries</h2>
            <button type="submit" form="taskLogEntryForm" <?= $isViewer ? 'disabled' : '' ?>>Add Time Log</button>
        </div>
        <form id="taskLogEntryForm" method="post" action="/tasks/work-logs/create">
            <?= csrf_field() ?>
            <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
            <input type="hidden" name="task_category" value="DEV">
        </form>
        <div class="task-log-table-wrap">
            <table class="data-table task-log-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>User</th>
                        <th>Time Logged</th>
                        <th>Hours</th>
                        <th>Date</th>
                        <th>Billing Type</th>
                        <th>Notes</th>
                        <th>Created By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="task-log-add-row">
                        <td></td>
                        <td>
                            <select form="taskLogEntryForm" name="user_id" required aria-label="User">
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= e((string) $user['id']) ?>" <?= (int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= e($user['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <span class="time-range-inputs compact">
                                <input form="taskLogEntryForm" type="time" name="time_from" data-time-from aria-label="From time">
                                <span>to</span>
                                <input form="taskLogEntryForm" type="time" name="time_to" data-time-to aria-label="To time">
                            </span>
                        </td>
                        <td><input form="taskLogEntryForm" type="number" name="hours" min="0" step="0.25" data-hours-input placeholder="Hours" aria-label="Hours"></td>
                        <td><input form="taskLogEntryForm" type="date" name="log_date" value="<?= e(date('Y-m-d')) ?>" required aria-label="Date"></td>
                        <td>
                            <select form="taskLogEntryForm" name="billing_type" aria-label="Billing type">
                                <option value="Billable">Billable</option>
                                <option value="Non-Billable">Non-Billable</option>
                            </select>
                        </td>
                        <td><input form="taskLogEntryForm" type="text" name="notes" required placeholder="Work performed / notes" aria-label="Notes"></td>
                        <td><span class="user-dot"><?= e(strtoupper(substr((string) ($_SESSION['user_name'] ?? 'U'), 0, 1))) ?></span><?= e($_SESSION['user_name'] ?? 'Current User') ?></td>
                        <td></td>
                    </tr>
                    <?php if (!$workLogs): ?>
                        <tr>
                            <td></td>
                            <td colspan="8" class="muted">No time logs added for this task yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($workLogs as $log): ?>
                            <tr>
                                <td><input type="checkbox" aria-label="Select time log"></td>
                                <td><span class="user-dot"><?= e(strtoupper(substr($log['user_name'], 0, 1))) ?></span><?= e($log['user_name']) ?></td>
                                <td><strong><?= e($log['daily_log'] ?: '') ?></strong></td>
                                <td><?= e((string) $log['hours']) ?></td>
                                <td><?= e(date('d-m-Y', strtotime($log['log_date']))) ?></td>
                                <td><span class="billing-text <?= $log['billing_type'] === 'Billable' ? 'billable' : 'non-billable' ?>"><?= e($log['billing_type']) ?></span></td>
                                <td><?= e($log['notes']) ?></td>
                                <td><span class="user-dot"><?= e(strtoupper(substr($log['user_name'], 0, 1))) ?></span><?= e($log['user_name']) ?></td>
                                <td>
                                    <?php if (\App\Core\Permissions::canEditWorkLog($log)): ?>
                                    <span class="row-actions">
                                        <a href="/work-logs/edit?id=<?= e((string) $log['id']) ?>&return_to=task">Edit</a>
                                        <form method="post" action="/work-logs/delete" onsubmit="return confirm('Delete this time log?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $log['id']) ?>">
                                            <input type="hidden" name="return_to" value="task">
                                            <button type="submit" class="danger-link">Delete</button>
                                        </form>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="panel task-summary">
        <h2>Task Context</h2>
        <dl>
            <div>
                <dt>Phase</dt>
                <dd><?= e($task['phase_name'] ?: 'No Phase') ?></dd>
            </div>
            <div>
                <dt>Task List</dt>
                <dd><?= e($task['task_list_name'] ?: 'General') ?></dd>
            </div>
            <div>
                <dt>Billing Reports</dt>
                <dd>Logs entered here appear in Reports.</dd>
            </div>
        </dl>
    </aside>
</section>

<section class="task-detail-grid lower-grid">
    <div class="panel">
        <h2>Comments</h2>
        <?php if (!$comments): ?>
            <p class="muted">No comments yet.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment">
                    <strong><?= e($comment['user_name']) ?></strong>
                    <span><?= e(date('d M Y, h:i A', strtotime($comment['created_at']))) ?></span>
                    <p><?= e($comment['comment']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" action="/tasks/comment" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" value="<?= e((string) $task['project_id']) ?>">
            <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
            <input type="hidden" name="return_to" value="task">
            <textarea name="comment" rows="3" placeholder="Add a comment"></textarea>
            <button type="submit" <?= $isViewer ? 'disabled' : '' ?>>Comment</button>
        </form>
    </div>

    <div class="panel">
        <h2>Attachments</h2>
        <?php if (!$attachments): ?>
            <p class="muted">No files attached.</p>
        <?php else: ?>
            <ul class="attachment-list">
                <?php foreach ($attachments as $attachment): ?>
                    <li>
                        <a href="/attachments/download?id=<?= e((string) $attachment['id']) ?>"><?= e($attachment['original_name']) ?></a>
                        <small><?= e($attachment['user_name']) ?> - <?= e(date('d M Y, h:i A', strtotime($attachment['created_at']))) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/tasks/attachment" enctype="multipart/form-data" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" value="<?= e((string) $task['project_id']) ?>">
            <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
            <input type="hidden" name="return_to" value="task">
            <input type="file" name="attachment" required>
            <button type="submit" <?= $isViewer ? 'disabled' : '' ?>>Upload</button>
        </form>
    </div>
</section>
