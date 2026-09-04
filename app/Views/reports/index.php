<section class="section-header">
    <div>
        <h2>Reports</h2>
        <p>Detailed logs, billing status, and project service hours capacity tracking.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn ghost" onclick="history.back()">Back</button>
        <?php if (\App\Core\Permissions::canExport()): ?>
        <button type="button" class="btn ghost" data-open-modal="reportsExportModal" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export Excel
        </button>
        <?php endif; ?>
        <?php if (\App\Core\Permissions::canWrite()): ?>
        <a class="btn" href="<?= url('/work-logs') ?>">Add Daily Log</a>
        <?php endif; ?>
    </div>
</section>

<!-- KPI Billing Summary Cards -->
<section class="report-kpi-grid">
    <div class="stat-card">
        <span class="stat-label">Total Logged Time</span>
        <div class="stat-value"><?= e(number_format((float) ($billingStats['total_logged'] ?? 0), 2)) ?> <small>hrs</small></div>
        <div class="stat-subtext">Across all filtered logs</div>
    </div>
    <div class="stat-card">
        <span class="stat-label">Total Billable Hours</span>
        <div class="stat-value text-blue"><?= e(number_format((float) ($billingStats['total_billable'] ?? 0), 2)) ?> <small>hrs</small></div>
        <div class="stat-subtext"><?= e(number_format((float) ($billingStats['total_non_billable'] ?? 0), 2)) ?> hrs Non-Billable</div>
    </div>
    <div class="stat-card kpi-unbilled">
        <span class="stat-label">Unbilled Hours (To Invoice)</span>
        <div class="stat-value text-orange"><?= e(number_format((float) ($billingStats['unbilled_hours'] ?? 0), 2)) ?> <small>hrs</small></div>
        <div class="stat-subtext"><strong><?= e((string) ($billingStats['unbilled_count'] ?? 0)) ?></strong> pending billable entries</div>
    </div>
    <div class="stat-card kpi-billed">
        <span class="stat-label">Billed Hours (Invoiced)</span>
        <div class="stat-value text-success"><?= e(number_format((float) ($billingStats['billed_hours'] ?? 0), 2)) ?> <small>hrs</small></div>
        <div class="stat-subtext"><strong><?= e((string) ($billingStats['billed_count'] ?? 0)) ?></strong> invoiced entries</div>
    </div>
    <div class="stat-card" style="border-left: 4px solid #64748b; background: linear-gradient(to right, #f8fafc, #ffffff);">
        <span class="stat-label">Internal / R&amp;D Time</span>
        <div class="stat-value" style="color: #475569;"><?= e(number_format((float) ($billingStats['internal_hours'] ?? 0), 2)) ?> <small>hrs</small></div>
        <div class="stat-subtext">Internal training, meetings &amp; R&amp;D</div>
    </div>
</section>

<nav class="tabs">
    <a class="<?= $reportType === 'detailed' ? 'active' : '' ?>" href="<?= url('/reports?type=detailed') ?>">Detailed Work Log</a>
    <a class="<?= $reportType === 'service_hours' ? 'active' : '' ?>" href="<?= url('/reports?type=service_hours') ?>">Service Hours & Capacity</a>
    <a class="<?= $reportType === 'project_billing' ? 'active' : '' ?>" href="<?= url('/reports?type=project_billing') ?>">Project-wise Billing</a>
    <a class="<?= $reportType === 'phase_billing' ? 'active' : '' ?>" href="<?= url('/reports?type=phase_billing') ?>">Phase-wise Billing</a>
    <a class="<?= $reportType === 'customer' ? 'active' : '' ?>" href="<?= url('/reports?type=customer') ?>">Customer Report</a>
</nav>

<?php
    $searchLabel = 'Project Name';
    $searchPlaceholder = 'Search project';
    $searchAutosuggestKey = 'projectsOnly';
    if ($reportType === 'customer') {
        $searchLabel = 'Client Name';
        $searchPlaceholder = 'Search client';
        $searchAutosuggestKey = 'clientsOnly';
    }
