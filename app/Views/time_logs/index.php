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

<nav class="tabs">
    <a class="<?= $view === 'user' ? 'active' : '' ?>" href="/time-logs?view=user&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>">View by User</a>
    <a class="<?= $view === 'client' ? 'active' : '' ?>" href="/time-logs?view=client&from_date=<?= e($fromDate) ?>&to_date=<?= e($toDate) ?>">View by Client</a>
</nav>

<form method="get" action="/time-logs" class="panel filter-bar timelog-filter">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <label>
        From Date
        <input type="date" name="from_date" value="<?= e($fromDate) ?>">
    </label>
    <label>
        To Date
        <input type="date" name="to_date" value="<?= e($toDate) ?>">
    </label>
    <div class="filter-actions">
        <button type="submit">Apply</button>
    </div>
</form>

<?php $columnCount = count($dates) + 3; ?>
<div class="panel report-table-panel">
    <div class="wide-table-wrap">
        <table class="data-table timelog-table">
            <thead>
                <tr>
                    <th class="toggle-col"></th>
                    <th><?= $view === 'user' ? 'User' : 'Client' ?></th>
                    <?php foreach ($dates as $date): ?>
                        <th>
                            <?= e(date('d', strtotime($date))) ?><br>
                            <small><?= e(date('D', strtotime($date))) ?></small>
                        </th>
                    <?php endforeach; ?>
                    <th>Total</th>
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
                    $breakdownRows = \App\Models\WorkLog::rowsForBreakdown($breakdown[$row['name']] ?? [], $dates, $rowId);
                ?>
                <tr>
                    <td class="toggle-col">
                        <?php if ($breakdownRows): ?>
                            <button type="button" class="breakdown-toggle" data-breakdown-toggle data-target-parent="<?= e($rowId) ?>" aria-expanded="false">▸</button>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($row['name']) ?></strong></td>
                    <?php foreach ($dates as $date): ?>
                        <td><?= (float) $row['days'][$date] > 0 ? e((string) $row['days'][$date]) : '' ?></td>
                    <?php endforeach; ?>
                    <td><strong><?= e((string) $row['total']) ?></strong></td>
                </tr>
                <?php foreach ($breakdownRows as $node): ?>
                    <tr class="timelog-breakdown-row" data-breakdown-row data-row-id="<?= e($node['id']) ?>" data-parent-id="<?= e($node['parent']) ?>" hidden>
                        <td class="toggle-col">
                            <?php if ($node['hasChildren']): ?>
                                <button type="button" class="breakdown-toggle" data-breakdown-toggle data-target-parent="<?= e($node['id']) ?>" aria-expanded="false">▸</button>
                            <?php endif; ?>
                        </td>
                        <td class="breakdown-label" style="padding-left: <?= e((string) (16 + $node['depth'] * 18)) ?>px">
                            <?= e($node['label']) ?>
                        </td>
                        <?php foreach ($dates as $date): ?>
                            <td><?= $node['days'][$date] > 0 ? e(number_format($node['days'][$date], 2)) : '' ?></td>
                        <?php endforeach; ?>
                        <td><strong><?= e(number_format($node['total'], 2)) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
