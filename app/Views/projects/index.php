<?php
$canWrite = \App\Core\Permissions::canWrite();
$canEditInline = \App\Core\Permissions::canManageProjectActions() && $canWrite;
$canDeleteProject = \App\Core\Permissions::isManager() && $canWrite;
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
$projectSortTypes = [
    'code' => 'text', 'name' => 'text', 'owner_name' => 'text', 'status' => 'text',
    'billed_learners' => 'number', 'learners_on_platform' => 'number',
    'learners_connected' => 'number', 'started_with_courses' => 'number',
    'total_time' => 'number', 'average_time_per_learner' => 'number',
    'adoption_percent' => 'number', 'start_date' => 'date', 'due_date' => 'date',
    'tasks' => 'number',
];
?>
<section class="section-header">
    <div>
        <h2>Projects</h2>
        <p>Create, monitor, and control project delivery.<?= $canEditInline ? ' Click a number or date cell to edit it directly.' : '' ?></p>
    </div>
    <?php if (\App\Core\Permissions::canManageProjectActions() && $canWrite): ?>
    <a class="btn" href="<?= url('/projects/create') ?>">New Project</a>
    <?php endif; ?>
</section>

<nav class="tabs">
    <a class="<?= !$showArchived ? 'active' : '' ?>" href="<?= url('/projects') ?>">Active Projects</a>
    <a class="<?= $showArchived ? 'active' : '' ?>" href="<?= url('/projects?archived=1') ?>">Archived<?= $archivedCount ? ' (' . e((string) $archivedCount) . ')' : '' ?></a>
</nav>

<?php if ($projects): ?>
<div class="project-list-toolbar">
    <label class="project-search">
        <span class="sr-only">Search projects</span>
        <input type="search" placeholder="Search projects…" autocomplete="off" data-project-search data-project-search-table="projects-table">
        <ul class="project-search-suggestions" data-project-search-suggestions hidden></ul>
    </label>
    <span class="project-list-count" data-project-search-count><?= e((string) count($projects)) ?> projects</span>
</div>
<div class="project-table-help">
    <span>Drag a column title to change its position. Drag the right edge of a title to resize it. Your layout is saved on this browser.</span>
    <button type="button" class="link-button" data-reset-project-columns>Reset column layout</button>
</div>
<?php endif; ?>

