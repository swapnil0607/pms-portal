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

<?php
$sumHours = function (array $branch) use (&$sumHours): float {
    $total = 0.0;
    foreach ($branch as $value) {
        $total += is_array($value) ? $sumHours($value) : (float) $value;
    }
    return $total;
};
$columnCount = count($dates) + 3;
?>
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
            <?php foreach ($rows as $row): ?>
                <?php $rowBreakdown = $breakdown[$row['name']] ?? []; ?>
                <tr>
                    <td class="toggle-col">
                        <?php if ($rowBreakdown): ?>
                            <button type="button" class="breakdown-toggle" data-breakdown-toggle aria-expanded="false">▸</button>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= e($row['name']) ?></strong></td>
                    <?php foreach ($dates as $date): ?>
                        <td><?= (float) $row['days'][$date] > 0 ? e((string) $row['days'][$date]) : '' ?></td>
                    <?php endforeach; ?>
                    <td><strong><?= e((string) $row['total']) ?></strong></td>
                </tr>
                <?php if ($rowBreakdown): ?>
                    <tr class="timelog-breakdown-row" data-breakdown-row hidden>
                        <td></td>
                        <td colspan="<?= e((string) ($columnCount - 1)) ?>">
                            <div class="breakdown-tree">
                                <?php if ($view === 'user'): ?>
                                    <?php foreach ($rowBreakdown as $clientName => $taskLists): ?>
                                        <details class="breakdown-group">
                                            <summary><span><?= e($clientName) ?></span><span class="breakdown-hours"><?= e(number_format($sumHours($taskLists), 2)) ?> hrs</span></summary>
                                            <?php foreach ($taskLists as $taskListName => $tasks): ?>
                                                <details class="breakdown-group nested">
                                                    <summary><span><?= e($taskListName) ?></span><span class="breakdown-hours"><?= e(number_format($sumHours($tasks), 2)) ?> hrs</span></summary>
                                                    <ul>
                                                        <?php foreach ($tasks as $taskLabel => $hours): ?>
                                                            <li><span><?= e($taskLabel) ?></span><span class="breakdown-hours"><?= e(number_format((float) $hours, 2)) ?> hrs</span></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </details>
                                            <?php endforeach; ?>
                                        </details>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($rowBreakdown as $taskListName => $tasks): ?>
                                        <details class="breakdown-group">
                                            <summary><span><?= e($taskListName) ?></span><span class="breakdown-hours"><?= e(number_format($sumHours($tasks), 2)) ?> hrs</span></summary>
                                            <ul>
                                                <?php foreach ($tasks as $taskLabel => $hours): ?>
                                                    <li><span><?= e($taskLabel) ?></span><span class="breakdown-hours"><?= e(number_format((float) $hours, 2)) ?> hrs</span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
