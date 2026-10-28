# webx-ui/module-users

Visitors of the site: customers, readers of a closed section, members of a club. Their own table,
their own guard `site`, sign-in and registration by e-mail, and the panel's seam
`WebxUi\Admin\Visitors\Visitors` answered by them. A visitor is never an administrator: no
permissions, no way into `/cms` (administrators are `webx-ui/module-auth`). Nothing of a shop is
in here — shop modules plug into the seam, the module knows none of them.

Built in steps: social sign-in, the panel section «Customers» and the agent tools come later;
what is listed here exists now.

## What it owns

- **Table** `users` (`Models\User`): `email` (unique, trimmed, lower case), `email_verified_at`,
  `password` (null — none), `first_name`, `last_name`, `phone` (indexed), `locale`, `extra` (json),
  `blocked_at`, `remember_token` (set on creation), `last_sign_in_at` and `created_at` (indexed).
  No `name` column: `$user->name` is first and last name, or the address before «@».
- **Table** `users_password_resets` (`Passwords\ResetLinks`): one live link per visitor (`user_id`
  primary), `token_hash` — sha256 of a 32-byte base64url token, never the token; `purpose`
  `reset` / `invite`; `expires_at` (`webx-users.reset_minutes`, 60). Gone once used, with a new
  password, with a new address. **Table** `users_identities` — linked networks (the flow comes later).
- **Over Laravel's table** (`Schema\UsersTable`): empty — dropped with an empty
  `password_reset_tokens`; with rows — «Table `users` already holds N rows; move them out or drop the
  table, then migrate again», the same in `webx:doctor`. The rollback drops only the module's table.
- **Guard** `site` (`webx-users.guard`), provider `webx-users` (`Auth\UserProvider`: null for a blocked
  visitor — the session ends). Added to `auth` only if the site has not declared them; `web` untouched.
- **The seam** `UserVisitors` bound to `Visitors` (scoped): `current()`, `find($id)`, `findByEmail()`
  (exact, never a search), `signInUrl($back)` — where `webx.visitor` of `module-admin` sends a guest.
