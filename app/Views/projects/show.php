<?php
$phaseMap = [];
foreach ($phases as $phase) {
    $phaseMap[(int) $phase['id']] = $phase;
}

$listMap = [];
foreach ($taskLists as $list) {
    $listMap[(int) $list['id']] = $list;
}

$grouped = [];
foreach ($tasks as $task) {
    $phaseId = $task['phase_id'] ? (int) $task['phase_id'] : 0;
    $listId = $task['task_list_id'] ? (int) $task['task_list_id'] : 0;
    $grouped[$phaseId] ??= ['id' => $phaseId, 'name' => $phaseMap[$phaseId]['name'] ?? 'No Phase', 'lists' => []];
    $grouped[$phaseId]['lists'][$listId] ??= ['id' => $listId, 'phase_id' => $phaseId, 'name' => $listMap[$listId]['name'] ?? 'General', 'tasks' => []];
    $grouped[$phaseId]['lists'][$listId]['tasks'][] = $task;
}

foreach ($phases as $phase) {
    $phaseId = (int) $phase['id'];
    $grouped[$phaseId] ??= ['id' => $phaseId, 'name' => $phase['name'], 'lists' => []];
}

foreach ($taskLists as $list) {
    $phaseId = $list['phase_id'] ? (int) $list['phase_id'] : 0;
    $listId = (int) $list['id'];
    $grouped[$phaseId] ??= ['id' => $phaseId, 'name' => $phaseMap[$phaseId]['name'] ?? 'No Phase', 'lists' => []];
    $grouped[$phaseId]['lists'][$listId] ??= ['id' => $listId, 'phase_id' => $phaseId, 'name' => $list['name'], 'tasks' => []];
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
        </div>
        <?php if (!\App\Core\Permissions::isViewer()): ?>
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
        <details class="add-menu">
            <summary>Add Task</summary>
            <div class="add-menu-panel">
                <form method="post" action="/projects/phases/create" class="mini-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                    <strong>Add Phase</strong>
                    <input type="text" name="name" placeholder="Phase name" required>
                    <button type="submit">Save Phase</button>
                </form>

                <form method="post" action="/projects/task-lists/create" class="mini-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                    <strong>Add Task List</strong>
                    <select name="phase_id">
                        <option value="">No Phase</option>
                        <?php foreach ($phases as $phase): ?>
                            <option value="<?= e((string) $phase['id']) ?>"><?= e($phase['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="name" placeholder="Task list name" required>
                    <button type="submit">Save Task List</button>
                </form>

                <form method="post" action="/projects/task-list-templates/apply" class="mini-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                    <strong>Use Template</strong>
                    <select name="phase_id">
                        <option value="">No Phase</option>
                        <?php foreach ($phases as $phase): ?>
                            <option value="<?= e((string) $phase['id']) ?>"><?= e($phase['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="template_id" required>
                        <option value="">Select template</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?= e((string) $template['id']) ?>"><?= e($template['name']) ?> (<?= e((string) $template['task_count']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Apply Template</button>
                    <a class="table-link" href="/settings">Manage Templates</a>
                </form>

                <form method="post" action="/tasks/create" class="mini-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                    <strong>Add Task</strong>
                    <select name="phase_id">
                        <option value="">No Phase</option>
                        <?php foreach ($phases as $phase): ?>
                            <option value="<?= e((string) $phase['id']) ?>"><?= e($phase['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="task_list_id">
                        <option value="">General</option>
                        <?php foreach ($taskLists as $list): ?>
                            <option value="<?= e((string) $list['id']) ?>"><?= e(($list['phase_name'] ? $list['phase_name'] . ' - ' : '') . $list['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="task_code" placeholder="Task ID">
                    <input type="text" name="title" placeholder="Task name" required>
                    <select name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= e((string) $user['id']) ?>"><?= e($user['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mini-form-row">
                        <input type="date" name="start_date">
                        <input type="date" name="due_date">
                    </div>
                    <div class="mini-form-row">
                        <input type="number" name="estimated_hours" min="0" step="0.25" placeholder="Hours">
                        <select name="priority">
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="workload-preview" data-workload hidden></div>
                    <textarea name="description" rows="2" placeholder="Short description"></textarea>
                    <button type="submit">Save Task</button>
                </form>
            </div>
        </details>
        <?php endif; ?>
    </div>

    <?php if (!$grouped): ?>
        <div class="empty-state slim">
            <h3>No tasks yet</h3>
            <p>Use Add Task to create the first phase, task list, or task.</p>
        </div>
    <?php else: ?>
        <form id="taskBulkForm" method="post" action="/tasks/bulk">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
        </form>
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
                    <tr class="phase-row hierarchy-row" draggable="<?= (int) $phase['id'] > 0 ? 'true' : 'false' ?>" data-row-type="phase" data-phase-id="<?= e((string) $phase['id']) ?>">
                        <td colspan="10">
                            <span class="row-tools">
                                <?php if ((int) $phase['id'] > 0): ?>
                                    <span class="drag-handle" aria-hidden="true">::</span>
                                <?php endif; ?>
                                <button type="button" class="collapse-toggle" data-collapse="phase" data-phase-id="<?= e((string) $phase['id']) ?>" aria-label="Collapse phase"></button>
                                <details class="row-menu">
                                    <summary aria-label="Phase actions">...</summary>
                                    <div class="row-menu-panel">
                                        <?php if ((int) $phase['id'] > 0): ?>
                                        <form method="post" action="/projects/task-lists/create">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                            <input type="hidden" name="phase_id" value="<?= e((string) $phase['id']) ?>">
                                            <input type="text" name="name" placeholder="New task list" required>
                                            <button type="submit">Add Task List</button>
                                        </form>
                                        <form method="post" action="/projects/phases/delete">
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
                            </span>
                            <?php if ((int) $phase['id'] > 0): ?>
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
                        <tr class="task-list-row hierarchy-row" draggable="<?= (int) $list['id'] > 0 ? 'true' : 'false' ?>" data-row-type="task-list" data-parent-phase="<?= e((string) $phase['id']) ?>" data-phase-id="<?= e((string) $phase['id']) ?>" data-task-list-id="<?= e((string) $list['id']) ?>">
                            <td></td>
                            <td colspan="9">
                                <span class="row-tools">
                                    <span class="drag-handle" aria-hidden="true">::</span>
                                    <button type="button" class="collapse-toggle" data-collapse="task-list" data-task-list-id="<?= e((string) $list['id']) ?>" aria-label="Collapse task list"></button>
                                    <details class="row-menu">
                                        <summary aria-label="Task list actions">...</summary>
                                        <div class="row-menu-panel">
                                            <?php if ((int) $list['id'] > 0): ?>
                                            <form method="post" action="/tasks/create">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                <input type="hidden" name="phase_id" value="<?= e((string) $phase['id']) ?>">
                                                <input type="hidden" name="task_list_id" value="<?= e((string) $list['id']) ?>">
                                                <input type="text" name="title" placeholder="New task" required>
                                                <button type="submit">Add Task</button>
                                            </form>
                                            <form method="post" action="/projects/task-lists/delete">
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
                                </span>
                                <span class="row-title">
                                    <?php if ((int) $list['id'] > 0): ?>
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
                            <tr class="task-data-row hierarchy-row" draggable="true" data-row-type="task" data-parent-phase="<?= e((string) $phase['id']) ?>" data-parent-task-list="<?= e((string) $list['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>">
                                <td><input form="taskBulkForm" type="checkbox" name="task_ids[]" value="<?= e((string) $task['id']) ?>" data-task-select aria-label="Select task"></td>
                                <td><?= e($task['task_code'] ?: 'T-' . $task['id']) ?></td>
                                <td>
                                    <span class="row-tools">
                                        <span class="drag-handle" aria-hidden="true">::</span>
                                        <details class="row-menu">
                                            <summary aria-label="Task actions">...</summary>
                                            <div class="row-menu-panel">
                                                <a href="/tasks/show?id=<?= e((string) $task['id']) ?>">View Details</a>
                                                <a target="_blank" rel="noopener" href="/tasks/show?id=<?= e((string) $task['id']) ?>">View Details in New Tab</a>
                                                <button type="button" data-copy-link="/tasks/show?id=<?= e((string) $task['id']) ?>">Copy Link</button>
                                                <form method="post" action="/tasks/bulk">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                                    <input type="hidden" name="task_ids[]" value="<?= e((string) $task['id']) ?>">
                                                    <button type="submit" name="bulk_action" value="trash" class="danger-link">Trash</button>
                                                </form>
                                            </div>
                                        </details>
                                    </span>
                                    <span class="editable-cell task-name-cell" data-edit-type="task-field" data-field="title" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>"><?= e($task['title']) ?></span>
                                    <a class="open-task-link" href="/tasks/show?id=<?= e((string) $task['id']) ?>">Open</a>
                                </td>
                                <td><?= e($task['assignee'] ?: 'Unassigned') ?></td>
                                <td><span class="badge"><?= e($labels[$task['status']] ?? $task['status']) ?></span></td>
                                <td><span class="editable-cell" data-edit-type="task-field" data-field="start_date" data-input-type="date" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['start_date'] ?: '') ?>"><?= e($task['start_date'] ?: '-') ?></span></td>
                                <td><span class="editable-cell" data-edit-type="task-field" data-field="due_date" data-input-type="date" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['due_date'] ?: '') ?>"><?= e($task['due_date'] ?: '-') ?></span></td>
                                <td><span class="editable-cell" data-edit-type="task-field" data-field="estimated_hours" data-input-type="number" data-step="0.25" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['estimated_hours'] !== null ? (string) $task['estimated_hours'] : '') ?>"><?= e($task['estimated_hours'] !== null ? (string) $task['estimated_hours'] : '-') ?></span></td>
                                <td><span class="priority <?= e($task['priority']) ?> editable-cell" data-edit-type="task-field" data-field="priority" data-input-type="priority" data-project-id="<?= e((string) $project['id']) ?>" data-task-id="<?= e((string) $task['id']) ?>" data-value="<?= e($task['priority']) ?>"><?= e($task['priority']) ?></span></td>
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
    <?php endif; ?>
    <?php elseif ($activeTab === 'details'): ?>
        <section class="project-tab-panel">
            <div class="section-header compact">
                <div>
                    <h2>Project Details</h2>
                    <p>Dashboard fields for this project.</p>
                </div>
                <a class="button-link secondary" href="/projects/edit?id=<?= e((string) $project['id']) ?>">Edit Project</a>
            </div>
            <dl class="detail-grid">
                <div><dt>Client</dt><dd><?= e($project['client_name'] ?: ($project['project_group'] ?: '-')) ?></dd></div>
                <div><dt>Status</dt><dd><?= e($project['status']) ?></dd></div>
                <div><dt>Owner</dt><dd><?= e($project['owner_name']) ?></dd></div>
                <div><dt>Start Date</dt><dd><?= e($project['start_date'] ?: '-') ?></dd></div>
                <div><dt>Due Date</dt><dd><?= e($project['due_date'] ?: '-') ?></dd></div>
                <div><dt>Billed Learners</dt><dd><?= e((string) ($project['billed_learners'] ?? '-')) ?></dd></div>
                <div><dt>Learners On Platform</dt><dd><?= e((string) ($project['learners_on_platform'] ?? '-')) ?></dd></div>
                <div><dt>Learners Connected</dt><dd><?= e((string) ($project['learners_connected'] ?? '-')) ?></dd></div>
                <div><dt>Started With Courses</dt><dd><?= e((string) ($project['started_with_courses'] ?? '-')) ?></dd></div>
                <div><dt>Total Time</dt><dd><?= e((string) ($project['total_time'] ?? '-')) ?></dd></div>
                <div><dt>Average Time</dt><dd><?= e((string) ($project['average_time_per_learner'] ?? '-')) ?></dd></div>
                <div><dt>Adoption %</dt><dd><?= e((string) ($project['adoption_percent'] ?? '-')) ?></dd></div>
                <?php foreach ($customFields as $field): ?>
                    <div><dt><?= e($field['label']) ?></dt><dd><?= e((string) ($customValues[$field['field_key']] ?? '-')) ?></dd></div>
                <?php endforeach; ?>
            </dl>
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
                                    <form method="post" action="/projects/members/remove" onsubmit="return confirm('Remove this member?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="project_id" value="<?= e((string) $project['id']) ?>">
                                        <input type="hidden" name="user_id" value="<?= e((string) $member['user_id']) ?>">
                                        <button type="submit" class="danger-link">Remove</button>
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
