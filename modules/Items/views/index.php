<?php
/**
 * Expects $rows, $search, $page, $pages, $total, $status ('active'|'archived'|'all'), $sort, $dir ('asc'|'desc'),
 * $listQs (current search/page/status/sort/filters, appended to links), $filters (ItemFilter), $filterQuery (its
 * ?f[...] part for links), $chips (one per filter in use), $categories (value => label).
 */
use App\Modules\Items\ItemFilter;

// The sort as it goes in a link: nothing for the default order (name, A to Z).
$sortQuery = static fn(string $key, string $d): array => [
    'sort' => $key === 'name' ? null : $key,
    'dir' => $d === 'desc' ? 'desc' : null,
];
// The search, status and sort every link keeps (the advanced filters are added by the link that needs them).
$stateQuery = ['q' => $search, 'status' => $status === 'active' ? null : $status] + $sortQuery($sort, $dir);
$pageUrl = static fn(int $p): string => url('/items') . '?' . http_build_query(array_filter(
    $stateQuery + ['page' => $p > 1 ? $p : null] + $filterQuery
));
// A link to a page of this module carrying the given query (no "?" when there is nothing to carry).
$withQuery = static function (string $path, array $query): string {
    $qs = http_build_query(array_filter($query));
    return url($path) . ($qs === '' ? '' : '?' . $qs);
};
$exportUrl = $withQuery('/items/export', $stateQuery + $filterQuery);
$statusLabels = ['active' => 'Active', 'archived' => 'Archived', 'all' => 'All'];
$filterCount = $filters->count();

// Column headers: [sort key, label, direction of the first click, th class]. Quantity starts biggest-first.
$columns = [['name', 'Name', 'asc', ''], ['category', 'Category', 'asc', ''], ['qty', 'Quantity', 'desc', 'num'], ['due', 'Due', 'asc', '']];
$sortLink = static function (string $key, string $label, string $first) use ($sort, $dir, $search, $status, $sortQuery, $filterQuery): string {
    $active = $key === $sort;
    $next = $active ? ($dir === 'asc' ? 'desc' : 'asc') : $first; // clicking the sorted column reverses it
    $href = url('/items') . '?' . http_build_query(array_filter([
        'q' => $search,
        'status' => $status === 'active' ? null : $status,
    ] + $sortQuery($key, $next) + $filterQuery));
    $arrow = $active ? ' <span class="sort-arrow" aria-hidden="true">' . ($dir === 'asc' ? '▲' : '▼') . '</span>' : '';
    return '<a class="sort-link" href="' . e($href) . '" title="Sort by ' . e(strtolower($label)) . '">' . e($label) . $arrow . '</a>';
};

// Every filter control belongs to the search form above (the panel sits outside it) and refreshes the list as it changes.
$fa = 'form="item-search" data-live-filter';
$fv = static fn(string $k): string => (string) $filters->value($k);
$today = date('Y-m-d');
?>
<div class="page-head">
    <div data-live-region>
        <h1>Items</h1>
        <p class="muted">
            <?= e(number_format($total)) ?> <?= $status === 'archived' ? 'archived ' : '' ?><?= $total === 1 ? 'item' : 'items' ?><?= $search !== '' || $filterCount > 0 ? ' matching your ' . ($filterCount > 0 && $search !== '' ? 'search and filters' : ($filterCount > 0 ? 'filters' : 'search')) : '' ?>
        </p>
    </div>
    <div class="page-actions">
        <form method="get" action="<?= e(url('/items')) ?>" class="search-form" id="item-search" role="search">
            <div class="search">
                <?= icon('search', 16) ?>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name or notes" aria-label="Search items" maxlength="64" data-live-search>
            </div>
            <select name="status" aria-label="Show" data-autosubmit>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php /* Searching or filtering keeps the current sort. */ ?>
            <?php foreach (array_filter($sortQuery($sort, $dir)) as $k => $v): ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endforeach; ?>
            <button type="button" class="btn" data-toggle="item-filters" aria-expanded="false" aria-controls="item-filters">
                <?= icon('filter', 16) ?> Filter <span class="filter-count" data-live-region><?php if ($filterCount > 0): ?><span class="badge badge-accent"><?= e($filterCount) ?></span><?php endif; ?></span>
            </button>
            <a class="btn btn-icon" data-live-region href="<?= e($exportUrl) ?>" title="Export <?= e(number_format($total)) ?> <?= $total === 1 ? 'item' : 'items' ?> to CSV" aria-label="Export to CSV"><?= icon('download', 16) ?></a>
        </form>
        <a class="btn btn-primary" href="<?= e(url('/items/new') . $listQs) ?>">Add item</a>
    </div>
</div>

