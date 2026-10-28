# Site visitors

`webx-ui/module-users` gives a site its own visitors: the people who register and sign in on the
site itself — customers, readers of a closed section, members of a club. They live in their own
`users` table behind their own guard `site`, apart from the administrators of the panel
(`cms_users`, guard `cms`). Signing out of the site never signs anybody out of the panel, and a
visitor never passes `cms.auth`.

Every other module asks «who is this» through the seam `Visitors` of `module-admin`, which answers
`null` when this package is not installed. So a shop works for guests without it, and nothing in
this package knows about a shop.

## Install

```bash
composer require webx-ui/module-users
php artisan migrate
php artisan webx:panel --sync
```

The pages are at `/account/sign-in`, `/account/register`, `/account/forgot` and `/account` (and
under the language prefix, `/pl/account/sign-in`). The section **Customers** appears in the panel
for whoever holds `users.view` or `users.manage`; by default that is only the «Administrator».

The module writes its letters through the queue, in the visitor's language: confirm the address,
reset the password, the address or the password changed, a network linked, the account deleted. It
needs a mail transport that delivers and a queue worker. `php artisan webx:doctor` warns about
`log` and `array` anywhere but a local machine.

A site made before the module still has Laravel's `users` table from the skeleton. The migration
drops it while it is empty and stops with `Table users already holds N rows` while it is not; the
doctor says the same before a deploy runs into it.

## Signing in

- **Registration** — e-mail and password, plus the first name, last name and phone as the settings
  of the site ask (Customers → Settings: `hidden`, `optional`, `required`), plus the fields of the
  site with the place `registration`. The visitor is signed in at once; the address waits for its
  letter.
- **Sign-in** — e-mail and password, «Remember me». A blocked visitor gets the same answer as a wrong
  password, and an open session of a blocked visitor ends on its next request.
- **Forgot the password** — the answer is the same whether the address has an account or not. The
  link lives an hour and works once. A reset also confirms an unconfirmed address and unlinks its
  social networks: whoever owns the mailbox owns the account.
- **Confirm the address** — a signed link, 72 hours, works without being signed in. Until then a
  banner asks for it on every page, cached pages included: it is drawn by a small script from the
  cookie `webx_visitor`, so a guest costs the server nothing.

Who is signed in, in Blade and in code:

```blade
@visitor
    Hello, {{ app(\WebxUi\Admin\Visitors\Visitors::class)->current()->name() }}
@else
    <x-webx-users::sign-in-button />
@endvisitor
```

```php
Route::get('/wishlist', WishlistController::class)->middleware('webx.visitor');          // signed in
Route::post('/reviews', StoreReviewController::class)->middleware('webx.visitor:verified'); // and confirmed
```

A guest is sent to the sign-in page and back; JSON gets `401 {code: "visitor.guest"}`. Without the
module both answer 404 — the page does not exist on that site.

### On a checkout

`<x-webx-users::sign-in-button />` opens a dialog with three views — sign in, forgot the password,
register — without leaving the page. After a sign-in from the dialog the page receives a
cancellable event and reloads unless somebody cancels it:

```js
document.addEventListener('webx:users:signed-in', (event) => {
  event.preventDefault() // stay on the page
  fillContacts(event.detail.name)
})
```

Without JavaScript the same forms are ordinary pages with a way back. Any link with
`data-webx-sign-in` or `href="#webx-sign-in"` opens the dialog too — a link in a menu or a block
needs no template.

## The account

`/account` opens the first section of its menu: **Profile** (names, phone, the fields of the site
with the place `profile`, the address and its change) and **Sign-in and security** (the password,
the social networks, «Sign out everywhere», deleting the account). Every page of it is `no-store`.

### A section from a module

A module adds a section of its own — «My orders», a wallet, saved addresses — without touching this
package. Register the item and declare the routes in its `boot()`; `class_exists` keeps the module
working on a site without visitors:

