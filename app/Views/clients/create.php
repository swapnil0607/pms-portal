<section class="section-header">
    <div>
        <h2>New Client</h2>
        <p>Clients become selectable when creating or editing a project.</p>
    </div>
    <a class="btn ghost" href="/clients">Back</a>
</section>

<form method="post" class="panel form-grid">
    <?= csrf_field() ?>
    <?php if (!empty($error)): ?>
        <div class="alert error span-2"><?= e($error) ?></div>
    <?php endif; ?>
    <label class="span-2">
        Client Name
        <input type="text" name="name" required>
    </label>
    <label>
        Status
        <select name="status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </label>
    <label class="span-2">
        Notes
        <textarea name="notes" rows="4"></textarea>
    </label>
    <div class="form-actions span-2">
        <button type="submit">Create Client</button>
    </div>
</form>
