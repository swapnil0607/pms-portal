<section class="section-header">
    <div>
        <h2>Settings</h2>
        <p>Manage project table fields and reusable task list templates.</p>
    </div>
</section>

<nav class="tabs">
    <a class="<?= $activeTab === 'fields' ? 'active' : '' ?>" href="/settings?tab=fields">Project Fields</a>
    <a class="<?= $activeTab === 'templates' ? 'active' : '' ?>" href="/settings?tab=templates">Task List Templates</a>
</nav>

<?php if ($activeTab === 'fields'): ?>

<form method="post" action="/settings/project-fields" class="panel settings-panel">
    <?= csrf_field() ?>
    <div class="section-header compact">
        <div>
            <h2>Built-in Project Fields</h2>
            <p>Rename columns or hide fields from the Projects table.</p>
        </div>
        <button type="submit">Save Fields</button>
    </div>
    <div class="settings-table-wrap">
        <table class="data-table settings-table">
            <thead>
                <tr>
                    <th>Show</th>
                    <th>Field</th>
                    <th>Display Label</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projectFields as $field): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="visible[]" value="<?= e($field['key']) ?>" <?= $field['visible'] ? 'checked' : '' ?>>
                    </td>
                    <td><strong><?= e($field['key']) ?></strong></td>
                    <td>
                        <input type="text" name="labels[<?= e($field['key']) ?>]" value="<?= e($field['label']) ?>" required>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<section class="panel lower-grid settings-panel">
    <div class="section-header compact">
        <div>
            <h2>Custom Project Fields</h2>
            <p>Add Text, Number, or Date fields that appear on every project's create/edit form. Visible fields also show as a column on the Projects list, with Number/Date fields directly editable there.</p>
        </div>
    </div>

    <form method="post" action="/settings/custom-fields/create" class="template-settings-form">
        <?= csrf_field() ?>
        <div class="template-form-grid">
            <label>
                Field Label
                <input type="text" name="label" placeholder="Example: Warranty Months" required>
            </label>
            <label>
                Field Type
                <select name="field_type">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                </select>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit">Add Field</button>
        </div>
    </form>

    <?php if (!$customFields): ?>
        <div class="empty-state slim">
            <h3>No custom fields yet</h3>
            <p>Add one above to start collecting extra project data.</p>
        </div>
    <?php else: ?>
        <div class="template-settings-list">
            <?php foreach ($customFields as $field): ?>
                <details class="template-editor">
                    <summary>
                        <span>
                            <strong><?= e($field['label']) ?></strong>
                            <small><?= e($field['field_key']) ?> · <?= e(ucfirst($field['field_type'])) ?><?= $field['visible'] ? '' : ' · hidden from list' ?></small>
                        </span>
                    </summary>
                    <form method="post" action="/settings/custom-fields/update" class="template-settings-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="field_id" value="<?= e((string) $field['id']) ?>">
                        <div class="template-form-grid">
                            <label>
                                Label
                                <input type="text" name="label" value="<?= e($field['label']) ?>" required>
                            </label>
                            <label>
                                Type
                                <select name="field_type">
                                    <option value="text" <?= $field['field_type'] === 'text' ? 'selected' : '' ?>>Text</option>
                                    <option value="number" <?= $field['field_type'] === 'number' ? 'selected' : '' ?>>Number</option>
                                    <option value="date" <?= $field['field_type'] === 'date' ? 'selected' : '' ?>>Date</option>
                                </select>
                            </label>
                            <label>
                                <input type="checkbox" name="visible" value="1" <?= $field['visible'] ? 'checked' : '' ?>>
                                Show on Projects list
                            </label>
                        </div>
                        <div class="form-actions split-actions">
                            <button type="submit">Save Field</button>
                        </div>
                    </form>
                    <form method="post" action="/settings/custom-fields/delete" class="inline-delete-form" onsubmit="return confirm('Delete this custom field? Saved values for every project will be removed too.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="field_id" value="<?= e((string) $field['id']) ?>">
                        <button type="submit" class="danger-button">Delete Field</button>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php elseif ($activeTab === 'templates'): ?>

<section class="panel settings-panel">
    <div class="section-header compact">
        <div>
            <h2>Task List Templates</h2>
            <p>Template name becomes the Task List/Module. Each line below becomes a Task.</p>
        </div>
    </div>

    <form method="post" action="/settings/templates/create" class="template-settings-form">
        <?= csrf_field() ?>
        <h3>Create Template</h3>
        <div class="template-form-grid">
            <label>
                Task List Name
                <input type="text" name="name" placeholder="Example: Custom Content Template" required>
            </label>
            <label>
                Description
                <input type="text" name="description" placeholder="Optional">
            </label>
            <label class="span-2">
                Tasks
                <textarea name="tasks" rows="5" required placeholder="Instructional Design/Storyboarding&#10;Visual Design & Asset Creation&#10;Development"></textarea>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit">Create Template</button>
        </div>
    </form>

    <?php if (!$templates): ?>
        <div class="empty-state slim">
            <h3>No templates yet</h3>
            <p>Create templates here and apply them inside any project phase.</p>
        </div>
    <?php else: ?>
        <div class="template-settings-list">
            <?php foreach ($templates as $template): ?>
                <details class="template-editor">
                    <summary>
                        <span>
                            <strong><?= e($template['name']) ?></strong>
                            <small><?= e((string) $template['task_count']) ?> tasks</small>
                        </span>
                    </summary>
                    <form method="post" action="/settings/templates/update" class="template-settings-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="template_id" value="<?= e((string) $template['id']) ?>">
                        <div class="template-form-grid">
                            <label>
                                Task List Name
                                <input type="text" name="name" value="<?= e($template['name']) ?>" required>
                            </label>
                            <label>
                                Description
                                <input type="text" name="description" value="<?= e($template['description'] ?? '') ?>">
                            </label>
                            <label class="span-2">
                                Tasks
                                <textarea name="tasks" rows="7" required><?= e(implode("\n", array_map(static fn ($task): string => (string) $task['title'], $template['tasks'] ?? []))) ?></textarea>
                            </label>
                        </div>
                        <div class="form-actions split-actions">
                            <button type="submit">Save Template</button>
                        </div>
                    </form>
                    <form method="post" action="/settings/templates/delete" class="inline-delete-form" onsubmit="return confirm('Delete this template?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="template_id" value="<?= e((string) $template['id']) ?>">
                        <button type="submit" class="danger-button">Delete Template</button>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php endif; ?>
