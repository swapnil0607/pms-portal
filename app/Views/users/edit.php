<?php $userPages = \App\Models\User::permissionsFor($editUser); ?>
<section class="section-header">
    <div>
        <h2>Edit User</h2>
        <p><?= $isAdminEditor ? 'Update this person\'s details and page access.' : 'Update this person\'s page access and status.' ?></p>
    </div>
    <a class="btn ghost" href="/users">Back</a>
</section>

<form method="post" action="/users/edit" class="panel form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) $editUser['id']) ?>">
    <?php if (!empty($error)): ?>
        <div class="alert error span-2"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($isAdminEditor): ?>
        <label>
            Name
            <input type="text" name="name" value="<?= e($editUser['name']) ?>" required>
        </label>
        <label>
            Email
            <input type="text" name="email" inputmode="email" value="<?= e($editUser['email']) ?>" required>
        </label>
        <label>
            New Password (leave blank to keep current)
            <input type="password" name="password">
        </label>
        <label>
            Role
            <select name="role">
                <?php foreach (['member' => 'Member', 'manager' => 'Manager', 'admin' => 'Admin', 'viewer' => 'Viewer'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $editUser['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Designation
            <input type="text" name="designation" value="<?= e($editUser['designation'] ?? '') ?>">
        </label>
        <label>
            Department
            <input type="text" name="department" value="<?= e($editUser['department'] ?? '') ?>">
        </label>
    <?php else: ?>
        <label>
            Name
            <input type="text" value="<?= e($editUser['name']) ?>" disabled>
        </label>
        <label>
            Email
            <input type="text" value="<?= e($editUser['email']) ?>" disabled>
        </label>
        <label>
            Role
            <input type="text" value="<?= e(ucfirst($editUser['role'])) ?>" disabled>
        </label>
        <p class="muted span-2">Name, email, and role can only be changed by an Admin.</p>
    <?php endif; ?>

    <label>
        Status
        <select name="status">
            <option value="active" <?= $editUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $editUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </label>

    <?php $userRights = \App\Core\Permissions::userRights($editUser); ?>
    <div class="span-2">
        <span class="field-label">Action Rights & Permissions</span>
        <p class="muted field-hint">Controls whether this user can view, edit, or export data across their allowed pages.</p>
        <div class="permission-grid">
            <label class="checkbox-inline">
                <input type="checkbox" name="rights[]" value="read" <?= in_array(\App\Core\Permissions::RIGHT_READ, $userRights, true) ? 'checked' : '' ?>>
                <strong>Read</strong> &mdash; View records & pages
            </label>
            <label class="checkbox-inline">
                <input type="checkbox" name="rights[]" value="write" <?= in_array(\App\Core\Permissions::RIGHT_WRITE, $userRights, true) ? 'checked' : '' ?>>
                <strong>Write</strong> &mdash; Add, edit, or delete records & logs
            </label>
            <label class="checkbox-inline">
                <input type="checkbox" name="rights[]" value="export" <?= in_array(\App\Core\Permissions::RIGHT_EXPORT, $userRights, true) ? 'checked' : '' ?>>
                <strong>Export</strong> &mdash; Download Excel reports & statements
            </label>
        </div>
    </div>

    <div class="span-2">
        <span class="field-label">Page Access</span>
        <p class="muted field-hint">What this person can see, independent of their role.</p>
        <div class="permission-grid">
            <?php foreach (\App\Core\Permissions::PAGES as $key => $label): ?>
                <?php if (!$isAdminEditor && in_array($key, ['users', 'settings'], true)): ?>
                    <span class="checkbox-inline disabled"><input type="checkbox" disabled> <?= e($label) ?></span>
                <?php else: ?>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="pages[]" value="<?= e($key) ?>" <?= in_array($key, $userPages, true) ? 'checked' : '' ?>>
                        <?= e($label) ?>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if (!$isAdminEditor): ?>
            <p class="permission-note">Users and Settings access can only be granted by an Admin.</p>
        <?php endif; ?>
    </div>

    <?php if ($isAdminEditor): ?>
    <label class="checkbox-inline span-2">
        <input type="checkbox" name="project_actions" value="1" <?= \App\Core\Permissions::userCanManageProjectActions($editUser) ? 'checked' : '' ?>>
        Allow project actions (create, edit, archive, unarchive, and inline project updates)
    </label>
    <?php endif; ?>

    <div class="form-actions span-2">
        <button type="submit">Save Changes</button>
    </div>
</form>
