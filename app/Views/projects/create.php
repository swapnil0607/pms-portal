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
        <input type="text" name="project_group" placeholder="Client or group name" list="suggest-clients">
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
    </label>
    <label>
        Start Date
        <input type="date" name="start_date">
    </label>
    <label>
        Due Date
        <input type="date" name="due_date">
    </label>
    <label>
        Billed Learners
        <input type="number" name="billed_learners" min="0">
    </label>
    <label>
        Learners On Platform
        <input type="number" name="learners_on_platform" min="0">
    </label>
    <label>
        Learners Connected
        <input type="number" name="learners_connected" min="0">
    </label>
    <label>
        Started With Courses
        <input type="number" name="started_with_courses" min="0">
    </label>
    <label>
        Total Time
        <input type="number" name="total_time" min="0" step="0.01">
    </label>
    <label>
        Average Time Per Learner
        <input type="number" name="average_time_per_learner" min="0" step="0.01">
    </label>
    <label>
        Adoption %
        <input type="number" name="adoption_percent" min="0" max="100" step="0.01">
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
