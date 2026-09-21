# webx-ui/module-auth

Administrators, roles and sign-in for a [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel.

Installing it is what closes the panel. Before it, `webx-ui/module-admin` serves its API to anyone who
asks — and says so during `webx:install`, because that is the kind of thing that is easy to
leave for later.

## Requirements

- PHP 8.3+
- Laravel 13
- `webx-ui/module-admin`, `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-auth
php artisan migrate
php artisan webx:admin
```

`webx:admin` asks for a name, an address and a password. There is deliberately no `--password`
option: a password on the command line lands in the shell history and in the process list. The
first account created is a super administrator, because otherwise nobody could grant anybody
else anything.

## Administrators are not users

They live in `cms_users`, behind their own `cms` guard, and they are not the people who use the
site. A leak in the public part therefore does not reach the panel, and the two sets of accounts
never have to agree about what an account is.

The guard and provider are registered for you. Anything the application has already defined
under those names wins — it knows something this package does not.

## Roles and permissions

Permissions are the strings modules declare in their manifest — `pages.view`, `media.manage` —
so the manifest is the list of what exists. A role only records which of them it grants, in a
JSON column; a permissions table would be a second copy of that list, quietly diverging.

```php
$role = Role::create([
    'slug' => 'editor',
    'name' => 'Editor',
    'permissions' => ['pages.view', 'pages.manage'],
]);

$user->roles()->attach($role);

$user->hasPermission('pages.manage'); // true
$user->permissions();                 // flattened, deduplicated, sorted
```

A super administrator passes every check without holding any permission.

On your module's routes:

```php
Route::middleware(['cms.auth', 'cms.can:pages.manage'])->group(/* … */);
```

Several permissions mean any of them: `cms.can:pages.manage,pages.publish`.

## Signing in

| Route                       | What it does                                  |
| --------------------------- | --------------------------------------------- |
| `POST /api/cms/auth/login`  | Session sign-in. Throttled.                   |
| `POST /api/cms/auth/logout` | Ends it.                                      |
| `GET /api/cms/auth/me`      | Who is signed in, with roles and permissions. |

The session is what authenticates the panel, so its API runs through the `web` group rather
than the stateless `api` one. A stranger gets **401** rather than a redirect: there is no
server-rendered login page to redirect to, and the front end is waiting for that answer to draw
its own.

A wrong password, an address nobody owns and a deactivated account all fail the same way. A
different answer to any of them would turn the login form into a way to find out who has an
account. An account switched off mid-session stops working at its next request, not at its next
sign-in.

The shell at `/cms` stays public: it carries no data, and it is what draws the login form.

## What gets written down

Every attempt, successful or not, lands in `cms_login_records` with the address, the IP and the
user agent. Failures matter more than successes here — a burst of them against one address, or
one address against many, is the shape of an attack.

Events: `AdminLoggedIn`, `AdminLoggedOut`, `AdminLoginFailed`.

## For AI agents

The module offers MCP tools for reading who has what, moving people between roles, and looking
at the sign-in trail:

```
admins_list_admins              admins:read
admins_list_roles              admins:read
admins_grant_role              admins:write
admins_revoke_role             admins:write
admins_recent_sign_ins         admins:audit
admins_failed_sign_in_bursts   admins:audit
```

There is deliberately **no tool that sets a password, mints a token, creates an account or
deletes one**. Those are the operations where a mistaken tool call is not a mistake you can
review afterwards. A test asserts that no such tool appears.

Both role tools honour `dry_run`, so an agent can be asked what it would change first.

## Configuration

`config/webx-auth.php`. If you keep your own middleware stack on the panel's API, set
`protect_panel` to `false` and add `cms.auth` yourself — otherwise this package replaces it.

## Not here yet

Password reset, two-factor, and invitations.

An administrator is also the account an agent acts as, so the model carries Passport's
`HasApiTokens` and Passport is a dependency. It stays inert until a site publishes Passport's
migrations and generates its keys — see `webx-ui/mcp` for what that opens.

## Languages

Ten shipped: en, ru, uk, de, pl, fr, es, it, pt, tr. English is the fallback, and it is laid
_under_ the chosen language line by line, so a half-translated group shows what it has and
English for the rest.

Only English, Russian and Ukrainian have been read by a speaker; the other seven are machine
translations. A correction from someone who speaks the language is welcome — they are plain PHP
arrays in `lang/`.

## Licence

MIT.