```php
use Illuminate\Support\Facades\Route;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\AccountRoutes;
use WebxUi\Users\Account\AccountSection;

public function boot(): void
{
    if (! class_exists(AccountMenu::class)) {
        return;
    }

    $this->callAfterResolving(AccountMenu::class, fn (AccountMenu $menu) => $menu->register(new AccountSection(
        id: 'addresses',
        label: 'shop::account.addresses',   // a dictionary key or plain words
        route: 'shop.account.addresses',
        icon: 'map-pin',
        position: 30,                        // the profile is 10, security 90
        verified: true,                      // locked in the menu until the address is confirmed
        active: 'shop.account.addresses*',
        badge: fn () => null,                // a number by the item, asked only by the menu
    )));

    // The same `verified` on the routes: an unconfirmed visitor gets «Confirm your e-mail».
    AccountRoutes::group(function (): void {
        Route::get('addresses', [AddressesController::class, 'index'])->name('shop.account.addresses');
        Route::post('addresses', [AddressesController::class, 'store'])->name('shop.account.addresses.store');
    }, verified: true);
}
```

`AccountRoutes::group()` puts the routes under the prefix of the account with the middleware of its
pages — `web`, the language, `webx.visitor`, a service route, `no-store` — and declares their copy
behind the language prefix (the names get `.localised`; `Urls::named()` gives the address in the
language of the page). The page prints itself in the frame of the account, so the menu and the grid
are there whatever layout the site has:

```blade
<x-webx-users::account :title="__('shop::account.addresses')">
    …
</x-webx-users::account>
```

The site hides a section it does not want with `webx-users.account.hidden`. Before an account is
deleted, a module says what will be lost and takes away what is its own:

```php
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Users\Account\DeletionWarnings;

app(DeletionWarnings::class)->register(fn (Visitor $visitor): ?string => $this->openOrders($visitor->id) > 0
    ? __('shop::account.open-orders-lost')
    : null);

Event::listen(VisitorDeleting::class, fn (VisitorDeleting $event) => Address::query()->where('user_id', $event->visitorId)->delete());
```

The listener runs inside the transaction that deletes the row; one that throws keeps the account.

## Fields of the site

A club card, a date of birth, a company — declared once, a field reaches the registration, the
profile, the card in the panel and MCP, and is kept in `users.extra`:

```php
// config/webx-users.php — rules as strings: the config is cached.
'fields' => [
    'birthday' => ['type' => 'wx-date-picker', 'label' => 'Date of birth', 'rules' => ['before:today']],
    'club-card' => ['type' => 'wx-input', 'label' => 'Club card', 'unique' => true,
        'visitorEditable' => false, 'placements' => ['profile', 'panel']],
],
```

A module registers one with `app(UserFields::class)->register(new UserField(…))`. Whether a visitor
must fill it in is the setting `users.fields.<name>` — `hidden`, `optional`, `required` — changed in
the panel without code. Read it with `$user->extra('club-card')`; for a fast search,
`php artisan webx:users:index-field club-card` writes a migration with an indexed column.

## Social networks

Google and Facebook come with the module. A provider is shown — its button and its routes — only
when it is switched on and every required key has a value. The keys are entered in Customers →
Settings; the secret is stored encrypted and never given back. Where a field there is empty, `.env`
fills it:

```dotenv
WEBX_USERS_GOOGLE_ENABLED=true
WEBX_USERS_GOOGLE_CLIENT_ID=…
WEBX_USERS_GOOGLE_CLIENT_SECRET=…
```

The address of return for the provider's console is `https://<site>/account/auth/<key>/callback`,
one for every language. A verified Google address links to the account with that confirmed address
by itself; anything less links once the visitor signs in with the password. Without an address
from the provider, or a field the site requires, the visitor finishes on «Almost done». The last
way to sign in is never unlinked.

### A new provider: Apple

Another network is a class of your own, registered in `boot()` of the site or of a package. Apple,
on top of the community driver `socialiteproviders/apple`:

```bash
composer require socialiteproviders/apple firebase/php-jwt
```

