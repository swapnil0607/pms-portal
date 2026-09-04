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
        <input type="text" name="project_group" value="<?= e($project['project_group'] ?? '') ?>" data-autosuggest="clients" autocomplete="off">
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
<?php
$pfs = $projectFieldSettings ?? \App\Models\ProjectFieldSetting::getMap();
$isFieldVisible = static fn (string $key): bool => (bool) ($pfs[$key]['visible'] ?? true);
$getFieldLabel = static fn (string $key, string $default): string => !empty($pfs[$key]['label']) ? $pfs[$key]['label'] : $default;
?>
    <?php if ($isFieldVisible('start_date')): ?>
    <label>
        <?= e($getFieldLabel('start_date', 'Start Date')) ?>
        <input type="date" name="start_date" value="<?= e($project['start_date'] ?? '') ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('due_date')): ?>
    <label>
        <?= e($getFieldLabel('due_date', 'End Date')) ?>
        <input type="date" name="due_date" value="<?= e($project['due_date'] ?? '') ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('billed_learners')): ?>
    <label>
        <?= e($getFieldLabel('billed_learners', 'Billed Learners')) ?>
        <input type="number" name="billed_learners" min="0" value="<?= e((string) ($project['billed_learners'] ?? '')) ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('learners_on_platform')): ?>
    <label>
        <?= e($getFieldLabel('learners_on_platform', 'Learners On Platform')) ?>
        <input type="number" name="learners_on_platform" min="0" value="<?= e((string) ($project['learners_on_platform'] ?? '')) ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('learners_connected')): ?>
    <label>
        <?= e($getFieldLabel('learners_connected', 'Learners Connected')) ?>
        <input type="number" name="learners_connected" min="0" value="<?= e((string) ($project['learners_connected'] ?? '')) ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('started_with_courses')): ?>
    <label>
        <?= e($getFieldLabel('started_with_courses', 'Started With Courses')) ?>
        <input type="number" name="started_with_courses" min="0" value="<?= e((string) ($project['started_with_courses'] ?? '')) ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('total_time')): ?>
    <label>
        <?= e($getFieldLabel('total_time', 'Total Time')) ?>
        <input type="number" name="total_time" min="0" step="0.01" value="<?= e((string) ($project['total_time'] ?? '')) ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('average_time_per_learner')): ?>
    <label>
        <?= e($getFieldLabel('average_time_per_learner', 'Avg Time')) ?>
        <input type="number" name="average_time_per_learner" min="0" step="0.01" value="<?= e((string) ($project['average_time_per_learner'] ?? '')) ?>">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('adoption_percent')): ?>
    <label>
        <?= e($getFieldLabel('adoption_percent', 'Adoption %')) ?>
        <input type="number" name="adoption_percent" min="0" max="100" step="0.01" value="<?= e((string) ($project['adoption_percent'] ?? '')) ?>">
    </label>
    <?php endif; ?>
    <div class="span-2" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:10px 14px;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#0369a1; margin-bottom:0;">
                <input type="checkbox" name="is_open_po" id="project_is_open_po" value="1" <?= !empty($project['is_open_po']) ? 'checked' : '' ?> style="width:18px; height:18px; cursor:pointer;">
                <span>Open PO (Hourly Basis)</span>
            </label>
            <div class="muted" style="font-size:12px; margin-top:4px; color:#0284c7;">
                Billed directly on hourly consumption without fixed Build/Run limits.
            </div>
        </div>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#334155; margin-bottom:0;">
                <input type="checkbox" name="is_internal" id="project_is_internal" value="1" <?= !empty($project['is_internal']) ? 'checked' : '' ?> style="width:18px; height:18px; cursor:pointer;">
                <span>🏢 Internal / Non-Billable Project</span>
            </label>
            <div class="muted" style="font-size:12px; margin-top:4px; color:#64748b;">
                For internal meetings, R&amp;D, or training. Excluded from client billing reports.
            </div>
        </div>
    </div>
    <label>
        Allocated Build Hours
        <input type="number" name="build_hours" id="project_build_hours" min="0" step="0.01" value="<?= e((string) ($project['build_hours'] ?? '0.00')) ?>" placeholder="e.g. 120.00">
    </label>
    <label>
        Allocated Run Hours
        <input type="number" name="run_hours" id="project_run_hours" min="0" step="0.01" value="<?= e((string) ($project['run_hours'] ?? '0.00')) ?>" placeholder="e.g. 40.00">
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
