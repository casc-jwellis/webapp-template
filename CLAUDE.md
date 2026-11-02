# Project conventions

This project was made from the web app template. These are the rules every app made from it follows, so they stay alike.
Read this before changing anything. `README.md` describes what is built in.

## How I work with the owner

- **Never commit or push until I am told to.** Make the change, test it, summarise it, and wait.
- Commit messages end with the trailer the tooling gives you for attribution (`Co-Authored-By: ...`).
- There is no command-line work for the owner: they pull on a server and apply database changes from the **Migrations** page
  in the browser. Everything must work that way.
- When a request is ambiguous in a way that changes the result, ask; otherwise choose the obvious option and say what you chose.

## Stack

PHP 8.1+, PDO/MySQL or MariaDB, plain PHP templates, plain CSS and JavaScript. **No Composer, no npm, no frameworks.** The one
bundled library is `lib/tehsmtp/` (SMTP). Do not add dependencies; write the small thing instead.

## Layout and how a request flows

`public/index.php` → `src/bootstrap.php` (autoload) → `App::handle()` in `src/Core/App.php`: session, security headers, auth
gate, forced password change, CSRF, authorization, handler. Route options: `public`, `admin`, `pending_ok`.

A module is `modules/<Name>/<Name>Module.php` (namespace `App\Modules\<Name>`, extends `App\Core\Module`) with `routes()`, optional
`nav()` and handlers that `return $this->render('view', [...])` from `modules/<Name>/views/`. It is found automatically. Other
modules are reached with `$this->app->module('Name')`, which returns null if that folder was removed: always handle null.

## Adding a feature: the checklist

Copy `modules/Items` (read `ItemsModule.php` first; it is the reference) and then:

1. **Migration**: add the next numbered file in `migrations/` (`003_...sql`). `CREATE TABLE IF NOT EXISTS`, utf8mb4, safe to run
   twice. Never edit a migration that has been applied anywhere; add a new one.
2. **Module**: routes (fixed paths before `{id:\d+}` ones), `nav()`, handlers.
3. **Views**: every dynamic value through `e()`; every `<form method="post">` includes `<?= csrf_field() ?>`; no inline `<style>`
   or `style=""` (the CSP forbids it) and no inline `<script>` without `nonce()`.
4. Put new CSS in `public/static/css/app.css` using the existing tokens (`var(--accent)`, `var(--surface)`, ...), check light and dark.
5. Test it for real (below), including what happens to someone who is not allowed.
6. Update `README.md` if you added something people will need to know about.

### Code that ships before its migration

The owner pulls new code, then applies migrations. A page that needs a new table or column must not crash in between: guard it with
`$this->app->migrationApplied('003_name.sql')` and show a short "apply the pending migrations" notice instead. Tables from
`001_baseline.sql` need no guard.

## Rules that are easy to forget

- **SQL**: prepared statements with bound parameters, always. Anything put into SQL text (a sort column, a table name) comes from
  a fixed list in the code, never from the request. Search text uses `LIKE ? ESCAPE '!'` with `!`, `%`, `_` escaped.
- **Lists** (see Items): search box with `data-live-search`, sort by clicking headers, paging, `$listQs` carried to detail pages
  and back, filters validated into a small class (`ItemFilter`) that also produces the SQL, chips for filters in use, CSV export
  that streams in chunks and prefixes `'` to cells starting with `= + - @`.
- **Forms**: validate on the server, return 422 with the values the person typed, show errors above the form. Show the errors,
  never exception text; log the real error with `error_log()`.
- **Destructive actions** ask first (a GET confirmation page, then a POST), unless trivially reversible (disable/enable, archive).
- **Messages**: `$this->app->session->flash('success'|'info'|'warning'|'danger', '...')` then redirect (POST → redirect → GET).
- **Notifications** never block the change that caused them: ask the Email module and turn a failure into a warning.
- **Administrators**: never allow the last active administrator to be demoted or disabled.
- **Secrets** (API keys, SMTP password) are encrypted with `SecretBox` before they go in the `settings` table; never log them, never
  put them in a URL.
- **Errors in views**: nothing from the database or an exception goes to the page unescaped or unfiltered.
- Match the surrounding code: comment density, naming, idiom. Comments explain why, not what.

## Testing

There is no test framework; test the way a person would, against a real database:

- `php -l` every file you changed.
- Run the real setup wizard on an empty database (`public/setup.php`) with `APP_CONFIG` pointing at a scratch config file, using
  PHP's built-in server: `APP_CONFIG=/path/to/config.php php -S 127.0.0.1:8080 -t public public/index.php`.
- Drive the flows with `curl` and a cookie jar (sign in, the page, the POST with its CSRF token) and look at the HTML and the
  status codes. Signed-out requests redirect with 303.
- Look at changed pages in a browser at desktop and phone width, light and dark.
- Check the not-allowed case: a signed-out visitor, and a signed-in user who is not an administrator.

## Starting a project from this template

See "Starting a new project" in `README.md`. The example module (`Items`) and `migrations/002_items.sql` go once the first real
feature exists.
