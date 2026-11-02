<?php
/** Expects $item (row, with the creator's firstname/lastname/username), $category (label), $creator (name), $canDelete (bool), $listQs. */
$id = (int) $item['item_id'];
?>
<div class="page-head">
    <div>
        <a class="back-link" href="<?= e(url('/items') . $listQs) ?>">&larr; <?= $listQs !== '' ? 'Back to results' : 'All items' ?></a>
        <h1><?= e($item['name']) ?></h1>
        <p class="muted"><?= e($category) ?></p>
    </div>
    <div class="page-actions">
        <?php if ($item['archived']): ?><span class="badge badge-warn">Archived</span><?php endif; ?>
        <a class="btn" href="<?= e(url('/items/' . $id . '/edit') . $listQs) ?>">Edit</a>
        <?php if ($canDelete): ?>
            <a class="btn btn-danger" href="<?= e(url('/items/' . $id . '/delete') . $listQs) ?>">Delete…</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Details</h2></div>
    <dl class="detail-grid">
        <div><dt>Name</dt><dd><?= e($item['name']) ?></dd></div>
        <div><dt>Category</dt><dd><?= e($category) ?></dd></div>
        <div><dt>Quantity</dt><dd><?= e(number_format((int) $item['quantity'])) ?></dd></div>
        <div><dt>Due date</dt><dd><?= e(fmt_date($item['due_date'])) ?></dd></div>
        <div><dt>Added by</dt><dd><?= e($creator) ?></dd></div>
        <div><dt>Added on</dt><dd><?= e(fmt_date($item['created_date'])) ?></dd></div>
    </dl>
</div>

<div class="card section">
    <div class="card-head"><h2>Notes</h2></div>
    <?php if (trim((string) $item['notes']) === ''): ?>
        <p class="empty">No notes.</p>
    <?php else: ?>
        <div class="notes"><?= e($item['notes']) ?></div>
    <?php endif; ?>
</div>
