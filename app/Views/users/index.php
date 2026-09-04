<section class="section-header">
    <div>
        <h2>Users</h2>
        <p>Manage team members, toggle action rights (Read/Write/Export), and adjust page access directly.</p>
    </div>
    <a class="btn" href="/users/create">New User</a>
</section>

<?php
$isAdminEditor = \App\Core\Permissions::isAdmin();
$canEditUsers = \App\Core\Permissions::isManager();
?>
<div class="panel" style="overflow-x: auto;">
    <table class="data-table users-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th style="min-width: 220px;">Action Rights</th>
                <th style="min-width: 440px;">Page Access</th>
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
            <?php
            $userPages = \App\Models\User::permissionsFor($user);
            $userRights = \App\Core\Permissions::userRights($user);
            $userPageKeys = array_diff($userPages, ['project_actions', 'read', 'write', 'export']);
            $isRowDisabled = !$canEditUsers || (!$isAdminEditor && $user['role'] === 'admin');
            ?>
            <tr data-user-row="<?= e((string) $user['id']) ?>">
                <td>
                    <strong><?= e($user['name']) ?></strong>
                    <span class="perm-saving-indicator" id="saving-indicator-<?= e((string) $user['id']) ?>">Saved ✓</span>
                </td>
                <td><?= e($user['email']) ?></td>
                <td><span class="badge"><?= e(ucfirst($user['role'])) ?></span></td>
                <td>
                    <div class="perm-toggle-cluster">
                        <?php foreach (['read' => 'Read', 'write' => 'Write', 'export' => 'Export'] as $rKey => $rLabel): ?>
                            <?php
                            $isChecked = in_array($rKey, $userRights, true);
                            ?>
                            <label class="perm-toggle-item right-<?= e($rKey) ?> <?= $isChecked ? 'active' : '' ?> <?= $isRowDisabled ? 'disabled' : '' ?>" title="Toggle <?= e($rLabel) ?> right for <?= e($user['name']) ?>">
                                <input type="checkbox"
                                       data-quick-perm-toggle
                                       data-user-id="<?= e((string) $user['id']) ?>"
                                       data-perm="<?= e($rKey) ?>"
                                       <?= $isChecked ? 'checked' : '' ?>
                                       <?= $isRowDisabled ? 'disabled' : '' ?>>
                                <span><?= e($rLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td>
                    <div class="perm-toggle-cluster">
                        <?php foreach (\App\Core\Permissions::PAGES as $pageKey => $pageLabel): ?>
                            <?php
                            $isChecked = in_array($pageKey, $userPageKeys, true);
                            $isPageDisabled = $isRowDisabled || (!$isAdminEditor && in_array($pageKey, ['users', 'settings'], true));
                            ?>
                            <label class="perm-toggle-item <?= $isChecked ? 'active' : '' ?> <?= $isPageDisabled ? 'disabled' : '' ?>" title="Toggle <?= e($pageLabel) ?> access for <?= e($user['name']) ?>">
                                <input type="checkbox"
                                       data-quick-perm-toggle
                                       data-user-id="<?= e((string) $user['id']) ?>"
                                       data-perm="<?= e($pageKey) ?>"
                                       <?= $isChecked ? 'checked' : '' ?>
                                       <?= $isPageDisabled ? 'disabled' : '' ?>>
                                <span><?= e($pageLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td><?= e($user['designation'] ?: '-') ?></td>
                <td><?= e($user['department'] ?: '-') ?></td>
                <td><?= e($user['status']) ?></td>
                <?php if ($canEditUsers): ?>
                    <td style="white-space:nowrap;">
                        <a class="btn tiny" href="<?= url('/users/edit?id=' . e((string) $user['id'])) ?>">Edit</a>
                        <?php if ($isAdminEditor && (int) $user['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                            <form method="post" action="<?= url('/users/delete') ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this user?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                                <button type="submit" class="btn tiny danger" style="padding: 4px 8px; font-size: 11px; margin-left: 4px; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
