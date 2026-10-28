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

| Key                   | Default   | What                                                     |
| --------------------- | --------- | -------------------------------------------------------- |
| `prefix`              | `account` | where the pages are                                      |
| `registration`        | `open`    | `closed` — no registration page, sign-in only            |
| `password_rules`      | `min: 8`  | `mixed`, `numbers`, `symbols`, `uncompromised` on demand |
| `remember`            | `true`    | «Remember me» on the sign-in                             |
| `throttle.sign_in`    | `5`       | failed sign-ins a minute, per address and IP             |
| `throttle.sign_in_ip` | `30`      | failed sign-ins a minute, per IP                         |
| `throttle.register`   | `5`       | registrations an hour, per IP                            |
| `layout`              | —         | the Blade component the pages stand in (`layout`)        |
| `account_layout`      | —         | the account's; `layout.account` of the theme by default  |

## License

MIT
