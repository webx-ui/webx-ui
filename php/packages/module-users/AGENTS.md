# webx-ui/module-users

Visitors of the site: customers, readers of a closed section, members of a club. Their own table,
their own guard `site`, sign-in and registration by e-mail, and the panel's seam
`WebxUi\Admin\Visitors\Visitors` answered by them. A visitor is never an administrator: no
permissions, no way into `/cms` (administrators are `webx-ui/module-auth`). Nothing of a shop is
in here — shop modules plug into the seam, the module knows none of them.

Built in steps: own fields, the account, social sign-in, the panel section «Customers» and the
agent tools come later. What is listed here
is what exists now.

## What it owns

- **Table** `users` (`WebxUi\Users\Models\User`): `email` (unique, kept trimmed and lower case),
  `email_verified_at`, `password` (null — no password), `first_name`, `last_name`, `phone`
  (indexed), `locale` (the language of the last sign-in), `extra` (json), `blocked_at`,
  `remember_token`, `last_sign_in_at` (indexed), timestamps (`created_at` indexed). No `name`
  column: `$user->name` is first and last name, or the part of the address before «@».
- **Table** `users_password_resets` (`Passwords\ResetLinks`): one live link per visitor (`user_id`
  primary), `token_hash` — sha256 of a 32-byte base64url token, never the token; `purpose`
  `reset` / `invite`; `expires_at` (`webx-users.reset_minutes`, 60). Gone once used, with a new
  password, with a new address. **Table** `users_identities` — linked networks (the flow comes later).
- **Over Laravel's table** (`WebxUi\Users\Schema\UsersTable`): a site from the skeleton of before
  has Laravel's `users`. Empty — the migration drops it with an empty `password_reset_tokens` and
  makes its own; with rows — it refuses: «Table `users` already holds N rows; move them out or drop
  the table, then migrate again». `webx:doctor` says the same line (`Doctor\UsersTableCheck`). The
  rollback drops only the module's table.
- **Guard** `site` (`webx-users.guard`): driver `session`, provider `webx-users` —
  `WebxUi\Users\Auth\UserProvider`, Eloquent over `User` that answers null for a blocked visitor, so
  an open session ends on its next request. Both are added to `auth` only if the site has not
  declared them; the default guard `web` is left alone.
- **The seam** `WebxUi\Users\UserVisitors` is bound to `Visitors` (scoped): `current()` — the
  visitor of the guard `site`; `find($id)`; `findByEmail()` — the exact normalised address, never a
  search; `signInUrl($back)` — the sign-in in the page's language with `?back=` (a path of this site
  only). `webx.visitor` of `module-admin` sends a guest there.
