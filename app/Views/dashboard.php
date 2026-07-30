<section class="stats-grid">
    <article class="stat-card">
        <span>Clients</span>
        <strong><?= e((string) $stats['clients']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Open Tasks</span>
        <strong><?= e((string) $stats['open_tasks']) ?></strong>
    </article>
    <article class="stat-card warning">
        <span>My Overdue</span>
        <strong><?= e((string) $stats['my_overdue_tasks']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Today's Logs</span>
        <strong><?= e((string) $stats['my_log_hours_today']) ?>h</strong>
    </article>
</section>

<section class="home-workspace">
    <?php if (\App\Core\Permissions::isViewer()): ?>
    <div class="panel empty-state slim">
        <h3>Read-only account</h3>
        <p>Your role can view work but not log time. Contact an admin if this should change.</p>
    </div>
    <?php else: ?>
    <?php require __DIR__ . '/partials/suggestions.php'; ?>
    <form method="post" action="/work-logs/create" class="panel home-log-form">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="home">
        <div class="section-header compact">
            <div>
                <h2>Add Daily Log</h2>
                <p>Record the exact values needed for reports.</p>
            </div>
        </div>
        <div class="compact-form-grid">
            <label>
                Client Name
                <input type="text" name="project_group" placeholder="Example: Jindal Stainless" data-autosuggest="clients" autocomplete="off" required>
            </label>
            <label>
                Project Phase
                <input type="text" name="phase" placeholder="Example: Phase 1" data-autosuggest="phases" autocomplete="off" required>
            </label>
            <label>
                Module Number & Name
                <input type="text" name="module_name" placeholder="Example: M01 - LMS Setup" data-autosuggest="taskLists" autocomplete="off" required>
            </label>
            <label>
                Task Category
                <select name="task_category" required>
                    <?php foreach ($categories as $code => $name): ?>
                        <option value="<?= e($code) ?>"><?= e($code) ?> - <?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                Activity / Work Performed
                <textarea name="notes" rows="3" placeholder="What work was done?" required></textarea>
            </label>
            <label>
                Time Logged
                <span class="time-range-inputs">
                    <input type="time" name="time_from" data-time-from aria-label="From time">
                    <span>to</span>
                    <input type="time" name="time_to" data-time-to aria-label="To time">
                </span>
            </label>
            <label>
                Actual Hours
                <input type="number" name="hours" min="0" step="0.25" data-hours-input required>
            </label>
            <label>
                Log Date
                <input type="date" name="log_date" value="<?= e(date('Y-m-d')) ?>" required>
            </label>
            <label>
                Billing Type
                <select name="billing_type" required>
                    <option value="Billable">Billable</option>
                    <option value="Non-Billable">Non-Billable</option>
                </select>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit">Save Daily Log</button>
        </div>
    </form>
    <?php endif; ?>

    <div class="home-side-stack">
        <div class="panel">
            <div class="section-header compact">
                <div>
                    <h2>My Work</h2>
                    <p>Tasks assigned to me.</p>
                </div>
            </div>
            <?php if (!$myTasks): ?>
                <p class="muted">No open tasks assigned to you.</p>
            <?php else: ?>
                <div class="simple-list">
                    <?php foreach ($myTasks as $task): ?>
                        <a class="simple-row task-row" href="/tasks/show?id=<?= e((string) $task['id']) ?>">
                            <span class="task-row-info">
                                <strong><?= e($task['title']) ?></strong>
                                <small class="task-row-path">
                                    <?= e($task['project_name']) ?>
                                    <?php if (!empty($task['task_list_name'])): ?>
                                        <span class="path-sep">&rsaquo;</span><?= e($task['task_list_name']) ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                            <span class="task-row-meta">
                                <?php if ($task['due_date']): ?>
                                    <small class="task-due<?= $task['due_date'] < date('Y-m-d') ? ' overdue' : '' ?>">
                                        Due <?= e(date('d M', strtotime($task['due_date']))) ?>
                                    </small>
                                <?php endif; ?>
                                <span class="badge"><?= e($labels[$task['status']] ?? $task['status']) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="section-header compact">
                <div>
                    <h2>Time Logs</h2>
                    <p>Your current logging summary.</p>
                </div>
                <a class="button-link secondary" href="/time-logs">Open Full View</a>
            </div>
            <div class="mini-stats">
                <div>
                    <span>Today</span>
                    <strong><?= e((string) $myLogSummary['today_hours']) ?>h</strong>
                </div>
                <div>
                    <span>This Month</span>
                    <strong><?= e((string) $myLogSummary['month_hours']) ?>h</strong>
                </div>
                <div>
                    <span>Entries</span>
                    <strong><?= e((string) $myLogSummary['month_entries']) ?></strong>
                </div>
            </div>
            <?php if (!$myRecentLogs): ?>
                <p class="muted">No time logs yet.</p>
            <?php else: ?>
                <div class="simple-list log-list">
                    <?php foreach ($myRecentLogs as $log): ?>
                        <div class="simple-row">
                            <span>
                                <strong><?= e($log['project_group']) ?></strong>
                                <small><?= e($log['log_date']) ?> - <?= e($log['module_name']) ?> - <?= e($log['task_category']) ?></small>
                            </span>
                            <span><?= e((string) $log['hours']) ?>h</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
