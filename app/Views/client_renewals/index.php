<?php
$formatDate = static fn (?string $d): string => $d ? date('d M Y', strtotime($d)) : '-';
?>

<section class="section-header">
    <div>
        <h2>Client Renewals</h2>
        <p>Monitor client licences, audit renewal history cycles, and export client statements.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn ghost" onclick="history.back()">Back</button>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= e(url('/client-renewals/check-alerts')) ?>" style="display: inline-block; margin: 0;">
            <?= csrf_field() ?>
            <button type="submit" class="btn ghost" title="Scan active batches and dispatch email & in-app alerts to Project Owners, Managers and Admins">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                Sync & Send Alerts
            </button>
        </form>
        <?php endif; ?>
        <?php if (\App\Core\Permissions::canExport()): ?>
        <button type="button" class="btn ghost" data-open-modal="exportStatementModal">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export Statement (Excel)
        </button>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <button type="button" class="btn" data-open-modal="addBatchModal">Add Licence Batch</button>
        <?php endif; ?>
    </div>
</section>

<!-- Stat Cards -->
<section class="stats-grid renewals-stats">
    <article class="stat-card">
        <span>Total Licences</span>
        <strong><?= e(number_format($stats['total_licences'])) ?></strong>
        <small class="stat-subtext">Across <?= e((string) $stats['total_batches']) ?> <?= $stats['total_batches'] === 1 ? 'batch' : 'batches' ?></small>
    </article>
    <article class="stat-card">
        <span>Active Batches</span>
        <strong class="text-success"><?= e((string) $stats['active_count']) ?></strong>
        <small class="stat-subtext"><?= e((string) $stats['total_clients']) ?> <?= $stats['total_clients'] === 1 ? 'client' : 'clients' ?> &bull; <?= e((string) $stats['total_projects']) ?> <?= $stats['total_projects'] === 1 ? 'project' : 'projects' ?></small>
    </article>
    <article class="stat-card <?= $stats['expiring_30_days'] > 0 ? 'warning' : '' ?>">
        <span>Expiring in 30 Days</span>
        <strong><?= e((string) $stats['expiring_30_days']) ?></strong>
        <small class="stat-subtext">Action required soon</small>
    </article>
    <article class="stat-card <?= $stats['overdue_count'] > 0 ? 'danger' : '' ?>">
        <span>Overdue / Expired</span>
        <strong class="<?= $stats['overdue_count'] > 0 ? 'text-danger' : '' ?>"><?= e((string) $stats['overdue_count']) ?></strong>
        <small class="stat-subtext">Past renewal date</small>
    </article>
</section>

