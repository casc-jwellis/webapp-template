<?php

declare(strict_types=1);

namespace App\Modules\Items;

use App\Core\Module;
use App\Core\Request;
use App\Core\Router;

/**
 * Items: the example module. A complete list-and-edit feature built the way every module in an app made from this
 * template should be: a searchable, sortable, filterable, paged list with live search, a CSV export, bulk actions, a
 * detail page, an add/edit form with validation, a delete that asks first, and an email notification.
 *
 * Copy this folder, rename Item/items throughout (ItemsModule, ItemFilter, views, migrations/002_items.sql) and change
 * the columns. Delete it (and migrations/002_items.sql, before it is applied anywhere) if you do not want it.
 *
 * Who may do what: everyone signed in can see, add, change and archive items; only administrators can delete them.
 */
final class ItemsModule extends Module
{
    private const PER_PAGE = 25;

    /** Most items one bulk action will touch. */
    private const BULK_LIMIT = 1000;

    /** Allowed values for items.category: value stored => label shown. */
    public const CATEGORIES = [
        'general'   => 'General',
        'supplies'  => 'Supplies',
        'equipment' => 'Equipment',
        'software'  => 'Software',
    ];

    private const NAME_MAX = 100;
    private const NOTES_MAX = 5000;
    private const QUANTITY_MAX = 1000000;

    public function routes(Router $router): void
    {
        $router->get('/items', [$this, 'index']);
        // Fixed paths before the {id} ones.
        $router->get('/items/export', [$this, 'export']);
        $router->get('/items/new', [$this, 'showNew']);
        $router->post('/items/new', [$this, 'create']);
        $router->post('/items/bulk', [$this, 'bulk']);
        $router->get('/items/{id:\d+}', [$this, 'show']);
        $router->get('/items/{id:\d+}/edit', [$this, 'showEdit']);
        $router->post('/items/{id:\d+}/edit', [$this, 'update']);
        $router->get('/items/{id:\d+}/delete', [$this, 'showDelete'], ['admin' => true]);
        $router->post('/items/{id:\d+}/delete', [$this, 'delete'], ['admin' => true]);
    }

    public function nav(): array
    {
        return [['label' => 'Items', 'path' => '/items', 'icon' => 'box', 'order' => 20]];
    }

    // ------------------------------------------------------------------ the list

    /**
     * Sortable columns: key => ORDER BY columns (the chosen direction applies to each). Only these keys are
     * ever used in SQL; whatever is in the request is matched against this list, never inserted.
     */
    private const SORTS = [
        'name'     => ['i.name'],
        'category' => ['i.category'],
        'qty'      => ['i.quantity'],
        'due'      => ['i.due_date'], // items with no due date come first when ascending
        'added'    => ['i.created_date'],
    ];
    private const DEFAULT_SORT = 'name';

    /** @return array{0:string, 1:string} the sort column key and 'asc'|'desc' */
    private function sortState(Request $req): array
    {
        $key = (string) $req->query('sort');
        return [isset(self::SORTS[$key]) ? $key : self::DEFAULT_SORT, $req->query('dir') === 'desc' ? 'desc' : 'asc'];
    }

    /** The ?sort= and ?dir= values to put in links: nothing for the default order. */
    private function sortParams(string $key, string $dir): array
    {
        return ['sort' => $key === self::DEFAULT_SORT ? null : $key, 'dir' => $dir === 'desc' ? 'desc' : null];
    }

    /** The advanced filters in the address (?f[...]), validated. */
    private function filters(Request $req): ItemFilter
    {
        return ItemFilter::fromQuery($req->queryArray('f'));
    }

    /** 'active' (default), 'archived' or 'all'. */
    private function statusFilter(Request $req): string
    {
        $s = (string) $req->query('status');
        return in_array($s, ['archived', 'all'], true) ? $s : 'active';
    }

    /** The list view to return to: search text, page, status, sort and filters (see Module::listState). */
    protected function rememberList(Request $req): void
    {
        $status = $this->statusFilter($req);
        [$sort, $dir] = $this->sortState($req);
        $this->listQs = $this->listState(
            mb_substr(trim((string) $req->query('q')), 0, 64),
            max(1, (int) $req->query('page', '1')),
            ['status' => $status === 'active' ? null : $status] + $this->sortParams($sort, $dir) + $this->filters($req)->query()
        );
    }

