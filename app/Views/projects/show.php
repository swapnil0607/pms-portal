<?php
$canWrite = $canWrite ?? \App\Core\Permissions::canWrite();
$canManageProject = $canManageProject ?? (\App\Core\Permissions::canManageProjectActions() && $canWrite);

$phaseMap = [];
foreach ($phases as $phase) {
    $phaseMap[(int) $phase['id']] = $phase;
}

$listMap = [];
foreach ($taskLists as $list) {
    $listMap[(int) $list['id']] = $list;
}

$grouped = [];

// 1. Initialize phases in their exact sort_order
foreach ($phases as $phase) {
    $phaseId = (int) $phase['id'];
    $grouped[$phaseId] = ['id' => $phaseId, 'name' => $phase['name'], 'lists' => []];
}

// 2. Initialize task lists under phases in their exact sort_order
foreach ($taskLists as $list) {
    $phaseId = $list['phase_id'] ? (int) $list['phase_id'] : 0;
    $listId = (int) $list['id'];
    if (!isset($grouped[$phaseId])) {
        $grouped[$phaseId] = ['id' => $phaseId, 'name' => $phaseMap[$phaseId]['name'] ?? 'No Phase', 'lists' => []];
    }
    $grouped[$phaseId]['lists'][$listId] = ['id' => $listId, 'phase_id' => $phaseId, 'name' => $list['name'], 'tasks' => []];
}

// 3. Assign tasks to their respective phase & task list
foreach ($tasks as $task) {
    $listId = $task['task_list_id'] ? (int) $task['task_list_id'] : 0;
    $phaseId = 0;
    if ($listId && isset($listMap[$listId])) {
        $phaseId = $listMap[$listId]['phase_id'] ? (int) $listMap[$listId]['phase_id'] : 0;
    } elseif ($task['phase_id']) {
        $phaseId = (int) $task['phase_id'];
    }

    if (!isset($grouped[$phaseId])) {
        $grouped[$phaseId] = ['id' => $phaseId, 'name' => $phaseMap[$phaseId]['name'] ?? 'No Phase', 'lists' => []];
    }
    if (!isset($grouped[$phaseId]['lists'][$listId])) {
        $grouped[$phaseId]['lists'][$listId] = ['id' => $listId, 'phase_id' => $phaseId, 'name' => $listMap[$listId]['name'] ?? 'General', 'tasks' => []];
    }
    $grouped[$phaseId]['lists'][$listId]['tasks'][] = $task;
}

// 4. Remove empty "No Phase" group if it has no lists and no tasks
if (isset($grouped[0]) && empty($grouped[0]['lists'])) {
    unset($grouped[0]);
}

if (($groupBy ?? 'task_list') === 'phase') {
    foreach ($grouped as &$phase) {
        $phaseTasks = [];
        foreach ($phase['lists'] as $list) {
            foreach ($list['tasks'] as $task) {
                $phaseTasks[] = $task;
            }
        }
        $phase['lists'] = [[
            'id' => 0,
            'phase_id' => $phase['id'],
            'name' => 'Tasks',
            'tasks' => $phaseTasks,
        ]];
    }
    unset($phase);
}

$tabUrl = fn (string $tab): string => '/projects/show?' . http_build_query(['id' => $project['id'], 'tab' => $tab]);
$taskUrl = '/projects/show?' . http_build_query([
    'id' => $project['id'],
    'tab' => 'tasks',
    'status' => $statusFilter,
    'group_by' => $groupBy,
]);
?>

