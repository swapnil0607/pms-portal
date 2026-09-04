<section class="section-header">
    <div>
        <h2>New Project</h2>
        <p>Set up the project foundation and delivery ownership.</p>
    </div>
    <a class="btn ghost" href="/projects">Back</a>
</section>

<?php require __DIR__ . '/../partials/suggestions.php'; ?>
<form method="post" class="panel form-grid">
    <?= csrf_field() ?>
    <label class="span-2">
        Project Name
        <input type="text" name="name" required>
    </label>
    <label>
        Project Code
        <input type="text" name="code" placeholder="PMS-001">
    </label>
    <label>
        Client
        <select name="client_id">
            <option value="">No client (freeform)</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= e((string) $client['id']) ?>"><?= e($client['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Project Group (freeform, used only when no client is picked)
        <input type="text" name="project_group" placeholder="Client or group name" data-autosuggest="clients" autocomplete="off">
    </label>
    <label>
        Title Color (used to tint the project title on the Projects page)
        <span class="color-field">
            <input type="color" name="color" value="#1f6f8b" disabled>
            <label class="checkbox-inline"><input type="checkbox" name="color_clear" value="1" checked> No custom color</label>
        </span>
    </label>
    <label>
        Owner
        <select name="owner_id" required>
            <?php foreach ($users as $user): ?>
                <option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Status
        <select name="status">
            <option value="planned">Planned</option>
            <option value="active">Active</option>
            <option value="on_hold">On Hold</option>
            <option value="completed">Completed</option>
        </select>
    </label>
    <label>
        Priority
        <select name="priority">
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
            <option value="low">Low</option>
        </select>
<?php
$pfs = $projectFieldSettings ?? \App\Models\ProjectFieldSetting::getMap();
$isFieldVisible = static fn (string $key): bool => (bool) ($pfs[$key]['visible'] ?? true);
$getFieldLabel = static fn (string $key, string $default): string => !empty($pfs[$key]['label']) ? $pfs[$key]['label'] : $default;
?>
    <?php if ($isFieldVisible('start_date')): ?>
    <label>
        <?= e($getFieldLabel('start_date', 'Start Date')) ?>
        <input type="date" name="start_date">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('due_date')): ?>
    <label>
        <?= e($getFieldLabel('due_date', 'End Date')) ?>
        <input type="date" name="due_date">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('billed_learners')): ?>
    <label>
        <?= e($getFieldLabel('billed_learners', 'Billed Learners')) ?>
        <input type="number" name="billed_learners" min="0">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('learners_on_platform')): ?>
    <label>
        <?= e($getFieldLabel('learners_on_platform', 'Learners On Platform')) ?>
        <input type="number" name="learners_on_platform" min="0">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('learners_connected')): ?>
    <label>
        <?= e($getFieldLabel('learners_connected', 'Learners Connected')) ?>
        <input type="number" name="learners_connected" min="0">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('started_with_courses')): ?>
    <label>
        <?= e($getFieldLabel('started_with_courses', 'Started With Courses')) ?>
        <input type="number" name="started_with_courses" min="0">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('total_time')): ?>
    <label>
        <?= e($getFieldLabel('total_time', 'Total Time')) ?>
        <input type="number" name="total_time" min="0" step="0.01">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('average_time_per_learner')): ?>
    <label>
        <?= e($getFieldLabel('average_time_per_learner', 'Avg Time')) ?>
        <input type="number" name="average_time_per_learner" min="0" step="0.01">
    </label>
    <?php endif; ?>
    <?php if ($isFieldVisible('adoption_percent')): ?>
    <label>
        <?= e($getFieldLabel('adoption_percent', 'Adoption %')) ?>
        <input type="number" name="adoption_percent" min="0" max="100" step="0.01">
    </label>
    <?php endif; ?>
    <div class="span-2" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:10px 14px;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#0369a1; margin-bottom:0;">
                <input type="checkbox" name="is_open_po" id="project_is_open_po" value="1" style="width:18px; height:18px; cursor:pointer;">
                <span>Open PO (Hourly Basis)</span>
            </label>
            <div class="muted" style="font-size:12px; margin-top:4px; color:#0284c7;">
                Billed directly on hourly consumption without fixed Build/Run limits.
            </div>
        </div>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px;">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#334155; margin-bottom:0;">
                <input type="checkbox" name="is_internal" id="project_is_internal" value="1" style="width:18px; height:18px; cursor:pointer;">
                <span>🏢 Internal / Non-Billable Project</span>
            </label>
            <div class="muted" style="font-size:12px; margin-top:4px; color:#64748b;">
                For internal meetings, R&amp;D, or training. Excluded from client billing reports.
            </div>
        </div>
    </div>
    <label>
        Allocated Build Hours
        <input type="number" name="build_hours" id="project_build_hours" min="0" step="0.01" value="0.00" placeholder="e.g. 120.00">
    </label>
    <label>
        Allocated Run Hours
        <input type="number" name="run_hours" id="project_run_hours" min="0" step="0.01" value="0.00" placeholder="e.g. 40.00">
    </label>
    <label class="span-2">
        Description
        <textarea name="description" rows="5"></textarea>
    </label>
    <?php foreach ($customFields as $field): ?>
        <label>
            <?= e($field['label']) ?>
            <input type="<?= e($field['field_type']) ?>" name="custom[<?= e($field['field_key']) ?>]" <?= $field['field_type'] === 'number' ? 'step="0.01"' : '' ?>>
        </label>
    <?php endforeach; ?>
    <div class="form-actions span-2">
        <button type="submit">Create Project</button>
    </div>
</form>