    /**
     * WHERE clause for the list, the export and "select everything matching": search text + status + filters.
     *
     * @return array{0:string, 1:list<mixed>}
     */
    private function listWhere(string $search, string $status, ItemFilter $filters): array
    {
        $where = '1 = 1';
        $params = [];
        if ($search !== '') {
            // '!' is the LIKE escape character so user-typed % and _ are matched literally.
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $where .= " AND (i.name LIKE ? ESCAPE '!' OR i.notes LIKE ? ESCAPE '!')";
            array_push($params, $like, $like);
        }
        if ($status === 'active') {
            $where .= ' AND i.archived = 0';
        } elseif ($status === 'archived') {
            $where .= ' AND i.archived = 1';
        }
        [$filterSql, $filterParams] = $filters->where();
        return [$where . $filterSql, [...$params, ...$filterParams]];
    }

    /**
     * ORDER BY for the list and the export: the chosen column in the chosen direction, then name so rows with
     * equal values keep a stable order (and do not shuffle between pages).
     */
    private function orderBy(string $sort, string $dir): string
    {
        $direction = strtoupper($dir);
        return implode(', ', [
            ...array_map(static fn(string $c): string => "{$c} {$direction}", self::SORTS[$sort]),
            'i.name ASC', 'i.item_id ASC',
        ]);
    }

