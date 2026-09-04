<section class="section-header">
    <div>
        <h2>New User</h2>
        <p>Add a person who can participate in PMS projects.</p>
    </div>
    <a class="btn ghost" href="/users">Back</a>
</section>

<?php
$roleDefaults = [];
$roleRightsDefaults = [];
foreach (['admin', 'manager', 'member', 'viewer'] as $roleOption) {
    $roleDefaults[$roleOption] = \App\Core\Permissions::defaultPagesForRole($roleOption);
    $roleRightsDefaults[$roleOption] = \App\Core\Permissions::defaultRightsForRole($roleOption);
}
?>
<form method="post" class="panel form-grid">
    <?= csrf_field() ?>
    <label>
        Name
        <input type="text" name="name" required>
    </label>
    <label>
        Email
        <input type="text" name="email" inputmode="email" required>
    </label>
    <label>
        Password
        <input type="password" name="password" required>
    </label>
    <label>
        Role
        <select name="role" data-role-select data-role-defaults="<?= e(json_encode($roleDefaults)) ?>" data-role-rights-defaults="<?= e(json_encode($roleRightsDefaults)) ?>">
            <option value="member">Member</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
            <option value="viewer">Viewer</option>
        </select>
    </label>
    <label>
        Designation
        <input type="text" name="designation">
    </label>
    <label>
        Department
        <input type="text" name="department">
    </label>
    <label>
        Status
        <select name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </label>

    <div class="span-2">
        <span class="field-label">Action Rights & Permissions</span>
        <p class="muted field-hint">Controls whether this user can view, edit, or export data across their allowed pages.</p>
        <div class="permission-grid">
            <label class="checkbox-inline">
                <input type="checkbox" name="rights[]" value="read" checked>
                <strong>Read</strong> &mdash; View records & pages
            </label>
            <label class="checkbox-inline">
                <input type="checkbox" name="rights[]" value="write" checked>
                <strong>Write</strong> &mdash; Add, edit, or delete records & logs
            </label>
            <label class="checkbox-inline">
                <input type="checkbox" name="rights[]" value="export" checked>
                <strong>Export</strong> &mdash; Download Excel reports & statements
            </label>
        </div>
    </div>

    <div class="span-2">
        <span class="field-label">Page Access</span>
        <p class="muted field-hint">What sections this person can see. Changing Role above resets these to that role's defaults &mdash; still yours to adjust.</p>
        <div class="permission-grid">
            <?php foreach (\App\Core\Permissions::PAGES as $key => $label): ?>
                <label class="checkbox-inline">
                    <input type="checkbox" name="pages[]" value="<?= e($key) ?>" <?= in_array($key, $roleDefaults['member'], true) ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <label class="checkbox-inline span-2">
        <input type="checkbox" name="project_actions" value="1">
        Allow project actions (create, edit, archive, unarchive, and inline project updates)
    </label>
    <div class="form-actions span-2">
        <button type="submit">Create User</button>
    </div>
</form>