- **Pages** (`routes/web.php`, all `webx_service`, `no-store`, `noindex`, inside the language prefix
  `{webxLocale}` like the blog's feed):
  - `GET|POST /account/sign-in` (`SignInController`) — «Remember me»; every wrong pair is «Wrong
    e-mail or password.» (a missing address is hashed all the same); blocked — only on the right one.
  - `GET|POST /account/register` (`RegisterController`) — names and phone as the settings ask, then
    `fields[…]`; signed in at once; a taken address said plainly; 404 when registration is `closed`.
  - `POST /account/sign-out` (`SignOutController`) — this device only and a new CSRF token, never
    `session()->invalidate()`: an administrator signed in on the same browser stays in the panel.
  - `GET|POST /account/forgot` (`ForgotController`) — one answer for every address; the letter
    through the queue, none to a blocked account or over the limit.
  - `GET|POST /account/reset/{token}` (`ResetController`) — a new `remember_token`, signed in; an
    unconfirmed address gets confirmed and **its `users_identities` rows are deleted** (§7 item 5).
  - `GET /account/verify/{id}/{hash}` (`VerifyController`) — signed, `verify_hours` (72), sha1 of
    the address; confirms without signing in. `POST /account/verify/resend` — `webx.visitor`.
  - `POST /account/email`, `GET /account/email/{id}` (`EmailController`, `Emails\EmailChange`) — with
    the current password; a signed link (`email_minutes`) with a fingerprint of the old address.
  - `GET /account/state` (`StateController`) — JSON `signedIn`, `name`, `verified`, `email` masked.
  - `GET /webx/users/users.js` (`Http\ScriptController`) — `resources/js/users.js` as written, immutable.
- **Account** (spec §11, `Account\*`): `/account` → the first section; `/account/profile`
  (`ProfileController`: names, phone, the fields of `profile`, the e-mail and its change);
  `/account/security` (`SecurityController`: the password with the current one or «Set a password»
  by a reset link, `sign-out-everywhere` — a new `remember_token`, `delete`). `AccountMenu` — sections
  `AccountSection` (profile 10, security 90, `badge` asked only by the menu), hidden by
  `webx-users.account.hidden`; `AccountRoutes::group(fn, verified:)` — a section's routes with every
  middleware, `.localised` copies; `<x-webx-users::account :title>` — the frame; the menu
  `users.account-menu` by the patch `resources/compositions/layout.account/default.json` (the frame
  prints it itself in another layout). Grid and menu — `resources/theme/account.css` (`@container`).
- **Deletion** (`Account\DeleteAccount`, §14.1): one transaction — `VisitorDeleting` (a listener that
  throws keeps the account), the row for good, identities and links; `Mail\AccountDeleted` by text.
  `DeletionWarnings::register(fn (Visitor): ?string)` — sentences shown before it.
- **Letters** `Mail\*` (`Letter`: queued, the visitor's `locale`, `webx-users::mail.<name>` in
  `<x-webx-users::letter>`): `VerifyEmail`, `ResetPassword`, `PasswordChanged`, `EmailChangeLink`, `EmailChanged`.
- **`Http\TrackVisitor`** in `web`: a session of an old password or `remember_token` signs out; the cookie `webx_visitor`
  (`1` signed in, `u` unconfirmed, none for a guest; not encrypted, not httpOnly). A guest — no query.
- **Banner** `<x-webx-users::verify-banner>`, node `users.verify-banner` first in `main` of `layout`:
  hidden and alike on a cached page, shown by `users.js`; on a page of the module by the server.
- **`webx.visitor:verified`** — `UserVisitors` answers an unconfirmed visitor with «Confirm your
  e-mail» and the letter once more (403, `AnswersUnverified`).
- **Console** `webx:users:prune [--dry-run]`, daily: links out of date, never an account.
  `webx:doctor` — `MailCheck` (`log` mail off a local machine warns), `FieldsCheck` (the refusals).
- **Limits** (`WebxUi\Users\Throttle`, a 429 that names the field and the seconds):
  failed sign-ins 5 a minute per address and IP, 30 per IP; registrations 5 an hour per IP; reset
  requests 3 an hour per address and IP, 20 per IP (silently); new passwords 10 a minute per IP;
  the letter of confirmation once a minute, 5 an hour; letters to a new address 3 an hour; wrong
  current passwords in the account 5 a minute; «Set a password» 3 an hour.
- **Events**: `VisitorRegistered`, `VisitorSignedIn` (`password` / `registration` / `reset`),
  `VisitorSignedOut` of `module-admin`; own `Events\VisitorSignInFailed` (sha256 of address and IP).
- **Views** `sign-in`, `register`, `forgot`, `reset`, `notice`, `account.profile`, `account.security`, the
  frames `<x-webx-users::page>` / `account`, the bare `standalone`; forms of `widgets` (`FormRules`).
  Classes `webx-account-auth*`, `webx-account*`, `webx-users-verify*`; base look `resources/theme/*.css`.
- **Layouts** (`Rendering\Layouts`): sign-in pages — `webx-users.layout` (`'layout'` after
  `webx:panel --sync`), handle `users.auth`; the account — `layout.account` where the site has it.
- **Fields of the site** (`Fields\*`, spec §10): `UserField` in `UserFields` — `register()` in a
  module's `boot()`, or `webx-users.fields`; an exception for a column's or taken name, a type
  `FieldTypes` lacks, options missing, a type with no site field outside the panel (`siteType()`).
  In `users.extra` (`$user->extra('club-card')`); `FieldValues` checks them alike for a form, the panel
  and MCP; `FieldCatalog` — nodes of the card `project-fields` and the list for `users://catalog`.
  `Requirements` — setting `users.fields.<name>` `hidden`/`optional`/`required`, also for
  `first_name`, `last_name`, `phone`. `webx:users:index-field <name>` — a site migration with
  generated `extra_<name>` and its index, used by `unique`.
- **Dictionary** `webx-users` in ten languages: `module` («Customers»), `auth`, `account`, `mail`.

### Permissions

None yet: every route is the visitor's own door, open with its limit in `tests/AccessCoverageTest.php`;
`users.view` and `users.manage` come with the panel section.

## Change it without forking

| You want                                   | Do                                                                     |
| ------------------------------------------ | ---------------------------------------------------------------------- |
| Pages at another address                   | `WEBX_USERS_PREFIX` (`webx-users.prefix`, default `account`)           |
| Registration by invitation only            | `WEBX_USERS_REGISTRATION=closed`                                       |
| A stricter password                        | `webx-users.password_rules`: `min`, `mixed`, `numbers`, `symbols`      |
| Other limits                               | `webx-users.throttle.*` (`sign_in`, `forgot`, `verify_minute`, …)      |
| Links that live longer or shorter          | `webx-users.reset_minutes`, `.verify_hours`, `.email_minutes`          |
| Other letters                              | `views/vendor/webx-users/mail/<name>.blade.php` or `components/letter` |
| No banner, or elsewhere                    | a theme patch on `layout`: `remove` / `move` `users.verify-banner`     |
| No «Remember me»                           | `webx-users.remember` → `false`                                        |
| The sign-in in a narrow layout             | `webx-compositions.layouts`: `['users.auth' => 'layout.minimal']`      |
| Another layout for the pages               | `WEBX_USERS_LAYOUT` / `WEBX_USERS_ACCOUNT_LAYOUT` — a Blade component  |
| Other words                                | `lang/vendor/webx-users/<lang>/auth.php` (`webx-users-lang`)           |
| Another look                               | the theme: the classes above; or `views/vendor/webx-users/*.blade.php` |
| Know who is signed in, from another module | `app(Visitors::class)->current()` (`module-admin`), not `User`         |
| A page only for a signed-in visitor        | the middleware `webx.visitor` of `module-admin`                        |
| A field of your own on the visitors        | `webx-users.fields` or `UserFields::register()`; asked — the setting   |
| A section of the account from your module  | `AccountMenu::register()` + `AccountRoutes::group()` in `boot()`       |
| No section of a module in the menu         | `webx-users.account.hidden`: `['wallet']`                              |
| Say something before an account goes       | `DeletionWarnings::register()`; unlink rows on `VisitorDeleting`       |

## Do not

- Do not call `auth()->user()` or `Auth::user()` for a visitor: that is the default guard `web`,
  which is nobody. Name the guard (`Auth::guard('site')`) or ask `Visitors`.
- Do not end a visitor's session with `session()->invalidate()` or `flush()`: the session is one for
  both guards and the administrator goes with it. Use `WebxUi\Users\SignIn::out()`.
- Do not give `User` permissions or let it through `cms.auth`: an editor who shops has two accounts.
- Do not require or name a shop module here (`module-cart`, `module-orders`, …): the shop knows the
  visitor through `Visitors` and the events; `BoundariesTest` reads `composer.json` and `src`.
- Do not take `back` from a request as it is: `Urls::local()` keeps only a path of this site.
- Do not declare a section's routes by hand or call the guard's `logout()`: `AccountRoutes::group()`
  has the middleware, `SignIn::out()` keeps the other devices (the token is their stamp).
- Do not print the visitor's name or address into a cached page: hidden, filled by `users.js`.
- Do not change a password without a new `remember_token` (and `ResetLinks::forget()` on a reset).
- Do not drop a `users` table with rows to get past the refusal: move them out first.

## Check your work

- `php artisan webx:doctor` — «Visitors: table `users` is the module's.» (a `fail` names the rows) and «letters to visitors …» (a warning: they stay in a log).
- `php artisan route:list --path=account` — the pages, and the same behind `{webxLocale}`.
- `/account/sign-in` and `/account/register` answer `Cache-Control: no-store, private` and
  `X-Robots-Tag: noindex`; the forms are sent in place (the theme's widgets script).
- `vendor/bin/phpunit` in the package: `SignInTest`, `RegisterTest`, `PasswordResetTest`,
  `VerificationTest`, `EmailChangeTest`, `BannerTest`, `PruneTest`, `BoundariesTest`,
  `MigrationTest`, `SkeletonTest`, `AccessCoverageTest`, `FieldsTest`, `FieldDeclarationsTest`, `AccountTest`,
  `SecurityTest`, `AccountThemeTest`; `resources/js/users.test.js` (vitest).

## Read more

- README.md beside this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_USERS.md
- The seam of visitors: https://github.com/webx-ui/webx-ui/blob/main/apps/docs/guide/extending.md