?>
<?php require __DIR__ . '/../partials/suggestions.php'; ?>
<form method="get" action="<?= url('/reports') ?>" class="panel filter-bar report-filter" id="reportsFilterForm">
    <input type="hidden" name="type" value="<?= e($reportType) ?>">
    <label>
        From Date
        <input type="date" name="from_date" value="<?= e($filters['from_date'] ?? '') ?>">
    </label>
    <label>
        To Date
        <input type="date" name="to_date" value="<?= e($filters['to_date'] ?? '') ?>">
    </label>
    <?php if (!empty($clients) && in_array($reportType, ['detailed', 'service_hours', 'project_billing', 'phase_billing'], true)): ?>
    <label>
        Client
        <select name="client_id" id="report_filter_client_id">
            <option value="">All Clients</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= e((string) $client['id']) ?>" <?= (string) ($filters['client_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>>
                    <?= e($client['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <label>
        <?= e($searchLabel) ?>
        <input type="text" name="project_group" value="<?= e($filters['project_group'] ?? '') ?>" placeholder="<?= e($searchPlaceholder) ?>" data-autosuggest="<?= e($searchAutosuggestKey) ?>" autocomplete="off">
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
    <?php if (in_array($reportType, ['service_hours', 'project_billing'], true)): ?>
    <label>
        Contract Model
        <select name="contract_model">
            <option value="">All Contract Models</option>
            <option value="yearly_renewal" <?= ($filters['contract_model'] ?? '') === 'yearly_renewal' ? 'selected' : '' ?>>🔄 Yearly Renewal (Batches)</option>
            <option value="open_po" <?= ($filters['contract_model'] ?? '') === 'open_po' ? 'selected' : '' ?>>🔵 Open PO (Hourly Basis)</option>
            <option value="contract_cap" <?= ($filters['contract_model'] ?? '') === 'contract_cap' ? 'selected' : '' ?>>🟢 Contract Cap (Fixed Hours)</option>
            <option value="internal" <?= ($filters['contract_model'] ?? '') === 'internal' ? 'selected' : '' ?>>🏢 Internal / Non-Billable</option>
        </select>
    </label>
    <?php endif; ?>
    <?php if ($reportType === 'project_billing'): ?>
    <label>
        Billing Status
        <select name="billing_status">
            <option value="">All Billing Statuses</option>
            <option value="unbilled" <?= ($filters['billing_status'] ?? '') === 'unbilled' ? 'selected' : '' ?>>Unbilled (Pending)</option>
            <option value="partially_billed" <?= ($filters['billing_status'] ?? '') === 'partially_billed' ? 'selected' : '' ?>>Partially Billed</option>
            <option value="fully_billed" <?= ($filters['billing_status'] ?? '') === 'fully_billed' ? 'selected' : '' ?>>Fully Billed (100%)</option>
            <option value="internal" <?= ($filters['billing_status'] ?? '') === 'internal' ? 'selected' : '' ?>>Internal (Non-Billable)</option>
        </select>
    </label>
    <?php endif; ?>
    <?php if ($reportType === 'detailed' || $reportType === 'service_hours'): ?>
    <label>
        Billing Status
        <select name="billing_status">
            <option value="">All Statuses</option>
            <option value="unbilled" <?= ($filters['billing_status'] ?? '') === 'unbilled' ? 'selected' : '' ?>>Unbilled (Pending)</option>
            <option value="billed" <?= ($filters['billing_status'] ?? '') === 'billed' ? 'selected' : '' ?>>Billed (Invoiced)</option>
        </select>
    </label>
    <?php endif; ?>
    <?php if ($reportType === 'detailed'): ?>
    <label>
        Phase
        <select name="phase">
            <option value="">All Phases</option>
            <?php foreach ($distinctPhases ?? [] as $phaseOption): ?>
                <option value="<?= e($phaseOption) ?>" <?= ($filters['phase'] ?? '') === $phaseOption ? 'selected' : '' ?>>
                    <?= e($phaseOption) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Task List / Module
        <select name="module_name">
            <option value="">All Modules / Task Lists</option>
            <?php foreach ($distinctModules ?? [] as $moduleOption): ?>
                <option value="<?= e($moduleOption) ?>" <?= ($filters['module_name'] ?? '') === $moduleOption ? 'selected' : '' ?>>
                    <?= e($moduleOption) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        Logs Per Page
        <select name="per_page" onchange="this.form.submit()">
            <option value="25" <?= ($filters['per_page'] ?? '50') === '25' ? 'selected' : '' ?>>25 per page</option>
            <option value="50" <?= ($filters['per_page'] ?? '50') === '50' ? 'selected' : '' ?>>50 per page</option>
            <option value="100" <?= ($filters['per_page'] ?? '50') === '100' ? 'selected' : '' ?>>100 per page</option>
            <option value="250" <?= ($filters['per_page'] ?? '50') === '250' ? 'selected' : '' ?>>250 per page</option>
            <option value="500" <?= ($filters['per_page'] ?? '50') === '500' ? 'selected' : '' ?>>500 per page</option>
            <option value="all" <?= ($filters['per_page'] ?? '50') === 'all' ? 'selected' : '' ?>>All Logs</option>
        </select>
    </label>
    <?php endif; ?>
    <div class="filter-actions">
        <button type="submit">Apply</button>
        <a class="btn ghost" href="<?= url('/reports?type=' . e($reportType)) ?>">Reset</a>
    </div>
</form>

<div class="panel report-table-panel">
    <?php if ($reportType === 'detailed'): ?>
        <!-- ================= 1. DETAILED WORK LOG TAB ================= -->
        <?php if (empty($workLogs)): ?>
            <div class="empty-state slim">
                <h3>No report data</h3>
                <p>Add daily logs to generate this report.</p>
            </div>
        <?php else: ?>
            <?php
            $pageUrl = function (int $targetPage) use ($reportType, $filters): string {
                $params = array_filter([
                    'type' => $reportType,
                    'from_date' => $filters['from_date'] ?? '',
                    'to_date' => $filters['to_date'] ?? '',
                    'project_group' => $filters['project_group'] ?? '',
                    'phase' => $filters['phase'] ?? '',
                    'module_name' => $filters['module_name'] ?? '',
                    'user_id' => $filters['user_id'] ?? '',
                    'client_id' => $filters['client_id'] ?? '',
                    'billing_status' => $filters['billing_status'] ?? '',
                    'per_page' => $filters['per_page'] ?? '50',
                    'page' => $targetPage,
                ], fn ($v) => $v !== '' && $v !== null);
                return url('/reports?' . http_build_query($params));
            };
            ?>

            <?php if (!empty($pagination) && $pagination['total_items'] > 0): ?>
            <div class="report-pagination-wrap" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:12px;">
                <div style="font-size:13px; color:#475569;">
                    Showing <strong><?= e(number_format((int) $pagination['from_item'])) ?></strong> to <strong><?= e(number_format((int) $pagination['to_item'])) ?></strong> of <strong><?= e(number_format((int) $pagination['total_items'])) ?></strong> entries
                    <?php if ($pagination['per_page'] !== 'all'): ?>
                        &bull; Page <strong><?= e((string) $pagination['page']) ?></strong> of <strong><?= e((string) $pagination['total_pages']) ?></strong>
                    <?php endif; ?>
                </div>
                <?php if ($pagination['per_page'] !== 'all' && $pagination['total_pages'] > 1): ?>
                <div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="<?= $pageUrl(1) ?>" class="btn-tool ghost" title="First Page">&laquo; First</a>
                        <a href="<?= $pageUrl($pagination['page'] - 1) ?>" class="btn-tool ghost" title="Previous Page">&lsaquo; Prev</a>
                    <?php endif; ?>

                    <?php
                        $startP = max(1, $pagination['page'] - 2);
                        $endP = min($pagination['total_pages'], $pagination['page'] + 2);
                        if ($startP > 1) {
                            echo '<span class="muted" style="padding:0 4px;">...</span>';
                        }
                        for ($p = $startP; $p <= $endP; $p++) {
                            $isCurrent = $p === (int) $pagination['page'];
                            if ($isCurrent) {
                                echo '<span class="btn-tool" style="background:var(--primary, #0369a1); color:#fff; font-weight:700; min-width:32px; text-align:center;">' . $p . '</span>';
                            } else {
                                echo '<a href="' . $pageUrl($p) . '" class="btn-tool ghost" style="min-width:32px; text-align:center;">' . $p . '</a>';
                            }
                        }
                        if ($endP < $pagination['total_pages']) {
                            echo '<span class="muted" style="padding:0 4px;">...</span>';
                        }
                    ?>

                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="<?= $pageUrl($pagination['page'] + 1) ?>" class="btn-tool ghost" title="Next Page">Next &rsaquo;</a>
                        <a href="<?= $pageUrl($pagination['total_pages']) ?>" class="btn-tool ghost" title="Last Page">Last &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (\App\Core\Permissions::canWrite()): ?>
            <div class="bulk-billing-toolbar" id="bulkBillingToolbar" hidden>
                <div class="bulk-toolbar-info">
                    <span id="selectedLogCount">0</span> logs selected (<strong id="selectedLogHours">0.00</strong> hrs)
                </div>
                <div class="bulk-toolbar-actions">
                    <button type="button" class="btn" style="background:#16a34a; border-color:#16a34a; color:#fff;" id="btnOpenBulkBillModal">Mark Selected as Billed</button>
                    <button type="button" class="btn ghost" id="btnBulkUnbill">Mark as Unbilled</button>
                    <button type="button" class="btn ghost" id="btnClearSelected">Clear Selection</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="desktop-only-table">
                <div class="wide-table-wrap">
                    <table class="data-table report-table" id="workLogsReportTable">
                        <thead>
                            <tr>
                                <?php if (\App\Core\Permissions::canWrite()): ?>
                                <th class="text-center" style="width: 36px;">
                                    <input type="checkbox" id="selectAllLogsCheckbox" title="Select / Deselect all" aria-label="Select all rows">
                                </th>
                                <?php endif; ?>
                                <th>Project Group</th>
                                <th>Phase</th>
                                <th>Task List/Module</th>
                                <th>Task/General/Issue</th>
                                <th>Notes</th>
                                <th>Daily Log</th>
                                <th>Log Hours</th>
                                <th class="text-right">Hours</th>
                                <th>Date</th>
                                <th>Billing Type</th>
                                <th class="text-center">Billing Status</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($workLogs as $log): ?>
                            <?php
                                $isBilled = ($log['billing_status'] ?? 'unbilled') === 'billed';
                                $isBillable = ($log['billing_type'] ?? 'Billable') === 'Billable';
                            ?>
                            <tr data-log-id="<?= e((string) $log['id']) ?>" data-hours="<?= e((string) $log['hours']) ?>">
                                <?php if (\App\Core\Permissions::canWrite()): ?>
                                <td class="text-center">
                                    <?php if ($isBillable): ?>
                                    <input type="checkbox" class="log-select-checkbox" value="<?= e((string) $log['id']) ?>" data-hours="<?= e((string) $log['hours']) ?>" aria-label="Select row">
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td><?= e($log['resolved_project_name'] ?? $log['project_group']) ?></td>
                                <td><?= e($log['resolved_phase_name'] ?? $log['phase']) ?></td>
                                <td><?= e($log['resolved_module_name'] ?? $log['module_name']) ?></td>
                                <td><?= e($log['report_task_issue'] ?? $log['task_category']) ?></td>
                                <td><?= e($log['notes']) ?></td>
                                <td><?= e($log['daily_log'] ?: '-') ?></td>
                                <td><?= e(sprintf('%02d:%02d', intdiv((int) round(((float) $log['hours']) * 60), 60), ((int) round(((float) $log['hours']) * 60)) % 60)) ?></td>
                                <td class="text-right"><strong><?= e((string) $log['hours']) ?></strong></td>
                                <td><?= e($log['log_date']) ?></td>
                                <td>
                                    <span class="badge <?= $isBillable ? 'primary' : 'secondary' ?>">
                                        <?= e($log['billing_type']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if (!$isBillable): ?>
                                        <span class="billing-chip na" title="Non-billable activity">Non-Billable</span>
                                    <?php elseif ($isBilled): ?>
                                        <span class="billing-chip billed" data-log-id="<?= e((string) $log['id']) ?>" title="<?= e($log['invoice_reference'] ? 'Invoice: ' . $log['invoice_reference'] : 'Invoiced') ?>">
                                            ✓ Billed <?= !empty($log['invoice_reference']) ? '(' . e($log['invoice_reference']) . ')' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="billing-chip unbilled" data-log-id="<?= e((string) $log['id']) ?>" title="Click to mark as billed">
                                            Unbilled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($log['user_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Cards for Detailed Work Log -->
            <div class="mobile-only-cards report-mobile-cards">
                <?php foreach ($workLogs as $log): ?>
                    <?php
                        $isBilled = ($log['billing_status'] ?? 'unbilled') === 'billed';
                        $isBillable = ($log['billing_type'] ?? 'Billable') === 'Billable';
                    ?>
                    <article class="mobile-card report-worklog-card">
                        <header class="mobile-card-header">
                            <div class="mobile-card-header-left">
                                <span class="mobile-card-code"><?= e(date('d M Y', strtotime($log['log_date']))) ?></span>
                                <h4 class="mobile-card-title"><?= e($log['resolved_project_name'] ?? $log['project_group']) ?></h4>
                                <span class="mobile-card-sublink">
                                    <?= e($log['resolved_phase_name'] ?? $log['phase']) ?> &rsaquo; <?= e($log['resolved_module_name'] ?? $log['module_name']) ?>
                                </span>
                            </div>
                            <div class="mobile-card-header-right">
                                <?php if (!$isBillable): ?>
                                    <span class="billing-chip na">Non-Billable</span>
                                <?php elseif ($isBilled): ?>
                                    <span class="billing-chip billed" data-log-id="<?= e((string) $log['id']) ?>" title="<?= e($log['invoice_reference'] ? 'Invoice: ' . $log['invoice_reference'] : 'Invoiced') ?>">
                                        ✓ Billed
                                    </span>
                                <?php else: ?>
                                    <span class="billing-chip unbilled" data-log-id="<?= e((string) $log['id']) ?>">
                                        Unbilled
                                    </span>
                                <?php endif; ?>
                            </div>
                        </header>

                        <div class="mobile-card-body">
                            <?php if (!empty($log['notes'])): ?>
                            <div class="mobile-card-notes" style="margin-top: 4px; margin-bottom: 8px;">
                                <?= e($log['notes']) ?>
                            </div>
                            <?php endif; ?>

                            <div class="mobile-card-grid">
                                <div class="mobile-metric-item">
                                    <span class="mobile-metric-label">Logged Time</span>
                                    <span class="mobile-metric-val"><strong><?= e((string) $log['hours']) ?> hrs</strong></span>
                                </div>
                                <div class="mobile-metric-item">
                                    <span class="mobile-metric-label">Logged By</span>
                                    <span class="mobile-metric-val"><?= e($log['user_name']) ?></span>
                                </div>
                                <?php if (!empty($log['report_task_issue'] ?? $log['task_category'])): ?>
                                <div class="mobile-metric-item span-2">
                                    <span class="mobile-metric-label">Task / Activity</span>
                                    <span class="mobile-metric-val"><?= e($log['report_task_issue'] ?? $log['task_category']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($pagination) && $pagination['total_items'] > 0 && $pagination['per_page'] !== 'all' && $pagination['total_pages'] > 1): ?>
            <div class="report-pagination-wrap" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-top:12px;">
                <div style="font-size:13px; color:#475569;">
                    Page <strong><?= e((string) $pagination['page']) ?></strong> of <strong><?= e((string) $pagination['total_pages']) ?></strong> (<?= e(number_format((int) $pagination['total_items'])) ?> total entries)
                </div>
                <div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="<?= $pageUrl(1) ?>" class="btn-tool ghost" title="First Page">&laquo; First</a>
                        <a href="<?= $pageUrl($pagination['page'] - 1) ?>" class="btn-tool ghost" title="Previous Page">&lsaquo; Prev</a>
                    <?php endif; ?>

                    <?php
                        $startP = max(1, $pagination['page'] - 2);
                        $endP = min($pagination['total_pages'], $pagination['page'] + 2);
                        if ($startP > 1) {
                            echo '<span class="muted" style="padding:0 4px;">...</span>';
                        }
                        for ($p = $startP; $p <= $endP; $p++) {
                            $isCurrent = $p === (int) $pagination['page'];
                            if ($isCurrent) {
                                echo '<span class="btn-tool" style="background:var(--primary, #0369a1); color:#fff; font-weight:700; min-width:32px; text-align:center;">' . $p . '</span>';
                            } else {
                                echo '<a href="' . $pageUrl($p) . '" class="btn-tool ghost" style="min-width:32px; text-align:center;">' . $p . '</a>';
                            }
                        }
                        if ($endP < $pagination['total_pages']) {
                            echo '<span class="muted" style="padding:0 4px;">...</span>';
                        }
                    ?>

                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="<?= $pageUrl($pagination['page'] + 1) ?>" class="btn-tool ghost" title="Next Page">Next &rsaquo;</a>
                        <a href="<?= $pageUrl($pagination['total_pages']) ?>" class="btn-tool ghost" title="Last Page">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($reportType === 'service_hours'): ?>
        <!-- ================= 2. SERVICE HOURS & CAPACITY TAB ================= -->
        <?php if (empty($serviceHours)): ?>
            <div class="empty-state slim">
                <h3>No project capacity data</h3>
                <p>Add projects or log time entries to view service hours capacity.</p>
            </div>
        <?php else: ?>
            <div class="table-legend-banner" style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 13px; color: #475569; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <strong>Contract Types:</strong>
                    <span class="badge" style="background:#ecfeff; color:#0e7490; border:1px solid #a5f3fc; font-size:11px;">🔄 Yearly Renewal</span>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px;">🔵 Open PO (Hourly)</span>
                    <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:11px;">🟢 Cap (Fixed)</span>
                    <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-size:11px;">🏢 Internal</span>
                </div>
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                    <span><span class="service-alert-pill normal">Healthy (&lt;75%)</span></span>
                    <span><span class="service-alert-pill warning">Approaching (75%–89%)</span></span>
                    <span><span class="service-alert-pill critical">Critical Limit (&ge;90%)</span></span>
                </div>
            </div>
            <div class="wide-table-wrap">
                <table class="data-table report-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Contract Model</th>
                            <th style="min-width: 170px;">Billing Cycle / Period</th>
                            <th class="text-right">Build Hours</th>
                            <th class="text-right">Run Hours</th>
                            <th class="text-right">Total Allocated</th>
                            <th class="text-right">Billable Logged</th>
                            <th class="text-right">Non-Billable</th>
                            <th class="text-right">Total Logged</th>
                            <th class="text-right">Remaining Capacity</th>
                            <th class="text-center" style="min-width: 150px;">Capacity &amp; Alert</th>
                            <?php if (\App\Core\Permissions::canWrite()): ?>
                            <th class="text-center">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($serviceHours as $row): ?>
                        <tr class="<?= !empty($row['is_alert_90']) ? 'row-alert-critical' : '' ?>" style="<?= !empty($row['is_internal']) ? 'background: #fafbfc;' : '' ?>">
                            <td>
                                <strong><a href="<?= url('/projects/show?id=' . e((string) ($row['project_id'] ?? $row['id']))) ?>"><?= e($row['project_name']) ?></a></strong>
                                <?php if (!empty($row['project_code'])): ?>
                                    <small class="muted" style="display:block; font-size:11px;"><?= e($row['project_code']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($row['client_name'] ?: '-') ?></td>
                            <td>
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-size:11px; white-space:nowrap;">
                                        🏢 Internal
                                    </span>
                                <?php elseif (($row['contract_model_key'] ?? '') === 'yearly_renewal'): ?>
                                    <span class="badge" style="background:#ecfeff; color:#0e7490; border:1px solid #a5f3fc; font-size:11px; white-space:nowrap; font-weight:600;">
                                        <?= e($row['contract_model_label']) ?>
                                    </span>
                                <?php elseif (!empty($row['is_open_po'])): ?>
                                    <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px; white-space:nowrap; font-weight:600;">
                                        🔵 Open PO (Hourly)
                                    </span>
                                <?php elseif (!empty($row['total_allocated_hours']) && (float)$row['total_allocated_hours'] > 0): ?>
                                    <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:11px; white-space:nowrap; font-weight:600;">
                                        🟢 Cap: <?= e(number_format((float) $row['total_allocated_hours'], 2)) ?> hrs
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:11px; white-space:nowrap;">
                                        ⚪ Fixed / Unset
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:11.5px; font-weight:500; color:#334155; line-height:1.35;">
                                    <?= e($row['billing_cycle_label'] ?? '-') ?>
                                </div>
                                <?php if (!empty($row['cycle_logged']) && $row['cycle_logged'] > 0 && abs((float)$row['cycle_logged'] - (float)$row['logged_hours']) > 0.01): ?>
                                    <small class="muted" style="display:block; font-size:11px; color:#64748b; margin-top:3px;">
                                        Active Cycle: <strong><?= e(number_format((float)$row['cycle_logged'], 2)) ?></strong> hrs (<?= e(number_format((float)$row['cycle_billable'], 2)) ?> billable)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if (!empty($row['is_internal']) || !empty($row['is_open_po'])): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <?= e(number_format((float) ($row['build_hours'] ?? 0), 2)) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if (!empty($row['is_internal']) || !empty($row['is_open_po'])): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <?= e(number_format((float) ($row['run_hours'] ?? 0), 2)) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="muted">-</span>
                                <?php elseif (!empty($row['is_open_po'])): ?>
                                    <span class="badge info" style="background:#e0f2fe; color:#0369a1; font-weight:600;">Open PO</span>
                                <?php else: ?>
                                    <strong><?= e(number_format((float) ($row['total_allocated_hours'] ?? 0), 2)) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="text-right blue-text">
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <?= e(number_format((float) ($row['billable_hours'] ?? 0), 2)) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right orange-text"><?= e(number_format((float) ($row['non_billable_hours'] ?? 0), 2)) ?></td>
                            <td class="text-right"><strong><?= e(number_format((float) ($row['logged_hours'] ?? 0), 2)) ?></strong></td>
                            <td class="text-right <?= empty($row['is_internal']) && empty($row['is_open_po']) && (float)($row['remaining_hours'] ?? 0) < 0 ? 'text-danger' : '' ?>">
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="muted" style="font-size:11.5px;">Internal</span>
                                <?php elseif (!empty($row['is_open_po'])): ?>
                                    <span class="muted" style="font-size:12px;">&infin; (Hourly)</span>
                                <?php elseif ((float)($row['total_allocated_hours'] ?? 0) > 0): ?>
                                    <strong><?= e(number_format((float) ($row['remaining_hours'] ?? 0), 2)) ?> hrs</strong>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="service-alert-pill normal" style="background:#f1f5f9; color:#475569; border-color:#cbd5e1;" title="Internal Non-Billable Project: No commercial cap required">
                                        🏢 Internal (No Cap)
                                    </span>
                                <?php elseif (!empty($row['is_open_po'])): ?>
                                    <span class="service-alert-pill info" style="background:#e0f2fe; color:#0369a1; border-color:#bae6fd;" title="Open Purchase Order: Billed on actual hourly consumption">
                                        ♾️ Open PO (Hourly)
                                    </span>
                                <?php elseif ((float)($row['total_allocated_hours'] ?? 0) <= 0): ?>
                                    <span class="service-alert-pill muted" title="Allocated hours not defined yet">No Budget Set</span>
                                <?php elseif (!empty($row['is_alert_90'])): ?>
                                    <span class="service-alert-pill critical" title="Alert: <?= e((string) $row['consumption_percent']) ?>% of allocated hours consumed!">
                                        ⚠️ <?= e((string) $row['consumption_percent']) ?>% Critical
                                    </span>
                                <?php elseif (!empty($row['is_alert_75'])): ?>
                                    <span class="service-alert-pill warning" title="<?= e((string) $row['consumption_percent']) ?>% consumed">
                                        <?= e((string) $row['consumption_percent']) ?>% Approaching
                                    </span>
                                <?php else: ?>
                                    <span class="service-alert-pill normal">
                                        <?= e((string) $row['consumption_percent']) ?>% Healthy
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php if (\App\Core\Permissions::canWrite()): ?>
                            <td class="text-center">
                                <?php if (empty($row['is_internal'])): ?>
                                <button type="button" class="btn-tool secondary" data-action="edit-capacity"
                                    data-project-id="<?= e((string) ($row['project_id'] ?? $row['id'])) ?>"
                                    data-project-name="<?= e($row['project_name']) ?>"
                                    data-build-hours="<?= e((string) ($row['build_hours'] ?? 0)) ?>"
                                    data-run-hours="<?= e((string) ($row['run_hours'] ?? 0)) ?>"
                                    data-is-open-po="<?= !empty($row['is_open_po']) ? '1' : '0' ?>"
                                    title="Edit Allocated Build/Run Hours or Toggle Open PO">Edit Hours</button>
                                <?php else: ?>
                                <span class="muted" style="font-size:11px;">-</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($reportType === 'project_billing'): ?>
        <!-- ================= 3. PROJECT-WISE BILLING TAB ================= -->
        <?php if (empty($projectBilling)): ?>
            <div class="empty-state slim">
                <h3>No project billing data</h3>
                <p>Add projects or log billable time to track project billing.</p>
            </div>
        <?php else: ?>
            <div class="table-legend-banner" style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 13px; color: #475569; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <strong>Contract Types:</strong>
                    <span class="badge" style="background:#ecfeff; color:#0e7490; border:1px solid #a5f3fc; font-size:11px;">🔄 Yearly Renewal</span>
                    <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px;">🔵 Open PO (Hourly)</span>
                    <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:11px;">🟢 Cap (Fixed)</span>
                    <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-size:11px;">🏢 Internal</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <strong>Billing Status:</strong>
                    <span class="badge success">Fully Billed (100%)</span>
                    <span class="badge warning">Partially Billed</span>
                    <span class="badge" style="background:#ffedd5; color:#c2410c;">Unbilled (Pending)</span>
                </div>
            </div>
            <div class="wide-table-wrap">
                <table class="data-table report-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Contract Model</th>
                            <th style="min-width: 170px;">Billing Cycle / Period</th>
                            <th class="text-right">Total Logged</th>
                            <th class="text-right">Billable</th>
                            <th class="text-right">Billed (Invoiced)</th>
                            <th class="text-right">Unbilled (To Invoice)</th>
                            <th class="text-right">Contract Balance</th>
                            <th style="min-width: 170px;">Billing Progress</th>
                            <?php if (\App\Core\Permissions::canWrite()): ?>
                            <th class="text-center" style="min-width: 160px;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projectBilling as $row): ?>
                        <tr style="<?= !empty($row['is_internal']) ? 'background: #fafbfc;' : '' ?>">
                            <td>
                                <strong><a href="<?= url('/projects/show?id=' . e((string) $row['project_id'])) ?>"><?= e($row['project_name']) ?></a></strong>
                                <?php if (!empty($row['project_code'])): ?>
                                    <small class="muted" style="display:block; font-size:11px;"><?= e($row['project_code']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($row['client_name'] ?: '-') ?></td>
                            <td>
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-size:11px; white-space:nowrap;">
                                        🏢 Internal
                                    </span>
                                <?php elseif ($row['contract_model_key'] === 'yearly_renewal'): ?>
                                    <span class="badge" style="background:#ecfeff; color:#0e7490; border:1px solid #a5f3fc; font-size:11px; white-space:nowrap; font-weight:600;">
                                        <?= e($row['contract_model_label']) ?>
                                    </span>
                                <?php elseif ($row['is_open_po']): ?>
                                    <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-size:11px; white-space:nowrap; font-weight:600;">
                                        🔵 Open PO (Hourly)
                                    </span>
                                <?php elseif ($row['total_allocated_hours'] > 0): ?>
                                    <span class="badge" style="background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; font-size:11px; white-space:nowrap; font-weight:600;">
                                        🟢 Cap: <?= e(number_format($row['total_allocated_hours'], 2)) ?> hrs
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:11px; white-space:nowrap;">
                                        ⚪ Fixed / Unset
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:11.5px; font-weight:500; color:#334155; line-height:1.35;">
                                    <?= e($row['billing_cycle_label']) ?>
                                </div>
                                <?php if (!empty($row['cycle_logged']) && $row['cycle_logged'] > 0 && abs((float)$row['cycle_logged'] - (float)$row['total_logged']) > 0.01): ?>
                                    <small class="muted" style="display:block; font-size:11px; color:#64748b; margin-top:3px;">
                                        Active Cycle: <strong><?= e(number_format((float)$row['cycle_logged'], 2)) ?></strong> hrs (<?= e(number_format((float)$row['cycle_billable'], 2)) ?> billable)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= e(number_format((float) $row['total_logged'], 2)) ?></td>
                            <td class="text-right blue-text">
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <strong><?= e(number_format((float) $row['total_billable'], 2)) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="text-right text-success">
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <strong><?= e(number_format((float) $row['billed_hours'], 2)) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="text-right text-orange">
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="muted">-</span>
                                <?php else: ?>
                                    <strong><?= e(number_format((float) $row['unbilled_hours'], 2)) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php
                                    $cbLabel = (string) ($row['contract_balance_label'] ?? '-');
                                    $cbNum = str_replace([' hrs', ','], '', $cbLabel);
                                ?>
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="muted" style="font-size:11.5px;">Internal</span>
                                <?php elseif (!empty($row['is_open_po'])): ?>
                                    <span class="muted" style="font-size:12px;">&infin; (Hourly)</span>
                                <?php elseif ($cbLabel !== '-'): ?>
                                    <strong class="<?= (is_numeric($cbNum) && (float)$cbNum <= 0) ? 'text-danger' : 'text-success' ?>">
                                        <?= e($cbLabel) ?>
                                    </strong>
                                <?php else: ?>
                                    <span class="muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['is_internal'])): ?>
                                    <span class="badge" style="background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; font-size:11px; padding:3px 8px;">
                                        🏢 Internal (No Invoice Needed)
                                    </span>
                                <?php else: ?>
                                    <?php
                                        $statusClass = (string) ($row['billing_status_class'] ?? 'unbilled');
                                        $statusLabel = (string) ($row['billing_status_label'] ?? 'Pending');
                                        $billedPct = (float) ($row['billed_percent'] ?? 0);
                                    ?>
                                    <div style="display:flex; justify-content:space-between; font-size:11.5px; margin-bottom:3px;">
                                        <span class="badge <?= $statusClass === 'unbilled' ? '' : $statusClass ?>" style="<?= $statusClass === 'unbilled' ? 'background:#ffedd5; color:#c2410c;' : '' ?>">
                                            <?= e($statusLabel) ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($row['total_billable']) && (float)$row['total_billable'] > 0): ?>
                                    <div class="month-progress-bar-wrap" style="margin-bottom:0;" title="<?= e((string) $billedPct) ?>% Billed">
                                        <div class="month-bar-billed" style="width: <?= e((string) $billedPct) ?>%;"></div>
                                        <div class="month-bar-unbilled" style="width: <?= e((string) max(0, 100 - $billedPct)) ?>%;"></div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php if (\App\Core\Permissions::canWrite()): ?>
                            <td class="text-center">
                                <div style="display:inline-flex; gap:6px; align-items:center;">
                                    <?php if (empty($row['is_internal']) && $row['unbilled_hours'] > 0): ?>
                                    <button type="button" class="btn-tool" style="background:#16a34a; border-color:#16a34a; color:#fff;"
                                        data-action="bill-project"
                                        data-project-id="<?= e((string) $row['project_id']) ?>"
                                        data-project-name="<?= e($row['project_name']) ?>"
                                        data-unbilled-hours="<?= e(number_format((float) $row['unbilled_hours'], 2)) ?>"
                                        title="Mark all unbilled hours for this project as Billed">Mark Billed</button>
                                    <?php endif; ?>
                                    <?php if (empty($row['is_internal']) && $row['billed_hours'] > 0): ?>
                                    <button type="button" class="btn-tool secondary"
                                        data-action="unbill-project"
                                        data-project-id="<?= e((string) $row['project_id']) ?>"
                                        data-project-name="<?= e($row['project_name']) ?>"
                                        title="Revert project logs back to unbilled">Unbill</button>
                                    <?php endif; ?>
                                    <a class="btn-tool ghost" href="<?= url('/reports?type=detailed&project_group=' . urlencode($row['project_name'])) ?>" title="View detailed logs">Logs &rarr;</a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($reportType === 'phase_billing'): ?>
        <!-- ================= 4. PHASE-WISE BILLING TAB ================= -->
        <?php if (empty($phaseBilling)): ?>
            <div class="empty-state slim">
                <h3>No phase billing data</h3>
                <p>Add phases to projects or log billable time to track phase billing.</p>
            </div>
        <?php else: ?>
            <div class="table-legend-banner" style="margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 13px; color: #475569; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <div>
                    <strong>Phase-wise Milestone Billing Status:</strong> Mark milestone phases as billed or unbilled as work completes.
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="badge success">Fully Billed</span>
                    <span class="badge warning">Partially Billed</span>
                    <span class="badge" style="background:#ffedd5; color:#c2410c;">Unbilled</span>
                </div>
            </div>
            <div class="wide-table-wrap">
                <table class="data-table report-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Milestone Phase</th>
                            <th>Client</th>
                            <th class="text-right">Total Logged</th>
                            <th class="text-right">Billable</th>
                            <th class="text-right">Billed (Invoiced)</th>
                            <th class="text-right">Unbilled (Left to Bill)</th>
                            <th style="min-width: 170px;">Billing Progress</th>
                            <?php if (\App\Core\Permissions::canWrite()): ?>
                            <th class="text-center" style="min-width: 160px;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($phaseBilling as $row): ?>
                        <tr>
                            <td>
                                <strong><a href="<?= url('/projects/show?id=' . e((string) $row['project_id'])) ?>"><?= e($row['project_name']) ?></a></strong>
                            </td>
                            <td>
                                <span class="badge secondary" style="font-size:12px; font-weight:600;"><?= e($row['phase_name']) ?></span>
                            </td>
                            <td><?= e($row['client_name'] ?: '-') ?></td>
                            <td class="text-right"><?= e(number_format((float) $row['total_logged'], 2)) ?></td>
                            <td class="text-right blue-text"><strong><?= e(number_format((float) $row['total_billable'], 2)) ?></strong></td>
                            <td class="text-right text-success"><strong><?= e(number_format((float) $row['billed_hours'], 2)) ?></strong></td>
                            <td class="text-right text-orange"><strong><?= e(number_format((float) $row['unbilled_hours'], 2)) ?></strong></td>
                            <td>
                                <div style="display:flex; justify-content:space-between; font-size:11.5px; margin-bottom:3px;">
                                    <span class="badge <?= $row['billing_status_class'] === 'unbilled' ? '' : $row['billing_status_class'] ?>" style="<?= $row['billing_status_class'] === 'unbilled' ? 'background:#ffedd5; color:#c2410c;' : '' ?>">
                                        <?= e($row['billing_status_label']) ?>
                                    </span>
                                </div>
                                <?php if ($row['total_billable'] > 0): ?>
                                <div class="month-progress-bar-wrap" style="margin-bottom:0;" title="<?= e((string) $row['billed_percent']) ?>% Billed">
                                    <div class="month-bar-billed" style="width: <?= e((string) $row['billed_percent']) ?>%;"></div>
                                    <div class="month-bar-unbilled" style="width: <?= e((string) (100 - $row['billed_percent'])) ?>%;"></div>
                                </div>
                                <?php endif; ?>
                            </td>
                            <?php if (\App\Core\Permissions::canWrite()): ?>
                            <td class="text-center">
                                <div style="display:inline-flex; gap:6px; align-items:center;">
                                    <?php if ($row['unbilled_hours'] > 0): ?>
                                    <button type="button" class="btn-tool" style="background:#16a34a; border-color:#16a34a; color:#fff;"
                                        data-action="bill-phase"
                                        data-phase-id="<?= e((string) $row['phase_id']) ?>"
                                        data-phase-name="<?= e($row['phase_name']) ?>"
                                        data-project-name="<?= e($row['project_name']) ?>"
                                        data-unbilled-hours="<?= e(number_format((float) $row['unbilled_hours'], 2)) ?>"
                                        title="Mark all unbilled hours for this phase as Billed">Mark Billed</button>
                                    <?php endif; ?>
                                    <?php if ($row['billed_hours'] > 0): ?>
                                    <button type="button" class="btn-tool secondary"
                                        data-action="unbill-phase"
                                        data-phase-id="<?= e((string) $row['phase_id']) ?>"
                                        data-phase-name="<?= e($row['phase_name']) ?>"
                                        title="Revert phase logs back to unbilled">Unbill</button>
                                    <?php endif; ?>
                                    <a class="btn-tool ghost" href="<?= url('/reports?type=detailed&project_group=' . urlencode($row['project_name'])) ?>" title="View detailed logs">Logs &rarr;</a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($reportType === 'project' || $reportType === 'customer'): ?>
        <!-- ================= 5. SUMMARY TABLES: Customer / Project ================= -->
        <?php $summaryRows = $reportType === 'project' ? $projectSummary : $customerSummary; ?>
        <?php if (!$summaryRows): ?>
            <div class="empty-state slim">
                <h3>No report data</h3>
                <p>Add daily logs to generate this report.</p>
            </div>
        <?php else: ?>
            <table class="data-table report-table">
                <thead>
                    <tr>
                        <th><?= $reportType === 'project' ? 'Project Name' : 'Client / Customer Name' ?></th>
                        <th class="text-right">Billable Hours</th>
                        <th class="text-right">Non-Billable Hours</th>
                        <th class="text-right">Total Logged Hours</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($summaryRows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['group_name']) ?></strong></td>
                        <td class="text-right blue-text"><strong><?= e(number_format((float) $row['billable_hours'], 2)) ?></strong></td>
                        <td class="text-right text-orange"><?= e(number_format((float) $row['non_billable_hours'], 2)) ?></td>
                        <td class="text-right"><strong><?= e(number_format((float) $row['logged_hours'], 2)) ?></strong></td>
                        <td class="text-center">
                            <a class="btn-tool ghost" href="<?= url('/reports?type=detailed&project_group=' . urlencode($row['group_name'])) ?>" title="View logs for this <?= $reportType === 'project' ? 'project' : 'client' ?>">View Logs &rarr;</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ================= MODAL: Quick Mark Billed (Single or Bulk) ================= -->
<div class="modal-backdrop" id="markBilledModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Mark Hours as Billed</h3>
                <small class="muted" id="markBilledSubtext">Record invoice reference for client billing</small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="<?= url('/work-logs/mark-billed') ?>" class="modal-body form-grid" id="markBilledForm">
            <?= csrf_field() ?>
            <div id="markBilledHiddenInputs"></div>

            <div class="span-2" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 14px;">
                <strong id="markBilledSummary" style="color:#15803d; font-size:14px;"></strong>
                <div class="muted" style="font-size:12px; margin-top:2px;">These hours will be marked as invoiced to the client.</div>
            </div>

            <label class="span-2">
                <span>Invoice Number / Reference</span>
                <input type="text" name="invoice_reference" id="modal_invoice_reference" placeholder="e.g. INV-2026-081 or PO #9948">
                <small class="muted">Optional reference number to easily cross-reference with your accounting system</small>
            </label>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn" style="background:#16a34a; border-color:#16a34a; color:#fff;">Confirm & Mark as Billed</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: Quick Edit Project Service Hours ================= -->
<div class="modal-backdrop" id="editServiceHoursModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Edit Allocated Service Hours</h3>
                <small class="muted" id="serviceHoursModalProjectName"></small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="<?= url('/projects/quick-service-hours') ?>" class="modal-body form-grid" id="editServiceHoursForm">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" id="service_hours_project_id">

            <div class="span-2" style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:10px 14px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:600; color:#0369a1; margin-bottom:0;">
                    <input type="checkbox" name="is_open_po" id="modal_is_open_po" value="1" style="width:18px; height:18px; cursor:pointer;">
                    <span>Open PO (Hourly Basis &mdash; No Budget Cap)</span>
                </label>
                <div class="muted" style="font-size:12px; margin-top:4px; color:#0284c7;">
                    Check this option if this project is billed directly on actual hourly logs without a fixed Build/Run hours budget limit.
                </div>
            </div>

            <label id="modal_build_hours_wrap">
                <span>Allocated Build Hours</span>
                <input type="number" name="build_hours" id="modal_build_hours" min="0" step="0.01" value="0.00">
            </label>

            <label id="modal_run_hours_wrap">
                <span>Allocated Run Hours</span>
                <input type="number" name="run_hours" id="modal_run_hours" min="0" step="0.01" value="0.00">
            </label>

            <div class="span-2" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:600; color:#334155;">Total Allocated Budget:</span>
                <strong id="modal_total_allocated_preview" style="font-size:16px; color:#0f172a;">0.00 hrs</strong>
            </div>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn">Save Service Hours</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: Mark Entire Project as Billed ================= -->
<div class="modal-backdrop" id="markProjectBillingModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Mark Project as Billed</h3>
                <small class="muted" id="markProjectModalSubtext">Mark all billable hours for this project as invoiced</small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="<?= url('/billing/mark-project') ?>" class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" id="modal_bill_project_id">
            <input type="hidden" name="action" value="billed">

            <div class="span-2" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 14px;">
                <div style="font-weight:700; color:#15803d; font-size:15px;" id="modal_bill_project_name"></div>
                <div style="color:#166534; font-size:13px; margin-top:2px;">
                    <strong id="modal_bill_project_unbilled_hours">0.00</strong> unbilled hours will be marked as Billed.
                </div>
            </div>

            <label class="span-2">
                <span>Invoice Number / Reference</span>
                <input type="text" name="invoice_reference" placeholder="e.g. INV-2026-081 or PO #9948">
                <small class="muted">Optional reference to cross-reference with client invoice</small>
            </label>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn" style="background:#16a34a; border-color:#16a34a; color:#fff;">Confirm & Mark Project Billed</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: Mark Milestone Phase as Billed ================= -->
<div class="modal-backdrop" id="markPhaseBillingModal" hidden>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3>Mark Milestone Phase as Billed</h3>
                <small class="muted" id="markPhaseModalSubtext">Mark all billable hours for this phase as invoiced</small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post" action="<?= url('/billing/mark-phase') ?>" class="modal-body form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="phase_id" id="modal_bill_phase_id">
            <input type="hidden" name="action" value="billed">

            <div class="span-2" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 14px;">
                <div style="font-size:12px; color:#475569;" id="modal_bill_phase_project_name"></div>
                <div style="font-weight:700; color:#15803d; font-size:15px;" id="modal_bill_phase_name"></div>
                <div style="color:#166534; font-size:13px; margin-top:2px;">
                    <strong id="modal_bill_phase_unbilled_hours">0.00</strong> unbilled hours will be marked as Billed.
                </div>
            </div>

            <label class="span-2">
                <span>Invoice Number / Reference</span>
                <input type="text" name="invoice_reference" placeholder="e.g. INV-2026-081 or PO #9948">
                <small class="muted">Optional reference to cross-reference with client invoice</small>
            </label>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn" style="background:#16a34a; border-color:#16a34a; color:#fff;">Confirm & Mark Phase Billed</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: Smart Reports Excel Export ================= -->
<div class="modal-backdrop" id="reportsExportModal" hidden>
    <div class="modal-dialog" style="max-width: 620px;">
        <div class="modal-header">
            <div>
                <h3>Export Reports to Excel (.xlsx)</h3>
                <small class="muted">Generate structured Excel timesheets and billing reports</small>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="get" action="<?= url('/reports/export') ?>" class="modal-body form-grid" id="reportsExportForm">
            <label class="span-2">
                <span>Report Type / Dataset</span>
                <select name="type" id="export-report-type">
                    <option value="detailed" <?= $reportType === 'detailed' ? 'selected' : '' ?>>Detailed Work Log (Individual Task Entries & Timesheets)</option>
                    <option value="project_billing" <?= $reportType === 'project_billing' ? 'selected' : '' ?>>Project-wise Billing Summary</option>
                    <option value="service_hours" <?= $reportType === 'service_hours' ? 'selected' : '' ?>>Service Hours & Capacity Report</option>
                    <option value="phase_billing" <?= $reportType === 'phase_billing' ? 'selected' : '' ?>>Phase-wise Billing Summary</option>
                    <option value="customer" <?= $reportType === 'customer' ? 'selected' : '' ?>>Customer / Client Summary</option>
                </select>
            </label>

            <div class="span-2">
                <span class="field-label" style="display: block; margin-bottom: 6px; font-weight: 600;">Quick Date Range Presets:</span>
                <div class="preset-buttons" style="display:flex; flex-wrap:wrap; gap:6px;">
                    <button type="button" class="btn-preset" data-export-preset="all">All Time</button>
                    <button type="button" class="btn-preset" data-export-preset="this_month">This Month</button>
                    <button type="button" class="btn-preset" data-export-preset="last_month">Last Month</button>
                    <button type="button" class="btn-preset" data-export-preset="this_year">This Year (<?= date('Y') ?>)</button>
                    <button type="button" class="btn-preset" data-export-preset="last_year">Last Year (<?= date('Y') - 1 ?>)</button>
                    <button type="button" class="btn-preset" data-export-preset="last_90">Last 90 Days</button>
                </div>
            </div>

            <label>
                <span>From Date</span>
                <input type="date" name="from_date" id="export-modal-from-date" value="<?= e($filters['from_date'] ?? '') ?>">
            </label>

            <label>
                <span>To Date</span>
                <input type="date" name="to_date" id="export-modal-to-date" value="<?= e($filters['to_date'] ?? '') ?>">
            </label>

            <label>
                <span>Client Scope</span>
                <select name="client_id" id="export-modal-client-select">
                    <option value="">All Clients</option>
                    <?php foreach ($clients ?? [] as $client): ?>
                        <option value="<?= e((string) $client['id']) ?>" <?= (string) ($filters['client_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>>
                            <?= e($client['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Project Name</span>
                <input type="text" name="project_group" id="export-modal-project-group" value="<?= e($filters['project_group'] ?? '') ?>" placeholder="Search or select project" data-autosuggest="projectsOnly" autocomplete="off">
            </label>

            <label>
                <span>User / Team Member</span>
                <select name="user_id" id="export-modal-user-select">
                    <option value="">All Users</option>
                    <?php foreach ($users ?? [] as $user): ?>
                        <option value="<?= e((string) $user['id']) ?>" <?= (string) ($filters['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>>
                            <?= e($user['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Billing Status</span>
                <select name="billing_status" id="export-modal-billing-status">
                    <option value="">All Statuses</option>
                    <option value="unbilled" <?= ($filters['billing_status'] ?? '') === 'unbilled' ? 'selected' : '' ?>>Unbilled (Pending)</option>
                    <option value="billed" <?= ($filters['billing_status'] ?? '') === 'billed' ? 'selected' : '' ?>>Billed (Invoiced)</option>
                    <option value="partially_billed" <?= ($filters['billing_status'] ?? '') === 'partially_billed' ? 'selected' : '' ?>>Partially Billed</option>
                    <option value="fully_billed" <?= ($filters['billing_status'] ?? '') === 'fully_billed' ? 'selected' : '' ?>>Fully Billed (100%)</option>
                    <option value="internal" <?= ($filters['billing_status'] ?? '') === 'internal' ? 'selected' : '' ?>>Internal (Non-Billable)</option>
                </select>
            </label>

            <label>
                <span>Phase</span>
                <select name="phase" id="export-modal-phase-select">
                    <option value="">All Phases</option>
                    <?php foreach ($distinctPhases ?? [] as $phaseOption): ?>
                        <option value="<?= e($phaseOption) ?>" <?= ($filters['phase'] ?? '') === $phaseOption ? 'selected' : '' ?>>
                            <?= e($phaseOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Task List / Module</span>
                <select name="module_name" id="export-modal-module-select">
                    <option value="">All Task Lists / Modules</option>
                    <?php foreach ($distinctModules ?? [] as $moduleOption): ?>
                        <option value="<?= e($moduleOption) ?>" <?= ($filters['module_name'] ?? '') === $moduleOption ? 'selected' : '' ?>>
                            <?= e($moduleOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="span-2" id="export-modal-group-by-box">
                <span>Group Detailed Export By</span>
                <select name="group_by" id="export-modal-group-by">
                    <option value="user">Group by User (Separate Sheets per User)</option>
                    <option value="client">Group by Client</option>
                    <option value="phase">Group by Phase</option>
                    <option value="tasklist">Group by Task List</option>
                </select>
            </label>

            <div class="modal-footer span-2">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn" style="background:#0284c7; border-color:#0284c7; color:#fff; display:inline-flex; align-items:center; gap:6px;">
                    <span>📥</span> Download Excel Report
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.__pmsAllProjects = <?= json_encode($allProjectsWithClients ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

