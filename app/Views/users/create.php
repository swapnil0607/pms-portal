<section class="section-header">
    <div>
        <h2>New User</h2>
        <p>Add a person who can participate in PMS projects.</p>
    </div>
    <a class="btn ghost" href="/users">Back</a>
</section>

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
        <select name="role">
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
    <div class="form-actions span-2">
        <button type="submit">Create User</button>
    </div>
</form>
