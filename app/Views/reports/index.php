<section class="section-header">
    <div>
        <h2>Reports</h2>
        <p>Detailed logs and summarized time reports.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn ghost" onclick="history.back()">Back</button>
        <form method="get" action="/reports/export" class="export-form">
            <input type="hidden" name="type" value="<?= e($reportType) ?>">
            <input type="hidden" name="from_date" value="<?= e($filters['from_date'] ?? '') ?>">
            <input type="hidden" name="to_date" value="<?= e($filters['to_date'] ?? '') ?>">
            <input type="hidden" name="project_group" value="<?= e($filters['project_group'] ?? '') ?>">
            <input type="hidden" name="user_id" value="<?= e($filters['user_id'] ?? '') ?>">
            <select name="group_by" aria-label="Group export by">
                <option value="user">Group by User</option>
                <option value="client">Group by Client</option>
                <option value="phase">Group by Phase</option>
                <option value="tasklist">Group by Task List</option>
            </select>
            <button type="submit" class="btn ghost">Export Excel</button>
        </form>
        <a class="btn" href="/work-logs">Add Daily Log</a>
    </div>
</section>

<nav class="tabs">
    <a class="<?= $reportType === 'detailed' ? 'active' : '' ?>" href="/reports?type=detailed">Detailed Work Log</a>
    <a class="<?= $reportType === 'project' ? 'active' : '' ?>" href="/reports?type=project">Project Report</a>
    <a class="<?= $reportType === 'customer' ? 'active' : '' ?>" href="/reports?type=customer">Customer Report</a>
</nav>

<?php require __DIR__ . '/../partials/suggestions.php'; ?>
<form method="get" action="/reports" class="panel filter-bar report-filter">
    <input type="hidden" name="type" value="<?= e($reportType) ?>">
    <label>
        From Date
        <input type="date" name="from_date" value="<?= e($filters['from_date'] ?? '') ?>">
    </label>
    <label>
        To Date
        <input type="date" name="to_date" value="<?= e($filters['to_date'] ?? '') ?>">
    </label>
    <label>
        Client Name
        <input type="text" name="project_group" value="<?= e($filters['project_group'] ?? '') ?>" placeholder="Search client" list="suggest-clients" autocomplete="off">
    </label>
    <label>
        User
        <select name="user_id">
            <option value="">All Users</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= e((string) $user['id']) ?>" <?= (string) ($filters['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>>
                    <?= e($user['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit">Apply</button>
        <a class="btn ghost" href="/reports">Reset</a>
    </div>
</form>

<div class="panel report-table-panel">
    <?php if ($reportType === 'project' || $reportType === 'customer'): ?>
        <?php $summaryRows = $reportType === 'project' ? $projectSummary : $customerSummary; ?>
        <?php if (!$summaryRows): ?>
            <div class="empty-state slim">
                <h3>No report data</h3>
                <p>Add daily logs to generate this report.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><?= $reportType === 'project' ? 'Group / Project Name' : 'Customer Name' ?></th>
                        <th>Billable</th>
                        <th>Non Billable</th>
                        <th>Logged Hours</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($summaryRows as $row): ?>
                    <tr>
                        <td><?= e($row['group_name']) ?></td>
                        <td class="blue-text"><?= e((string) $row['billable_hours']) ?></td>
                        <td class="orange-text"><?= e((string) $row['non_billable_hours']) ?></td>
                        <td><?= e((string) $row['logged_hours']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php elseif (!$workLogs): ?>
        <div class="empty-state slim">
            <h3>No report data</h3>
            <p>Add daily logs to generate this report.</p>
        </div>
    <?php else: ?>
        <div class="wide-table-wrap">
            <table class="data-table report-table">
                <thead>
                    <tr>
                        <th>Project Group</th>
                        <th>Phase</th>
                        <th>Task List/Module</th>
                        <th>Task/General/Issue</th>
                        <th>Notes</th>
                        <th>Daily Log</th>
                        <th>Log Hours</th>
                        <th>Hours</th>
                        <th>Date</th>
                        <th>Billing Type</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($workLogs as $log): ?>
                    <tr>
                        <td><?= e($log['project_group']) ?></td>
                        <td><?= e($log['phase']) ?></td>
                        <td><?= e($log['module_name']) ?></td>
                        <td><?= e($log['report_task_issue'] ?? $log['task_category']) ?></td>
                        <td><?= e($log['notes']) ?></td>
                        <td><?= e($log['daily_log'] ?: '-') ?></td>
                        <td><?= e(sprintf('%02d:%02d', intdiv((int) round(((float) $log['hours']) * 60), 60), ((int) round(((float) $log['hours']) * 60)) % 60)) ?></td>
                        <td><?= e((string) $log['hours']) ?></td>
                        <td><?= e($log['log_date']) ?></td>
                        <td><?= e($log['billing_type']) ?></td>
                        <td><?= e($log['user_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
