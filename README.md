# Web app template

A starting point for small PHP 8.1+ / PDO (MySQL or MariaDB) web apps, with no Composer dependencies. Every new project
starts from the same foundation, so they look, behave and are built alike: sign-in (password and Google), users and roles,
password reset by email, light/dark theme, a migrations page, and a complete example feature (**Items**) to copy.

## Starting a new project

1. On GitHub, open this repository and choose **Use this template → Create a new repository**.
2. Clone the new repository. Point the web server's document root at `public/` (Apache: `.htaccess` is included; nginx:
   `try_files $uri /index.php?$query_string;`). The `config/` and `storage/` folders must be writable by the web server.
   For local development: `php -S localhost:8000 -t public public/index.php`
3. Open the site. With no config yet you are sent to **`/setup.php`**: enter the application name and the database details
   (the database is created if it does not exist, the tables are created from `migrations/`) and the first administrator.
   Setup writes `config/config.php` and then locks itself (delete that file to run it again).
4. Sign in as that administrator. On **Theme** set the site name, colours and the privacy-policy contact; on **Email**
   set up the mail server (needed for temporary passwords and "Forgot password"); on **Sign-in** turn on Google if wanted.
5. Make it yours. In order:
   - Rename the example: copy `modules/Items` and `migrations/002_items.sql` to your first real feature (or delete both
     before applying `002` anywhere, if you do not want the example). `CLAUDE.md` describes how a module is built.
   - Replace the cards on `modules/Dashboard/views/index.php` and the "Getting started" text.
   - Replace `public/static/favicon.svg` if you remove the Theme module; edit `modules/Auth/views/privacy.php` to say what
     your app really keeps, and update `PRIVACY_UPDATED` in `modules/Auth/AuthModule.php`.
   - Rewrite this README for the new project (keep the parts that still apply).

## What is included

| Module | What it does |
|---|---|
| **Auth** | Sign in / out, **Forgot password**, **Sign in with Google**, the public `/privacy` page |
| **Account** | Change your own password (and the forced change after a temporary password) |
| **Users** (admin) | List, search, sort, add, edit, disable and re-enable users; roles **User** and **Administrator** |
| **Theme** (admin) | Site name, privacy-policy contact, ten ready-made palettes (light and dark) or your own colours |
| **Email** (admin) | Mail server settings, notification list, test email, log of what was sent |
| **Sign-in** (admin) | Turn Google sign-in on and paste the client ID |
| **Migrate** (admin) | Apply pending database migrations from the browser |
| **Dashboard** | The home page: a welcome and a few cards to replace |
| **Items** | The example feature: list with live search, filters, sort, paging, CSV export, bulk archive/restore, detail page, add/edit form, delete with confirmation, email notification |

Everything lives under `modules/<Name>/`; a module is found automatically, and deleting its folder removes it (the rest of the
app keeps working). The framework is in `src/Core/`, the page layouts in `templates/`, CSS and JS in `public/static/`.

### Roles

| | User | Administrator |
|---|---|---|
| Use the app's own features (e.g. Items) | yes | yes |
| Users, Theme, Email, Sign-in, Migrations | no | yes |
| Delete an item | no | yes |

Roles are the `users.admin` flag. Add more by following the same pattern (a column, a check in `src/Core/Auth.php`, a route
option in `App::handle`); keep the first administrator impossible to demote or disable (see `UsersModule`).

### New users and temporary passwords

The **New user** page has no password box. Creating a user generates a random temporary password (14 characters, no look-alike
letters or digits) and emails it, with the username and sign-in link, to the address you enter. The first time the new user
signs in, every page except **Change password** sends them to choosing their own. If the email cannot be sent (mail not set up,
or the server refuses) the user is still created and the temporary password is shown to you **once**, on that page. It is never
stored in readable form or put in the email log. An administrator cannot set someone's password: people choose their own, or use
**Forgot password?**. Passwords are 10 to 64 characters (counted in bytes).

