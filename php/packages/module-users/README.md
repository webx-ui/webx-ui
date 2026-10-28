# webx-ui/module-users

Visitors of a WebX UI site — the people who register and sign in on the site itself: customers,
readers of a closed section, members of a club. Their own table and guard, apart from the
administrators of the panel, and the panel's seam `Visitors` answered by them, so every other
module asks «who is this» the same way whether this package is installed or not.

```bash
composer require webx-ui/module-users
php artisan migrate
```

The visitor's pages are then at `/account/sign-in` and `/account/register` (and under the language
prefix, `/pl/account/sign-in`). `php artisan webx:panel --sync` stands them in the site's layout.

## A site made before this module

The skeleton of the site used to create Laravel's own `users` table. The migration looks before it
creates its own:

- no `users` — it creates it;
- Laravel's `users`, empty — it drops it, with an empty `password_reset_tokens`, and creates its own;
- a `users` with rows — it stops: `Table users already holds N rows; move them out or drop the table,
then migrate again`. `php artisan webx:doctor` says the same before a deploy runs into it.

## Letters: the password and the address

The module writes its own letters — through the queue, in the visitor's language — and needs a
mail transport that delivers (`webx:doctor` warns about `log` and `array` off a local machine) and
a queue worker:

- **Forgot your password?** `/account/forgot` answers the same for every address and sends a link
  that works once and for an hour. The new password ends the visitor's other sessions; it also
  confirms an unconfirmed address and unlinks the networks linked to it.
- **Confirming the address** — a link after the registration, valid for 72 hours. It never stands
  in the way: an unconfirmed visitor signs in and buys. A page that needs a confirmed address
  stands behind `webx.visitor:verified`, and the visitor sees «Confirm your e-mail» there.
- **The banner** «Confirm your e-mail» is added first in `main` of the layout. A cached page
  prints it hidden and the same for everybody; `users.js` shows it by the cookie `webx_visitor`.
- **A new address** — `POST /account/email` with the current password sends a link to the new
  address; the sign-in moves when it is followed, and the old address is told.

`php artisan webx:users:prune` (daily) deletes the links that are out of date.

## Who is signed in

From any module — a shop, a closed section — ask the seam of the panel, not this package:

```php
use WebxUi\Admin\Visitors\Visitors;

$visitor = app(Visitors::class)->current();   // null for a guest

$visitor?->email;
$visitor?->name();          // first and last name, or the part of the address before «@»
$visitor?->verified;        // the address is confirmed
```

A page only for a signed-in visitor stands behind `webx.visitor`: a guest is sent to the sign-in
and comes back. Without this package installed the same page answers 404.

```php
Route::middleware(['web', 'webx.visitor'])->get('/orders', OrdersController::class);
```

In Blade: `@visitor … @else … @endvisitor`.

Visitors sign in on the guard `site`. The site's default guard (`web`) is not a visitor:
`auth()->user()` stays null — name the guard (`Auth::guard('site')->user()`) or use `Visitors`.

## Configuration

```bash
php artisan vendor:publish --tag=webx-users-config
```

| Key                     | Default   | What                                                      |
| ----------------------- | --------- | --------------------------------------------------------- |
| `prefix`                | `account` | where the pages are                                       |
| `registration`          | `open`    | `closed` — no registration page, sign-in only             |
| `password_rules`        | `min: 8`  | `mixed`, `numbers`, `symbols`, `uncompromised` on demand  |
| `remember`              | `true`    | «Remember me» on the sign-in                              |
| `throttle.sign_in`      | `5`       | failed sign-ins a minute, per address and IP              |
| `throttle.sign_in_ip`   | `30`      | failed sign-ins a minute, per IP                          |
| `throttle.register`     | `5`       | registrations an hour, per IP                             |
| `throttle.forgot`       | `3`       | reset links an hour, per address and IP (`forgot_ip`: 20) |
| `throttle.reset`        | `10`      | new passwords a minute, per IP                            |
| `throttle.verify_*`     | `1` / `5` | letters of confirmation a minute / an hour, per visitor   |
| `throttle.email_change` | `3`       | letters to a new address an hour, per visitor             |
| `reset_minutes`         | `60`      | how long a reset link works                               |
| `verify_hours`          | `72`      | how long a link of confirmation works                     |
| `email_minutes`         | `60`      | how long a link to a new address works                    |
| `layout`                | —         | the Blade component the pages stand in (`layout`)         |
| `account_layout`        | —         | the account's; `layout.account` of the theme by default   |
| `fields`                | `[]`      | the site's own fields on its visitors (below)             |

## Fields of the site

A club card, a date of birth, a company — declared once, they reach the registration, the
profile, the visitor's card in the panel and MCP, and are kept in `users.extra`:

```php
// config/webx-users.php — rules as strings: the config is cached.
'fields' => [
    'birthday' => ['type' => 'wx-date-picker', 'label' => 'Date of birth', 'rules' => ['before:today']],
    'club-card' => ['type' => 'wx-input', 'label' => 'Club card', 'unique' => true,
        'visitorEditable' => false, 'placements' => ['profile', 'panel']],
],
```

A module does the same with `app(UserFields::class)->register(new UserField(…))` in its `boot()`.
The type is a field type of the panel (`wx-input`, `wx-select`, `wx-date-picker`, …) and checks the
value the same way everywhere; one the site's forms have no field for (a colour, a picture) lives
in the panel only — or gets one with `UserFields::siteType('map', 'text')`. A field that cannot
work is an exception, and `webx:doctor` says which.

Whether the visitor must fill one in is a setting of the site, `users.fields.<name>`: `hidden`,
`optional` or `required` — for these fields and for the first name, last name and phone. Read one
in Blade with `$user->extra('club-card')`. For a fast search by a field,
`php artisan webx:users:index-field club-card` writes a migration with an indexed column.

## The account

`/account` opens the first section of the menu: **Profile** (names, phone, the fields of the site
with the place `profile`, the e-mail and its change) and **Sign-in and security** (a new password
with the current one, or «Set a password» by a letter for an account without one; «Sign out
everywhere»; deleting the account). Every page is `no-store` and behind `webx.visitor`.

A module adds its own section without touching this package:

```php
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\AccountRoutes;
use WebxUi\Users\Account\AccountSection;

if (class_exists(AccountMenu::class)) {
    app(AccountMenu::class)->register(new AccountSection(
        id: 'orders', label: 'webx-orders::account.menu', route: 'webx.orders.account.index',
        position: 30, active: 'webx.orders.account.*',
    ));

    AccountRoutes::group(function () {
        Route::get('orders', [AccountOrdersController::class, 'index'])->name('webx.orders.account.index');
    });
}
```

`AccountRoutes::group()` puts the routes under the prefix of the account with the middleware of its
pages (`web`, the language, `webx.visitor`, a service route, `no-store`) and declares their copy behind
the language prefix (names with `.localised`; `Urls::named()` picks the page's). A section's page
prints itself in the frame of the account: `<x-webx-users::account :title="…">…</x-webx-users::account>`.
The site hides a section with `webx-users.account.hidden`.

Before an account goes, the modules say what will be lost — `DeletionWarnings::register(fn (Visitor
$visitor): ?string => …)` — and unlink what is theirs on `VisitorDeleting` of `module-admin`, inside the
transaction that deletes the row; a listener that throws keeps the account.

The menu stands in `layout.account` of the theme (node `users.account-menu`), the grid and the cards
are `resources/theme/account.css` — container queries, site tokens.

## License

MIT
