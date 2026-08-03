<section class="section-header">
    <div>
        <h2>Clients</h2>
        <p>Client records power the Project Group / Customer Report mapping used across reports.</p>
    </div>
    <a class="btn" href="/clients/create">New Client</a>
</section>

<?php if (!$clients): ?>
    <div class="panel empty-state">
        <h3>No clients yet</h3>
        <p>Add a client, then pick it when creating or editing a project.</p>
        <a class="btn" href="/clients/create">Create Client</a>
    </div>
<?php else: ?>
    <div class="panel">
        <table class="data-table clients-table">
            <thead>
                <tr>
                    <th class="toggle-col"></th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Projects</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <?php
                    $rowId = 'client-' . $client['id'];
                    $clientProjects = $projectsByClient[(int) $client['id']] ?? [];
                ?>
                <tr>
                    <td class="toggle-col">
                        <?php if ($clientProjects): ?>
                            <button type="button" class="breakdown-toggle" data-breakdown-toggle data-target-parent="<?= e($rowId) ?>" aria-expanded="false">&#9656;</button>
                        <?php endif; ?>
                    </td>
                    <td><?= e($client['name']) ?></td>
                    <td><span class="status-pill <?= e($client['status']) ?>"><?= e($client['status']) ?></span></td>
                    <td><?= e((string) $client['project_count']) ?></td>
                    <td><a class="btn tiny" href="/clients/edit?id=<?= e((string) $client['id']) ?>">Edit</a></td>
                </tr>
                <?php if ($clientProjects): ?>
                    <tr class="client-projects-row" data-parent-id="<?= e($rowId) ?>" hidden>
                        <td></td>
                        <td colspan="4">
                            <div class="client-project-list">
                                <?php foreach ($clientProjects as $project): ?>
                                    <a class="client-project-item" href="/projects/show?id=<?= e((string) $project['id']) ?>">
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
    </div>
<?php endif; ?>
