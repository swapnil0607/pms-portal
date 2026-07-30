<datalist id="suggest-clients">
    <?php foreach ($suggestions['clients'] ?? [] as $value): ?>
        <option value="<?= e($value) ?>">
    <?php endforeach; ?>
</datalist>
<datalist id="suggest-phases">
    <?php foreach ($suggestions['phases'] ?? [] as $value): ?>
        <option value="<?= e($value) ?>">
    <?php endforeach; ?>
</datalist>
<datalist id="suggest-task-lists">
    <?php foreach ($suggestions['taskLists'] ?? [] as $value): ?>
        <option value="<?= e($value) ?>">
    <?php endforeach; ?>
</datalist>
