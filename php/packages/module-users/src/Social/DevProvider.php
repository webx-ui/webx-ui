<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use WebxUi\Users\Urls;

/**
 * A made-up social network for a machine of development (spec §19, stage 11): the whole flow of
 * §9.4–§9.7 — the button, the trip, the return with `state`, «Almost done», linking — without
 * keys from a real console. The «provider» is a page of this module that asks who to be and
 * posts the answer to the ordinary callback.
 *
 * Anybody may say any address there, so it is registered only while `APP_ENV` is `local`, and
 * shown only when switched on besides (`WEBX_USERS_DEV_ENABLED=true`). Its word on «verified»
 * is trusted, so that the page can play a provider that vouches for an address and one that does
 * not (§9.7).
 */
final class DevProvider implements Provider
{
    public const KEY = 'dev';

    public function key(): string
    {
        return self::KEY;
    }

    // Plain words rather than the dictionary: no site shows this to a visitor.
    public function label(): string
    {
        return 'Test sign-in';
    }

    public function icon(): string
    {
        return 'webx-users::social.icons.dev';
    }

    public function fields(): array
    {
        return [];
    }

    public function trustsVerifiedEmail(): bool
    {
        return true;
    }

    public function callbackUrl(): string
    {
        return app(Urls::class)->callback(self::KEY);
    }

    public function redirect(ProviderConfig $config, string $state): RedirectResponse
    {
        return new RedirectResponse(URL::route('webx-users.social.dev', ['state' => $state]));
    }

    public function profile(ProviderConfig $config, Request $request): SocialProfile
    {
        $email = $request->input('email');

        if ($email !== null && (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new ProviderFailed('The test provider was given no address it could use.');
        }

        $text = static fn (string $key): ?string => is_string($request->input($key)) && trim($request->input($key)) !== '' ? trim($request->input($key)) : null;
        $id = $text('id') ?? (is_string($email) ? 'dev-'.substr(sha1(mb_strtolower($email)), 0, 16) : null);

        if ($id === null) {
            throw new ProviderFailed('The test provider was given neither an id nor an address.');
        }

        return new SocialProfile(
            id: $id,
            email: is_string($email) && $email !== '' ? $email : null,
            emailVerified: $request->boolean('verified'),
            firstName: $text('first_name'),
            lastName: $text('last_name'),
        );
    }
}