### Forgot password

When email is set up, the sign-in page shows **Forgot password?**. The person types their username or email address and is
emailed a link to choose a new password.

- The link works **once** and expires after **60 minutes**; asking again replaces the earlier link. Only a hash of the link's
  secret is stored (table `password_resets`), so a copy of the database cannot reset a password, and the link is never written to
  the email log.
- **Nobody can find out whether an account exists.** Every request gets the same message at the same speed (the email is sent
  after the answer has gone). Nothing is sent for an unknown name, a disabled account, an account without an email address, or
  an address shared by two accounts.
- A new link for the same account is refused for a minute, and one address can ask for at most 10 links an hour.

### Signing in with Google

An administrator turns this on from **Sign-in**. The sign-in page then shows Google's button under the password form; passwords
keep working. The page lists the setup steps in the Google Cloud console with this site's address filled in: an OAuth client of
type *Web application*, this site's address under **Authorized JavaScript origins** (`https://` unless `localhost`), the consent
screen set to **External** and **published**, and the privacy-policy link set to `https://your-site/privacy`. The client ID is not
a secret.

- **Any Google account can try; only matching ones get in.** The person must have a user here whose email address is the verified
  one in Google's token (capitals ignored). No match, two matches, or a disabled user means no sign-in. Nobody is created
  automatically.
- **The token is checked here, not trusted.** `src/Core/GoogleIdToken.php` verifies the RS256 signature against Google's published
  keys (cached in `storage/google-certs.json`), then the issuer, the client ID, expiry, a verified email, and a **one-time nonce**
  tied to the visitor's session. The browser posts it to `/login/google` as an ordinary form with the usual CSRF token.
