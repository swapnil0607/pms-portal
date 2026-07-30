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

    <div class="form-actions span-2">
        <button type="submit">Save Changes</button>
    </div>
</form>
