<section class="section-header">
    <div>
        <h2>Daily Work Log</h2>
        <p>Enter what you worked on. This directly creates the management report.</p>
    </div>
    <a class="btn" href="/reports">View Report</a>
</section>

<?php if (\App\Core\Permissions::isViewer()): ?>
<div class="panel empty-state slim">
    <h3>Read-only account</h3>
    <p>Your role can view logs but not add new ones.</p>
</div>
<?php else: ?>
<form method="post" action="/work-logs/create" class="panel simple-log-form">
    <?= csrf_field() ?>
    <label>
        Client Name
        <input type="text" name="project_group" placeholder="Example: ABC School" required>
    </label>
    <label>
        Project Phase
        <input type="text" name="phase" placeholder="Example: Implementation Phase 1" required>
    </label>
    <label class="span-2">
        Module Number & Name
        <input type="text" name="module_name" placeholder="Example: M01 - Student Admission" required>
    </label>
    <label>
        Task Category
        <select name="task_category" required>
            <?php foreach ($categories as $code => $label): ?>
                <option value="<?= e($code) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Date
        <input type="date" name="log_date" value="<?= e(date('Y-m-d')) ?>" required>
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
        Hours
        <input type="number" name="hours" min="0" step="0.25" placeholder="Example: 2.5" data-hours-input required>
    </label>
    <label>
        Billing Type
        <select name="billing_type" required>
            <option value="Billable">Billable</option>
            <option value="Non-Billable">Non-Billable</option>
        </select>
    </label>
    <label class="span-2">
        Activity / Work Performed
        <textarea name="notes" rows="4" placeholder="Write a simple summary of what was done." required></textarea>
    </label>
    <div class="form-actions span-2">
        <button type="submit">Save Daily Log</button>
    </div>
</form>
<?php endif; ?>

<section class="section-header lower-grid">
    <div>
        <h2>My Recent Logs</h2>
        <p>Your latest saved work entries.</p>
    </div>
</section>

<?php if (!$recentLogs): ?>
    <div class="panel empty-state slim">
        <h3>No logs yet</h3>
        <p>Save your first daily log using the form above.</p>
    </div>
<?php else: ?>
    <div class="panel recent-log-list">
        <?php foreach ($recentLogs as $log): ?>
            <article class="recent-log">
                <div>
                    <strong><?= e($log['project_group']) ?></strong>
                    <span><?= e($log['module_name']) ?> · <?= e($log['task_category']) ?></span>
                    <p><?= e($log['notes']) ?></p>
                </div>
                <div>
                    <strong><?= e((string) $log['hours']) ?> hrs</strong>
                    <span><?= e($log['log_date']) ?></span>
                    <span><?= e($log['billing_type']) ?></span>
                    <?php if (!\App\Core\Permissions::isViewer()): ?>
                    <span class="row-actions">
                        <a href="/work-logs/edit?id=<?= e((string) $log['id']) ?>">Edit</a>
                        <form method="post" action="/work-logs/delete" onsubmit="return confirm('Delete this time log?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $log['id']) ?>">
                            <button type="submit" class="danger-link">Delete</button>
                        </form>
                    </span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