```php
namespace App\Social;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Apple\Provider as AppleDriver;
use WebxUi\Admin\Support\Field;
use WebxUi\Users\Social\ProviderConfig;
use WebxUi\Users\Social\SocialiteProvider;
use WebxUi\Users\Social\SocialProfile;

final class AppleProvider extends SocialiteProvider
{
    public function key(): string
    {
        return 'apple';
    }

    public function label(): string
    {
        return 'Apple';
    }

    /** What the administrator fills in Customers → Settings. */
    public function fields(): array
    {
        return [
            new Field('client_id', 'Services ID', required: true),
            new Field('team_id', 'Team ID', required: true),
            new Field('key_id', 'Key ID', required: true),
            new Field('private_key', 'Private key (.p8)', secret: true, required: true),
        ];
    }

    // Apple says `email_verified` and means it, relay addresses included.
    public function trustsVerifiedEmail(): bool
    {
        return true;
    }

    protected function driver(): string
    {
        return AppleDriver::class;
    }

    // The client secret of Apple is a short-lived JWT signed with the private key.
    protected function build(ProviderConfig $config, Request $request): AbstractProvider
    {
        $secret = JWT::encode([
            'iss' => $config->string('team_id'),
            'iat' => time(),
            'exp' => time() + 300,
            'aud' => 'https://appleid.apple.com',
            'sub' => $config->string('client_id'),
        ], $config->string('private_key'), 'ES256', $config->string('key_id'));

        return (new AppleDriver($request, $config->string('client_id'), $secret, $this->callbackUrl()))
            ->scopes(['name', 'email'])
            ->stateless();
    }

    protected function toProfile(SocialiteUser $user): SocialProfile
    {
        $raw = $user->getRaw();
        $verified = $raw['email_verified'] ?? false;

        return new SocialProfile(
            id: (string) $user->getId(),
            email: self::text($user->getEmail()),
            emailVerified: $verified === true || $verified === 'true',
            // Apple sends the name on the first sign-in only; «Almost done» asks for it later.
            firstName: self::text($user->getName()),
        );
    }
}
```

```php
// AppServiceProvider::boot()
use WebxUi\Users\Social\SocialProviders;

$this->callAfterResolving(SocialProviders::class, fn (SocialProviders $providers) => $providers->register(new AppleProvider));
```

Add an icon as the view `resources/views/vendor/webx-users/social/icons/apple.blade.php`, and the
words of the button with a dictionary key in `label()` if the site speaks more than one language.
Apple comes back by `POST` from its own site: the callback is outside CSRF for every provider, and
the module's `state` guards it instead. A network Socialite does not know implements
`WebxUi\Users\Social\Provider` itself — `redirect()` and `profile()` are the whole of it.

### The test provider

On a machine of development there is a made-up network to walk the whole flow without a real
console — the button, the return, «Almost done», linking to an existing account. It is registered
only while `APP_ENV=local` and shown only when switched on:

```dotenv
WEBX_USERS_DEV_ENABLED=true
```

The button «Test sign-in» leads to a page that asks who to come back as — an address, a name,
whether the network calls the address verified — and posts that to the ordinary callback.

## The panel and agents

**Customers** has the list (search by e-mail, name and phone; filters by group, confirmation,
block, way of signing in and date), the card (profile, fields, groups, ways of signing in, history),
**Groups** (a code and a name) and **Settings** (registration open or closed, the fields, the keys
of the providers). There is no password in the panel at all: a new customer is invited by a letter
and sets their own.

Agents get the same section as `users_*` tools behind `users:read` and `users:write`, and
`users://catalog` to read first. No password, token or secret is in any answer; see
[Agents](/guide/agents#customers-are-personal-data).

## Demo

`php artisan webx:demo --module=users` adds two groups («Wholesale», «Club») and five visitors at
`@example.com` with `+800` phone numbers, one confirmed address short and one linked to Google. They
have no passwords: register yourself, or ask for a reset link and take it from the mail log.
`webx:demo --remove` takes all of it back.