- **Pages** (`routes/web.php`, all `webx_service`, `no-store`, `noindex`, inside the language prefix
  `{webxLocale}` like the blog's feed):
  - `GET|POST /account/sign-in` (`Http\Controllers\SignInController`) — e-mail, password, «Remember
    me»; every wrong pair is «Wrong e-mail or password.», an address that is not there is hashed
    all the same; a blocked account is told so only on the right password.
  - `GET|POST /account/register` (`RegisterController`) — first and last name, phone (optional),
    e-mail and password (required); signed in at once; a taken address is said plainly; 404 when
    `webx-users.registration` is `closed`.
  - `POST /account/sign-out` (`SignOutController`) — the guard's logout and a new CSRF token, never
    `session()->invalidate()`: an administrator signed in on the same browser stays in the panel.
  - `GET|POST /account/forgot` (`ForgotController`) — one answer for every address; the letter
    through the queue, none to a blocked account or over the limit.
  - `GET|POST /account/reset/{token}` (`ResetController`) — new password and its repetition; a dead
    link shows «The link is out of date» over the request. After: new `remember_token`, other
    sessions end, signed in, «Your password was changed». An unconfirmed address gets confirmed
    and **every `users_identities` row of the account is deleted** (spec §7 item 5).
  - `GET /account/verify/{id}/{hash}` (`VerifyController`) — signed, `webx-users.verify_hours` (72),
    `hash` = sha1 of the address; confirms without signing in. `POST /account/verify/resend` —
    the signed-in visitor's own (`webx.visitor`).
  - `POST /account/email` and `GET /account/email/{id}` (`EmailController`, `Emails\EmailChange`) —
    new address with the current password; a signed link (`email_minutes`, 60) to the new address
    with a fingerprint of the old one; nothing changes before it. Then confirmed, the reset link
    gone, «Your sign-in address was changed» to the old address.
  - `GET /account/state` (`StateController`) — JSON `signedIn`, `name`, `verified`, `email` masked.
  - `GET /webx/users/users.js` (`Http\ScriptController`) — `resources/js/users.js` as written, immutable.
- **Letters** `WebxUi\Users\Mail\*` (`Letter`: queued, in the visitor's `locale`, view
  `webx-users::mail.<name>` in the frame `<x-webx-users::letter>`): `VerifyEmail`, `ResetPassword`,
  `PasswordChanged`, `EmailChangeLink`, `EmailChanged`. Words — `webx-users::mail`.
- **`Http\TrackVisitor`** in the `web` group (through the HTTP kernel): the session's stamp of the
  password — a session opened with an old password signs out on its next request; and the cookie
  `webx_visitor` (`1` signed in, `u` unconfirmed, none for a guest; not encrypted, not httpOnly).
  A guest costs no query.
- **Banner** `<x-webx-users::verify-banner>`, the node `users.verify-banner` first in `main` of
  `layout` (`resources/compositions/layout/default.json`): on a cached page printed hidden and the
  same for everybody, shown by `users.js`; on a page of the module (`KeepPrivate`) by the server.
- **`webx.visitor:verified`** — `UserVisitors` answers an unconfirmed visitor with «Confirm your
  e-mail» and the letter once more (403, `AnswersUnverified`).
- **Console** `webx:users:prune [--dry-run]`, daily: links out of date, never an account.
  `webx:doctor` — `Doctor\MailCheck`: `log` / `array` mail off a local machine is a warning.
- **Limits** (`WebxUi\Users\Throttle`, a 429 that names the field and the seconds):
  failed sign-ins 5 a minute per address and IP, 30 per IP; registrations 5 an hour per IP; reset
  requests 3 an hour per address and IP, 20 per IP (silently); new passwords 10 a minute per IP;
  the letter of confirmation once a minute, 5 an hour; letters to a new address 3 an hour.
- **Events**: `VisitorRegistered($id, 'password')`, `VisitorSignedIn($id, 'password' |
'registration' | 'reset')`, `VisitorSignedOut($id)` of `module-admin`; `WebxUi\Users\Events\VisitorSignInFailed`
  (sha256 of the address and the IP — no address, no password).
- **Views** `webx-users::sign-in`, `register`, `forgot`, `reset`, `notice`, the frame `<x-webx-users::page>` and the
  bare document `webx-users::standalone`; the forms are `<x-webx-form>` and `<x-webx-field>` of
  `widgets`, checked in the browser by the server's rules (`FormRules`). Classes `webx-account-auth`,
  `__card`, `__title`, `__form`, `__note`, `__forgot`, `__links`; `webx-users-verify`, `__text`, `__email`,
  `__form`, `__close`; base look `resources/theme/users.css` on `--site-*`.
- **Layouts** (`WebxUi\Users\Rendering\Layouts`): the sign-in and the registration stand in
  `webx-users.layout` (`'layout'` once `webx:panel --sync` has seen the site's one) with the handle
  `users.auth`; the account — `layout.account` of the theme where the site has it. Handles
  `users.account` and `users.auth` are declared to the compositions.
- **Dictionary** `webx-users` in ten languages: `module` (the section is «Customers»), `auth`, `mail`.

### Permissions

None yet: every route is the visitor's own door, listed open with its limit in
`tests/AccessCoverageTest.php`, none behind `cms.auth`. `users.view` and `users.manage` come with the
panel section.

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

## Do not

- Do not call `auth()->user()` or `Auth::user()` for a visitor: that is the default guard `web`,
  which is nobody. Name the guard (`Auth::guard('site')`) or ask `Visitors`.
- Do not end a visitor's session with `session()->invalidate()` or `flush()`: the session is one for
  both guards and the administrator goes with it. Use `WebxUi\Users\SignIn::out()`.
- Do not give `User` permissions or let it through `cms.auth`: a visitor is never an administrator.
  An editor who is also a customer has two accounts.
- Do not require or name a shop module here (`module-cart`, `module-orders`, …): the shop knows the
  visitor through `Visitors` and the events; `BoundariesTest` reads `composer.json` and `src`.
- Do not take `back` from a request as it is: `Urls::local()` keeps only a path of this site.
- Do not print the visitor's name or address into a page the cache serves to everybody: print it
  hidden and let `users.js` fill it from `/account/state`.
- Do not change a password without `ResetLinks::forget()` and a new `remember_token`.
- Do not drop a `users` table with rows to get past the refusal unless the rows are worthless; move
  them out first — the migration never does it for you.

## Check your work

- `php artisan webx:doctor` — «Visitors: table `users` is the module's.» (a `fail` names the rows) and «letters to visitors …» (a warning: they stay in a log).
- `php artisan route:list --path=account` — the pages, and the same behind `{webxLocale}`.
- `/account/sign-in` and `/account/register` answer `Cache-Control: no-store, private` and
  `X-Robots-Tag: noindex`; the forms are sent in place (the theme's widgets script).
- `vendor/bin/phpunit` in the package: `SignInTest`, `RegisterTest`, `PasswordResetTest`,
  `VerificationTest`, `EmailChangeTest`, `BannerTest`, `PruneTest`, `BoundariesTest`,
  `MigrationTest`, `SkeletonTest`, `AccessCoverageTest`; `resources/js/users.test.js` (vitest).

## Read more

- README.md beside this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_USERS.md
- The seam of visitors: https://github.com/webx-ui/webx-ui/blob/main/apps/docs/guide/extending.md
