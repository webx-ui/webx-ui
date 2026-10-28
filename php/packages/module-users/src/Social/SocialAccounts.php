<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use WebxUi\Users\Mail\ProviderLinked;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\User;
use WebxUi\Users\Urls;

/**
 * The linked social networks of a visitor (spec §9.6, §9.7): who a provider's person is here,
 * linking with the letter that tells the owner, and the rule of the last way to sign in.
 */
final readonly class SocialAccounts
{
    /** The way to sign in that is not a provider. */
    public const string PASSWORD = 'password';

    public function __construct(
        private ProviderKeys $keys,
        private Mail $mail,
        private Urls $urls,
    ) {}

    /** The identity of the provider's person, whoever's it is. */
    public function identity(Provider $provider, SocialProfile $profile): ?Identity
    {
        return Identity::query()
            ->where('provider', $provider->key())
            ->where('provider_user_id', $profile->id)
            ->first();
    }

    /**
     * Whether the provider's person is the owner of the account by the address alone (§9.7,
     * decisions 14 and 23): the provider says the address is verified, the registry trusts it to
     * say so, and the account's own address is confirmed. Anything less links only by signing in
     * with the password.
     */
    public function vouchedFor(User $account, Provider $provider, SocialProfile $profile): bool
    {
        return $profile->emailVerified
            && $provider->trustsVerifiedEmail()
            && $profile->email !== null
            && User::normaliseEmail($profile->email) === $account->email
            && $account->email_verified_at !== null;
    }

    /**
     * Link the provider to the account and tell the address of the account (§9.6): if it was not
     * the owner, the owner learns it from the letter.
     */
    public function link(User $user, Provider $provider, SocialProfile $profile, bool $tell = true): Identity
    {
        $identity = Identity::query()->create([
            'user_id' => (int) $user->getKey(),
            'provider' => $provider->key(),
            'provider_user_id' => $profile->id,
            'email' => $profile->email === null ? null : User::normaliseEmail($profile->email),
            'last_used_at' => Date::now(),
        ]);

        if ($tell) {
            $this->mail->mailer()->to($user->email)->send(new ProviderLinked($user, $provider->label(), $this->urls->to('security')));
        }

        return $identity;
    }

    /**
     * The password sign-in of the account a provider's person waits for links it (§9.7): the
     * same account, in the same session, within its fifteen minutes. Another account, or later —
     * nothing; a provider switched off since, or a person linked meanwhile — nothing either.
     */
    public function linkWaiting(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $session = new SocialSession($request->session());
        $pending = $session->waiting();

        if ($pending === null || $pending['account'] !== (int) $user->getKey()) {
            return;
        }

        $session->forget();
        $provider = $this->keys->find($pending['provider']);

        if ($provider === null || $this->identity($provider, $pending['profile']) !== null
            || $user->identities()->where('provider', $provider->key())->exists()) {
            return;
        }

        $this->link($user, $provider, $pending['profile']);
    }

    /**
     * The ways the visitor signs in with now: the password, and every linked provider that is
     * shown — a link of a provider switched off is no way in (§9.6).
     *
     * @return list<string>
     */
    public function ways(User $user): array
    {
        $shown = array_map(static fn (Provider $provider): string => $provider->key(), $this->keys->all());
        $linked = $shown === [] ? [] : Identity::query()
            ->where('user_id', $user->getKey())
            ->whereIn('provider', $shown)
            ->pluck('provider')
            ->all();

        return array_values(array_unique([
            ...($user->password !== null ? [self::PASSWORD] : []),
            ...array_map('strval', $linked),
        ]));
    }

    /** Whether another way to sign in stays once this provider is unlinked (decision 5). */
    public function canUnlink(User $user, string $provider): bool
    {
        return array_diff($this->ways($user), [$provider]) !== [];
    }

    /**
     * Unlink a provider, or a 422 in the words the button says: the server holds the rule, not
     * only the disabled button.
     *
     * @throws ValidationException
     */
    public function unlink(User $user, string $provider): void
    {
        $identity = Identity::query()->where('user_id', $user->getKey())->where('provider', $provider)->first();

        if ($identity === null) {
            throw ValidationException::withMessages(['provider' => trans('webx-users::account.social.not_linked')]);
        }

        if (! $this->canUnlink($user, $provider)) {
            throw ValidationException::withMessages(['provider' => trans('webx-users::account.social.last')]);
        }

        $identity->delete();
    }
}
