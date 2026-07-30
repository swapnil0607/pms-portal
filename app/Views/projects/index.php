<?php
$canEditInline = \App\Core\Permissions::isManager();
$numericDateFields = [
    'billed_learners' => ['type' => 'number', 'step' => '1'],
    'learners_on_platform' => ['type' => 'number', 'step' => '1'],
    'learners_connected' => ['type' => 'number', 'step' => '1'],
    'started_with_courses' => ['type' => 'number', 'step' => '1'],
    'total_time' => ['type' => 'number', 'step' => '0.01'],
    'average_time_per_learner' => ['type' => 'number', 'step' => '0.01'],
    'adoption_percent' => ['type' => 'number', 'step' => '0.01'],
    'start_date' => ['type' => 'date', 'step' => ''],
    'due_date' => ['type' => 'date', 'step' => ''],
];
?>
<section class="section-header">
    <div>
        <h2>Projects</h2>
        <p>Create, monitor, and control project delivery.<?= $canEditInline ? ' Click a number or date cell to edit it directly.' : '' ?></p>
    </div>
    <?php if ($canEditInline): ?>
    <a class="btn" href="/projects/create">New Project</a>
    <?php endif; ?>
</section>

<?php if (!$projects): ?>
    <div class="panel empty-state">
        <h3>No projects created yet</h3>
        <p>Start with one live or internal project and add tasks under it.</p>
        <a class="btn" href="/projects/create">Create Project</a>
    </div>
<?php else: ?>
    <div class="panel report-table-panel">
        <div class="wide-table-wrap">
            <table class="data-table projects-table">
                <thead>
                    <tr>
                        <?php foreach ($projectFields as $field): ?>
                            <th><?= e($field['label']) ?></th>
                        <?php endforeach; ?>
                        <?php foreach ($customFields as $field): ?>
                            <th><?= e($field['label']) ?></th>
                        <?php endforeach; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php
                        $taskCount = (int) $project['task_count'];
                        $completed = (int) $project['completed_tasks'];
                        $progress = $taskCount > 0 ? round(($completed / $taskCount) * 100) : (int) $project['progress'];
                        $projectCustomValues = $customValues[(int) $project['id']] ?? [];
                    ?>
                    <tr>
                        <?php foreach ($projectFields as $field): ?>
                            <?php if ($field['key'] === 'code'): ?>
                                <td><?= e($project['code'] ?: 'P-' . $project['id']) ?></td>
                            <?php elseif ($field['key'] === 'name'): ?>
                                <td><a class="table-link" href="/projects/show?id=<?= e((string) $project['id']) ?>"><?= e($project['name']) ?></a></td>
                            <?php elseif ($field['key'] === 'status'): ?>
                                <td><span class="status-pill <?= e($project['status']) ?>"><?= e(str_replace('_', ' ', $project['status'])) ?></span></td>
                            <?php elseif ($field['key'] === 'tasks'): ?>
                                <td>
                                    <div class="mini-progress">
                                        <span style="width: <?= e((string) $progress) ?>%"></span>
                                    </div>
                                    <small><?= e((string) $progress) ?>%</small>
                                </td>
                            <?php elseif (isset($numericDateFields[$field['key']]) && $canEditInline): ?>
                                <?php
                                    $fieldMeta = $numericDateFields[$field['key']];
                                    $rawValue = $field['key'] === 'total_time'
                                        ? ($project['total_time'] ?? $project['logged_hours'] ?? '')
                                        : ($project[$field['key']] ?? '');
                                ?>
                                <td>
                                    <span class="editable-cell" data-edit-type="project-field" data-field="<?= e($field['key']) ?>" data-input-type="<?= e($fieldMeta['type']) ?>" <?= $fieldMeta['step'] ? 'data-step="' . e($fieldMeta['step']) . '"' : '' ?> data-project-id="<?= e((string) $project['id']) ?>" data-value="<?= e((string) $rawValue) ?>"><?= e((string) $rawValue !== '' ? (string) $rawValue : '-') ?></span>
                                </td>
                            <?php elseif ($field['key'] === 'total_time'): ?>
                                <td><?= e((string) ($project['total_time'] ?? $project['logged_hours'] ?? '')) ?></td>
                            <?php else: ?>
                                <td><?= e((string) ($project[$field['key']] ?? '')) ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ($customFields as $field): ?>
                            <?php $customValue = $projectCustomValues[$field['field_key']] ?? ''; ?>
                            <?php if ($canEditInline && in_array($field['field_type'], ['number', 'date'], true)): ?>
                                <td>
                                    <span class="editable-cell" data-edit-type="project-custom-field" data-field="<?= e($field['field_key']) ?>" data-input-type="<?= e($field['field_type']) ?>" <?= $field['field_type'] === 'number' ? 'data-step="0.01"' : '' ?> data-project-id="<?= e((string) $project['id']) ?>" data-value="<?= e((string) $customValue) ?>"><?= e($customValue !== '' ? (string) $customValue : '-') ?></span>
                                </td>
                            <?php else: ?>
                                <td><?= e($customValue !== '' ? (string) $customValue : '-') ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <td><?php if ($canEditInline): ?><a class="btn tiny" href="/projects/edit?id=<?= e((string) $project['id']) ?>">Edit</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
