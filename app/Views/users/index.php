<section class="section-header">
    <div>
        <h2>Users</h2>
        <p>Manage team members who can be assigned to projects and tasks.</p>
    </div>
    <a class="btn" href="/users/create">New User</a>
</section>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Designation</th>
                <th>Department</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= e($user['name']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td><span class="badge"><?= e($user['role']) ?></span></td>
                <td><?= e($user['designation'] ?: '-') ?></td>
                <td><?= e($user['department'] ?: '-') ?></td>
                <td><?= e($user['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
