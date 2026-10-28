<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Illuminate\Http\Request;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\FacebookProvider as FacebookDriver;
use Laravel\Socialite\Two\User as SocialiteUser;
use WebxUi\Admin\Support\Field;

/**
 * Facebook (spec §9.1). It does not say whether the address is verified, so the address it gives
 * is never taken as confirmed: a new account gets the letter of confirmation, an existing one is
 * linked only by signing in with its password (§9.7).
 */
final class FacebookProvider extends SocialiteProvider
{
    public function key(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'webx-users::auth.social.providers.facebook';
    }

    public function fields(): array
    {
        return [
            new Field('app_id', 'webx-users::auth.social.fields.app_id', required: true),
            new Field('app_secret', 'webx-users::auth.social.fields.app_secret', secret: true, required: true),
        ];
    }

    public function trustsVerifiedEmail(): bool
    {
        return false;
    }

    protected function clientIdField(): string
    {
        return 'app_id';
    }

    protected function clientSecretField(): string
    {
        return 'app_secret';
    }

    protected function driver(): string
    {
        return FacebookDriver::class;
    }

    protected function build(ProviderConfig $config, Request $request): AbstractProvider
    {
        $driver = parent::build($config, $request);

        return $driver instanceof FacebookDriver ? $driver->fields(['name', 'email', 'first_name', 'last_name']) : $driver;
    }

    protected function toProfile(SocialiteUser $user): SocialProfile
    {
        $raw = $user->getRaw();

        return new SocialProfile(
            id: (string) $user->getId(),
            email: self::text($user->getEmail()),
            emailVerified: false,
            firstName: self::text($raw['first_name'] ?? null),
            lastName: self::text($raw['last_name'] ?? null),
        );
    }
}
