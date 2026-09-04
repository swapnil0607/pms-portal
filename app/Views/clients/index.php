<?php
$canWrite = \App\Core\Permissions::canWrite();
?>
<section class="section-header">
    <div>
        <h2>Clients</h2>
        <p>Client records power the Project Group / Customer Report mapping used across reports.</p>
    </div>
    <?php if ($canWrite): ?>
        <a class="btn" href="/clients/create">New Client</a>
    <?php endif; ?>
</section>

<?php if (!$clients): ?>
    <div class="panel empty-state">
        <h3>No clients yet</h3>
        <p>Add a client, then pick it when creating or editing a project.</p>
        <?php if ($canWrite): ?>
            <a class="btn" href="/clients/create">Create Client</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="panel">
        <table class="data-table clients-table" data-client-dnd>
            <thead>
                <tr>
                    <th class="toggle-col"></th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Projects</th>
                    <?php if ($canWrite): ?><th>Action</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <?php
                    $rowId = 'client-' . $client['id'];
                    $clientProjects = $projectsByClient[(int) $client['id']] ?? [];
                ?>
                <tr class="client-row" data-client-id="<?= e((string) $client['id']) ?>">
                    <td class="toggle-col">
                        <?php if ($clientProjects): ?>
                            <button type="button" class="breakdown-toggle" data-breakdown-toggle data-target-parent="<?= e($rowId) ?>" aria-expanded="false">&#9656;</button>
                        <?php endif; ?>
                    </td>
                    <td><?= e($client['name']) ?></td>
                    <td><span class="status-pill <?= e($client['status']) ?>"><?= e($client['status']) ?></span></td>
                    <td><?= e((string) $client['project_count']) ?></td>
                    <?php if ($canWrite): ?>
                        <td><a class="btn tiny" href="/clients/edit?id=<?= e((string) $client['id']) ?>">Edit</a></td>
                    <?php endif; ?>
                </tr>
                <?php if ($clientProjects): ?>
                    <tr class="client-projects-row" data-parent-id="<?= e($rowId) ?>" hidden>
                        <td></td>
                        <td colspan="<?= $canWrite ? '4' : '3' ?>">
                            <div class="client-project-list">
                                <?php foreach ($clientProjects as $project): ?>
                                    <a class="client-project-item" draggable="<?= $canWrite ? 'true' : 'false' ?>" data-project-id="<?= e((string) $project['id']) ?>" data-current-client-id="<?= e((string) $client['id']) ?>" href="/projects/show?id=<?= e((string) $project['id']) ?>">
                                        <?php if ($canWrite): ?><span>&#8942;&#8942;</span><?php endif; ?>
                                        <span><?= e($project['name']) ?></span>
                                        <span class="status-pill <?= e($project['status']) ?>"><?= e(str_replace('_', ' ', $project['status'])) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($canWrite): ?>
            <p class="muted client-dnd-hint">Drag a project onto a different client to move it there.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