<?php if (!$projects): ?>
    <div class="panel empty-state">
        <?php if ($showArchived): ?>
            <h3>No archived projects</h3>
            <p>Projects you archive from the active list will show up here.</p>
        <?php else: ?>
            <h3>No projects created yet</h3>
            <p>Start with one live or internal project and add tasks under it.</p>
            <?php if (\App\Core\Permissions::canManageProjectActions()): ?>
                <a class="btn" href="<?= url('/projects/create') ?>">Create Project</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="panel report-table-panel">
        <div class="desktop-only-table">
            <div class="wide-table-wrap">
                <table class="data-table projects-table" data-sortable-table data-project-column-manager>
                    <thead>
                        <tr>
                            <?php foreach ($projectFields as $field): ?>
                                <th data-sort-key="<?= e($field['key']) ?>" data-column-id="project-<?= e($field['key']) ?>" data-column-type="<?= e($projectSortTypes[$field['key']] ?? 'text') ?>"><?= e($field['label']) ?></th>
                            <?php endforeach; ?>
                            <?php foreach ($customFields as $field): ?>
                                <th data-sort-key="custom-<?= e($field['field_key']) ?>" data-column-id="custom-<?= e($field['field_key']) ?>" data-column-type="<?= in_array($field['field_type'], ['number', 'date'], true) ? e($field['field_type']) : 'text' ?>"><?= e($field['label']) ?></th>
                            <?php endforeach; ?>
                            <?php if ($canEditInline): ?><th data-no-sort data-column-id="action" data-column-type="none">Action</th><?php endif; ?>
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
                        <tr data-project-name="<?= e($project['name']) ?>">
                            <?php foreach ($projectFields as $field): ?>
                                <?php if ($field['key'] === 'code'): ?>
                                    <td><?= e($project['code'] ?: 'P-' . $project['id']) ?></td>
                                <?php elseif ($field['key'] === 'name'): ?>
                                    <td>
                                        <a class="table-link project-name-link" href="<?= url('/projects/show?id=' . e((string) $project['id'])) ?>">
                                            <span class="project-name-marker"<?= !empty($project['color']) ? ' style="background-color: ' . e($project['color']) . '"' : '' ?>></span>
                                            <span><?= e($project['name']) ?></span>
                                            <span class="project-name-arrow" aria-hidden="true">→</span>
                                        </a>
                                    </td>
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
                            <?php if ($canEditInline): ?>
                            <td data-column-id="action">
                                    <span class="row-tools">
                                        <a class="btn tiny" href="<?= url('/projects/edit?id=' . e((string) $project['id'])) ?>">Edit</a>
                                        <details class="row-menu">
                                            <summary aria-label="Project actions">...</summary>
                                            <div class="row-menu-panel">
                                                <?php if ($showArchived): ?>
                                                    <form method="post" action="<?= url('/projects/unarchive') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                        <button type="submit">Unarchive</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" action="<?= url('/projects/archive') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                        <button type="submit">Archive</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($canDeleteProject): ?>
                                                <form method="post" action="<?= url('/projects/delete') ?>" onsubmit="return confirm('Permanently delete &quot;<?= e(addslashes($project['name'])) ?>&quot; and all its phases, task lists, and tasks? This cannot be undone.')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                    <input type="hidden" name="was_archived" value="<?= $showArchived ? '1' : '0' ?>">
                                                    <button type="submit" class="danger-link">Delete</button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </span>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Cards for Projects -->
        <div class="mobile-only-cards project-mobile-cards">
            <?php foreach ($projects as $project): ?>
                <?php
                    $taskCount = (int) $project['task_count'];
                    $completed = (int) $project['completed_tasks'];
                    $progress = $taskCount > 0 ? round(($completed / $taskCount) * 100) : (int) ($project['progress'] ?? 0);
                ?>
                <article class="mobile-card project-mobile-card" data-project-card data-project-name="<?= e($project['name']) ?>" data-project-code="<?= e($project['code'] ?: 'P-' . $project['id']) ?>" data-project-owner="<?= e($project['owner_name'] ?? '') ?>">
                    <header class="mobile-card-header">
                        <div class="mobile-card-header-left">
                            <span class="mobile-card-code"><?= e($project['code'] ?: 'P-' . $project['id']) ?></span>
                            <h4 class="mobile-card-title">
                                <a href="<?= url('/projects/show?id=' . e((string) $project['id'])) ?>">
                                    <span class="project-marker" <?= !empty($project['color']) ? 'style="background-color:' . e($project['color']) . '"' : '' ?>></span>
                                    <?= e($project['name']) ?>
                                </a>
                            </h4>
                        </div>
                        <span class="status-pill <?= e($project['status']) ?>"><?= e(str_replace('_', ' ', $project['status'])) ?></span>
                    </header>
                    <div class="mobile-card-body">
                        <div class="mobile-card-grid">
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Contract Model</span>
                                <span class="mobile-metric-val">
                                    <?php if (($project['contract_model'] ?? '') === 'open_po'): ?>
                                        <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700;">Open PO</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f0fdf4; color:#15803d; font-weight:700;">Annual Renewal</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Tasks Progress</span>
                                <span class="mobile-metric-val">
                                    <strong><?= e((string) $progress) ?>%</strong> <small class="muted">(<?= e((string) $completed) ?>/<?= e((string) $taskCount) ?>)</small>
                                </span>
                            </div>
                            <?php if (!empty($project['owner_name'])): ?>
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Owner</span>
                                <span class="mobile-metric-val"><?= e($project['owner_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($project['due_date'])): ?>
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Due Date</span>
                                <span class="mobile-metric-val"><?= e(date('d M Y', strtotime($project['due_date']))) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mini-progress" style="margin-top: 10px; height: 6px;">
                            <span style="width: <?= e((string) $progress) ?>%"></span>
                        </div>
                    </div>
                    <footer class="mobile-card-footer">
                        <div class="mobile-card-actions">
                            <a class="btn-tool" href="<?= url('/projects/show?id=' . e((string) $project['id'])) ?>" style="font-weight: 600;">📂 Open Project</a>
                            <?php if ($canEditInline): ?>
                                <a class="btn-tool secondary" href="<?= url('/projects/edit?id=' . e((string) $project['id'])) ?>">✏️ Edit</a>
                                <?php if ($showArchived): ?>
                                    <form method="post" action="<?= url('/projects/unarchive') ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                        <button type="submit" class="btn-tool secondary">Unarchive</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= url('/projects/archive') ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                        <button type="submit" class="btn-tool archive">Archive</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
