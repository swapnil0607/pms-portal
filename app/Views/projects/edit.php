<section class="section-header">
    <div>
        <h2>Edit Project</h2>
        <p>Update the dashboard fields shown in the Projects table.</p>
    </div>
    <a class="btn ghost" href="/projects/show?id=<?= e((string) $project['id']) ?>&tab=details">Back</a>
</section>

<?php require __DIR__ . '/../partials/suggestions.php'; ?>
<form method="post" class="panel form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) $project['id']) ?>">
    <label class="span-2">
        Project Name
        <input type="text" name="name" value="<?= e($project['name']) ?>" required>
    </label>
    <label>
        Project Code
        <input type="text" name="code" value="<?= e($project['code'] ?? '') ?>">
    </label>
    <label>
        Client
        <select name="client_id">
            <option value="">No client (freeform)</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= e((string) $client['id']) ?>" <?= (int) ($project['client_id'] ?? 0) === (int) $client['id'] ? 'selected' : '' ?>>
                    <?= e($client['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Project Group (freeform, used only when no client is picked)
        <input type="text" name="project_group" value="<?= e($project['project_group'] ?? '') ?>" list="suggest-clients" autocomplete="off">
    </label>
    <label>
        Title Color (used to tint the project title on the Projects page)
        <span class="color-field">
            <input type="color" name="color" value="<?= e($project['color'] ?? '#1f6f8b') ?>" <?= empty($project['color']) ? 'disabled' : '' ?>>
            <label class="checkbox-inline"><input type="checkbox" name="color_clear" value="1" <?= empty($project['color']) ? 'checked' : '' ?>> No custom color</label>
        </span>
    </label>
    <label>
        Owner
        <select name="owner_id" required>
            <?php foreach ($users as $user): ?>
                <option value="<?= e((string) $user['id']) ?>" <?= (int) $project['owner_id'] === (int) $user['id'] ? 'selected' : '' ?>>
                    <?= e($user['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Status
        <select name="status">
            <?php foreach (['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On Hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $project['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Priority
        <select name="priority">
            <?php foreach (['low', 'medium', 'high', 'critical'] as $priority): ?>
                <option value="<?= e($priority) ?>" <?= $project['priority'] === $priority ? 'selected' : '' ?>><?= e(ucfirst($priority)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Start Date
        <input type="date" name="start_date" value="<?= e($project['start_date'] ?? '') ?>">
    </label>
    <label>
        Due Date
        <input type="date" name="due_date" value="<?= e($project['due_date'] ?? '') ?>">
    </label>
    <label>
        Billed Learners
        <input type="number" name="billed_learners" min="0" value="<?= e((string) ($project['billed_learners'] ?? '')) ?>">
    </label>
    <label>
        Learners On Platform
        <input type="number" name="learners_on_platform" min="0" value="<?= e((string) ($project['learners_on_platform'] ?? '')) ?>">
    </label>
    <label>
        Learners Connected
        <input type="number" name="learners_connected" min="0" value="<?= e((string) ($project['learners_connected'] ?? '')) ?>">
    </label>
    <label>
        Started With Courses
        <input type="number" name="started_with_courses" min="0" value="<?= e((string) ($project['started_with_courses'] ?? '')) ?>">
    </label>
    <label>
        Total Time
        <input type="number" name="total_time" min="0" step="0.01" value="<?= e((string) ($project['total_time'] ?? '')) ?>">
    </label>
    <label>
        Average Time Per Learner
        <input type="number" name="average_time_per_learner" min="0" step="0.01" value="<?= e((string) ($project['average_time_per_learner'] ?? '')) ?>">
    </label>
    <label>
        Adoption %
        <input type="number" name="adoption_percent" min="0" max="100" step="0.01" value="<?= e((string) ($project['adoption_percent'] ?? '')) ?>">
    </label>
    <label class="span-2">
        Description
        <textarea name="description" rows="5"><?= e($project['description'] ?? '') ?></textarea>
    </label>
    <?php foreach ($customFields as $field): ?>
        <label>
            <?= e($field['label']) ?>
            <input type="<?= e($field['field_type']) ?>" name="custom[<?= e($field['field_key']) ?>]" <?= $field['field_type'] === 'number' ? 'step="0.01"' : '' ?> value="<?= e((string) ($customValues[$field['field_key']] ?? '')) ?>">
        </label>
    <?php endforeach; ?>
    <div class="form-actions span-2">
        <button type="submit">Save Project</button>
    </div>
</form>
