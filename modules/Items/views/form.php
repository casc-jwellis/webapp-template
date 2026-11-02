<?php
/** Add/edit form. Expects $isNew, $item (row|null), $f (values), $errors, $categories (value => label), $nameMax, $notesMax. */
$action = ($isNew ? url('/items/new') : url('/items/' . (int) $item['item_id'] . '/edit')) . $listQs;
$back = ($isNew ? url('/items') : url('/items/' . (int) $item['item_id'])) . $listQs;
?>
<div class="narrow-col">
<div class="page-head">
    <div>
        <a class="back-link" href="<?= e($back) ?>">&larr; <?= $isNew ? 'All items' : 'Back to item' ?></a>
        <h1><?= $isNew ? 'New item' : 'Edit ' . e($item['name']) ?></h1>
    </div>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger" role="alert"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e($action) ?>" autocomplete="off">
    <?= csrf_field() ?>

    <div class="card form-card wide">
        <h2>Item</h2>
        <div class="field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= e($f['name']) ?>" maxlength="<?= e($nameMax) ?>" required>
        </div>
        <div class="field-row even">
            <div class="field">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $f['category'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="quantity">Quantity</label>
                <input type="text" id="quantity" name="quantity" value="<?= e($f['quantity']) ?>" inputmode="numeric" maxlength="9" required>
            </div>
        </div>
        <div class="field">
            <label for="due_date">Due date <span class="muted">(optional)</span></label>
            <input type="date" id="due_date" name="due_date" value="<?= e($f['due_date']) ?>">
        </div>
    </div>

    <div class="card form-card wide section">
        <h2>Notes</h2>
        <div class="field">
            <label for="notes" class="sr-only">Notes</label>
            <textarea id="notes" name="notes" rows="6" maxlength="<?= e($notesMax) ?>"><?= e($f['notes']) ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isNew ? 'Add item' : 'Save changes' ?></button>
        <a class="btn" href="<?= e($back) ?>">Cancel</a>
    </div>
</form>
</div>
