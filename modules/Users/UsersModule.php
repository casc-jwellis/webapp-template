<?php

declare(strict_types=1);

namespace App\Modules\Users;

use App\Core\Module;
use App\Core\Request;
use App\Core\Router;

/**
 * User management (administrators only): list/search/sort, create, edit, disable and re-enable.
 *
 * A new user gets a generated temporary password, emailed to them (or shown once to the administrator if email is not
 * set up) and must choose their own at first sign-in. Roles are User and Administrator (users.admin).
 */
final class UsersModule extends Module
{
    private const PER_PAGE = 25;
    private const ID = '\d+';

    /** No 0/O/1/l/I: people retype this from an email. 14 characters of 54 is about 80 bits. */
    private const TEMP_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    private const TEMP_LENGTH = 14;

    public function routes(Router $router): void
    {
        $admin = ['admin' => true];
        $router->get('/users', [$this, 'index'], $admin);
        // Fixed path first: "new" would also match the {uid} pattern below.
        $router->get('/users/new', [$this, 'showNew'], $admin);
        $router->post('/users/new', [$this, 'create'], $admin);
        $router->get('/users/{uid:' . self::ID . '}', [$this, 'showEdit'], $admin);
        $router->post('/users/{uid:' . self::ID . '}', [$this, 'update'], $admin);
        $router->post('/users/{uid:' . self::ID . '}/disable', [$this, 'disable'], $admin);
        $router->post('/users/{uid:' . self::ID . '}/enable', [$this, 'enable'], $admin);
    }

    public function nav(): array
    {
        return [['label' => 'Users', 'path' => '/users', 'icon' => 'users', 'order' => 80, 'admin' => true]];
    }

    // ---- list ----

