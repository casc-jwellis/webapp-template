<?php
/** Confirm deleting an item. Expects $item (row), $listQs. */
$id = (int) $item['item_id'];
$back = url('/items/' . $id) . $listQs;
?>
<div class="narrow-col">
<div class="page-head">
    <div>
        <a class="back-link" href="<?= e($back) ?>">&larr; Back to <?= e($item['name']) ?></a>
        <h1>Delete item</h1>
    </div>
</div>

<div class="card form-card danger-card">
    <p>Delete <strong><?= e($item['name']) ?></strong>?</p>
    <p class="muted small">This cannot be undone. To keep the record but take it off the main list, archive it instead (tick it on the Items list and choose Archive).</p>
    <form method="post" action="<?= e(url('/items/' . $id . '/delete') . $listQs) ?>">
        <?= csrf_field() ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-danger">Delete item</button>
            <a class="btn" href="<?= e($back) ?>">Cancel</a>
        </div>
    </form>
</div>
</div>