    public function index(Request $req): string
    {
        $db = $this->app->db;
        $search = trim((string) $req->query('q'));
        $page = max(1, (int) $req->query('page', '1'));
        $status = $this->statusFilter($req);
        $statusParam = $status === 'active' ? null : $status;
        [$sort, $dir] = $this->sortState($req);
        $sortParams = $this->sortParams($sort, $dir);
        $filters = $this->filters($req);
        $filterQuery = $filters->query();

        // An empty search is just the plain list: keep the address bar tidy ("/items", not "/items?q=").
        if ($req->query('q') !== null && $search === '') {
            $this->redirect('/items' . $this->listState('', $page, ['status' => $statusParam] + $sortParams + $filterQuery));
        }

        [$where, $params] = $this->listWhere($search, $status, $filters);

        $total = (int) $db->value("SELECT COUNT(*) FROM items i WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE; // ints only: safe to inline
        $this->listQs = $this->listState($search, $page, ['status' => $statusParam] + $sortParams + $filterQuery);

        $rows = $db->all(
            'SELECT i.item_id, i.name, i.category, i.quantity, i.due_date, i.archived
               FROM items i
              WHERE ' . $where . '
              ORDER BY ' . $this->orderBy($sort, $dir) . '
              LIMIT ' . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );

        return $this->render('index', [
            'title'       => 'Items',
            'rows'        => $rows,
            'search'      => $search,
            'page'        => $page,
            'pages'       => $pages,
            'total'       => $total,
            'status'      => $status,
            'sort'        => $sort,
            'dir'         => $dir,
            'filters'     => $filters,
            'filterQuery' => $filterQuery,
            'chips'       => $filters->chips(['cat' => self::CATEGORIES]),
            'categories'  => self::CATEGORIES,
        ]);
    }

    /**
     * Download the list as a CSV: every item the current search, status and filters allow (not just the page on
     * screen), in the order shown. The address carries the same ?q=, ?status=, ?f[...] and ?sort= as the list.
     */
    public function export(Request $req): never
    {
        $db = $this->app->db;
        $search = trim((string) $req->query('q'));
        [$sort, $dir] = $this->sortState($req);
        [$where, $params] = $this->listWhere($search, $this->statusFilter($req), $this->filters($req));
        $order = $this->orderBy($sort, $dir);

        // Out of the session lock first, so the rest of the site stays usable while a long file is built.
        session_write_close();
        ignore_user_abort(false);
        @set_time_limit(300);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="items-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // a byte-order mark so Excel reads accented letters correctly
        $put = static fn(array $fields) => fputcsv($out, $fields, ',', '"', '', "\r\n");
        $put(['Name', 'Category', 'Quantity', 'Due', 'Status', 'Notes', 'Added', 'Added by']);

        // A cell that starts with = + - @ is run as a formula by spreadsheet programs: a leading ' keeps it as text.
        $safe = static fn(string $s): string => $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false ? "'" . $s : $s;

        $chunk = 1000;
        for ($offset = 0;; $offset += $chunk) {
            $rows = $db->all(
                "SELECT i.*, u.firstname, u.lastname, u.username
                   FROM items i LEFT JOIN users u ON u.user_id = i.created_by
                  WHERE {$where} ORDER BY {$order} LIMIT {$chunk} OFFSET {$offset}",
                $params
            );
            foreach ($rows as $r) {
                $put([
                    $safe((string) $r['name']),
                    self::CATEGORIES[$r['category']] ?? $r['category'],
                    (string) $r['quantity'],
                    $r['due_date'] === null ? '' : substr((string) $r['due_date'], 0, 10),
                    $r['archived'] ? 'Archived' : 'Active',
                    $safe((string) $r['notes']),
                    (string) $r['created_date'],
                    $safe($this->personName($r['firstname'], $r['lastname'], $r['username'])),
                ]);
            }
            if (count($rows) < $chunk) {
                break;
            }
            flush();
        }
        fclose($out);
        exit;
    }

    // ------------------------------------------------------------------ bulk actions

    /**
     * Archive or restore the ticked items, or everything matching the list when "select all matching" was used.
     * Both are reversible, so there is no confirmation page. The list's search, status and filters travel in the
     * address of this POST (the form's action carries $listQs), so "everything matching" means what was on screen.
     */
    public function bulk(Request $req): never
    {
        $this->rememberList($req);
        $db = $this->app->db;
        $back = '/items' . $this->listQs;
        $action = (string) $req->post('action');
        if (!in_array($action, ['archive', 'restore'], true)) {
            $this->app->session->flash('danger', 'Choose what to do with the items.');
            $this->redirect($back);
        }

        if ($req->post('select_all') === '1') {
            [$where, $params] = $this->listWhere(trim((string) $req->query('q')), $this->statusFilter($req), $this->filters($req));
            $ids = array_map('intval', array_column($db->all('SELECT i.item_id FROM items i WHERE ' . $where . ' LIMIT ' . (self::BULK_LIMIT + 1), $params), 'item_id'));
        } else {
            $ids = array_values(array_unique(array_filter(array_map('intval', $req->postArray('ids')), static fn(int $id): bool => $id > 0)));
        }
        if ($ids === []) {
            $this->app->session->flash('danger', 'No items were selected.');
            $this->redirect($back);
        }
        if (count($ids) > self::BULK_LIMIT) {
            $this->app->session->flash('danger', 'That is more than ' . number_format(self::BULK_LIMIT) . ' items. Narrow the list with a search or filter and try again.');
            $this->redirect($back);
        }

        $changed = $db->execute(
            'UPDATE items SET archived = ? WHERE archived = ? AND item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            [$action === 'archive' ? 1 : 0, $action === 'archive' ? 0 : 1, ...$ids]
        );
        $this->app->session->flash('success', number_format($changed) . ' item' . ($changed === 1 ? ' was ' : 's were ') . ($action === 'archive' ? 'archived.' : 'restored.'));
        $this->redirect($back);
    }

    // ------------------------------------------------------------------ one item

    public function show(Request $req, array $params): string
    {
        $this->rememberList($req);
        $item = $this->find((int) $params['id']);
        return $this->render('show', [
            'title'     => $item['name'],
            'item'      => $item,
            'category'  => self::CATEGORIES[$item['category']] ?? $item['category'],
            'creator'   => $this->personName($item['firstname'], $item['lastname'], $item['username']),
            'canDelete' => $this->app->auth->isAdmin(),
        ]);
    }

    public function showNew(Request $req): string
    {
        $this->rememberList($req);
        return $this->form(null, ['name' => '', 'category' => 'general', 'quantity' => '1', 'due_date' => '', 'notes' => ''], []);
    }

    public function showEdit(Request $req, array $params): string
    {
        $this->rememberList($req);
        $item = $this->find((int) $params['id']);
        return $this->form($item, [
            'name'     => (string) $item['name'],
            'category' => (string) $item['category'],
            'quantity' => (string) $item['quantity'],
            'due_date' => $item['due_date'] === null ? '' : substr((string) $item['due_date'], 0, 10),
            'notes'    => (string) $item['notes'],
        ], []);
    }

    public function create(Request $req): string
    {
        $this->rememberList($req);
        return $this->save($req, null);
    }

    public function update(Request $req, array $params): string
    {
        $this->rememberList($req);
        return $this->save($req, $this->find((int) $params['id']));
    }

    private function save(Request $req, ?array $existing): string
    {
        $f = [
            'name'     => trim((string) preg_replace('/\s+/u', ' ', (string) $req->post('name'))),
            'category' => (string) $req->post('category'),
            'quantity' => trim((string) $req->post('quantity')),
            'due_date' => trim((string) $req->post('due_date')),
            'notes'    => trim((string) $req->post('notes')),
        ];

        $errors = $this->validate($f);
        if ($errors === []) {
            $db = $this->app->db;
            $due = $f['due_date'] === '' ? null : $f['due_date'];
            try {
                if ($existing === null) {
                    $db->execute(
                        'INSERT INTO items (name, category, quantity, due_date, notes, created_by, created_date) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$f['name'], $f['category'], (int) $f['quantity'], $due, $f['notes'], $this->app->auth->user()['user_id'], date('Y-m-d H:i:s')]
                    );
                    $id = (int) $db->pdo()->lastInsertId();
                    $this->warnAbout($this->notifier()?->itemCreated($id));
                    $this->app->session->flash('success', 'Item "' . $f['name'] . '" was added.');
                } else {
                    $id = (int) $existing['item_id'];
                    $db->execute(
                        'UPDATE items SET name = ?, category = ?, quantity = ?, due_date = ?, notes = ? WHERE item_id = ?',
                        [$f['name'], $f['category'], (int) $f['quantity'], $due, $f['notes'], $id]
                    );
                    $this->app->session->flash('success', 'Item "' . $f['name'] . '" was saved.');
                }
                $this->redirect('/items/' . $id . $this->listQs);
            } catch (\PDOException $e) { // its text is not for the screen
                error_log('items save failed: ' . $e->getMessage());
                $errors[] = 'The item could not be saved because of a database error.';
            }
        }

        http_response_code(422);
        return $this->form($existing, $f, $errors);
    }

    /** @return list<string> what is wrong with the posted values (empty when all is well) */
    private function validate(array $f): array
    {
        $errors = [];
        if ($f['name'] === '' || mb_strlen($f['name']) > self::NAME_MAX || preg_match('/[\x00-\x1f\x7f]/', $f['name'])) {
            $errors[] = 'Enter a name of up to ' . self::NAME_MAX . ' characters.';
        }
        if (!isset(self::CATEGORIES[$f['category']])) {
            $errors[] = 'Choose a category.';
        }
        if (preg_match('/^\d{1,9}$/', $f['quantity']) !== 1 || (int) $f['quantity'] > self::QUANTITY_MAX) {
            $errors[] = 'The quantity must be a whole number from 0 to ' . number_format(self::QUANTITY_MAX) . '.';
        }
        if ($f['due_date'] !== '') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $f['due_date']);
            if ($d === false || $d->format('Y-m-d') !== $f['due_date']) {
                $errors[] = 'Enter the due date as a real date (or leave it blank).';
            }
        }
        if (mb_strlen($f['notes']) > self::NOTES_MAX) {
            $errors[] = 'Notes can be at most ' . number_format(self::NOTES_MAX) . ' characters.';
        }
        return $errors;
    }