<section class="project-workspace" data-project-id="<?= e((string) $project['id']) ?>">
    <header class="project-workspace-top">
        <div>
            <a class="back-link" href="/projects">Projects</a>
            <h2><?= e(($project['code'] ? $project['code'] . ' ' : '') . $project['name']) ?></h2>
        </div>
        <span class="project-owner">Owner: <?= e($project['owner_name']) ?></span>
    </header>

    <div class="project-workspace-tabs">
        <a class="<?= $activeTab === 'tasks' ? 'active' : '' ?>" href="<?= e($tabUrl('tasks')) ?>">Tasks</a>
        <a class="<?= $activeTab === 'details' ? 'active' : '' ?>" href="<?= e($tabUrl('details')) ?>">Project Details</a>
        <a class="<?= $activeTab === 'reports' ? 'active' : '' ?>" href="<?= e($tabUrl('reports')) ?>">Reports</a>
        <a class="<?= $activeTab === 'time-logs' ? 'active' : '' ?>" href="<?= e($tabUrl('time-logs')) ?>">Time Logs</a>
    </div>

    <?php if ($activeTab === 'tasks'): ?>
    <div class="project-task-toolbar">
        <div class="toolbar-left">
            <form method="get" action="/projects/show" class="project-filter-form">
                <input type="hidden" name="id" value="<?= e((string) $project['id']) ?>">
                <input type="hidden" name="tab" value="tasks">
                <label>
                    <span>Status</span>
                    <select name="status" onchange="this.form.submit()">
                        <option value="all_open" <?= $statusFilter === 'all_open' ? 'selected' : '' ?>>All Open</option>
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Tasks</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($labels[$status] ?? $status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Group By</span>
                    <select name="group_by" onchange="this.form.submit()">
                        <option value="task_list" <?= $groupBy === 'task_list' ? 'selected' : '' ?>>Task List</option>
                        <option value="phase" <?= $groupBy === 'phase' ? 'selected' : '' ?>>Phase</option>
                    </select>
                </label>
            </form>
            <div class="toolbar-collapse-group">
                <button type="button" class="btn-tool-sm" id="btnCollapseTasks" title="Show Phase & Task Lists only (Collapse individual tasks)">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    <span>Collapse Tasks</span>
                </button>
                <button type="button" class="btn-tool-sm" id="btnCollapsePhases" title="Collapse all to Phases only">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                    <span>Collapse Phases</span>
                </button>
                <button type="button" class="btn-tool-sm" id="btnExpandAll" title="Expand all phases, task lists, and tasks">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                    <span>Expand All</span>
                </button>
            </div>
        </div>
        <?php if ($canWrite): ?>
        <div class="selection-toolbar" data-selection-toolbar hidden>
            <button type="button" class="icon-button" data-clear-selection aria-label="Clear selection">x</button>
            <strong><span data-selected-count>0</span></strong>
            <select form="taskBulkForm" name="target_task_list_id" aria-label="Move selected tasks to">
                <option value="">Move to General</option>
                <?php foreach ($taskLists as $list): ?>
                    <option value="<?= e(($list['phase_id'] ?: '') . ':' . $list['id']) ?>"><?= e(($list['phase_name'] ? $list['phase_name'] . ' - ' : '') . $list['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" form="taskBulkForm" name="bulk_action" value="move">Move</button>
            <button type="submit" form="taskBulkForm" name="bulk_action" value="trash" class="danger-button">Trash</button>
        </div>
        <details class="add-menu" id="projectAddMenu">
            <summary>+ Add Item</summary>
            <div class="add-menu-panel">
                <div class="add-menu-header">
                    <h3>Add to Project</h3>
                    <button type="button" class="add-menu-close" onclick="this.closest('details').removeAttribute('open')" aria-label="Close">&times;</button>
                </div>
                <div class="add-menu-tabs">
                    <button type="button" class="add-menu-tab active" data-add-tab="task">Task</button>
                    <button type="button" class="add-menu-tab" data-add-tab="phase">Phase</button>
                    <button type="button" class="add-menu-tab" data-add-tab="list">Task List</button>
                    <button type="button" class="add-menu-tab" data-add-tab="template">Template</button>
                </div>

                <div class="add-tab-pane active" data-add-pane="task">
                    <form method="post" action="/tasks/create" class="mini-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                        <label>
                            <span>Phase</span>
                            <select name="phase_id">
                                <option value="">No Phase</option>
                                <?php foreach ($phases as $phase): ?>
                                    <option value="<?= e((string) $phase['id']) ?>"><?= e($phase['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Task List</span>
                            <select name="task_list_id">
                                <option value="">General</option>
                                <?php foreach ($taskLists as $list): ?>
                                    <option value="<?= e((string) $list['id']) ?>"><?= e(($list['phase_name'] ? $list['phase_name'] . ' - ' : '') . $list['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Task ID (Optional)</span>
                            <input type="text" name="task_code" placeholder="e.g. T-101">
                        </label>
                        <label>
                            <span>Task Title *</span>
                            <input type="text" name="title" placeholder="Task name" required>
                        </label>
                        <div class="assignee-picker compact" data-assignee-picker>
                            <label><span>Assignees</span></label>
                            <div class="assignee-chips" data-assignee-chips>
                                <span class="assignee-empty" data-assignee-empty>No people added yet.</span>
                            </div>
                            <div class="assignee-picker-controls">
                                <select data-assignee-picker-select aria-label="Person to add">
                                    <option value="">Select a person to add</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mini-form-row">
                            <label>
                                <span>Start Date</span>
                                <input type="date" name="start_date">
                            </label>
                            <label>
                                <span>Due Date</span>
                                <input type="date" name="due_date">
                            </label>
                        </div>
                        <div class="mini-form-row">
                            <label>
                                <span>Est. Hours</span>
                                <input type="number" name="estimated_hours" min="0" step="0.25" placeholder="Hours">
                            </label>
                            <label>
                                <span>Priority</span>
                                <select name="priority">
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                    <option value="low">Low</option>
                                </select>
                            </label>
                        </div>
                        <div class="workload-preview" data-workload hidden></div>
                        <label>
                            <span>Description</span>
                            <textarea name="description" rows="2" placeholder="Short description"></textarea>
                        </label>
                        <button type="submit" class="btn primary-btn full-btn">Save Task</button>
                    </form>
                </div>

                <div class="add-tab-pane" data-add-pane="phase">
                    <form method="post" action="/projects/phases/create" class="mini-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                        <label>
                            <span>Phase Name *</span>
                            <input type="text" name="name" placeholder="Phase name" required>
                        </label>
                        <button type="submit" class="btn primary-btn full-btn">Save Phase</button>
                    </form>
                </div>

                <div class="add-tab-pane" data-add-pane="list">
                    <form method="post" action="/projects/task-lists/create" class="mini-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                        <label>
                            <span>Phase</span>
                            <select name="phase_id">
                                <option value="">No Phase</option>
                                <?php foreach ($phases as $phase): ?>
                                    <option value="<?= e((string) $phase['id']) ?>"><?= e($phase['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Task List Name *</span>
                            <input type="text" name="name" placeholder="Task list name" required>
                        </label>
                        <button type="submit" class="btn primary-btn full-btn">Save Task List</button>
                    </form>
                </div>

                <div class="add-tab-pane" data-add-pane="template">
                    <form method="post" action="/projects/task-list-templates/apply" class="mini-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                        <label>
                            <span>Phase</span>
                            <select name="phase_id">
                                <option value="">No Phase</option>
                                <?php foreach ($phases as $phase): ?>
                                    <option value="<?= e((string) $phase['id']) ?>"><?= e($phase['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Template *</span>
                            <select name="template_id" required>
                                <option value="">Select template</option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?= e((string) $template['id']) ?>"><?= e($template['name']) ?> (<?= e((string) $template['task_count']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit" class="btn primary-btn full-btn">Apply Template</button>
                        <a class="table-link" href="/settings" style="margin-top: 6px; display: inline-block;">Manage Templates &rarr;</a>
                    </form>
                </div>
            </div>
        </details>
        <?php endif; ?>
    </div>

    <?php if (!$grouped): ?>
        <div class="empty-state slim">
            <h3>No tasks yet</h3>
            <p>Use Add Item to create the first phase, task list, or task.</p>
        </div>
    <?php else: ?>
        <form id="taskBulkForm" method="post" action="/tasks/bulk">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
        </form>

        <!-- Desktop Hierarchy Table (>= 768px) -->
        <div class="desktop-only-table">
            <div class="hierarchy-table-wrap">
                <table class="data-table hierarchy-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>ID</th>
                            <th>Task Name</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>Due Date</th>
                            <th>Work Hours</th>
                            <th>Priority</th>
                            <th>Completion</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($grouped as $phase): ?>
                        <tr class="phase-row hierarchy-row" draggable="<?= $canWrite && (int) $phase['id'] > 0 ? 'true' : 'false' ?>" data-row-type="phase" data-phase-id="<?= e((string) $phase['id']) ?>">
                            <td colspan="10">
                                <span class="row-tools">
                                    <?php if ($canWrite && (int) $phase['id'] > 0): ?>
                                        <span class="drag-handle" title="Drag to reorder Phase" aria-label="Drag to reorder Phase">⠿</span>
                                    <?php endif; ?>
                                    <button type="button" class="collapse-toggle" data-collapse="phase" data-phase-id="<?= e((string) $phase['id']) ?>" aria-label="Collapse phase"></button>
                                    <?php if ($canWrite): ?>
                                    <details class="row-menu">
                                        <summary aria-label="Phase actions">...</summary>
                                        <div class="row-menu-panel">
                                            <?php if ((int) $phase['id'] > 0): ?>
                                            <form method="post" action="<?= url('/projects/task-lists/create') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                <input type="hidden" name="phase_id" value="<?= e((string) $phase['id']) ?>">
                                                <input type="text" name="name" placeholder="New task list" required>
                                                <button type="submit">Add Task List</button>
                                            </form>
                                            <form method="post" action="<?= url('/projects/phases/delete') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                <input type="hidden" name="phase_id" value="<?= e((string) $phase['id']) ?>">
                                                <button type="submit" class="danger-link">Trash Phase</button>
                                            </form>
                                            <?php else: ?>
                                                <span class="muted">No phase actions available.</span>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                    <?php endif; ?>
                                </span>
                                <?php if ($canWrite && (int) $phase['id'] > 0): ?>
                                    <span class="row-title editable-cell" data-edit-type="phase-name" data-project-id="<?= e((string) $project['id']) ?>" data-phase-id="<?= e((string) $phase['id']) ?>"><?= e($phase['name']) ?></span>
                                <?php else: ?>
                                    <span class="row-title"><?= e($phase['name']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!$phase['lists']): ?>
                            <tr data-parent-phase="<?= e((string) $phase['id']) ?>">
                                <td></td>
                                <td colspan="9" class="muted">No task lists in this phase yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($phase['lists'] as $list): ?>
                            <tr class="task-list-row hierarchy-row" draggable="<?= $canWrite && (int) $list['id'] > 0 ? 'true' : 'false' ?>" data-row-type="task-list" data-parent-phase="<?= e((string) $phase['id']) ?>" data-phase-id="<?= e((string) $phase['id']) ?>" data-task-list-id="<?= e((string) $list['id']) ?>">
                                <td></td>
                                <td colspan="9">
                                    <span class="row-tools">
                                        <?php if ($canWrite && (int) $list['id'] > 0): ?>
                                            <span class="drag-handle" title="Drag to reorder Task List or move across Phases" aria-label="Drag to reorder Task List">⠿</span>
                                        <?php endif; ?>
                                        <button type="button" class="collapse-toggle" data-collapse="task-list" data-task-list-id="<?= e((string) $list['id']) ?>" aria-label="Collapse task list"></button>
                                        <?php if ($canWrite): ?>
                                        <details class="row-menu">
                                            <summary aria-label="Task list actions">...</summary>
                                            <div class="row-menu-panel">
                                                <?php if ((int) $list['id'] > 0): ?>
                                                <form method="post" action="<?= url('/tasks/create') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                    <input type="hidden" name="phase_id" value="<?= e((string) $phase['id']) ?>">
                                                    <input type="hidden" name="task_list_id" value="<?= e((string) $list['id']) ?>">
                                                    <input type="text" name="title" placeholder="New task" required>
                                                    <button type="submit">Add Task</button>
                                                </form>
                                                <form method="post" action="<?= url('/projects/task-lists/delete') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                    <input type="hidden" name="task_list_id" value="<?= e((string) $list['id']) ?>">
                                                    <button type="submit" class="danger-link">Trash Task List</button>
                                                </form>
                                                <?php else: ?>
                                                    <span class="muted">General list cannot be moved or trashed.</span>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                        <?php endif; ?>
                                    </span>
                                    <span class="row-title">
                                        <?php if ($canWrite && (int) $list['id'] > 0): ?>
                                            <span class="editable-cell" data-edit-type="task-list-name" data-project-id="<?= e((string) $project['id']) ?>" data-task-list-id="<?= e((string) $list['id']) ?>"><?= e($list['name']) ?></span>
                                        <?php else: ?>
                                            <span><?= e($list['name']) ?></span>
                                        <?php endif; ?>
                                        <span>(<?= e((string) count($list['tasks'])) ?>)</span>
                                    </span>
                                </td>
                            </tr>
                            <?php if (!$list['tasks']): ?>
                                <tr data-parent-phase="<?= e((string) $phase['id']) ?>" data-parent-task-list="<?= e((string) $list['id']) ?>">
                                    <td></td>
                                    <td></td>
                                    <td colspan="8" class="muted">No tasks added here yet.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($list['tasks'] as $task): ?>
                                <tr class="task-data-row hierarchy-row" draggable="<?= $canWrite ? 'true' : 'false' ?>" data-row-type="task" data-parent-phase="<?= e((string) $phase['id']) ?>" data-parent-task-list="<?= e((string) $list['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>">
                                    <td>
                                        <?php if ($canWrite): ?>
                                            <input form="taskBulkForm" type="checkbox" name="task_ids[]" value="<?= e((string) $task['id']) ?>" data-task-select aria-label="Select task">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($task['task_code'] ?: 'T-' . $task['id']) ?></td>
                                    <td>
                                        <span class="row-tools">
                                            <?php if ($canWrite): ?>
                                                <span class="drag-handle" title="Drag to reorder Task or move to another Task List" aria-label="Drag to reorder Task">⠿</span>
                                            <?php endif; ?>
                                            <details class="row-menu">
                                                <summary aria-label="Task actions">...</summary>
                                                <div class="row-menu-panel">
                                                    <a href="<?= url('/tasks/show?id=' . e((string) $task['id'])) ?>">View Details</a>
                                                    <a target="_blank" rel="noopener" href="<?= url('/tasks/show?id=' . e((string) $task['id'])) ?>">View Details in New Tab</a>
                                                    <button type="button" data-copy-link="<?= url('/tasks/show?id=' . e((string) $task['id'])) ?>">Copy Link</button>
                                                    <?php if ($canWrite): ?>
                                                    <form method="post" action="<?= url('/tasks/bulk') ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                        <input type="hidden" name="task_ids[]" value="<?= e((string) $task['id']) ?>">
                                                        <button type="submit" name="bulk_action" value="trash" class="danger-link">Trash</button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                        </span>
                                        <?php if ($canWrite): ?>
                                            <span class="editable-cell task-name-cell" data-edit-type="task-field" data-field="title" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>"><?= e($task['title']) ?></span>
                                        <?php else: ?>
                                            <span class="task-name-cell"><?= e($task['title']) ?></span>
                                        <?php endif; ?>
                                        <a class="open-task-link" href="<?= url('/tasks/show?id=' . e((string) $task['id'])) ?>">Open</a>
                                    </td>
                                    <td><?= e($task['assignee'] ?: 'Unassigned') ?></td>
                                    <td><span class="badge"><?= e($labels[$task['status']] ?? $task['status']) ?></span></td>
                                    <td>
                                        <?php if ($canWrite): ?>
                                            <span class="editable-cell" data-edit-type="task-field" data-field="start_date" data-input-type="date" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['start_date'] ?: '') ?>"><?= e($task['start_date'] ?: '-') ?></span>
                                        <?php else: ?>
                                            <span><?= e($task['start_date'] ?: '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($canWrite): ?>
                                            <span class="editable-cell" data-edit-type="task-field" data-field="due_date" data-input-type="date" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['due_date'] ?: '') ?>"><?= e($task['due_date'] ?: '-') ?></span>
                                        <?php else: ?>
                                            <span><?= e($task['due_date'] ?: '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($canWrite): ?>
                                            <span class="editable-cell" data-edit-type="task-field" data-field="estimated_hours" data-input-type="number" data-step="0.25" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['estimated_hours'] !== null ? (string) $task['estimated_hours'] : '') ?>"><?= e($task['estimated_hours'] !== null ? (string) $task['estimated_hours'] : '-') ?></span>
                                        <?php else: ?>
                                            <span><?= e($task['estimated_hours'] !== null ? (string) $task['estimated_hours'] : '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($canWrite): ?>
                                            <span class="priority <?= e($task['priority']) ?> editable-cell" data-edit-type="task-field" data-field="priority" data-input-type="priority" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['priority']) ?>"><?= e($task['priority']) ?></span>
                                        <?php else: ?>
                                            <span class="priority <?= e($task['priority']) ?>"><?= e($task['priority']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress table-progress">
                                            <span style="width: <?= e((string) $task['progress']) ?>%"></span>
                                        </div>
                                        <small><?= e((string) $task['progress']) ?>%</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile-Only Task Hierarchy Cards (< 768px) -->
        <div class="mobile-only-cards mobile-project-tasks">
            <?php foreach ($grouped as $phase): ?>
                <?php 
                    $totalPhaseTasks = 0;
                    foreach ($phase['lists'] as $l) {
                        $totalPhaseTasks += count($l['tasks']);
                    }
                ?>
                <div class="mobile-phase-card" data-phase-id="<?= e((string) $phase['id']) ?>">
                    <div class="mobile-phase-banner" data-mobile-collapse="phase" role="button" tabindex="0">
                        <div class="mobile-phase-info">
                            <span class="phase-icon">📁</span>
                            <span class="phase-name"><?= e($phase['name']) ?></span>
                        </div>
                        <div class="mobile-phase-right">
                            <span class="phase-count-badge"><?= $totalPhaseTasks ?> <?= $totalPhaseTasks === 1 ? 'task' : 'tasks' ?></span>
                            <span class="collapse-chevron" aria-hidden="true">▾</span>
                        </div>
                    </div>

                    <div class="mobile-phase-body">
                        <?php if (empty($phase['lists'])): ?>
                            <div class="mobile-empty-hint">No task lists in this phase.</div>
                        <?php endif; ?>

                        <?php foreach ($phase['lists'] as $list): ?>
                            <div class="mobile-tasklist-section" data-list-id="<?= e((string) $list['id']) ?>">
                                <div class="mobile-tasklist-banner" data-mobile-collapse="task-list" role="button" tabindex="0">
                                    <span class="list-icon">📋</span>
                                    <span class="list-title"><?= e($list['name']) ?></span>
                                    <span class="list-counter"><?= count($list['tasks']) ?></span>
                                    <span class="collapse-chevron" aria-hidden="true">▾</span>
                                </div>

                                <div class="mobile-task-cards-list">
                                    <?php if (empty($list['tasks'])): ?>
                                        <div class="mobile-empty-hint">No tasks added here yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($list['tasks'] as $task): ?>
                                            <div class="mobile-task-item-card" data-task-id="<?= e((string) $task['id']) ?>">
                                                <div class="mobile-task-header-row">
                                                    <div class="mobile-task-code-wrap">
                                                        <?php if ($canWrite): ?>
                                                            <input form="taskBulkForm" type="checkbox" name="task_ids[]" value="<?= e((string) $task['id']) ?>" data-task-select class="mobile-task-checkbox">
                                                        <?php endif; ?>
                                                        <span class="mobile-task-code"><?= e($task['task_code'] ?: 'T-' . $task['id']) ?></span>
                                                    </div>
                                                    <div class="mobile-task-status-tags">
                                                        <?php if (!empty($task['priority'])): ?>
                                                            <span class="mobile-priority-tag <?= e($task['priority']) ?>"><?= e(ucfirst($task['priority'])) ?></span>
                                                        <?php endif; ?>
                                                        <span class="badge status-pill <?= e($task['status']) ?>"><?= e($labels[$task['status']] ?? $task['status']) ?></span>
                                                    </div>
                                                </div>

                                                <a href="<?= url('/tasks/show?id=' . e((string) $task['id'])) ?>" class="mobile-task-title-link">
                                                    <?= e($task['title']) ?>
                                                </a>

                                                <div class="mobile-task-meta-row">
                                                    <div class="mobile-meta-pill">
                                                        <span>👤</span> <?= e($task['assignee'] ?: 'Unassigned') ?>
                                                    </div>
                                                    <?php if (!empty($task['due_date'])): ?>
                                                        <div class="mobile-meta-pill">
                                                            <span>📅</span> <?= e(date('d M', strtotime($task['due_date']))) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($task['estimated_hours'] !== null && (float) $task['estimated_hours'] > 0): ?>
                                                        <div class="mobile-meta-pill">
                                                            <span>⏱️</span> <?= e((string) $task['estimated_hours']) ?>h
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ((int) $task['progress'] > 0): ?>
                                                    <div class="mobile-task-progress-container">
                                                        <div class="mobile-task-progress-track">
                                                            <div class="mobile-task-progress-fill" style="width: <?= e((string) $task['progress']) ?>%;"></div>
                                                        </div>
                                                        <span class="mobile-task-progress-num"><?= e((string) $task['progress']) ?>%</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php elseif ($activeTab === 'details'): ?>
        <section class="project-tab-panel">
            <div class="section-header compact">
                <div>
                    <h2>Project Details</h2>
                    <p>Dashboard fields for this project.</p>
                </div>
                <?php if ($canManageProject): ?>
                    <a class="button-link secondary" href="/projects/edit?id=<?= e((string) $project['id']) ?>">Edit Project</a>
                <?php endif; ?>
            </div>
<?php
$pfs = $projectFieldSettings ?? \App\Models\ProjectFieldSetting::getMap();
$isFieldVisible = static fn (string $key): bool => (bool) ($pfs[$key]['visible'] ?? true);
$getFieldLabel = static fn (string $key, string $default): string => !empty($pfs[$key]['label']) ? $pfs[$key]['label'] : $default;
?>
            <dl class="detail-grid">
                <div><dt>Client</dt><dd><?= e($project['client_name'] ?: ($project['project_group'] ?: '-')) ?></dd></div>
                <div><dt><?= e($getFieldLabel('status', 'Status')) ?></dt><dd><?= e($project['status']) ?></dd></div>
                <div><dt><?= e($getFieldLabel('owner_name', 'Owner')) ?></dt><dd><?= e($project['owner_name']) ?></dd></div>
                <?php if ($isFieldVisible('start_date')): ?>
                    <div><dt><?= e($getFieldLabel('start_date', 'Start Date')) ?></dt><dd><?= e($project['start_date'] ?: '-') ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('due_date')): ?>
                    <div><dt><?= e($getFieldLabel('due_date', 'End Date')) ?></dt><dd><?= e($project['due_date'] ?: '-') ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('billed_learners')): ?>
                    <div><dt><?= e($getFieldLabel('billed_learners', 'Billed Learners')) ?></dt><dd><?= e((string) ($project['billed_learners'] ?? '-')) ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('learners_on_platform')): ?>
                    <div><dt><?= e($getFieldLabel('learners_on_platform', 'Learners On Platform')) ?></dt><dd><?= e((string) ($project['learners_on_platform'] ?? '-')) ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('learners_connected')): ?>
                    <div><dt><?= e($getFieldLabel('learners_connected', 'Learners Connected')) ?></dt><dd><?= e((string) ($project['learners_connected'] ?? '-')) ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('started_with_courses')): ?>
                    <div><dt><?= e($getFieldLabel('started_with_courses', 'Started With Courses')) ?></dt><dd><?= e((string) ($project['started_with_courses'] ?? '-')) ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('total_time')): ?>
                    <div><dt><?= e($getFieldLabel('total_time', 'Total Time')) ?></dt><dd><?= e((string) ($project['total_time'] ?? '-')) ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('average_time_per_learner')): ?>
                    <div><dt><?= e($getFieldLabel('average_time_per_learner', 'Avg Time')) ?></dt><dd><?= e((string) ($project['average_time_per_learner'] ?? '-')) ?></dd></div>
                <?php endif; ?>
                <?php if ($isFieldVisible('adoption_percent')): ?>
                    <div><dt><?= e($getFieldLabel('adoption_percent', 'Adoption %')) ?></dt><dd><?= e((string) ($project['adoption_percent'] ?? '-')) ?></dd></div>
                <?php endif; ?>
                <?php if (!empty($customFields)): ?>
                    <?php foreach ($customFields as $cField): ?>
                        <?php if (isset($customValues[$cField['field_key']]) && trim((string) $customValues[$cField['field_key']]) !== ''): ?>
                            <div><dt><?= e($cField['label']) ?></dt><dd><?= e((string) $customValues[$cField['field_key']]) ?></dd></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </dl>

            <!-- Contract & Service Hours Billing Model -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; margin: 16px 0;">
                <div style="font-size:14px; font-weight:700; color:#1e293b; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                    <span>⏱️ Service Hours & Contract Model</span>
                    <a class="btn-tool ghost" href="<?= url('/reports?type=project_billing&project_group=' . urlencode($project['name'])) ?>">View Billing Reports &rarr;</a>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center;">
                    <?php if (!empty($project['is_open_po'])): ?>
                        <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-weight:700; font-size:12px;">
                            🔵 Open PO (Hourly Basis &mdash; No Budget Cap)
                        </span>
                        <span class="muted" style="font-size:13px;">All logged billable hours are invoiced periodically on actual consumption.</span>
                    <?php else: ?>
                        <?php 
                            $totalAlloc = (float) ($project['build_hours'] ?? 0) + (float) ($project['run_hours'] ?? 0);
                        ?>
                        <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-weight:700; font-size:12px;">
                            🟢 Contracted Service Hours: <?= e(number_format($totalAlloc, 2)) ?> hrs
                        </span>
                        <span style="font-size:13px;">Build Phase: <strong><?= e(number_format((float) ($project['build_hours'] ?? 0), 2)) ?></strong> hrs</span>
                        <span style="font-size:13px;">Run / Support Phase: <strong><?= e(number_format((float) ($project['run_hours'] ?? 0), 2)) ?></strong> hrs</span>
                        <a class="btn-tool ghost" href="<?= url('/client-renewals?q=' . urlencode($project['name'])) ?>">Client Renewals &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="project-tab-panel">
            <div class="section-header compact">
                <div>
                    <h2>Project Members</h2>
                    <p>Who has an explicit role on this project.</p>
                </div>
            </div>
            <div class="panel">
                <table class="data-table">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Project Role</th><?php if ($canManageProject): ?><th>Action</th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php if (!$members): ?>
                            <tr><td colspan="<?= $canManageProject ? '4' : '3' ?>" class="muted">No members added yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?= e($member['name']) ?></td>
                                <td><?= e($member['email']) ?></td>
                                <td><span class="badge"><?= e($member['project_role']) ?></span></td>
                                <?php if ($canManageProject): ?>
                                <td>
                                    <form method="post" action="<?= url('/projects/members/remove') ?>" onsubmit="return confirm('Remove this member?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                        <input type="hidden" name="user_id" value="<?= e((string) $member['user_id']) ?>">
                                        <button type="submit" class="btn tiny danger">Remove</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($canManageProject): ?>
                <form method="post" action="/projects/members" class="mini-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                    <strong>Add Member</strong>
                    <select name="user_id" required>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="project_role">
                        <option value="member">Member</option>
                        <option value="manager">Manager</option>
                        <option value="viewer">Viewer</option>
                        <option value="owner">Owner</option>
                    </select>
                    <button type="submit">Add Member</button>
                </form>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($activeTab === 'reports'): ?>
        <section class="project-tab-panel">
            <div class="section-header compact">
                <div>
                    <h2>Reports</h2>
                    <p>Project-specific work log report.</p>
                </div>
                <a class="button-link secondary" href="/reports/export?<?= e(http_build_query([
                    'type' => 'detailed',
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'project_group' => $project['project_group'] ?: $project['name'],
                ])) ?>">Export Excel</a>
            </div>
            <form method="get" action="/projects/show" class="filter-bar report-filter">
                <input type="hidden" name="id" value="<?= e((string) $project['id']) ?>">
                <input type="hidden" name="tab" value="reports">
                <label>From <input type="date" name="from_date" value="<?= e($fromDate) ?>"></label>
                <label>To <input type="date" name="to_date" value="<?= e($toDate) ?>"></label>
                <button type="submit">Apply</button>
            </form>
            <div class="wide-table-wrap">
                <table class="data-table report-table">
                    <thead>
                        <tr>
                            <th>Date</th><th>Client</th><th>Phase</th><th>Module</th><th>Category</th><th>Notes</th><th>Log Hours</th><th>Hours</th><th>Billing</th><th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projectReportRows as $row): ?>
                        <tr>
                            <td><?= e($row['log_date']) ?></td>
                            <td><?= e($row['project_group']) ?></td>
                            <td><?= e($row['phase']) ?></td>
                            <td><?= e($row['module_name']) ?></td>
                            <td><?= e($row['report_task_issue'] ?? $row['task_category']) ?></td>
                            <td><?= e($row['notes']) ?></td>
                            <td><?= e(sprintf('%02d:%02d', intdiv((int) round(((float) $row['hours']) * 60), 60), ((int) round(((float) $row['hours']) * 60)) % 60)) ?></td>
                            <td><?= e((string) $row['hours']) ?></td>
                            <td><?= e($row['billing_type']) ?></td>
                            <td><?= e($row['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($activeTab === 'time-logs'): ?>
        <section class="project-tab-panel">
            <div class="section-header compact">
                <div>
                    <h2>Time Logs</h2>
                    <p>Project-specific logged hours by day.</p>
                </div>
                <a class="button-link secondary" href="/reports/export?<?= e(http_build_query([
                    'type' => 'project',
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'project_group' => $project['project_group'] ?: $project['name'],
                ])) ?>">Export Excel</a>
            </div>
            <form method="get" action="/projects/show" class="filter-bar report-filter">
                <input type="hidden" name="id" value="<?= e((string) $project['id']) ?>">
                <input type="hidden" name="tab" value="time-logs">
                <label>From <input type="date" name="from_date" value="<?= e($fromDate) ?>"></label>
                <label>To <input type="date" name="to_date" value="<?= e($toDate) ?>"></label>
                <button type="submit">Apply</button>
            </form>
            <div class="wide-table-wrap">
                <table class="data-table timelog-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <?php foreach ($dates as $date): ?>
                                <th><?= e(date('d', strtotime($date))) ?><br><small><?= e(date('D', strtotime($date))) ?></small></th>
                            <?php endforeach; ?>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projectTimeRows as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <?php foreach ($dates as $date): ?>
                                <td><?= $row['days'][$date] > 0 ? e((string) $row['days'][$date]) : '' ?></td>
                            <?php endforeach; ?>
                            <td><strong><?= e((string) $row['total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</section>