<!-- Filter Toolbar -->
<form method="get" action="/client-renewals" class="panel filter-bar renewals-filter">
    <input type="hidden" name="view" value="<?= e($viewMode) ?>">
    
    <label>
        <span>Search</span>
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search batch, client, region, given by..." autocomplete="off">
    </label>

    <label>
        <span>Client</span>
        <select name="client_id" id="filter-client-select" data-client-filter>
            <option value="">All Clients</option>
            <?php foreach ($clientsWithProjects as $c): ?>
                <option value="<?= e((string) $c['id']) ?>" <?= (string) $filters['client_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        <span>Project</span>
        <select name="project_id" id="filter-project-select" data-project-filter>
            <option value="">All Projects</option>
            <?php foreach ($clientsWithProjects as $c): ?>
                <?php foreach ($c['projects'] as $p): ?>
                    <?php if (empty($filters['client_id']) || (string) $filters['client_id'] === (string) $c['id']): ?>
                        <option value="<?= e((string) $p['id']) ?>" data-client-id="<?= e((string) $c['id']) ?>" <?= (string) $filters['project_id'] === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= e($c['name'] . ' › ' . $p['name']) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        <span>Renewal Urgency</span>
        <select name="urgency">
            <option value="">All Urgencies</option>
            <option value="30_days" <?= $filters['urgency'] === '30_days' ? 'selected' : '' ?>>Expiring in 30 Days</option>
            <option value="60_days" <?= $filters['urgency'] === '60_days' ? 'selected' : '' ?>>Expiring in 60 Days</option>
            <option value="overdue" <?= $filters['urgency'] === 'overdue' ? 'selected' : '' ?>>Overdue / Expired</option>
            <option value="active" <?= $filters['urgency'] === 'active' ? 'selected' : '' ?>>Active Only</option>
        </select>
    </label>

    <label>
        <span>Status</span>
        <select name="status">
            <option value="active" <?= ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active Batches</option>
            <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived Batches</option>
            <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>All Batches</option>
        </select>
    </label>

    <div class="filter-actions">
        <button type="submit">Apply</button>
        <a class="btn ghost" href="/client-renewals?view=<?= e($viewMode) ?>">Reset</a>
    </div>
</form>

<!-- View Mode Switcher Tabs -->
<div class="renewals-view-header">
    <nav class="tabs renewals-tabs">
        <a class="<?= $viewMode === 'hierarchy' ? 'active' : '' ?>" href="/client-renewals?<?= e(http_build_query(array_merge($filters, ['view' => 'hierarchy']))) ?>">
            <svg class="tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            Hierarchy (Contract Schedule)
        </a>
        <a class="<?= $viewMode === 'platform_hierarchy' ? 'active' : '' ?>" href="/client-renewals?<?= e(http_build_query(array_merge($filters, ['view' => 'platform_hierarchy']))) ?>">
            <svg class="tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
            Hierarchy (Platform Schedule)
        </a>
        <a class="<?= $viewMode === 'flat' ? 'active' : '' ?>" href="/client-renewals?<?= e(http_build_query(array_merge($filters, ['view' => 'flat']))) ?>">
            <svg class="tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            Schedule View (Sorted by Date)
        </a>
        <a class="<?= $viewMode === 'statement' ? 'active' : '' ?>" href="/client-renewals?<?= e(http_build_query(array_merge($filters, ['view' => 'statement']))) ?>">
            <svg class="tab-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Client Statement & Audit History
        </a>
    </nav>
    <div class="renewals-header-tools" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <?php if (($viewMode === 'hierarchy' || $viewMode === 'platform_hierarchy') && !empty($hierarchy)): ?>
            <div class="hierarchy-toggle-group" style="display:inline-flex; align-items:center; gap:6px;">
                <button type="button" class="btn-tool secondary" id="btnExpandAllHierarchy" title="Expand all client sections" style="padding:4px 10px; font-size:12px; font-weight:600; cursor:pointer;">
                    <span>▼</span> Expand All
                </button>
                <button type="button" class="btn-tool secondary" id="btnCollapseAllHierarchy" title="Collapse all client sections" style="padding:4px 10px; font-size:12px; font-weight:600; cursor:pointer;">
                    <span>▲</span> Collapse All
                </button>
            </div>
        <?php endif; ?>
        <div class="renewals-count-label">
            Showing <strong><?= count($batches) ?></strong> licence <?= count($batches) === 1 ? 'batch' : 'batches' ?>
        </div>
    </div>
</div>

<?php if ($viewMode === 'statement'): ?>
    <!-- ================= CLIENT STATEMENT & YEAR-WISE AUDIT VIEW ================= -->
    <div class="statement-view-wrap">
        <!-- Year-by-Year Comparison Cards -->
        <div class="panel statement-summary-panel">
            <div class="statement-header">
                <div>
                    <h3>Year-by-Year Licences Allocated & History</h3>
                    <p class="muted">Lifetime snapshot and breakdown of licenses granted per year.</p>
                </div>
                <?php if (\App\Core\Permissions::canExport()): ?>
                <button type="button" class="btn ghost" data-open-modal="exportStatementModal">Download Excel Report</button>
                <?php endif; ?>
            </div>

            <div class="year-cards-grid">
                <?php if (empty($yearComparison)): ?>
                    <p class="muted">No yearly allocation data available for current selection.</p>
                <?php else: ?>
                    <?php foreach ($yearComparison as $yr => $yData): ?>
                        <div class="year-card <?= $yr === (int) date('Y') ? 'current-year' : '' ?>">
                            <span class="year-label"><?= e((string) $yr) ?></span>
                            <strong class="year-licences"><?= e(number_format($yData['licences'])) ?> <small>Licences</small></strong>
                            <span class="year-subtext"><?= e((string) $yData['batches']) ?> <?= $yData['batches'] === 1 ? 'Batch' : 'Batches' ?> &bull; <?= e($yData['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Complete Audit Trail Table -->
        <div class="panel report-table-panel">
            <div class="statement-table-header">
                <h3>Complete Licence Audit Trail Ledger</h3>
                <p class="muted">All initial grants, renewals, and volume changes logged chronologically</p>
            </div>
            <?php if (empty($historyLogs)): ?>
                <div class="empty-state slim">
                    <h3>No audit logs recorded yet</h3>
                    <p>When you create or renew batches, their cycle records will appear here.</p>
                </div>
            <?php else: ?>
                <div class="wide-table-wrap">
                    <table class="data-table history-ledger-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Project</th>
                                <th>Batch Name</th>
                                <th>Event Type</th>
                                <th>Period Start</th>
                                <th>Period Renewal</th>
                                <th class="text-right">Licences in Period</th>
                                <th>Given By</th>
                                <th>Notes / Contract Ref</th>
                                <th>Logged By</th>
                                <th>Logged On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyLogs as $h): ?>
                                <tr>
                                    <td><strong><?= e($h['client_name']) ?></strong></td>
                                    <td><?= e($h['project_name']) ?></td>
                                    <td><?= e($h['batch_name']) ?></td>
                                    <td>
                                        <?php
                                            $actType = strtolower((string) ($h['action_type'] ?? 'renewed'));
                                            $actNotes = (string) ($h['notes'] ?? '');
                                            if (stripos($actNotes, 'Batch Archived') !== false || $actType === 'archived') {
                                                $actType = 'archived';
                                                $actLabel = 'Archived';
                                            } elseif (stripos($actNotes, 'Batch Unarchived') !== false || $actType === 'unarchived') {
                                                $actType = 'unarchived';
                                                $actLabel = 'Unarchived';
                                            } else {
                                                $actLabel = ucfirst($actType);
                                            }
                                        ?>
                                        <span class="action-tag <?= e($actType) ?>">
                                            <?= e($actLabel) ?>
                                        </span>
                                    </td>
                                    <td><?= e($formatDate($h['period_start'])) ?></td>
                                    <td><strong><?= e($formatDate($h['period_end'])) ?></strong></td>
                                    <td class="text-right">
                                        <span class="licence-badge"><?= e(number_format((int) $h['licence_count'])) ?></span>
                                    </td>
                                    <td><?= e($h['given_by'] ?: '-') ?></td>
                                    <td>
                                        <span class="notes-preview" title="<?= e($h['notes'] ?? '') ?>">
                                            <?= e($h['notes'] ?: '-') ?>
                                        </span>
                                    </td>
                                    <td><small><?= e($h['creator_name']) ?></small></td>
                                    <td><small class="muted"><?= e(date('d M Y, h:i A', strtotime($h['created_at']))) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif (!$batches): ?>
    <div class="panel empty-state">
        <h3>No licence batches found</h3>
        <p>No client renewal batches match your current filter criteria.</p>
        <?php if ($canEdit): ?>
            <button type="button" class="btn" data-open-modal="addBatchModal">Add First Batch</button>
        <?php endif; ?>
    </div>
<?php elseif ($viewMode === 'hierarchy' || $viewMode === 'platform_hierarchy'): ?>
    <!-- ================= HIERARCHY VIEW: Client -> Project -> Batches ================= -->
    <div class="renewals-hierarchy-wrap">
        <?php foreach ($hierarchy as $clientNode): ?>
            <details class="client-renewal-card panel" open>
                <summary class="client-renewal-summary">
                    <div class="client-summary-left">
                        <span class="client-icon" aria-hidden="true">🏢</span>
                        <div class="client-summary-title">
                            <h3><?= e($clientNode['client_name']) ?></h3>
                            <span class="client-meta-info">
                                <?= e((string) count($clientNode['projects'])) ?> <?= count($clientNode['projects']) === 1 ? 'Project' : 'Projects' ?> &bull;
                                <?= e((string) $clientNode['batch_count']) ?> <?= $clientNode['batch_count'] === 1 ? 'Batch' : 'Batches' ?>
                            </span>
                        </div>
                    </div>
                    <div class="client-summary-right">
                        <span class="licence-total-pill">
                            <strong><?= e(number_format($clientNode['total_licences'])) ?></strong> Licences
                        </span>
                        <?php if ($clientNode['has_overdue']): ?>
                            <span class="renewal-urgency-badge danger">Has Overdue</span>
                        <?php elseif ($clientNode['has_due_soon']): ?>
                            <span class="renewal-urgency-badge warning">Renewals Due Soon</span>
                        <?php else: ?>
                            <span class="renewal-urgency-badge success">Active</span>
                        <?php endif; ?>
                    </div>
                </summary>

                <div class="client-projects-content">
                    <?php foreach ($clientNode['projects'] as $projectNode): ?>
                        <div class="project-renewal-section">
                            <div class="project-renewal-header">
                                <div class="project-title-left">
                                    <span class="project-marker" <?= !empty($projectNode['project_color']) ? 'style="background-color:' . e($projectNode['project_color']) . '"' : '' ?>></span>
                                    <a class="project-link" href="/projects/show?id=<?= e((string) $projectNode['project_id']) ?>">
                                        <?= e($projectNode['project_name']) ?>
                                    </a>
                                    <?php if (!empty($projectNode['project_code'])): ?>
                                        <span class="code-badge"><?= e($projectNode['project_code']) ?></span>
                                    <?php endif; ?>
                                    <span class="status-pill tiny <?= e($projectNode['project_status']) ?>">
                                        <?= e(str_replace('_', ' ', $projectNode['project_status'])) ?>
                                    </span>
                                </div>
                                <div class="project-title-right">
                                    <span class="project-licence-count"><strong><?= e(number_format($projectNode['total_licences'])) ?></strong> licences (<?= count($projectNode['batches']) ?> <?= count($projectNode['batches']) === 1 ? 'batch' : 'batches' ?>)</span>
                                </div>
                            </div>

                            <!-- Project Contract Hours Overview Bar -->
                            <div class="project-contract-banner" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 14px; margin-bottom:12px; font-size:12.5px;">
                                <div style="display:inline-flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                    <?php if ($projectNode['is_open_po']): ?>
                                        <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-weight:700; font-size:11.5px;">
                                            🔵 Open PO (Hourly Basis &mdash; No Budget Cap)
                                        </span>
                                        <span>Logged: <strong><?= e(number_format((float) $projectNode['total_logged_hours'], 2)) ?></strong> hrs</span>
                                        <span class="text-success">Billed: <strong><?= e(number_format((float) $projectNode['billed_hours'], 2)) ?></strong> hrs</span>
                                        <?php if ($projectNode['unbilled_hours'] > 0): ?>
                                            <span class="text-orange">Unbilled: <strong><?= e(number_format((float) $projectNode['unbilled_hours'], 2)) ?></strong> hrs</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-weight:700; font-size:11.5px;">
                                            🟢 Contract Cap: <?= e(number_format((float) $projectNode['total_allocated_hours'], 2)) ?> hrs
                                        </span>
                                        <span>Logged: <strong><?= e(number_format((float) $projectNode['total_logged_hours'], 2)) ?></strong> hrs</span>
                                        <span class="<?= $projectNode['remaining_hours'] <= 0 ? 'text-danger font-bold' : 'text-success font-bold' ?>">
                                            Remaining: <strong><?= e(number_format((float) $projectNode['remaining_hours'], 2)) ?></strong> hrs
                                        </span>
                                        <?php if ($projectNode['total_allocated_hours'] > 0): ?>
                                        <div style="display:inline-flex; align-items:center; gap:6px; min-width:110px;" title="<?= e((string) $projectNode['consumption_percent']) ?>% hours consumed">
                                            <div style="flex:1; height:6px; width:70px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                                                <div style="height:100%; width:<?= min(100, (float) $projectNode['consumption_percent']) ?>%; background:<?= $projectNode['consumption_percent'] >= 100 ? '#ef4444' : ($projectNode['consumption_percent'] >= 80 ? '#f59e0b' : '#10b981') ?>;"></div>
                                            </div>
                                            <small class="muted" style="font-size:11px;"><?= e((string) $projectNode['consumption_percent']) ?>%</small>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <a class="btn-tool ghost" href="<?= url('/reports?type=project_billing&project_group=' . urlencode($projectNode['project_name'])) ?>" title="View project billing reports">Billing Report &rarr;</a>
                                </div>
                            </div>
                            <div class="desktop-only-table">
                                <div class="wide-table-wrap">
                                    <table class="data-table renewals-table">
                                        <thead>
                                            <tr>
                                                <th>Batch Name</th>
                                                <th>Region / Department</th>
                                                <th class="text-right">Licence Count</th>
                                                <th><?= ($scheduleMode === 'platform') ? 'Platform Start' : 'Creation Date' ?></th>
                                                <th><?= ($scheduleMode === 'platform') ? 'Platform Renewal' : 'Renewal Date' ?></th>
                                                <th>Urgency Status</th>
                                                <th>Given By</th>
                                                <th>Notes</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($projectNode['batches'] as $batch): ?>
                                                <tr class="renewal-row <?= e($batch['urgency_status']) ?>">
                                                    <td>
                                                        <strong><?= e($batch['batch_name']) ?></strong>
                                                    </td>
                                                    <td><?= e($batch['region_department'] ?: '-') ?></td>
                                                    <td class="text-right">
                                                        <span class="licence-badge"><?= e(number_format((int) $batch['licence_count'])) ?></span>
                                                    </td>
                                                    <td><?= e($formatDate($batch['effective_start_date'] ?? $batch['creation_date'])) ?></td>
                                                    <td>
                                                        <strong class="<?= $batch['is_overdue'] ? 'text-danger' : ($batch['is_30_days'] ? 'text-warning' : '') ?>">
                                                            <?= e($formatDate($batch['effective_renewal_date'] ?? $batch['renewal_date'])) ?>
                                                        </strong>
                                                        <?php if ($scheduleMode === 'platform' && !empty($batch['platform_renewal_date']) && $batch['platform_renewal_date'] !== $batch['renewal_date']): ?>
                                                            <small class="muted" style="display:block; font-size:11px; font-weight:normal;" title="Official contract renewal date">
                                                                Contract: <?= e($formatDate($batch['renewal_date'])) ?>
                                                            </small>
                                                        <?php elseif ($scheduleMode === 'contract' && !empty($batch['platform_renewal_date']) && $batch['platform_renewal_date'] !== $batch['renewal_date']): ?>
                                                            <small class="muted" style="display:block; font-size:11px; font-weight:normal;" title="Operational platform renewal date">
                                                                Platform: <?= e($formatDate($batch['platform_renewal_date'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= e($batch['urgency_class']) ?>">
                                                            <?= e($batch['urgency_label']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= e($batch['given_by'] ?: '-') ?></td>
                                                    <td>
                                                        <span class="notes-preview" title="<?= e($batch['notes'] ?? '') ?>">
                                                            <?php if (!empty($batch['is_extended'])): ?>
                                                                <span class="badge" style="background:#f3e8ff; color:#7e22ce; font-size:10.5px; font-weight:700; padding:1px 5px; border-radius:3px; margin-right:4px;">EXT</span>
                                                            <?php endif; ?>
                                                            <?= e($batch['notes'] ?: '-') ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="row-tools-inline">
                                                            <button type="button" class="btn-tool history" data-action="history" data-batch-id="<?= e((string) $batch['id']) ?>" data-batch-name="<?= e($batch['batch_name']) ?>" title="View Lifetime Audit History">History</button>
                                                            <?php if ($canEdit): ?>
                                                                <?php 
                                                                    $isArchived = ($batch['status'] ?? '') === 'archived' || ($batch['status'] ?? '') === 'cancelled' || !empty($batch['archived_at']) || ($batch['urgency_status'] ?? '') === 'archived';
                                                                ?>
                                                                <?php if ($isArchived): ?>
                                                                    <form method="post" action="/client-renewals/unarchive" onsubmit="return confirm('Restore batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot; to active list?');" class="inline-form">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                                        <button type="submit" class="btn-tool unarchive" title="Restore batch to active list">Unarchive</button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn-tool" data-action="renew" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>' title="Paid Renewal">Renew</button>
                                                                    <button type="button" class="btn-tool extend" data-action="extend" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>' title="Courtesy / Grace Extension">Extend</button>
                                                                    <button type="button" class="btn-tool secondary" data-action="edit" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>' title="Edit details">Edit</button>
                                                                    <form method="post" action="/client-renewals/archive" onsubmit="return confirm('Archive batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? It will be safely archived without deleting logs.');" class="inline-form">
                                                                        <?= csrf_field() ?>
                                                                        <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                                        <button type="submit" class="btn-tool archive" title="Archive batch">Archive</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                <form method="post" action="/client-renewals/delete" onsubmit="return confirm('Permanently delete batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? This cannot be undone.');" class="inline-form">
                                                                    <?= csrf_field() ?>
                                                                    <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                                    <button type="submit" class="btn-tool danger" title="Permanently delete batch">&times;</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="mobile-only-cards renewals-mobile-cards">
                                <?php foreach ($projectNode['batches'] as $batch): ?>
                                    <article class="mobile-card renewal-mobile-card <?= e($batch['urgency_status']) ?>">
                                        <header class="mobile-card-header">
                                            <div class="mobile-card-header-left">
                                                <h4 class="mobile-card-title"><?= e($batch['batch_name']) ?></h4>
                                                <span class="mobile-card-client-tag"><?= e($clientNode['client_name']) ?></span>
                                            </div>
                                            <span class="badge <?= e($batch['urgency_class']) ?>">
                                                <?= e($batch['urgency_label']) ?>
                                            </span>
                                        </header>
                                        <div class="mobile-card-body">
                                            <div class="mobile-card-grid">
                                                <div class="mobile-metric-item">
                                                    <span class="mobile-metric-label">Licences</span>
                                                    <span class="mobile-metric-val licence-badge"><?= e(number_format((int) $batch['licence_count'])) ?></span>
                                                </div>
                                                <div class="mobile-metric-item">
                                                    <span class="mobile-metric-label"><?= ($scheduleMode === 'platform') ? 'Platform Renewal' : 'Renewal Date' ?></span>
                                                    <span class="mobile-metric-val">
                                                        <strong class="<?= $batch['is_overdue'] ? 'text-danger' : ($batch['is_30_days'] ? 'text-warning' : '') ?>">
                                                            <?= e($formatDate($batch['effective_renewal_date'] ?? $batch['renewal_date'])) ?>
                                                        </strong>
                                                        <?php if ($scheduleMode === 'platform' && !empty($batch['platform_renewal_date']) && $batch['platform_renewal_date'] !== $batch['renewal_date']): ?>
                                                            <small class="muted" style="display:block; font-size:10.5px;">Contract: <?= e($formatDate($batch['renewal_date'])) ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($batch['region_department'])): ?>
                                                <div class="mobile-metric-item span-2">
                                                    <span class="mobile-metric-label">Region / Dept</span>
                                                    <span class="mobile-metric-val"><?= e($batch['region_department']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if (!empty($batch['given_by'])): ?>
                                                <div class="mobile-metric-item">
                                                    <span class="mobile-metric-label">Given By</span>
                                                    <span class="mobile-metric-val"><?= e($batch['given_by']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($batch['notes'])): ?>
                                            <div class="mobile-card-notes">
                                                <?php if (!empty($batch['is_extended'])): ?>
                                                    <span class="badge" style="background:#f3e8ff; color:#7e22ce; font-size:10.5px; font-weight:700; padding:1px 5px; border-radius:3px; margin-right:4px;">EXT</span>
                                                <?php endif; ?>
                                                <span><?= e($batch['notes']) ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <footer class="mobile-card-footer">
                                            <div class="mobile-card-actions">
                                                <button type="button" class="btn-tool history" data-action="history" data-batch-id="<?= e((string) $batch['id']) ?>" data-batch-name="<?= e($batch['batch_name']) ?>">📜 History</button>
                                                <?php if ($canEdit): ?>
                                                    <?php 
                                                        $isArchived = ($batch['status'] ?? '') === 'archived' || ($batch['status'] ?? '') === 'cancelled' || !empty($batch['archived_at']) || ($batch['urgency_status'] ?? '') === 'archived';
                                                    ?>
                                                    <?php if ($isArchived): ?>
                                                        <form method="post" action="/client-renewals/unarchive" onsubmit="return confirm('Restore batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot; to active list?');" class="inline-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                            <button type="submit" class="btn-tool unarchive">♻️ Unarchive</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn-tool" data-action="renew" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>🔄 Renew</button>
                                                        <button type="button" class="btn-tool extend" data-action="extend" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>⏳ Extend</button>
                                                        <button type="button" class="btn-tool secondary" data-action="edit" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>✏️ Edit</button>
                                                        <form method="post" action="/client-renewals/archive" onsubmit="return confirm('Archive batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? It will be safely archived without deleting logs.');" class="inline-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                            <button type="submit" class="btn-tool archive">📦 Archive</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" action="/client-renewals/delete" onsubmit="return confirm('Permanently delete batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? This cannot be undone.');" class="inline-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                        <button type="submit" class="btn-tool danger">&times;</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </footer>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- ================= FLAT SCHEDULE VIEW: Sorted directly by Renewal Date ================= -->
    <div class="panel report-table-panel">
        <div class="desktop-only-table">
            <div class="wide-table-wrap">
                <table class="data-table renewals-table flat-schedule">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Batch Name</th>
                            <th>Region / Department</th>
                            <th class="text-right">Licences</th>
                            <th><?= ($scheduleMode === 'platform') ? 'Platform Start' : 'Creation Date' ?></th>
                            <th><?= ($scheduleMode === 'platform') ? 'Platform Renewal' : 'Renewal Date' ?></th>
                            <th>Countdown</th>
                            <th>Given By</th>
                            <th>Created By</th>
                            <th>Notes</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                            <tr class="renewal-row <?= e($batch['urgency_status']) ?>">
                                <td>
                                    <strong><?= e($batch['client_name']) ?></strong>
                                </td>
                                <td>
                                    <a class="table-link" href="/projects/show?id=<?= e((string) $batch['project_id']) ?>">
                                        <span class="project-marker" <?= !empty($batch['project_color']) ? 'style="background-color:' . e($batch['project_color']) . '"' : '' ?>></span>
                                        <?= e($batch['project_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= e($batch['batch_name']) ?></strong>
                                </td>
                                <td><?= e($batch['region_department'] ?: '-') ?></td>
                                <td class="text-right">
                                    <span class="licence-badge"><?= e(number_format((int) $batch['licence_count'])) ?></span>
                                </td>
                                <td><?= e($formatDate($batch['effective_start_date'] ?? $batch['creation_date'])) ?></td>
                                <td>
                                    <strong><?= e($formatDate($batch['effective_renewal_date'] ?? $batch['renewal_date'])) ?></strong>
                                    <?php if ($scheduleMode === 'platform' && !empty($batch['platform_renewal_date']) && $batch['platform_renewal_date'] !== $batch['renewal_date']): ?>
                                        <small class="muted" style="display:block; font-size:11px; font-weight:normal;" title="Contract Schedule Renewal">
                                            Contract: <?= e($formatDate($batch['renewal_date'])) ?>
                                        </small>
                                    <?php elseif ($scheduleMode === 'contract' && !empty($batch['platform_renewal_date']) && $batch['platform_renewal_date'] !== $batch['renewal_date']): ?>
                                        <small class="muted" style="display:block; font-size:11px; font-weight:normal;" title="Platform Schedule Renewal">
                                            Platform: <?= e($formatDate($batch['platform_renewal_date'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="renewal-status-tag <?= e($batch['urgency_status']) ?>">
                                        <?= e($batch['urgency_label']) ?>
                                    </span>
                                </td>
                                <td><?= e($batch['given_by'] ?: '-') ?></td>
                                <td><small><?= e($batch['creator_name']) ?></small></td>
                                <td>
                                    <span class="notes-preview" title="<?= e($batch['notes'] ?? '') ?>">
                                        <?php if (!empty($batch['is_extended'])): ?>
                                            <span class="badge" style="background:#f3e8ff; color:#7e22ce; font-size:10.5px; font-weight:700; padding:1px 5px; border-radius:3px; margin-right:4px;">EXT</span>
                                        <?php endif; ?>
                                        <?= e($batch['notes'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="row-tools-inline">
                                        <button type="button" class="btn-tool history" data-action="history" data-batch-id="<?= e((string) $batch['id']) ?>" data-batch-name="<?= e($batch['batch_name']) ?>" title="View Lifetime Audit History">History</button>
                                        <?php if ($canEdit): ?>
                                            <?php 
                                                $isArchived = ($batch['status'] ?? '') === 'archived' || ($batch['status'] ?? '') === 'cancelled' || !empty($batch['archived_at']) || ($batch['urgency_status'] ?? '') === 'archived';
                                            ?>
                                            <?php if ($isArchived): ?>
                                                <form method="post" action="/client-renewals/unarchive" onsubmit="return confirm('Restore batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot; to active list?');" class="inline-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                    <button type="submit" class="btn-tool unarchive" title="Restore batch to active list">Unarchive</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn-tool" data-action="renew" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>' title="Paid Renewal">Renew</button>
                                                <button type="button" class="btn-tool extend" data-action="extend" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>' title="Courtesy / Grace Extension">Extend</button>
                                                <button type="button" class="btn-tool secondary" data-action="edit" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>' title="Edit details">Edit</button>
                                                <form method="post" action="/client-renewals/archive" onsubmit="return confirm('Archive batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? It will be safely archived without deleting logs.');" class="inline-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                    <button type="submit" class="btn-tool archive" title="Archive batch">Archive</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" action="/client-renewals/delete" onsubmit="return confirm('Permanently delete batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? This cannot be undone.');" class="inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                                <button type="submit" class="btn-tool danger" title="Permanently delete batch">&times;</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mobile-only-cards renewals-mobile-cards">
            <?php foreach ($batches as $batch): ?>
                <article class="mobile-card renewal-mobile-card <?= e($batch['urgency_status']) ?>">
                    <header class="mobile-card-header">
                        <div class="mobile-card-header-left">
                            <span class="mobile-card-client-tag"><?= e($batch['client_name']) ?></span>
                            <h4 class="mobile-card-title"><?= e($batch['batch_name']) ?></h4>
                            <a class="mobile-card-sublink" href="/projects/show?id=<?= e((string) $batch['project_id']) ?>">
                                <span class="project-marker" <?= !empty($batch['project_color']) ? 'style="background-color:' . e($batch['project_color']) . '"' : '' ?>></span>
                                <?= e($batch['project_name']) ?>
                            </a>
                        </div>
                        <span class="renewal-status-tag <?= e($batch['urgency_status']) ?>">
                            <?= e($batch['urgency_label']) ?>
                        </span>
                    </header>
                    <div class="mobile-card-body">
                        <div class="mobile-card-grid">
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Licences</span>
                                <span class="mobile-metric-val licence-badge"><?= e(number_format((int) $batch['licence_count'])) ?></span>
                            </div>
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Renewal Date</span>
                                <span class="mobile-metric-val"><strong><?= e($formatDate($batch['renewal_date'])) ?></strong></span>
                            </div>
                            <?php if (!empty($batch['region_department'])): ?>
                            <div class="mobile-metric-item span-2">
                                <span class="mobile-metric-label">Region / Dept</span>
                                <span class="mobile-metric-val"><?= e($batch['region_department']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($batch['given_by'])): ?>
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Given By</span>
                                <span class="mobile-metric-val"><?= e($batch['given_by']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="mobile-metric-item">
                                <span class="mobile-metric-label">Created Date</span>
                                <span class="mobile-metric-val"><?= e($formatDate($batch['creation_date'])) ?></span>
                            </div>
                        </div>
                        <?php if (!empty($batch['notes'])): ?>
                        <div class="mobile-card-notes">
                            <?php if (!empty($batch['is_extended'])): ?>
                                <span class="badge" style="background:#f3e8ff; color:#7e22ce; font-size:10.5px; font-weight:700; padding:1px 5px; border-radius:3px; margin-right:4px;">EXT</span>
                            <?php endif; ?>
                            <span><?= e($batch['notes']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <footer class="mobile-card-footer">
                        <div class="mobile-card-actions">
                            <button type="button" class="btn-tool history" data-action="history" data-batch-id="<?= e((string) $batch['id']) ?>" data-batch-name="<?= e($batch['batch_name']) ?>">📜 History</button>
                            <?php if ($canEdit): ?>
                                <?php 
                                    $isArchived = ($batch['status'] ?? '') === 'archived' || ($batch['status'] ?? '') === 'cancelled' || !empty($batch['archived_at']) || ($batch['urgency_status'] ?? '') === 'archived';
                                ?>
                                <?php if ($isArchived): ?>
                                    <form method="post" action="/client-renewals/unarchive" onsubmit="return confirm('Restore batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot; to active list?');" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                        <button type="submit" class="btn-tool unarchive">♻️ Unarchive</button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn-tool" data-action="renew" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>🔄 Renew</button>
                                    <button type="button" class="btn-tool extend" data-action="extend" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>⏳ Extend</button>
                                    <button type="button" class="btn-tool secondary" data-action="edit" data-batch='<?= e(json_encode($batch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'>✏️ Edit</button>
                                    <form method="post" action="/client-renewals/archive" onsubmit="return confirm('Archive batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? It will be safely archived without deleting logs.');" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                        <button type="submit" class="btn-tool archive">📦 Archive</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/client-renewals/delete" onsubmit="return confirm('Permanently delete batch &quot;<?= e(addslashes($batch['batch_name'])) ?>&quot;? This cannot be undone.');" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $batch['id']) ?>">
                                    <button type="submit" class="btn-tool danger">&times;</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- ================= MODALS ================= -->

<!-- 1. Smart Excel Statement Export Modal -->
<div class="modal-backdrop" id="exportStatementModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Export Client Licences Statement (Excel)</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="get" action="/client-renewals/export" class="modal-body form-grid">
            <p class="span-2 muted" style="margin: 0; font-size: 13px;">Generate an independent Excel statement workbook based on your desired schedule timeline.</p>

            <label class="span-2">
                <span style="font-weight:700;">Statement Schedule Scope</span>
                <select name="schedule_mode" id="export-schedule-mode" style="font-weight:600;">
                    <option value="contract" <?= ($scheduleMode ?? 'contract') === 'contract' ? 'selected' : '' ?>>Contract Schedule (Official Client Term)</option>
                    <option value="platform" <?= ($scheduleMode ?? '') === 'platform' ? 'selected' : '' ?>>Platform Schedule (Operational Delivery Term)</option>
                </select>
                <small class="muted">Each schedule produces an independent export with its respective timeline and urgency metrics.</small>
            </label>

            <label class="span-2">
                <span>Client Scope</span>
                <select name="client_id" id="export-client-select">
                    <option value="">All Clients</option>
                    <?php foreach ($clientsWithProjects as $c): ?>
                        <option value="<?= e((string) $c['id']) ?>" <?= (string) $filters['client_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="span-2">
                <span>Project Scope</span>
                <select name="project_id" id="export-project-select">
                    <option value="">All Projects</option>
                    <?php foreach ($clientsWithProjects as $c): ?>
                        <?php foreach ($c['projects'] as $p): ?>
                            <option value="<?= e((string) $p['id']) ?>" data-client-id="<?= e((string) $c['id']) ?>" <?= (string) $filters['project_id'] === (string) $p['id'] ? 'selected' : '' ?>>
                                <?= e($c['name'] . ' › ' . $p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="span-2">
                <span class="field-label" style="display: block; margin-bottom: 6px;">Date Range Presets:</span>
                <div class="preset-buttons">
                    <button type="button" class="btn-preset" data-export-preset="all">All Time</button>
                    <button type="button" class="btn-preset" data-export-preset="this_year">This Year (<?= date('Y') ?>)</button>
                    <button type="button" class="btn-preset" data-export-preset="last_year">Last Year (<?= date('Y') - 1 ?>)</button>
                    <button type="button" class="btn-preset" data-export-preset="next_90">Next 90 Days</button>
                </div>
            </div>

            <label>
                <span>From Date</span>
                <input type="date" name="from_date" id="export-from-date" value="<?= e($filters['from_date'] ?? '') ?>">
            </label>

            <label>
                <span>To Date</span>
                <input type="date" name="to_date" id="export-to-date" value="<?= e($filters['to_date'] ?? '') ?>">
            </label>

            <div class="span-2 export-sections-box">
                <span class="field-label" style="display: block; font-weight: 700; margin-bottom: 8px;">Select Workbook Sections:</span>
                <label class="checkbox-inline">
                    <input type="checkbox" name="include_summary" value="1" checked>
                    <strong>Executive Statement Sheet</strong> (Summary KPIs & Year-by-Year comparison)
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="include_active" value="1" checked>
                    <strong>Active Licences Sheet</strong> (Current live batch schedules)
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="include_history" value="1" checked>
                    <strong>Renewal Audit Trail Sheet</strong> (Full lifetime cycle log)
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="include_notes" value="1" checked>
                    <strong>Include Contract Notes & Given By</strong>
                </label>
            </div>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn">Download Excel Workbook</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Batch History Timeline Modal -->
<div class="modal-backdrop" id="batchHistoryModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3 id="history-modal-title">Licence Cycle Audit Trail</h3>
                <small class="muted" id="history-modal-subtitle">Lifetime renewal history & period logs</small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body">
            <div class="history-timeline" id="historyTimelineContainer">
                <div class="empty-state slim">
                    <p>Loading history records...</p>
                </div>
            </div>
            <div class="modal-footer" style="padding-top: 12px; margin-top: 10px;">
                <button type="button" class="btn ghost" data-close-modal>Close</button>
            </div>
        </div>
    </div>
</div>

<?php if ($canEdit): ?>
<!-- 3. Add Batch Modal -->
<div class="modal-backdrop" id="addBatchModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Add Licence Batch</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="/client-renewals/create" class="modal-body form-grid">
            <?= csrf_field() ?>
            
            <label class="span-2">
                <span>Client <span class="req">*</span></span>
                <select name="client_id" id="add-modal-client" required>
                    <option value="">Select Client</option>
                    <?php foreach ($clientsWithProjects as $c): ?>
                        <option value="<?= e((string) $c['id']) ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="span-2">
                <span>Project <span class="req">*</span></span>
                <select name="project_id" id="add-modal-project" required disabled>
                    <option value="">Select Client First</option>
                </select>
            </label>

            <label class="span-2">
                <span>Batch Name <span class="req">*</span></span>
                <input type="text" name="batch_name" placeholder="e.g. Batch 1 - West Zone LMS Licences" required>
            </label>

            <label>
                <span>Region / Department</span>
                <input type="text" name="region_department" placeholder="e.g. West Region, Operations HQ">
            </label>

            <label>
                <span>Licence Count <span class="req">*</span></span>
                <input type="number" name="licence_count" min="1" step="1" placeholder="e.g. 500" required>
            </label>

            <label>
                <span>Creation Date <span class="req">*</span></span>
                <input type="date" name="creation_date" id="add-modal-creation-date" value="<?= e(date('Y-m-d')) ?>" required>
            </label>

            <label>
                <span>Renewal Date <span class="req">*</span></span>
                <input type="date" name="renewal_date" id="add-modal-renewal-date" value="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>" required>
            </label>

            <div class="span-2" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px;">
                <label class="checkbox-inline" style="margin-bottom:0; font-size:12.5px; color:#334155; cursor:pointer;">
                    <input type="checkbox" id="add-modal-has-platform-dates" onchange="document.getElementById('add-modal-platform-fields').hidden = !this.checked">
                    <strong>Platform Schedule dates differ from Contract Schedule</strong>
                </label>
                <div id="add-modal-platform-fields" hidden style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:10px;">
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">Platform Start Date</span>
                        <input type="date" name="platform_start_date" id="add-modal-platform-start">
                        <small class="muted" style="font-size:11px;">Operational start date given to platform</small>
                    </label>
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">Platform Renewal Date</span>
                        <input type="date" name="platform_renewal_date" id="add-modal-platform-renewal">
                        <small class="muted" style="font-size:11px;">Operational renewal date given to platform</small>
                    </label>
                </div>
            </div>

            <label class="span-2">
                <span>Given By</span>
                <input type="text" name="given_by" placeholder="e.g. Vendor Name, EduRiser Central, SPOC Name">
            </label>

            <label class="span-2">
                <span>Notes / Licence Details</span>
                <textarea name="notes" rows="3" placeholder="Additional notes, purchase order #, or contract references..."></textarea>
            </label>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn">Save Licence Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Edit Batch Modal -->
<div class="modal-backdrop" id="editBatchModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Edit Licence Batch</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="/client-renewals/update" class="modal-body form-grid" id="editBatchForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit-modal-id">

            <label class="span-2">
                <span>Client <span class="req">*</span></span>
                <select name="client_id" id="edit-modal-client" required>
                    <option value="">Select Client</option>
                    <?php foreach ($clientsWithProjects as $c): ?>
                        <option value="<?= e((string) $c['id']) ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="span-2">
                <span>Project <span class="req">*</span></span>
                <select name="project_id" id="edit-modal-project" required>
                    <option value="">Select Project</option>
                </select>
            </label>

            <label class="span-2">
                <span>Batch Name <span class="req">*</span></span>
                <input type="text" name="batch_name" id="edit-modal-batch-name" required>
            </label>

            <label>
                <span>Region / Department</span>
                <input type="text" name="region_department" id="edit-modal-region">
            </label>

            <label>
                <span>Licence Count <span class="req">*</span></span>
                <input type="number" name="licence_count" id="edit-modal-count" min="1" step="1" required>
            </label>

            <label>
                <span>Creation Date <span class="req">*</span></span>
                <input type="date" name="creation_date" id="edit-modal-creation-date" required>
            </label>

            <label>
                <span>Renewal Date <span class="req">*</span></span>
                <input type="date" name="renewal_date" id="edit-modal-renewal-date" required>
            </label>

            <div class="span-2" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px;">
                <div style="font-weight:700; color:#1e293b; margin-bottom:8px; font-size:13px;">
                    🌐 Platform Schedule (Operational Delivery Term)
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">Platform Start Date</span>
                        <input type="date" name="platform_start_date" id="edit-modal-platform-start">
                        <small class="muted" style="font-size:11px;">Leave empty to inherit Contract Creation Date</small>
                    </label>
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">Platform Renewal Date</span>
                        <input type="date" name="platform_renewal_date" id="edit-modal-platform-renewal">
                        <small class="muted" style="font-size:11px;">Leave empty to inherit Contract Renewal Date</small>
                    </label>
                </div>
            </div>

            <label class="span-2">
                <span>Given By</span>
                <input type="text" name="given_by" id="edit-modal-given-by">
            </label>

            <label class="span-2">
                <span>Notes / Licence Details</span>
                <textarea name="notes" id="edit-modal-notes" rows="3"></textarea>
            </label>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn">Update Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Enhanced Renew Modal (with History preservation) -->
<div class="modal-backdrop" id="renewBatchModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Renew / Extend Licence Batch</h3>
                <small class="muted">A new period cycle will be recorded in audit history</small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="/client-renewals/renew" class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="renew-modal-id">

            <div class="span-2 renew-batch-info">
                <strong id="renew-modal-batch-name"></strong>
                <span class="muted" id="renew-modal-context"></span>
            </div>

            <div class="span-2 renew-presets">
                <span class="field-label">Quick Extend Presets:</span>
                <div class="preset-buttons">
                    <button type="button" class="btn-preset" data-extend-months="12">+1 Year</button>
                    <button type="button" class="btn-preset" data-extend-months="6">+6 Months</button>
                    <button type="button" class="btn-preset" data-extend-months="3">+3 Months</button>
                </div>
            </div>

            <label>
                <span>Cycle Start Date <span class="req">*</span></span>
                <input type="date" name="period_start" id="renew-modal-start" value="<?= e(date('Y-m-d')) ?>" required>
            </label>

            <label>
                <span>New Renewal Date <span class="req">*</span></span>
                <input type="date" name="renewal_date" id="renew-modal-date" required>
            </label>

            <div class="span-2" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; margin-top:2px;">
                <label class="checkbox-inline" style="margin-bottom:0; font-size:12.5px; color:#334155; cursor:pointer;">
                    <input type="checkbox" id="renew-modal-has-platform-dates" onchange="document.getElementById('renew-modal-platform-fields').hidden = !this.checked">
                    <strong>Platform Schedule dates differ for this renewal cycle</strong>
                </label>
                <div id="renew-modal-platform-fields" hidden style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:10px;">
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">Platform Cycle Start</span>
                        <input type="date" name="platform_period_start" id="renew-modal-platform-start">
                        <small class="muted" style="font-size:11px;">Leave empty to use Cycle Start</small>
                    </label>
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">New Platform Renewal Date</span>
                        <input type="date" name="platform_renewal_date" id="renew-modal-platform-renewal">
                        <small class="muted" style="font-size:11px;">Leave empty to use New Renewal Date</small>
                    </label>
                </div>
            </div>

            <label class="span-2">
                <span>Renewed Licence Count <span class="req">*</span></span>
                <input type="number" name="licence_count" id="renew-modal-count" min="1" step="1" placeholder="e.g. 350" required>
                <small class="muted">Enter the renewed volume (can be higher, lower, or same as before)</small>
            </label>

            <label class="span-2">
                <span>Given By / Authorised By</span>
                <input type="text" name="given_by" id="renew-modal-given-by" placeholder="e.g. Vendor, EduRiser Account Manager">
            </label>

            <label class="span-2">
                <span>Renewal Notes / Purchase Order #</span>
                <textarea name="notes" id="renew-modal-notes" rows="2" placeholder="e.g. Renewed 350 licenses for FY26-27 under PO #994812"></textarea>
            </label>

            <!-- Project Contract Hours & Billing Integration -->
            <div class="span-2" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 14px; margin-top:2px;">
                <div style="font-weight:700; color:#1e293b; margin-bottom:8px; font-size:13px; display:flex; align-items:center; gap:6px;">
                    <span>⏱️ Project Contract Hours & Billing (Optional)</span>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:10px;">
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">Next Cycle Contract Hours</span>
                        <input type="number" name="contract_hours" id="renew-modal-contract-hours" min="0" step="0.01" placeholder="e.g. 500.00">
                        <small class="muted" style="font-size:11px;">Updates the project's contracted hours for the new cycle</small>
                    </label>
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">Invoice Reference / PO #</span>
                        <input type="text" name="invoice_reference" id="renew-modal-invoice-ref" placeholder="e.g. INV-2026-081">
                        <small class="muted" style="font-size:11px;">Invoice number for the billed contract</small>
                    </label>
                </div>
                <label class="checkbox-inline" style="margin-bottom:0; font-size:12.5px; color:#334155;">
                    <input type="checkbox" name="mark_previous_billed" value="1" checked>
                    <strong>Mark previous cycle hours as Billed (Invoiced)</strong>
                </label>
            </div>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn">Confirm Renewal & Save History</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. Dedicated Courtesy / Grace Extension Modal -->
<div class="modal-backdrop" id="extendBatchModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Grant Courtesy / Grace Extension</h3>
                <small class="muted">Recorded separately in audit history as an extension cycle</small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="/client-renewals/extend" class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="extend-modal-id">

            <div class="span-2" style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:6px; padding:10px 14px;">
                <strong id="extend-modal-batch-name" style="color:#6b21a8; font-size:14px;"></strong>
                <div class="muted" id="extend-modal-context" style="font-size:12px; margin-top:2px;"></div>
            </div>

            <div class="span-2 renew-presets">
                <span class="field-label">Quick Extension Presets:</span>
                <div class="preset-buttons">
                    <button type="button" class="btn-preset" data-extend-days="15">+15 Days</button>
                    <button type="button" class="btn-preset" data-extend-days="30">+1 Month</button>
                    <button type="button" class="btn-preset" data-extend-days="45">+45 Days</button>
                    <button type="button" class="btn-preset" data-extend-days="60">+2 Months</button>
                </div>
            </div>

            <label>
                <span>Extension From Date <span class="req">*</span></span>
                <input type="date" name="period_start" id="extend-modal-start" value="<?= e(date('Y-m-d')) ?>" required>
            </label>

            <label>
                <span>Extended Until Date <span class="req">*</span></span>
                <input type="date" name="extended_date" id="extend-modal-date" required>
            </label>

            <div class="span-2" style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:6px; padding:10px 14px;">
                <label class="checkbox-inline" style="margin-bottom:0; font-size:12.5px; color:#6b21a8; cursor:pointer;">
                    <input type="checkbox" id="extend-modal-has-platform-dates" onchange="document.getElementById('extend-modal-platform-fields').hidden = !this.checked">
                    <strong>Adjust Platform Schedule renewal date as well</strong>
                </label>
                <div id="extend-modal-platform-fields" hidden style="margin-top:10px;">
                    <label style="margin-bottom:0;">
                        <span style="font-size:12px;">New Platform Renewal Date</span>
                        <input type="date" name="platform_extended_date" id="extend-modal-platform-date">
                        <small class="muted" style="font-size:11px;">Leave empty to inherit Contract extended date</small>
                    </label>
                </div>
            </div>

            <label class="span-2">
                <span>Licence Volume During Extension <span class="req">*</span></span>
                <input type="number" name="licence_count" id="extend-modal-count" min="1" step="1" required>
            </label>

            <label class="span-2">
                <span>Reason for Extension <span class="req">*</span></span>
                <textarea name="extension_reason" id="extend-modal-reason" rows="2" placeholder="e.g. Granted 15 days grace period while client processes renewal invoice #8841" required></textarea>
            </label>

            <label class="span-2">
                <span>Dashboard Notes</span>
                <textarea name="notes" id="extend-modal-notes" rows="2" placeholder="Leave empty to auto-update with extension reason, or enter custom note..."></textarea>
                <small class="muted">This note will update and appear in the Schedule View Notes column.</small>
            </label>

            <label class="span-2">
                <span>Approved / Given By</span>
                <input type="text" name="given_by" id="extend-modal-given-by" placeholder="e.g. Sales Head / Management Approval">
            </label>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn" style="background:#7e22ce; border-color:#7e22ce; color:#fff;">Grant Extension</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.__pmsClientsWithProjects = <?= json_encode($clientsWithProjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php endif; ?>
