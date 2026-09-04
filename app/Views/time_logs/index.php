<section class="section-header">
    <div>
        <h2>Time Logs</h2>
        <p>View logged hours by user or by client.</p>
    </div>
    <div class="header-actions">
        <button type="button" class="btn ghost" onclick="history.back()">Back</button>
        <a class="btn" href="/work-logs">Add Time Log</a>
    </div>
</section>

<?php $tabQuery = http_build_query(['month' => $month]); ?>
<nav class="tabs">
    <a class="<?= $view === 'user' ? 'active' : '' ?>" href="/time-logs?view=user&<?= e($tabQuery) ?>">View by User</a>
    <a class="<?= $view === 'client' ? 'active' : '' ?>" href="/time-logs?view=client&<?= e($tabQuery) ?>">View by Client</a>
</nav>

<form method="get" action="/time-logs" class="simple-month-stepper">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <button type="button" class="month-step-btn" data-month-step="-1" aria-label="Previous Month">&lsaquo;</button>
    <div class="month-step-center">
        <input type="month" name="month" value="<?= e($month) ?>" data-month-input onchange="this.form.submit()">
    </div>
    <button type="button" class="month-step-btn" data-month-step="1" aria-label="Next Month">&rsaquo;</button>
</form>

<?php $columnCount = count($dates) + 2; ?>
<div class="panel report-table-panel">
    <div class="desktop-only-table">
        <div class="wide-table-wrap">
            <table class="data-table timelog-table">
                <thead>
                    <tr>
                        <th class="timelog-name-th"><?= $view === 'user' ? 'User / Project / Phase / Task' : 'Client / Project / Phase / Task' ?></th>
                        <?php foreach ($dates as $date): ?>
                            <th class="timelog-date-th">
                                <?= e(date('d', strtotime($date))) ?><br>
                                <small><?= e(date('D', strtotime($date))) ?></small>
                            </th>
                        <?php endforeach; ?>
                        <th class="timelog-total-th">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="<?= e((string) $columnCount) ?>">No logs found.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rows as $index => $row): ?>
                    <?php
                        $rowId = 'row-' . $index;
                        $breakdownRows = \App\Models\WorkLog::rowsForBreakdown($breakdown[$row['name']] ?? [], $dates, $rowId, $breakdownTaskIds[$row['name']] ?? []);
                    ?>
                    <tr class="timelog-root-row">
                        <td class="timelog-name-cell depth-0">
                            <div class="tree-cell-inner">
                                <?php if ($breakdownRows): ?>
                                    <button type="button" class="breakdown-toggle" data-breakdown-toggle data-target-parent="<?= e($rowId) ?>" aria-expanded="false">▸</button>
                                <?php else: ?>
                                    <span class="tree-toggle-spacer"></span>
                                <?php endif; ?>
                                <span class="tree-badge root-badge"><?= $view === 'user' ? '👤 User' : '🏢 Client' ?></span>
                                <strong class="tree-title root-title"><?= e($row['name']) ?></strong>
                            </div>
                        </td>
                        <?php foreach ($dates as $date): ?>
                            <td class="timelog-day-cell"><?= (float) $row['days'][$date] > 0 ? e((string) $row['days'][$date]) : '' ?></td>
                        <?php endforeach; ?>
                        <td class="timelog-total-cell"><strong><?= e((string) $row['total']) ?></strong></td>
                    </tr>
                    <?php foreach ($breakdownRows as $node): ?>
                        <?php
                            $depth = (int) $node['depth'] + 1; // 1 = Project, 2 = Phase, 3 = Task
                            $indentPx = 12 + ($depth * 24);
                        ?>
                        <tr class="timelog-breakdown-row depth-<?= $depth ?>" data-breakdown-row data-row-id="<?= e($node['id']) ?>" data-parent-id="<?= e($node['parent']) ?>" hidden>
                            <td class="timelog-name-cell depth-<?= $depth ?>" style="padding-left: <?= $indentPx ?>px;">
                                <div class="tree-cell-inner">
                                    <?php if ($node['hasChildren']): ?>
                                        <button type="button" class="breakdown-toggle" data-breakdown-toggle data-target-parent="<?= e($node['id']) ?>" aria-expanded="false">▸</button>
                                    <?php else: ?>
                                        <span class="tree-toggle-spacer"></span>
                                    <?php endif; ?>

                                    <?php if ($depth === 1): ?>
                                        <span class="tree-badge project-badge">📁 Project</span>
                                        <span class="tree-title project-title"><?= e($node['label']) ?></span>
                                    <?php elseif ($depth === 2): ?>
                                        <span class="tree-badge phase-badge">📑 Phase</span>
                                        <span class="tree-title phase-title"><?= e($node['label']) ?></span>
                                    <?php elseif ($depth === 3): ?>
                                        <span class="tree-badge module-badge">📦 Module</span>
                                        <span class="tree-title module-title"><?= e($node['label']) ?></span>
                                    <?php else: ?>
                                        <span class="tree-badge task-badge">📄 Task</span>
                                        <?php if ($node['taskId']): ?>
                                            <a class="tree-title task-link" href="<?= url('/tasks/show?id=' . $node['taskId']) ?>"><?= e($node['label']) ?></a>
                                        <?php else: ?>
                                            <span class="tree-title task-title"><?= e($node['label']) ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php foreach ($dates as $date): ?>
                                <td class="timelog-day-cell"><?= $node['days'][$date] > 0 ? e(number_format($node['days'][$date], 2)) : '' ?></td>
                            <?php endforeach; ?>
                            <td class="timelog-total-cell"><?= e(number_format($node['total'], 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Time Log Summary Cards -->
    <div class="mobile-only-cards timelog-mobile-cards">
        <?php if (!$rows): ?>
            <div class="empty-state slim">
                <p>No time logs found for <?= e(date('F Y', strtotime($month . '-01'))) ?>.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($rows as $index => $row): ?>
            <?php
                $rowId = 'row-' . $index;
                $breakdownRows = \App\Models\WorkLog::rowsForBreakdown($breakdown[$row['name']] ?? [], $dates, $rowId, $breakdownTaskIds[$row['name']] ?? []);
            ?>
            <article class="mobile-card timelog-summary-card">
                <header class="mobile-card-header">
                    <div class="mobile-card-header-left">
                        <span class="mobile-card-client-tag"><?= $view === 'user' ? '👤 Team Member' : '🏢 Client' ?></span>
                        <h4 class="mobile-card-title"><?= e($row['name']) ?></h4>
                        <span class="mobile-card-sublink"><?= e(date('F Y', strtotime($month . '-01'))) ?></span>
                    </div>
                    <div class="mobile-card-header-right">
                        <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:13.5px; font-weight:700; padding:6px 12px; border-radius:20px; white-space:nowrap;">
                            <?= e((string) $row['total']) ?> hrs
                        </span>
                    </div>
                </header>

                <div class="mobile-card-body">
                    <?php if (!empty($breakdownRows)): ?>
                    <details class="mobile-breakdown-details" style="background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; padding:8px 10px;">
                        <summary style="font-size:12px; font-weight:600; color:#475569; cursor:pointer;">
                            <span>Project & Activity Breakdown (<?= count($breakdownRows) ?>)</span>
                        </summary>
                        <div style="display:flex; flex-direction:column; gap:6px; margin-top:8px; padding-top:6px; border-top:1px dashed #cbd5e1;">
                            <?php foreach ($breakdownRows as $node): ?>
                                <?php $depth = (int) $node['depth'] + 1; ?>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:11.5px; padding-left:<?= ($depth - 1) * 10 ?>px;">
                                    <span style="color:<?= $depth === 1 ? '#0f172a; font-weight:600;' : '#475569;' ?>">
                                        <?= $depth === 1 ? '📁 ' : ($depth === 2 ? '📑 ' : '📦 ') ?><?= e($node['label']) ?>
                                    </span>
                                    <strong style="color:#0284c7;"><?= e(number_format((float) $node['total'], 2)) ?>h</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>

                <footer class="mobile-card-footer">
                    <div class="mobile-card-actions">
                        <a class="btn-tool" href="<?= url('/reports?type=detailed&from_date=' . $month . '-01&to_date=' . date('Y-m-t', strtotime($month . '-01')) . ($view === 'client' ? '&project_group=' . urlencode($row['name']) : '')) ?>" style="font-weight:600;">
                            📋 Detailed Logs for <?= e(date('M Y', strtotime($month . '-01'))) ?>
                        </a>
                    </div>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
</div>
