<section class="section-header">
    <div>
        <h2>Users</h2>
        <p>Manage team members who can be assigned to projects and tasks.</p>
    </div>
    <a class="btn" href="/users/create">New User</a>
</section>

<?php $canEditUsers = \App\Core\Permissions::isManager(); ?>
<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Page Access</th>
                <th>Designation</th>
                <th>Department</th>
                <th>Status</th>
                <?php if ($canEditUsers): ?>
                    <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <?php $userPages = \App\Models\User::permissionsFor($user); ?>
            <tr>
                <td><?= e($user['name']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td><span class="badge"><?= e($user['role']) ?></span></td>
                <td>
                    <?php if (count($userPages) === count(\App\Core\Permissions::PAGES)): ?>
                        <span class="muted">All</span>
                    <?php elseif (!$userPages): ?>
                        <span class="muted">Home only</span>
                    <?php else: ?>
                        <?= e(implode(', ', array_map(static fn (string $key): string => \App\Core\Permissions::PAGES[$key] ?? $key, $userPages))) ?>
                    <?php endif; ?>
                </td>
                <td><?= e($user['designation'] ?: '-') ?></td>
                <td><?= e($user['department'] ?: '-') ?></td>
                <td><?= e($user['status']) ?></td>
                <?php if ($canEditUsers): ?>
                    <td><a class="btn tiny" href="/users/edit?id=<?= e((string) $user['id']) ?>">Edit</a></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
