<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Laravel\Socialite\Two\GoogleProvider as GoogleDriver;
use Laravel\Socialite\Two\User as SocialiteUser;
use WebxUi\Admin\Support\Field;

/**
 * Google (spec §9.1). Its answer says whether the address is verified (`email_verified`), and the
 * module trusts it: a verified Google address on a confirmed account links by itself (§9.7).
 */
final class GoogleProvider extends SocialiteProvider
{
    public function key(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'webx-users::auth.social.providers.google';
    }

    public function fields(): array
    {
        return [
            new Field('client_id', 'webx-users::auth.social.fields.client_id', required: true),
            new Field('client_secret', 'webx-users::auth.social.fields.client_secret', secret: true, required: true),
        ];
    }

    public function trustsVerifiedEmail(): bool
    {
        return true;
    }

    protected function driver(): string
    {
        return GoogleDriver::class;
    }

    protected function toProfile(SocialiteUser $user): SocialProfile
    {
        $raw = $user->getRaw();
        $verified = $raw['email_verified'] ?? false;

        return new SocialProfile(
            id: (string) $user->getId(),
            email: self::text($user->getEmail()),
            // Only an explicit yes: a missing flag is no promise.
            emailVerified: $verified === true || $verified === 'true',
            firstName: self::text($raw['given_name'] ?? null),
            lastName: self::text($raw['family_name'] ?? null),
        );
    }
}
