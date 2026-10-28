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
| `throttle.social`       | `20`      | trips to a social network a minute, per IP                |
| `reset_minutes`         | `60`      | how long a reset link works                               |
| `verify_hours`          | `72`      | how long a link of confirmation works                     |
| `email_minutes`         | `60`      | how long a link to a new address works                    |
| `layout`                | —         | the Blade component the pages stand in (`layout`)         |
| `account_layout`        | —         | the account's; `layout.account` of the theme by default   |
| `fields`                | `[]`      | the site's own fields on its visitors (below)             |
| `social.order`          | `[]`      | the keys of the social buttons first, in this order       |
| `social.keys`           | `.env`    | the fallback of a provider's keys (below)                 |

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

## Social networks

Google and Facebook come with the module (on top of `laravel/socialite`). A provider is shown — a
button under the sign-in and the registration, its routes — only when it is switched on and every
required key has a value. The keys are the panel's (`users_providers`, the secret encrypted and
never given back); where a field there is empty, `.env` fills it, and switches the provider on while
the panel has no row of it:

```dotenv
WEBX_USERS_GOOGLE_ENABLED=true
WEBX_USERS_GOOGLE_CLIENT_ID=…
WEBX_USERS_GOOGLE_CLIENT_SECRET=…
WEBX_USERS_FACEBOOK_APP_ID=…
WEBX_USERS_FACEBOOK_APP_SECRET=…
```

The return address for the provider's console is `https://<site>/account/auth/<key>/callback` —
one for every language. Google's verified address on an account with a confirmed address links by
itself and the owner gets a letter; anything less (Facebook, an unconfirmed account) links when the
visitor signs in with the password. With no address from the provider, or a field the site
requires, the visitor finishes on «Almost done» (`/account/auth/complete`). The last way to sign in
is never unlinked.

Another network is a class of your own, registered in `boot()` — the module is not edited:

```php
use WebxUi\Users\Social\SocialProviders;

$this->callAfterResolving(SocialProviders::class, fn (SocialProviders $providers) => $providers->register(new AppleProvider));
```

`SocialiteProvider` does the work for a Socialite driver: `key()`, `label()`, `fields()` (the shared
`WebxUi\Admin\Support\Field`, `secret: true` for a secret, `required: true` for what it cannot work
without), `trustsVerifiedEmail()`, `driver()` and `toProfile()`. A network Socialite does not know
implements `WebxUi\Users\Social\Provider` itself.

## The panel: «Customers»

Three entries in a menu group of their own — **Customers**, **Groups**, **Settings** — with the npm
half `@webx-ui/module-users` (`...users()` in the panel's `admin.ts`, written there by
`webx:panel --sync`).

- **Permissions:** `users.view` reads the list, the card and the groups — addresses and phones,
  personal data; `users.manage` writes, sends the letters, blocks, deletes, edits the groups;
  `users.settings` opens the settings alone. By default only «Administrator» holds them.
- **The card** is the screen `users.form`: a site patches it like any screen; modules add a tab to
  `tabs` and a line to `summary`; the site's fields with the place `panel` land in
  `project-fields` by themselves. A new address set by an administrator is unconfirmed at once and
  both addresses get a letter.
- **No password in the panel:** an administrator sends a link. A customer made in the panel has
  none, and «Send an invitation» mails a link of «Set a password», alive 7 days.
- **Groups** — a code and a name in one language; `$user->groups()->attach()` from a module;
  `Visitor::$groups` carries the codes.
- **Settings** — the registration open or closed (`users.registration`, over
  `webx-users.registration`) and how much each field is asked (`users.fields.<name>`), kept with
  the site's settings (`module-settings`); a card per social network, a secret answered «set» only.
- Every change of a customer, a group and a network is in the journal; a password and a secret as
  «changed».

## For an agent (MCP)

Through `webx-ui/mcp` the same section answers an agent, through the panel's own classes — the
same filters, the same checks of the card, the same groups and settings:

| Tool                                                            | What it does                                                  |
| --------------------------------------------------------------- | ------------------------------------------------------------- |
| `users_list`, `users_get`                                       | a page of customers; one by id or exact e-mail, with `fields` |
| `users_create`, `users_update`, `users_delete`                  | no password ever; `invite: true`; `groups` replace the list   |
| `users_send_password_reset`, `users_resend_verification`        | answered «sent», never with the link                          |
| `users_unlink_provider`                                         | the last way to sign in stays                                 |
| `users_groups_list`, `users_groups_save`, `users_groups_delete` | by code                                                       |
| `users_settings_get`, `users_settings_set`                      | `users.settings`; a secret only «set», never set through MCP  |

Reading needs `users:read` and `users.view` — and gives the addresses and phones, which is personal
data; writing `users:write` and `users.manage`. Every change takes `dry_run`: the write rolled back,
and no letter leaves. `users://catalog` lists the fields with their types and requirements, the
networks, the groups, the sections of the account, the rules of a password and the limits — read
it first.

## License

MIT
