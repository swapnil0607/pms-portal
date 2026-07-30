<section class="section-header">
    <div>
        <h2>Edit Time Log</h2>
        <p>Update the entry. Changes are reflected immediately in Reports and Time Logs.</p>
    </div>
    <a class="btn ghost" href="<?= e($returnTo === 'task' && $log['task_id'] ? '/tasks/show?id=' . $log['task_id'] : '/work-logs') ?>">Back</a>
</section>

<?php require __DIR__ . '/../partials/suggestions.php'; ?>
<form method="post" class="panel form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <?php if (!empty($error)): ?>
        <div class="alert error span-2"><?= e($error) ?></div>
    <?php endif; ?>
    <label>
        Client Name
        <input type="text" name="project_group" value="<?= e($log['project_group']) ?>" list="suggest-clients" autocomplete="off" required>
    </label>
    <label>
        Project Phase
        <input type="text" name="phase" value="<?= e($log['phase']) ?>" list="suggest-phases" autocomplete="off" required>
    </label>
    <label class="span-2">
        Module Number & Name
        <input type="text" name="module_name" value="<?= e($log['module_name']) ?>" list="suggest-task-lists" autocomplete="off" required>
    </label>
    <label>
        Task Category
        <select name="task_category" required>
            <?php foreach ($categories as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $log['task_category'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Date
        <input type="date" name="log_date" value="<?= e($log['log_date']) ?>" required>
    </label>
    <label>
        Time Logged (optional, recalculates hours if both set)
        <span class="time-range-inputs">
            <input type="time" name="time_from" data-time-from aria-label="From time">
            <span>to</span>
            <input type="time" name="time_to" data-time-to aria-label="To time">
        </span>
    </label>
    <label>
        Hours
        <input type="number" name="hours" min="0" step="0.25" value="<?= e((string) $log['hours']) ?>" data-hours-input required>
    </label>
    <label>
        Billing Type
        <select name="billing_type" required>
            <option value="Billable" <?= $log['billing_type'] === 'Billable' ? 'selected' : '' ?>>Billable</option>
            <option value="Non-Billable" <?= $log['billing_type'] === 'Non-Billable' ? 'selected' : '' ?>>Non-Billable</option>
        </select>
    </label>
    <label class="span-2">
        Activity / Work Performed
        <textarea name="notes" rows="4" required><?= e($log['notes']) ?></textarea>
    </label>
    <div class="form-actions span-2 split-actions">
        <button type="submit">Save Changes</button>
        <form method="post" action="/work-logs/delete" onsubmit="return confirm('Delete this time log?')">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) $log['id']) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <button type="submit" class="danger-button">Delete Log</button>
        </form>
    </div>
</form>