    // ------------------------------------------------------------------ delete

    /** Step 1: ask first. Deleting cannot be undone (archiving can: prefer it). */
    public function showDelete(Request $req, array $params): string
    {
        $this->rememberList($req);
        return $this->render('delete', ['title' => 'Delete item', 'item' => $this->find((int) $params['id'])]);
    }

    /** Step 2: do it. */
    public function delete(Request $req, array $params): never
    {
        $this->rememberList($req);
        $item = $this->find((int) $params['id']);
        $this->app->db->execute('DELETE FROM items WHERE item_id = ?', [$item['item_id']]);
        $this->app->session->flash('success', 'Item "' . $item['name'] . '" was deleted.');
        $this->redirect('/items' . $this->listQs);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * One item, or the "not found" page. The row also carries firstname, lastname and username of the person who
     * added it (null when that user no longer exists).
     *
     * @return array<string,mixed>
     */
    private function find(int $id): array
    {
        $item = $this->app->db->one(
            'SELECT i.*, u.firstname, u.lastname, u.username
               FROM items i LEFT JOIN users u ON u.user_id = i.created_by
              WHERE i.item_id = ?',
            [$id]
        );
        if ($item === null) {
            $this->app->error(404, 'Item not found');
        }
        return $item;
    }

    private function personName(?string $first, ?string $last, ?string $username): string
    {
        return trim((string) $first . ' ' . (string) $last) ?: ((string) $username !== '' ? (string) $username : 'Someone who has left');
    }

    private function form(?array $existing, array $f, array $errors): string
    {
        return $this->render('form', [
            'title'      => $existing === null ? 'New item' : 'Edit item',
            'isNew'      => $existing === null,
            'item'       => $existing,
            'f'          => $f,
            'errors'     => $errors,
            'categories' => self::CATEGORIES,
            'nameMax'    => self::NAME_MAX,
            'notesMax'   => self::NOTES_MAX,
        ]);
    }

    /** The Email module's notifier, or null if that module has been removed. */
    private function notifier(): ?object
    {
        $module = $this->app->module('Email');
        return $module !== null && method_exists($module, 'notifier') ? $module->notifier() : null;
    }

    /** A notification must never get in the way of the change that caused it: a failure is only a warning. */
    private function warnAbout(?string $warning): void
    {
        if ($warning !== null) {
            $this->app->session->flash('warning', $warning);
        }
    }
}
