<?php

declare(strict_types=1);

namespace WebxUi\Users\Panel;

use Illuminate\Contracts\Mail\Factory as Mail;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Users\Emails\Mask;
use WebxUi\Users\Emails\Verification;
use WebxUi\Users\Fields\FieldValues;
use WebxUi\Users\Fields\Placement;
use WebxUi\Users\Fields\UserFields;
use WebxUi\Users\Mail\EmailChanged;
use WebxUi\Users\Mail\Invitation;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\Identity;
use WebxUi\Users\Models\User;
use WebxUi\Users\Passwords\ResetLinks;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialAccounts;
use WebxUi\Users\Social\SocialProviders;
use WebxUi\Users\Urls;

/**
 * The card of a visitor on the server (spec §16.2): what `users.form` holds, and what a save
 * writes — the panel's door, and the agent's of stage 9 through the same class.
 *
 * No password anywhere: an administrator neither sees nor sets one, only sends a link. A new
 * visitor from the panel has an address and a profile, no password, and — when asked — the
 * invitation, a link of the page «Set a password» alive 7 days. A new address set here is set at
 * once and unconfirmed: the administrator acts on the visitor's word, and the visitor confirms by
 * the letter to the new address; the old one is told.
 */
final readonly class UserForm
{
    /** The visitor's own columns the screen writes. */
    public const array COLUMNS = ['first_name', 'last_name', 'phone', 'email'];

    /** How long the invitation works (§16.2). */
    public const int INVITE_MINUTES = 7 * 24 * 60;

    public function __construct(
        private ScreenRecord $record,
        private FieldValues $values,
        private UserFields $fields,
        private SocialProviders $providers,
        private ProviderKeys $keys,
        private SocialAccounts $accounts,
        private ResetLinks $links,
        private Verification $verification,
        private Validation $validation,
        private ConnectionInterface $db,
        private Mail $mail,
        private Urls $urls,
    ) {}

    /**
     * A visitor and the values of the screen — what `GET`, `POST` and `PUT` answer.
     *
     * @return array{user: array<string, mixed>, values: array<string, mixed>}
     */
    public function describe(User $user): array
    {
        $user->loadMissing(['groups', 'identities']);

        return ['user' => $this->summary($user), 'values' => $this->values($user)];
    }

    /**
     * What the card says beside the fields: the state, and the ways to sign in with what each
     * is — never a hash, a token or the id at a provider.
     *
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $shown = array_map(static fn ($provider): string => $provider->key(), $this->keys->all());
        $ways = [];

        if ($user->password !== null) {
            $ways[] = ['key' => SocialAccounts::PASSWORD, 'label' => (string) trans('webx-users::screen.way-password'), 'email' => null, 'linked_at' => null, 'used_at' => null, 'shown' => true];
        }

        foreach ($user->identities as $identity) {
            /** @var Identity $identity */
            $provider = $this->providers->find($identity->provider);
            $ways[] = [
                'key' => $identity->provider,
                'label' => $provider === null ? $identity->provider : (string) __($provider->label()),
                'email' => $identity->email,
                'linked_at' => $identity->created_at?->toAtomString(),
                'used_at' => $identity->last_used_at?->toAtomString(),
                'shown' => in_array($identity->provider, $shown, true),
            ];
        }

        $working = array_values(array_filter($ways, static fn (array $way): bool => $way['shown']));

        foreach ($ways as &$way) {
            // The rule of the last way (§9.6): a way the visitor still has another one beside.
            $way['can_unlink'] = $way['key'] !== SocialAccounts::PASSWORD
                && (! $way['shown'] || count($working) > 1);
        }

        unset($way);

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'verified' => $user->email_verified_at !== null,
            'verified_at' => $user->email_verified_at?->toAtomString(),
            'blocked' => $user->blocked_at !== null,
            'blocked_at' => $user->blocked_at?->toAtomString(),
            'has_password' => $user->password !== null,
            'locale' => $user->locale,
            'created_at' => $user->created_at?->toAtomString(),
            'last_sign_in_at' => $user->last_sign_in_at?->toAtomString(),
            'ways' => $ways,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(User $user): array
    {
        return [
            // The site's fields first, so none of them stands in for a column.
            ...($user->extraRaw() ?? []),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'groups' => $user->groups->pluck('code')->map(static fn (mixed $code): string => (string) $code)->values()->all(),
        ];
    }

    /**
     * Check what came in against the screen and write it — a new visitor or an existing one.
     * Only the keys sent are checked and written (§10.5): an old visitor without a field that
     * became required is not locked out of an edit of the name.
     *
     * `letters: false` writes without a letter — the agent's dry run, rolled back, must not leave
     * one in somebody's mailbox.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(User $user, array $input, ?callable $can = null, bool $invite = false, bool $letters = true): User
    {
        $declared = array_map(static fn ($field): string => $field->name, $this->fields->for(Placement::Panel));
        $split = $this->record->split(User::SCREEN, $input, [...self::COLUMNS, 'groups', ...$declared], [], $can);

        $own = array_intersect_key($split->own, array_flip(self::COLUMNS));
        $this->check($user, $own);

        // The fields of the site by their own declaration: the type, the rules, `unique`.
        $site = $this->values->validate(
            array_intersect_key($split->own, array_flip($declared)),
            Placement::Panel,
            $user,
            whole: false,
            prefix: '',
        );

        $groups = array_key_exists('groups', $split->own) ? $this->groups($split->own['groups']) : null;
        $creating = ! $user->exists;
        $before = $user->exists ? $user->email : null;

        $user = $this->db->transaction(function () use ($user, $own, $site, $split, $groups): User {
            foreach ($own as $column => $value) {
                $user->setAttribute($column, is_string($value) && trim($value) !== '' ? trim($value) : null);
            }

            if ($user->exists && $user->isDirty('email')) {
                // A new address is unconfirmed until the visitor follows the letter (§16.2); a link
                // of a reset sent to the old one dies with it.
                $user->email_verified_at = null;
                $this->links->forget($user);
            }

            if ($site !== [] || $split->extra !== []) {
                $user->setAttribute('extra', $this->record->merge(User::SCREEN, $user->extraRaw(), [...$split->extra, ...$site]));
            }

            $user->save();

            if ($groups !== null) {
                $user->groups()->syncWithPivotValues($groups, ['created_at' => Date::now()]);
            }

            return $user;
        });

        if (! $letters) {
            return $user->refresh()->load(['groups', 'identities']);
        }

        if ($creating) {
            if ($invite) {
                $this->invite($user);
            }
        } elseif ($before !== null && $before !== $user->email) {
            $this->verification->send($user);
            $this->mail->mailer()->to($before)->send(new EmailChanged($user, Mask::email($user->email), $this->urls->to('forgot')));
        }

        return $user->refresh()->load(['groups', 'identities']);
    }

    /** The invitation (§16.2): a link of the page «Set a password», alive 7 days. */
    public function invite(User $user): void
    {
        $token = $this->links->issue($user, ResetLinks::INVITE, self::INVITE_MINUTES);
        $link = $this->urls->to('reset', parameters: ['token' => $token]);

        $this->mail->mailer()->to($user->email)->send(new Invitation($user, $link, (int) (self::INVITE_MINUTES / 1440)));
    }

    /**
     * A link of «Forgot your password?» to the visitor's address — or, to an account without a
     * password, the invitation, which says «set» rather than «restore». Never to a blocked one: a
     * link does not let them in either (§6.3).
     *
     * @throws ValidationException
     */
    public function sendReset(User $user): void
    {
        if ($user->isBlocked()) {
            throw ValidationException::withMessages(['email' => (string) trans('webx-users::errors.blocked')]);
        }

        if ($user->password === null) {
            $this->invite($user);

            return;
        }

        $link = $this->urls->to('reset', parameters: ['token' => $this->links->issue($user)]);
        $this->mail->mailer()->to($user->email)->send(new ResetPassword($user, $link, ResetLinks::minutes()));
    }

    /**
     * A network unlinked by the administrator — the last way to sign in stays (§9.6). A link of a
     * provider that is not shown is no way in: taking it away leaves every way there was.
     *
     * @throws ValidationException
     */
    public function unlink(User $user, string $provider): User
    {
        $registered = $this->providers->find($provider);

        if ($registered !== null && $this->keys->shown($registered)) {
            $this->accounts->unlink($user, $provider);
        } elseif ($user->identities()->where('provider', $provider)->delete() === 0) {
            throw ValidationException::withMessages(['provider' => (string) trans('webx-users::account.social.not_linked')]);
        }

        return $user->unsetRelation('identities');
    }

    /**
     * The visitor's own columns, by the rules of the site's forms: the address is required and
     * one per account, the rest as long as the table holds.
     *
     * @param  array<string, mixed>  $own
     *
     * @throws ValidationException
     */
    private function check(User $user, array $own): void
    {
        if (! $user->exists && ! array_key_exists('email', $own)) {
            $own['email'] = null;
        }

        if (array_key_exists('email', $own) && is_string($own['email'])) {
            $own['email'] = User::normaliseEmail($own['email']);
        }

        $rules = [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->getKey())],
        ];

        $this->validation->make($own, array_intersect_key($rules, $own), [
            'email.unique' => (string) trans('webx-users::errors.email-taken'),
        ], [
            'first_name' => (string) trans('webx-users::screen.first-name'),
            'last_name' => (string) trans('webx-users::screen.last-name'),
            'phone' => (string) trans('webx-users::screen.phone'),
            'email' => (string) trans('webx-users::screen.email'),
        ])->validate();
    }

    /**
     * The ids of the groups named by code; an unknown code is a 422 with the known ones.
     *
     * @return list<int>
     *
     * @throws ValidationException
     */
    private function groups(mixed $codes): array
    {
        $codes = array_values(array_unique(array_map('strval', is_array($codes) ? $codes : [])));

        if ($codes === []) {
            return [];
        }

        $found = Group::query()->whereIn('code', $codes)->pluck('id', 'code')->all();
        $unknown = array_diff($codes, array_map('strval', array_keys($found)));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['groups' => (string) trans('webx-users::errors.group-unknown', [
                'code' => implode(', ', $unknown),
                'known' => implode(', ', Group::query()->orderBy('code')->pluck('code')->all()) ?: '—',
            ])]);
        }

        return array_values(array_map('intval', $found));
    }
}
