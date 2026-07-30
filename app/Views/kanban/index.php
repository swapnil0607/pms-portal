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
    <div class="filter-actions">
        <button type="submit">Apply</button>
        <a class="btn ghost" href="/kanban">Reset</a>
    </div>
</form>

<section class="kanban-board" data-kanban-board>
    <?php foreach ($statuses as $status): ?>
        <div class="kanban-column">
            <header>
                <h3><?= e($labels[$status]) ?></h3>
                <span data-column-count><?= e((string) count($columns[$status])) ?></span>
            </header>

            <div class="kanban-stack" data-status="<?= e($status) ?>">
                <p class="kanban-empty" <?= $columns[$status] ? 'hidden' : '' ?>>No tasks</p>

                <?php foreach ($columns[$status] as $task): ?>
                    <article class="kanban-card" <?= \App\Core\Permissions::isViewer() ? '' : 'draggable="true"' ?> data-task-id="<?= e((string) $task['id']) ?>" data-kanban-project-id="<?= e((string) $task['project_id']) ?>">
                        <div class="card-top">
                            <span class="priority <?= e($task['priority']) ?>"><?= e($task['priority']) ?></span>
                            <small><?= e($task['due_date'] ?: 'No due date') ?></small>
                        </div>
                        <h4><?= e($task['title']) ?></h4>
                        <p><?= e($task['project_name']) ?></p>
                        <footer>
                            <span><?= e($task['assignee'] ?: 'Unassigned') ?></span>
                            <a href="/tasks/show?id=<?= e((string) $task['id']) ?>">Open</a>
                        </footer>

                        <?php if (\App\Core\Permissions::isViewer()): ?>
                            <span class="badge"><?= e($labels[$task['status']]) ?></span>
                        <?php else: ?>
                        <form method="post" action="/tasks/status" class="kanban-move">
                            <?= csrf_field() ?>
                            <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
                            <input type="hidden" name="project_id" value="<?= e((string) $task['project_id']) ?>">
                            <input type="hidden" name="return_to" value="kanban">
                            <input type="hidden" name="filter_project_id" value="<?= e((string) ($selectedProject ?? '')) ?>">
                            <input type="hidden" name="filter_assignee_id" value="<?= e((string) ($selectedAssignee ?? '')) ?>">
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
            </div>
        </div>
    <?php endforeach; ?>
</section>
