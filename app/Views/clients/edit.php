<section class="section-header">
    <div>
        <h2>Edit Client</h2>
        <p>Renaming a client here also renames it wherever it is picked on projects.</p>
    </div>
    <a class="btn ghost" href="/clients">Back</a>
</section>

<form method="post" class="panel form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) $client['id']) ?>">
    <?php if (!empty($error)): ?>
        <div class="alert error span-2"><?= e($error) ?></div>
    <?php endif; ?>
    <label class="span-2">
        Client Name
        <input type="text" name="name" value="<?= e($client['name']) ?>" required>
    </label>
    <label>
        Status
        <select name="status">
            <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </label>
    <label class="span-2">
        Notes
        <textarea name="notes" rows="4"><?= e($client['notes'] ?? '') ?></textarea>
    </label>
    <div class="form-actions span-2">
        <button type="submit">Save Client</button>
    </div>
</form>