- A pending temporary password is switched off when that person signs in with Google.
- The sign-in page, and only that page, widens the Content-Security-Policy for Google's script, frame and stylesheet, and allows
  inline styles (Google's script writes some).
- Needs the `openssl` extension and outgoing HTTPS to `www.googleapis.com`; saving the settings checks that.

### Email

**Email** (administrators only) holds the mail server (host, port, **STARTTLS on 587** or **SSL/TLS on 465**), username and password,
the "send from" address, the people who receive notifications, and the public address of the site for links in emails. There is a
**Send test email** button and a log of what was sent.

- **Gmail:** `smtp.gmail.com`, STARTTLS (587), the full address as the username and an **app password** (turn on 2-Step
  Verification, then Google Account → Security → App passwords). The "send from" address must be that account or an alias.
- **The password** is stored encrypted. The key is `storage/secret.key`, created on first use: outside the web root, never
  committed and not part of a database dump. **Back up `storage/` together with the database and treat that backup as
  sensitive.** If the key is lost, enter the password again.
- **Sending never blocks the work.** If the mail server is down, the change is still saved; the person sees a warning and the
  details are in the log.
- Mail is sent by the bundled `lib/tehsmtp/` (a pure-PHP SMTP client). To add a notification see the comment at the top of
  `modules/Email/Notifier.php` and `ItemsModule::create`.

### Theme

**Theme** changes the site name and the colours for everyone, including the sign-in page. Pick a ready-made palette or adjust the
five colours yourself (accent, page background, cards, sidebar and text, per mode); a combination whose text or accent colour
would be hard to read (contrast below 4.5:1) is refused. The colours reach the pages as a small generated stylesheet at
`/theme/css`. The browser tab icon is generated at `/theme/favicon.svg`. To remove the feature, delete `modules/Theme`.

### Migrations

`migrations/*.sql` are applied once each, in filename order, and recorded in a `schema_migrations` table. Setup applies all of
them; after you update the code on a server, open **Migrations** (or `/migrate.php`) as an administrator to apply new ones. To
change the schema add the next numbered file (`003_add_something.sql`). MySQL cannot roll back schema changes, so write
migrations to be safe to run twice (`CREATE TABLE IF NOT EXISTS`, ...): one that fails part-way is retried. `001_baseline.sql`
holds the template's own tables; do not edit it after an app has been installed.

## Layout

| Path | Purpose |
|------|---------|
| `public/index.php` | Front controller for the application |
| `public/setup.php` | First-run wizard (self-locking); `public/migrate.php` redirects to the admin Migrations page |
| `src/Core/` | Framework: `App` (request pipeline), `Router`, `Auth`, `Csrf`, `Session`, `Database` (PDO), `View`, `Migrator`, `Installer`, `Settings`, `SecretBox`, `GoogleIdToken` |
| `templates/` | Layouts (`layout-app`, `layout-auth`) and shared partials |
| `modules/<Name>/` | Feature modules: one folder per tab (`<Name>Module.php`, `views/`) |
| `migrations/` | Numbered `.sql` files |
| `lib/tehsmtp/` | The SMTP client |
| `public/static/` | CSS / JS (theme tokens for light + dark live at the top of `app.css`) |
| `storage/` | Written by the app (encryption key, Google's key cache): git-ignored, back it up |

## Request pipeline (`App::handle`)

1. Start session, send security headers (CSP with nonce, `X-Frame-Options`, etc.)
2. **Auth gate**: anything except routes flagged `['public' => true]` redirects to `/login` when not signed in (unknown URLs included)
3. A user still on a temporary password is redirected to the change-password page (routes flagged `['pending_ok' => true]` are the exception)
4. **CSRF**: every non-GET request must carry a valid token (`csrf_field()` in forms, or the `X-CSRF-Token` header)
5. **Authorization**: routes may be flagged `['admin' => true]`
6. The handler runs and returns the HTML

## Messages (toasts)

A message for the person who just did something (`$this->app->session->flash('success', 'Saved.')`; types are `success`, `info`,
`warning` and `danger`) is shown on the next page as a toast that fades by itself. Messages that belong to a form (a validation
error above the fields you are fixing) are ordinary boxes in the page, not toasts. From scripts, `window.toast(type, message)`.

## Adding a module (new tab)

Copy `modules/Items` (see `CLAUDE.md` for the checklist), or for something small create `modules/Reports/ReportsModule.php`;
modules are auto-discovered.

```php
<?php
declare(strict_types=1);
namespace App\Modules\Reports;

use App\Core\{Module, Request, Router};

final class ReportsModule extends Module
{
    public function routes(Router $router): void
    {
        $router->get('/reports', [$this, 'index']);
        // $router->post('/reports/run', [$this, 'run'], ['admin' => true]);
    }

    public function nav(): array
    {
        return [['label' => 'Reports', 'path' => '/reports', 'icon' => 'box', 'order' => 30]];
    }

    public function index(Request $req): string
    {
        $rows = $this->app->db->all('SELECT ... WHERE x = ?', [$value]);
        return $this->render('index', ['title' => 'Reports', 'rows' => $rows]); // modules/Reports/views/index.php
    }
}
```

In views, wrap every dynamic value in `e()`; in forms include `<?= csrf_field() ?>`. New icons go in `src/Core/Icons.php`.

## Security notes

- **SQL**: PDO with real (non-emulated) prepared statements; user input is never concatenated into SQL (sort columns come from a fixed list).
- **XSS**: all output goes through `e()` (`htmlspecialchars`, `ENT_QUOTES`, UTF-8); CSP forbids inline styles and un-nonced scripts (except inline styles on the sign-in page when Google sign-in is on).
- **Passwords**: `password_hash()` / `password_verify()`; unknown usernames take the same code path as bad passwords.
- **Sessions**: HttpOnly + SameSite=Lax cookie, strict mode, ID regenerated on login, idle timeout (default 30 min).
- **CSRF**: a token on every POST; the Google sign-in post carries one too.
- **Secrets**: the SMTP password is encrypted with a key in `storage/`; `config/config.php` and `storage/` are git-ignored.
