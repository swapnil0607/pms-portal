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
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Projects</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?= e($client['name']) ?></td>
                    <td><span class="status-pill <?= e($client['status']) ?>"><?= e($client['status']) ?></span></td>
                    <td><?= e((string) $client['project_count']) ?></td>
                    <td><a class="btn tiny" href="/clients/edit?id=<?= e((string) $client['id']) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