<section class="card filter-panel" id="item-filters" aria-label="Filter items" hidden>
    <fieldset class="filter-group">
        <legend>What</legend>
        <div class="filter-grid">
            <?php foreach (ItemFilter::PICKS as $key => [, $label]): ?>
                <?php $picked = (array) $filters->value($key); ?>
                <div class="field">
                    <span class="field-label"><?= e($label) ?></span>
                    <details class="pick">
                        <summary><span data-pick-text><?= $picked === [] ? 'Any' : e(count($picked)) . ' selected' ?></span></summary>
                        <div class="pick-list">
                            <?php foreach ($categories as $value => $text): ?>
                                <label><input type="checkbox" name="f[<?= e($key) ?>][]" value="<?= e($value) ?>" <?= $fa /* fixed markup */ ?><?= in_array($value, $picked, true) ? ' checked' : '' ?>> <?= e($text) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
            <?php foreach (ItemFilter::TEXTS as $key => [, $label]): ?>
                <div class="field">
                    <label for="f-<?= e($key) ?>"><?= e($label) ?> contain</label>
                    <input type="text" id="f-<?= e($key) ?>" name="f[<?= e($key) ?>]" value="<?= e($fv($key)) ?>" maxlength="100" autocomplete="off" <?= $fa ?>>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted small">Archived items are hidden until <strong>Show</strong> above is set to Archived or All.</p>
    </fieldset>

    <fieldset class="filter-group">
        <legend>Quantity and dates</legend>
        <div class="filter-grid wide">
            <div class="field">
                <span class="field-label">Quantity</span>
                <div class="range">
                    <input type="text" inputmode="numeric" name="f[qty_min]" value="<?= e($fv('qty_min')) ?>" placeholder="From" aria-label="Quantity from" maxlength="9" autocomplete="off" <?= $fa ?>>
                    <input type="text" inputmode="numeric" name="f[qty_max]" value="<?= e($fv('qty_max')) ?>" placeholder="To" aria-label="Quantity to" maxlength="9" autocomplete="off" <?= $fa ?>>
                </div>
            </div>
            <?php foreach (ItemFilter::DATES as $key => [, $label]): ?>
                <div class="field">
                    <span class="field-label"><?= e($label) ?></span>
                    <div class="range">
                        <input type="date" name="f[<?= e($key) ?>_from]" value="<?= e($fv($key . '_from')) ?>" aria-label="<?= e($label) ?> from" <?= $fa ?>>
                        <input type="date" name="f[<?= e($key) ?>_to]" value="<?= e($fv($key . '_to')) ?>" aria-label="<?= e($label) ?> to" <?= $fa ?>>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="filter-foot">
        <button type="button" class="btn-link" data-filter-clear>Clear filters</button>
        <span class="muted small">Filters combine with each other and with the search box.</span>
    </div>
</section>

<div data-live-region<?= $chips !== [] ? ' class="filter-chips"' : '' ?>>
    <?php foreach ($chips as $chip): ?>
        <a class="chip" href="<?= e($withQuery('/items', $stateQuery + $chip['query'])) ?>" title="Remove this filter"><?= e($chip['label']) ?> <span aria-hidden="true">×</span><span class="sr-only"> (remove)</span></a>
    <?php endforeach; ?>
    <?php if (count($chips) > 1): ?>
        <a class="chip-clear" href="<?= e($withQuery('/items', $stateQuery)) ?>">Clear all filters</a>
    <?php endif; ?>
</div>

<div class="card" data-live-region>
    <?php if ($rows === []): ?>
        <p class="empty"><?= $search !== '' || $filterCount > 0 ? 'No items match your search.' : ($status === 'archived' ? 'No archived items.' : 'No items yet.') ?></p>
    <?php else: ?>
        <form method="post" action="<?= e(url('/items/bulk') . $listQs) ?>" data-bulk-form data-total="<?= e($total) ?>" data-noun="item" data-nouns="items">
            <?= csrf_field() ?>
            <input type="hidden" name="select_all" value="">
            <div class="bulk-bar" data-bulk-bar hidden>
                <span class="bulk-count" data-bulk-count></span>
                <button type="button" class="btn-link" data-bulk-all hidden>Select all <?= e(number_format($total)) ?> matching</button>
                <span class="bulk-buttons">
                    <?php if ($status !== 'archived'): ?><button type="submit" class="btn" name="action" value="archive">Archive</button><?php endif; ?>
                    <?php if ($status !== 'active'): ?><button type="submit" class="btn" name="action" value="restore">Restore</button><?php endif; ?>
                </span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="check-col"><input type="checkbox" data-select-all aria-label="Select all items on this page"></th>
                            <?php foreach ($columns as [$key, $label, $first, $class]): ?>
                                <th scope="col"<?= $class !== '' ? ' class="' . e($class) . '"' : '' ?> aria-sort="<?= $key === $sort ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' ?>"><?= $sortLink($key, $label, $first) /* built and escaped above */ ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr<?= $r['archived'] ? ' class="is-retired"' : '' ?>>
                            <td class="check-col"><input type="checkbox" name="ids[]" value="<?= e((int) $r['item_id']) ?>" aria-label="Select <?= e($r['name']) ?>"></td>
                            <td><a href="<?= e(url('/items/' . (int) $r['item_id']) . $listQs) ?>"><?= e($r['name']) ?></a><?php if ($r['archived']): ?> <span class="badge badge-warn">Archived</span><?php endif; ?></td>
                            <td><span class="badge"><?= e($categories[$r['category']] ?? $r['category']) ?></span></td>
                            <td class="num"><?= e(number_format((int) $r['quantity'])) ?></td>
                            <td class="nowrap">
                                <?php if ($r['due_date'] === null): ?>
                                    <span class="muted">—</span>
                                <?php elseif (!$r['archived'] && $r['due_date'] < $today): ?>
                                    <span class="badge badge-danger" title="Past its due date"><?= e(fmt_date($r['due_date'])) ?></span>
                                <?php else: ?>
                                    <?= e(fmt_date($r['due_date'])) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php if ($pages > 1): ?>
            <nav class="pager" aria-label="Pagination">
                <?php if ($page > 1): ?><a class="btn" href="<?= e($pageUrl($page - 1)) ?>">&larr; Previous</a><?php endif; ?>
                <span class="muted">Page <?= e($page) ?> of <?= e($pages) ?></span>
                <?php if ($page < $pages): ?><a class="btn" href="<?= e($pageUrl($page + 1)) ?>">Next &rarr;</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