    /**
     * Sortable columns: key => ORDER BY columns (the chosen direction applies to each). Only these keys are
     * ever used in SQL; whatever is in the request is matched against this list, never inserted.
     */
    private const SORTS = [
        'name'     => ['u.lastname', 'u.firstname', 'u.username'],
        'username' => ['u.username'],
        'id'       => ['u.user_id'],
        'email'    => ['u.email'],
        'role'     => ['u.admin'],
        'login'    => ['u.lastlogin'],
        'status'   => ['u.disabled'],
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

    /** 'active' (default), 'disabled' or 'all'. */
    private function statusFilter(Request $req): string
    {
        $s = (string) $req->query('status');
        return in_array($s, ['disabled', 'all'], true) ? $s : 'active';
    }

    /** Keep the status filter and the sort (as well as search and page) when moving between the list and a user. */
    protected function rememberList(Request $req): void
    {
        [$sort, $dir] = $this->sortState($req);
        $status = $this->statusFilter($req);
        $this->listQs = $this->listState(
            mb_substr(trim((string) $req->query('q')), 0, 64),
            max(1, (int) $req->query('page', '1')),
            ['status' => $status === 'active' ? null : $status] + $this->sortParams($sort, $dir)
        );
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

        // An empty search is just the plain list: keep the address bar tidy ("/users", not "/users?q=").
        if ($req->query('q') !== null && $search === '') {
            $this->redirect('/users' . $this->listState('', $page, ['status' => $statusParam] + $sortParams));
        }

        $where = '1 = 1';
        $params = [];
        if ($search !== '') {
            // '!' is the LIKE escape character so user-typed % and _ are matched literally.
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $where = "(u.username LIKE ? ESCAPE '!' OR u.firstname LIKE ? ESCAPE '!'
                       OR u.lastname LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
            $params = array_fill(0, 4, $like);
        }
        if ($status === 'active') {
            $where .= ' AND u.disabled = 0';
        } elseif ($status === 'disabled') {
            $where .= ' AND u.disabled = 1';
        }

        $total = (int) $db->value("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE; // ints only: safe to inline
        $this->listQs = $this->listState($search, $page, ['status' => $statusParam] + $sortParams);

        // The chosen column in the chosen direction, then name order A to Z so rows with equal values keep a
        // stable order (and do not shuffle between pages).
        $direction = strtoupper($dir);
        $order = implode(', ', [
            ...array_map(static fn(string $c): string => "{$c} {$direction}", self::SORTS[$sort]),
            'u.lastname ASC', 'u.firstname ASC', 'u.username ASC', 'u.user_id ASC',
        ]);

        $rows = $db->all(
            "SELECT u.user_id, u.username, u.firstname, u.lastname, u.email, u.admin, u.disabled, u.lastlogin
               FROM users u
              WHERE {$where}
              ORDER BY {$order}
              LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );

        return $this->render('index', [
            'title'  => 'Users',
            'rows'   => $rows,
            'search' => $search,
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
            'status' => $status,
            'sort'   => $sort,
            'dir'    => $dir,
        ]);
    }

    // ---- disable / re-enable ----

    public function disable(Request $req, array $params): never
    {
        $this->setDisabled($req, (int) $params['uid'], true);
    }

    public function enable(Request $req, array $params): never
    {
        $this->setDisabled($req, (int) $params['uid'], false);
    }

    /** Revoke or restore a person's ability to sign in. Reversible, so no confirmation page. */
    private function setDisabled(Request $req, int $userId, bool $disable): never
    {
        $this->rememberList($req);
        $db = $this->app->db;
        $auth = $this->app->auth;
        $back = '/users/' . $userId . $this->listQs;
        $user = $this->find($userId);

        if ($disable) {
            if ((int) $user['user_id'] === (int) $auth->user()['user_id']) {
                $this->app->session->flash('danger', 'You cannot disable your own account.');
                $this->redirect($back);
            }
            // Never leave the system without an active administrator.
            if ($user['admin'] && empty($user['disabled']) && $this->otherActiveAdmins((int) $user['user_id']) === 0) {
                $this->app->session->flash('danger', 'This is the last active administrator and cannot be disabled.');
                $this->redirect($back);
            }
        }

        $db->execute('UPDATE users SET disabled = ? WHERE user_id = ?', [(int) $disable, $user['user_id']]);
        $name = trim($user['firstname'] . ' ' . $user['lastname']) ?: $user['username'];
        $this->app->session->flash('success', $disable
            ? $name . ' is disabled and can no longer sign in. Their account and history are unchanged.'
            : $name . ' is enabled and can sign in again.');
        $this->redirect($back);
    }

    private function otherActiveAdmins(int $exceptUserId): int
    {
        return (int) $this->app->db->value('SELECT COUNT(*) FROM users WHERE admin = 1 AND disabled = 0 AND user_id <> ?', [$exceptUserId]);
    }

    // ---- create / edit forms ----

    public function showNew(Request $req): string
    {
        $this->rememberList($req);
        return $this->form(null, [
            'user_id' => null, 'username' => '', 'firstname' => '', 'lastname' => '', 'email' => '',
            'timezone' => 'UTC', 'admin' => false, 'disabled' => false,
        ], []);
    }

    public function showEdit(Request $req, array $params): string
    {
        $this->rememberList($req);
        $user = $this->find((int) $params['uid']);
        return $this->form($user, [
            'user_id'   => (int) $user['user_id'],
            'username'  => $user['username'],
            'firstname' => $user['firstname'],
            'lastname'  => $user['lastname'],
            'email'     => $user['email'],
            'timezone'  => $user['timezone'],
            'admin'     => (bool) $user['admin'],
            'disabled'  => !empty($user['disabled']),
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
        return $this->save($req, $this->find((int) $params['uid']));
    }

    // ---- saving ----

    private function save(Request $req, ?array $existing): string
    {
        $db = $this->app->db;
        $auth = $this->app->auth;
        $isNew = $existing === null;
        $isSelf = !$isNew && (int) $existing['user_id'] === (int) $auth->user()['user_id'];

        $f = [
            'user_id'   => $isNew ? null : (int) $existing['user_id'], // assigned by the database for new users
            'username'  => trim((string) $req->post('username')),
            'firstname' => trim((string) $req->post('firstname')),
            'lastname'  => trim((string) $req->post('lastname')),
            'email'     => trim((string) $req->post('email')),
            'timezone'  => (string) $req->post('timezone'),
            'admin'     => $req->post('role') === 'admin',
            // Disabling and re-enabling have their own buttons; saving the form never changes it.
            'disabled'  => !$isNew && !empty($existing['disabled']),
        ];
        if ($isSelf) { // you can't remove your own admin access; the form doesn't offer it for your own account
            $f['admin'] = true;
        }
        // A new user's password is generated and emailed, so none is typed in; afterwards people choose their own,
        // or use "Forgot password" on the sign-in page.
        $mail = $isNew ? $this->notifier() : null;
        $canMail = $mail !== null && $mail->canMailPeople();

        // ---- validation ----
        $errors = [];
        if ($f['username'] === '' || mb_strlen($f['username']) > 64 || preg_match('/[\x00-\x1f\x7f]/', $f['username'])) {
            $errors[] = 'Enter a username (up to 64 characters, no control characters).';
        } elseif ($db->one('SELECT 1 FROM users WHERE username = ? AND user_id <> ?', [$f['username'], (int) $f['user_id']]) !== null) {
            $errors[] = 'That username belongs to another user.';
        }
        if (mb_strlen($f['firstname']) > 32 || mb_strlen($f['lastname']) > 32) {
            $errors[] = 'First and last names can be at most 32 characters.';
        }
        if (mb_strlen($f['email']) > 128 || ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL))) {
            $errors[] = 'Enter a valid email address' . ($canMail ? '.' : ' (or leave it blank).');
        } elseif ($canMail && $f['email'] === '') {
            $errors[] = 'Enter the email address to send the temporary password to.';
        }
        if (!in_array($f['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'Choose a valid time zone.';
        }
        // Never leave the system without an active administrator.
        if (!$isNew && $existing['admin'] && empty($existing['disabled']) && !$f['admin'] && $this->otherActiveAdmins((int) $existing['user_id']) === 0) {
            $errors[] = 'This is the last active administrator; it cannot be demoted.';
        }

        if ($errors === []) {
            try {
                $password = $isNew ? $this->generatePassword() : '';
                $this->persist($isNew, $f, $password);
                if ($isNew) {
                    return $this->welcome($f, $password, $canMail ? $mail : null);
                }
                $this->app->session->flash('success', 'User "' . $f['username'] . '" saved.');
                $this->redirect('/users' . $this->listQs);
            } catch (\PDOException $e) { // first: PDOException is a RuntimeException, and its text is not for the screen
                error_log('users save failed: ' . $e->getMessage());
                $errors[] = 'The user could not be saved because of a database error.';
            }
        }

        http_response_code(422);
        return $this->form($existing, $f, $errors);
    }

    /** Write the user. A new user is flagged to choose their own password at first sign-in. */
    private function persist(bool $isNew, array $f, string $password): void
    {
        $db = $this->app->db;
        if ($isNew) {
            // lastlogin is NOT NULL: the epoch means "never signed in".
            $db->execute(
                'INSERT INTO users (username, password, firstname, lastname, email, admin, timezone, lastlogin, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
                [$f['username'], password_hash($password, PASSWORD_DEFAULT), $f['firstname'], $f['lastname'], $f['email'],
                 (int) $f['admin'], $f['timezone'], '1970-01-01 00:00:00']
            );
            return;
        }
        $db->execute(
            'UPDATE users SET username = ?, firstname = ?, lastname = ?, email = ?, admin = ?, timezone = ? WHERE user_id = ?',
            [$f['username'], $f['firstname'], $f['lastname'], $f['email'], (int) $f['admin'], $f['timezone'], $f['user_id']]
        );
    }

    // ---- temporary passwords for new users ----

    private function generatePassword(): string
    {
        $out = '';
        $max = strlen(self::TEMP_ALPHABET) - 1;
        for ($i = 0; $i < self::TEMP_LENGTH; $i++) {
            $out .= self::TEMP_ALPHABET[random_int(0, $max)];
        }
        return $out;
    }

    /** The Email module's notifier, or null if that module is not installed. */
    private function notifier(): ?object
    {
        $module = $this->app->module('Email');
        return $module !== null && method_exists($module, 'notifier') ? $module->notifier() : null;
    }

    /**
     * The new user exists. Email them the temporary password; if that cannot be done, show it to the administrator
     * once, on this response only (never stored or put in the session), so the account is not stranded.
     */
    private function welcome(array $f, string $password, ?object $mail): string
    {
        [$sent, $why] = $mail !== null ? $mail->sendTemporaryPassword($f, $password) : [false, ''];
        if ($sent) {
            $this->app->session->flash('success', 'User "' . $f['username'] . '" created. A temporary password was emailed to ' . $f['email'] . '; they will choose their own when they first sign in.');
            $this->redirect('/users' . $this->listQs);
        }
        return $this->render('created', [
            'title'    => 'User created',
            'username' => $f['username'],
            'email'    => $f['email'],
            'password' => $password,
            'reason'   => $mail === null
                ? 'Email is not set up (an administrator can do that on the Email page), so nothing was sent.'
                : 'The email could not be sent' . ($why !== '' ? ': ' . $why : '.'),
        ]);
    }

    // ---- helpers ----

    /** @return array<string,mixed> the user row (without password); 404 if there is none */
    private function find(int $userId): array
    {
        $user = $this->app->db->one('SELECT * FROM users WHERE user_id = ?', [$userId]);
        if ($user === null) {
            $this->app->error(404, 'User not found');
        }
        unset($user['password']);
        return $user;
    }

    private function form(?array $existing, array $f, array $errors): string
    {
        $isNew = $existing === null;
        return $this->render('form', [
            'title'      => $isNew ? 'New user' : 'Edit user',
            'isNew'      => $isNew,
            'isSelf'     => !$isNew && (int) $existing['user_id'] === (int) $this->app->auth->user()['user_id'],
            'user'       => $existing,
            'f'          => $f,
            'errors'     => $errors,
            'timezones'  => \DateTimeZone::listIdentifiers(),
            'mailReady'  => $isNew && ($this->notifier()?->canMailPeople() ?? false),
            'mustChange' => !$isNew && !empty($existing['must_change_password']),
        ]);
    }
}
