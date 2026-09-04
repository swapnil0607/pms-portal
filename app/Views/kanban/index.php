<section class="section-header">
    <div>
        <h2>Kanban Board</h2>
        <p>Scan active work by status and move tasks forward quickly.</p>
    </div>
    <a class="btn" href="/projects">Projects</a>
</section>

<form method="get" action="/kanban" class="panel filter-bar">
    <label>
        Project
        <select name="project_id">
            <option value="">All Projects</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= e((string) $project['id']) ?>" <?= $selectedProject === (int) $project['id'] ? 'selected' : '' ?>>
                    <?= e($project['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Assignee
        <select name="assignee_id">
            <option value="">All Assignees</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= e((string) $user['id']) ?>" <?= $selectedAssignee === (int) $user['id'] ? 'selected' : '' ?>>
                    <?= e($user['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Completed Tasks
        <select name="completed_limit" onchange="this.form.submit()">
            <option value="25" <?= ($completedLimit ?? '25') === '25' ? 'selected' : '' ?>>Recent 25 Tasks (Fast)</option>
            <option value="50" <?= ($completedLimit ?? '25') === '50' ? 'selected' : '' ?>>Recent 50 Tasks</option>
            <option value="100" <?= ($completedLimit ?? '25') === '100' ? 'selected' : '' ?>>Recent 100 Tasks</option>
            <option value="all" <?= ($completedLimit ?? '25') === 'all' ? 'selected' : '' ?>>All Completed Tasks</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit">Apply</button>
        <a class="btn ghost" href="/kanban">Reset</a>
    </div>
</form>

<!-- Mobile Column Switcher Tabs (Visible on Mobile) -->
<div class="mobile-kanban-nav" id="mobileKanbanNav">
    <div class="mobile-kanban-tabs" role="tablist">
        <?php foreach ($statuses as $idx => $status): ?>
            <button type="button" class="mobile-kanban-tab <?= $idx === 0 ? 'active' : '' ?>" data-status-target="<?= e($status) ?>" role="tab" aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>">
                <span><?= e($labels[$status]) ?></span>
                <span class="mobile-tab-count"><?= e((string) ($counts[$status] ?? count($columns[$status]))) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<section class="kanban-board" data-kanban-board id="kanbanBoard">
    <?php foreach ($statuses as $idx => $status): ?>
        <div class="kanban-column <?= $idx === 0 ? 'mobile-column-active' : '' ?>" data-status="<?= e($status) ?>">
            <header>
                <h3><?= e($labels[$status]) ?></h3>
                <span data-column-count><?= e((string) ($counts[$status] ?? count($columns[$status]))) ?></span>
            </header>

            <div class="kanban-stack">
                <p class="kanban-empty" <?= !empty($columns[$status]) ? 'hidden' : '' ?>>No tasks</p>

                <?php foreach ($columns[$status] as $task): ?>
                    <article class="kanban-card" <?= !\App\Core\Permissions::canWrite() ? '' : 'draggable="true"' ?> data-task-id="<?= e((string) $task['id']) ?>" data-kanban-project-id="<?= e((string) $task['project_id']) ?>">
                        <div class="card-top">
                            <span class="priority <?= e($task['priority']) ?>"><?= e($task['priority']) ?></span>
                            <small><?= e($task['due_date'] ?: 'No due date') ?></small>
                        </div>
                        <h4><?= e($task['title']) ?></h4>
                        <p class="kanban-project">
                            <?= e($task['project_name']) ?>
                            <?php if (!empty($task['task_list_name'])): ?>
                                <span class="kanban-task-list" style="display: block; font-size: 11.5px; color: #64748b; margin-top: 2px;">📁 <?= e($task['task_list_name']) ?></span>
                            <?php endif; ?>
                        </p>
                        <footer>
                            <span><?= e($task['assignee'] ?: 'Unassigned') ?></span>
                            <a href="/tasks/show?id=<?= e((string) $task['id']) ?>">Open</a>
                        </footer>

                        <?php if (!\App\Core\Permissions::canWrite()): ?>
                            <span class="badge"><?= e($labels[$task['status']]) ?></span>
                        <?php else: ?>
                        <form method="post" action="/tasks/status" class="kanban-move">
                            <?= csrf_field() ?>
                            <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
                            <input type="hidden" name="project_id" value="<?= e((string) $task['project_id']) ?>">
                            <input type="hidden" name="return_to" value="kanban">
                            <input type="hidden" name="filter_project_id" value="<?= e((string) ($selectedProject ?? '')) ?>">
                            <input type="hidden" name="filter_assignee_id" value="<?= e((string) ($selectedAssignee ?? '')) ?>">
                            <input type="hidden" name="filter_completed_limit" value="<?= e((string) ($completedLimit ?? '25')) ?>">
                            <select name="status">
                                <?php foreach ($statuses as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $task['status'] === $option ? 'selected' : '' ?>>
                                        <?= e($labels[$option]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Move</button>
                        </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>

                <?php if ($status === 'completed' && ($counts['completed'] ?? 0) > count($columns['completed'])): ?>
                    <div class="kanban-load-more-card" style="margin-top: 10px; padding: 12px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; text-align: center; font-size: 12.5px; color: #475569;">
                        <div style="margin-bottom: 8px; font-weight: 600;">
                            Showing latest <strong><?= e((string) count($columns['completed'])) ?></strong> of <strong><?= e((string) $counts['completed']) ?></strong> completed tasks
                        </div>
                        <div style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                            <?php
                            $currentLoaded = count($columns['completed']);
                            $nextLimit = min($counts['completed'], $currentLoaded + 50);
                            $loadMoreQuery = array_filter([
                                'project_id' => $selectedProject,
                                'assignee_id' => $selectedAssignee,
                                'completed_limit' => $nextLimit,
                            ], fn ($v) => $v !== '' && $v !== null);
                            $showAllQuery = array_filter([
                                'project_id' => $selectedProject,
                                'assignee_id' => $selectedAssignee,
                                'completed_limit' => 'all',
                            ], fn ($v) => $v !== '' && $v !== null);
                            ?>
                            <a href="<?= url('/kanban?' . http_build_query($loadMoreQuery)) ?>" class="btn secondary" style="padding: 4px 10px; font-size: 12px; text-decoration: none; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                <span>➕ Load 50 More</span>
                            </a>
                            <a href="<?= url('/kanban?' . http_build_query($showAllQuery)) ?>" class="btn secondary" style="padding: 4px 10px; font-size: 12px; text-decoration: none; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                <span>Show All (<?= e((string) $counts['completed']) ?>)</span>
                            </a>
                        </div>
                    </div>
                <?php elseif ($status === 'completed' && ($counts['completed'] ?? 0) > 25 && ($completedLimit === 'all' || (int)$completedLimit > 25)): ?>
                    <div class="kanban-load-more-card" style="margin-top: 10px; padding: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; text-align: center; font-size: 12px; color: #64748b;">
                        Showing all <?= e((string) count($columns['completed'])) ?> completed tasks &bull;
                        <?php
                        $showLessQuery = array_filter([
                            'project_id' => $selectedProject,
                            'assignee_id' => $selectedAssignee,
                            'completed_limit' => 25,
                        ], fn ($v) => $v !== '' && $v !== null);
                        ?>
                        <a href="<?= url('/kanban?' . http_build_query($showLessQuery)) ?>" style="color: #0284c7; text-decoration: none; font-weight: 600;">Show Less (25)</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>
