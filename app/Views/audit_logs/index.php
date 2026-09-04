<section class="section-header">
    <div>
        <h2>Audit &amp; Change Logs</h2>
        <p>Complete chronological audit trail of all task, time log, project, and billing modifications across the system.</p>
    </div>
</section>

<nav class="tabs">
    <a href="/settings?tab=fields">Project Fields</a>
    <a href="/settings?tab=templates">Task List Templates</a>
    <a class="active" href="/audit-logs">Audit &amp; Change Logs</a>
</nav>

<form method="get" action="/audit-logs" class="panel filter-bar report-filter" style="margin-bottom: 20px;">
    <label>
        From Date
        <input type="date" name="from_date" value="<?= e($filters['from_date'] ?? '') ?>">
    </label>
    <label>
        To Date
        <input type="date" name="to_date" value="<?= e($filters['to_date'] ?? '') ?>">
    </label>
    <label>
        Entity Type
        <select name="entity_type">
            <option value="">All Entities</option>
            <option value="work_log" <?= ($filters['entity_type'] ?? '') === 'work_log' ? 'selected' : '' ?>>Time Logs (Work Logs)</option>
            <option value="task" <?= ($filters['entity_type'] ?? '') === 'task' ? 'selected' : '' ?>>Tasks</option>
            <option value="project" <?= ($filters['entity_type'] ?? '') === 'project' ? 'selected' : '' ?>>Projects</option>
        </select>
    </label>
    <label>
        Action
        <select name="action">
            <option value="">All Actions</option>
            <option value="created" <?= ($filters['action'] ?? '') === 'created' ? 'selected' : '' ?>>Created</option>
            <option value="updated" <?= ($filters['action'] ?? '') === 'updated' ? 'selected' : '' ?>>Updated / Edited</option>
            <option value="deleted" <?= ($filters['action'] ?? '') === 'deleted' ? 'selected' : '' ?>>Deleted / Trashed</option>
            <option value="billed" <?= ($filters['action'] ?? '') === 'billed' ? 'selected' : '' ?>>Marked Billed</option>
            <option value="unbilled" <?= ($filters['action'] ?? '') === 'unbilled' ? 'selected' : '' ?>>Marked Unbilled</option>
        </select>
    </label>
    <label>
        User
        <select name="user_id">
            <option value="">All Users</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= e((string) $u['id']) ?>" <?= ((string) ($filters['user_id'] ?? '')) === ((string) $u['id']) ? 'selected' : '' ?>>
                    <?= e($u['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Search Details
        <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Search summary or ID..." autocomplete="off">
    </label>
    <label>
        Per Page
        <select name="per_page">
            <option value="25" <?= ($filters['per_page'] ?? '') === '25' ? 'selected' : '' ?>>25 per page</option>
            <option value="50" <?= ($filters['per_page'] ?? '50') === '50' ? 'selected' : '' ?>>50 per page</option>
            <option value="100" <?= ($filters['per_page'] ?? '') === '100' ? 'selected' : '' ?>>100 per page</option>
            <option value="200" <?= ($filters['per_page'] ?? '') === '200' ? 'selected' : '' ?>>200 per page</option>
            <option value="all" <?= ($filters['per_page'] ?? '') === 'all' ? 'selected' : '' ?>>All Entries</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="btn">Apply</button>
        <a href="/audit-logs" class="btn ghost">Reset</a>
    </div>
</form>

<div class="panel" style="padding: 0; overflow: hidden; border-radius: 8px;">
    <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--line, #e2e8f0); background: #ffffff;">
        <div style="font-weight: 700; font-size: 14.5px; color: var(--text, #0f172a);">
            Change History Logs <span style="font-weight: 400; color: #64748b; font-size: 13px;">(<?= number_format($totalCount) ?> total entries)</span>
        </div>
        <div style="font-size: 12.5px; color: #64748b; font-weight: 600;">
            Page <?= $page ?> of <?= max(1, $totalPages) ?>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div style="text-align: center; padding: 48px 16px; color: #64748b;">
            <div style="font-size: 36px; margin-bottom: 10px;">📋</div>
            <h3 style="margin: 0 0 6px 0; font-size: 16px; color: #1e293b;">No audit logs found</h3>
            <p style="margin: 0; font-size: 13.5px;">No changes match your selected filters. Future edits, creations, and deletions will appear here in real-time.</p>
        </div>
    <?php else: ?>
        <!-- Desktop Table View -->
        <div class="table-wrap desktop-only" style="margin: 0;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 170px;">Date &amp; Time</th>
                        <th style="width: 160px;">User</th>
                        <th style="width: 110px;">Action</th>
                        <th style="width: 130px;">Entity</th>
                        <th>Change Details / Summary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <?php
                            $actionKey = strtolower((string) $l['action']);
                            $entityLabel = match ($l['entity_type']) {
                                'work_log' => 'Time Log',
                                'task' => 'Task',
                                'project' => 'Project',
                                default => ucfirst(str_replace('_', ' ', (string) $l['entity_type'])),
                            };
                        ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <div style="font-weight: 600; color: #0f172a;"><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></div>
                                <div style="font-size: 11px; color: #94a3b8;"><?= e($l['ip_address'] ?? 'Internal') ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #0f172a;"><?= e($l['user_name'] ?? 'System') ?></div>
                                <?php if (!empty($l['user_email'])): ?>
                                    <div style="font-size: 11px; color: #64748b;"><?= e($l['user_email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="audit-badge <?= e($actionKey) ?>">
                                    <?= e($l['action']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="audit-entity-tag">
                                    <?= e($entityLabel) ?> #<?= (int) $l['entity_id'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="audit-diff-text">
                                    <?= e($l['summary']) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards View -->
        <div class="mobile-only audit-mobile-list" style="padding: 12px;">
            <?php foreach ($logs as $l): ?>
                <?php
                    $actionKey = strtolower((string) $l['action']);
                    $entityLabel = match ($l['entity_type']) {
                        'work_log' => 'Time Log',
                        'task' => 'Task',
                        'project' => 'Project',
                        default => ucfirst(str_replace('_', ' ', (string) $l['entity_type'])),
                    };
                ?>
                <article class="audit-mobile-card">
                    <div class="audit-mobile-card-header">
                        <span class="audit-badge <?= e($actionKey) ?>"><?= e($l['action']) ?></span>
                        <span class="audit-entity-tag"><?= e($entityLabel) ?> #<?= (int) $l['entity_id'] ?></span>
                    </div>
                    <div class="audit-diff-text" style="margin-bottom: 10px;">
                        <?= e($l['summary']) ?>
                    </div>
                    <div class="audit-mobile-meta" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 8px;">
                        <span style="font-weight: 600; color: #334155;">👤 <?= e($l['user_name'] ?? 'System') ?></span>
                        <span>🕒 <?= date('d M Y, H:i', strtotime($l['created_at'])) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="report-pagination-wrap" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:12px 18px; background:#f8fafc; border-top:1px solid var(--line, #e2e8f0);">
            <div style="font-size:13px; color:#475569;">
                Showing <strong><?= min($totalCount, ($page - 1) * $perPage + 1) ?></strong> to <strong><?= min($totalCount, $page * $perPage) ?></strong> of <strong><?= number_format($totalCount) ?></strong> entries
            </div>
            <div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
                <?php
                    $pageUrl = fn (int $p): string => '/audit-logs?' . http_build_query(array_merge($filters, ['page' => $p]));
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?= $pageUrl(1) ?>" class="btn-tool ghost" title="First Page">&laquo; First</a>
                    <a href="<?= $pageUrl($page - 1) ?>" class="btn-tool ghost" title="Previous Page">&lsaquo; Prev</a>
                <?php endif; ?>

                <?php
                    $startP = max(1, $page - 2);
                    $endP = min($totalPages, $page + 2);
                    for ($p = $startP; $p <= $endP; $p++) {
                        $isCurrent = $p === (int) $page;
                        if ($isCurrent) {
                            echo '<span class="btn-tool" style="background:var(--primary, #007a70); color:#fff; font-weight:700; min-width:32px; text-align:center;">' . $p . '</span>';
                        } else {
                            echo '<a href="' . $pageUrl($p) . '" class="btn-tool ghost" style="min-width:32px; text-align:center;">' . $p . '</a>';
                        }
                    }
                ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= $pageUrl($page + 1) ?>" class="btn-tool ghost" title="Next Page">Next &rsaquo;</a>
                    <a href="<?= $pageUrl($totalPages) ?>" class="btn-tool ghost" title="Last Page">Last &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
