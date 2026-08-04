<section class="section-header">
    <div>
        <h2>My Profile</h2>
        <p>Update your name, photo, designation, department, and password.</p>
    </div>
</section>

<form method="post" action="/profile" enctype="multipart/form-data" class="panel form-grid">
    <?= csrf_field() ?>
    <?php if (!empty($error)): ?>
        <div class="alert error span-2"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="span-2 avatar-field">
        <span class="avatar avatar-lg" data-avatar-preview>
            <?php if (!empty($profileUser['avatar_path'])): ?>
                <img src="<?= e(avatar_url($profileUser['avatar_path'])) ?>" alt="<?= e($profileUser['name']) ?>">
            <?php else: ?>
                <?= e(user_initials($profileUser['name'])) ?>
            <?php endif; ?>
        </span>
        <div>
            <label class="btn ghost tiny avatar-upload-label">
                Change Photo
                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" data-avatar-input hidden>
            </label>
            <p class="muted field-hint">JPG, PNG, or WEBP. Max 3MB.</p>
        </div>
    </div>

    <label>
        Name
        <input type="text" name="name" value="<?= e($profileUser['name']) ?>" required>
    </label>
    <label>
        Email
        <input type="text" value="<?= e($profileUser['email']) ?>" disabled>
    </label>
    <label>
        Designation
        <input type="text" name="designation" value="<?= e($profileUser['designation'] ?? '') ?>">
    </label>
    <label>
        Department
        <input type="text" name="department" value="<?= e($profileUser['department'] ?? '') ?>">
    </label>

    <div class="span-2">
        <span class="field-label">Change Password</span>
        <p class="muted field-hint">Leave these blank to keep your current password.</p>
    </div>
    <label>
        Current Password
        <input type="password" name="current_password" autocomplete="current-password">
    </label>
    <div></div>
    <label>
        New Password
        <input type="password" name="new_password" autocomplete="new-password">
    </label>
    <label>
        Confirm New Password
        <input type="password" name="confirm_password" autocomplete="new-password">
    </label>

    <div class="form-actions span-2">
        <button type="submit">Save Changes</button>
    </div>
</form>
