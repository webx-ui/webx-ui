<?php

declare(strict_types=1);

namespace WebxUi\Users\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;
use WebxUi\Users\Account\DeleteAccount;
use WebxUi\Users\Account\DeletionWarnings;
use WebxUi\Users\Emails\Verification;
use WebxUi\Users\Fields\Requirements;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\Groups;
use WebxUi\Users\Panel\SettingsState;
use WebxUi\Users\Panel\UserForm;
use WebxUi\Users\Panel\UserList;
use WebxUi\Users\Panel\UsersModule;
use WebxUi\Users\SignIn;
use WebxUi\Users\Social\Provider;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialAccounts;
use WebxUi\Users\Social\SocialProviders;

/**
 * What an agent can do with the visitors of the site (spec §17).
 *
 * The doors of the panel, not copies of them: the list is {@see UserList}, the card and its save
 * {@see UserForm} — a field of the site is written under its own name and refused where the screen
 * refuses it — the groups {@see Groups}, the settings {@see SettingsState}, the deletion
 * {@see DeleteAccount} with the same listeners as the visitor's own.
 *
 * Nothing here answers with a password, a hash, a link of a letter, a token or the id of a person
 * at a provider; a secret of a provider is never read and never written — the panel sets it. The
 * addresses and the phones are answered: that is what `users.view` gives, and the descriptions
 * say so, so that whoever connects an agent knows what it reads.
 *
 * A dry run is the write itself, rolled back ({@see Rehearsal}), and without its letters: a dry
 * run that left «Confirm your address» in somebody's mailbox would not be dry.
 */
final class UsersTools
{
    /** The most rows a page of `users_list` holds. */
    public const int MAX_PER_PAGE = 100;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $user = [
            'type' => ['integer', 'string'],
            'description' => 'The customer: their id, or the exact e-mail they sign in with.',
        ];
        $profile = [
            'first_name' => ['type' => ['string', 'null']],
            'last_name' => ['type' => ['string', 'null']],
            'phone' => ['type' => ['string', 'null'], 'description' => 'As the customer writes it: "+800 1234 5678".'],
            'fields' => ['type' => 'object', 'description' => 'The fields of the site by name, as users_get returns them under `fields`; users://catalog lists them with their types.'],
            'groups' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Group codes. The whole list: the customer is in these and no other.'],
        ];
        $read = [UsersModule::VIEW, UsersModule::MANAGE];
        $personal = ' The answer holds personal data — e-mail addresses and phones.';

        return Arguments::strictAll([
            Tool::read(
                'list',
                'The customers of the site, a page at a time: id, name, e-mail, phone, group codes, whether the '
                .'address is confirmed, whether they are blocked, the ways they sign in (`password`, `google`…) '
                .'and the dates. Filtered and sorted as the list in the panel.'.$personal,
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Part of the e-mail, the phone (digits are enough) or the name, in either order.'],
                    'group' => ['type' => 'string', 'description' => 'Only the customers in this group: its code.'],
                    'verified' => ['type' => 'boolean', 'description' => 'Only those whose address is confirmed (true) or is not (false).'],
                    'blocked' => ['type' => 'boolean'],
                    'provider' => ['type' => 'string', 'description' => '`password`, or the key of a social network: only those who sign in that way.'],
                    'from' => ['type' => 'string', 'description' => 'Registered on or after this day, YYYY-MM-DD.'],
                    'to' => ['type' => 'string', 'description' => 'Registered on or before this day, YYYY-MM-DD.'],
                    'sort' => ['type' => 'string', 'enum' => self::sorts(), 'description' => 'A leading `-` is newest (or Z) first. -created_at when omitted.'],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PER_PAGE, 'description' => '25 when omitted.'],
                ]],
                permission: $read,
            ),

            Tool::read(
                'get',
                'One customer in full: the profile, the fields of the site (`fields`), the group codes, the '
                .'ways they sign in with the dates each was linked and last used, and the dates of the account. '
                .'Never a password or a link.'.$personal,
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['user' => $user], 'required' => ['user']],
                permission: $read,
            ),

            Tool::mutating(
                'create',
                'Add a customer with an address and a profile and no password. With invite: true they get a '
                .'letter with a link to set one, alive 7 days; without it they set one by «Forgot your password?».',
                fn (array $arguments, ?Authenticatable $caller = null): array => $this->attempt(fn (): array => $this->create($arguments, $caller)),
                ['properties' => [
                    'email' => ['type' => 'string'],
                    ...$profile,
                    'invite' => ['type' => 'boolean', 'description' => 'Send the invitation. False when omitted.'],
                ], 'required' => ['email']],
            ),

            Tool::mutating(
                'update',
                'Change a customer: the profile, the fields of the site, the groups, blocked or not. Only what is '
                .'sent changes; the fields are checked as the card in the panel checks them. A new e-mail is set at '
                .'once and is unconfirmed: the customer gets a letter to confirm it, the old address is told.',
                fn (array $arguments, ?Authenticatable $caller = null): array => $this->attempt(fn (): array => $this->update($arguments, $caller)),
                ['properties' => [
                    'user' => $user,
                    'email' => ['type' => 'string'],
                    ...$profile,
                    'blocked' => ['type' => 'boolean', 'description' => 'A blocked customer cannot sign in, and their open sessions end.'],
                ], 'required' => ['user']],
            ),

            Tool::mutating(
                'delete',
                'Delete a customer for good, with their links to social networks and groups; other modules let go '
                .'of what is theirs (orders keep their copy of the contacts). Cannot be undone. A dry run answers '
                .'with what the modules warn will be lost — read it to the person first.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->delete($arguments)),
                ['properties' => ['user' => $user], 'required' => ['user']],
            ),

            Tool::mutating(
                'send_password_reset',
                'Send the customer a link to set a new password — or, to an account without one, the invitation '
                .'to set it. Answers «sent», never with the link. Not to a blocked customer.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->sendReset($arguments)),
                ['properties' => ['user' => $user], 'required' => ['user']],
            ),

            Tool::mutating(
                'resend_verification',
                'Send the letter that confirms the customer\'s address again. Only to an unconfirmed address.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->resendVerification($arguments)),
                ['properties' => ['user' => $user], 'required' => ['user']],
            ),

            Tool::mutating(
                'unlink_provider',
                'Take a social network away from a customer. The last way they sign in stays: a customer without a '
                .'password keeps their only network.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->unlink($arguments)),
                ['properties' => [
                    'user' => $user,
                    'provider' => ['type' => 'string', 'description' => 'The key of the network, as users_get lists it under `ways`: google, facebook.'],
                ], 'required' => ['user', 'provider']],
            ),

            Tool::read(
                'groups_list',
                'The customer groups: code, name and how many customers each holds. Read it before adding one.',
                fn (): array => ['groups' => $this->groups()->rows()],
                permission: $read,
            ),

            Tool::mutating(
                'groups_save',
                'Create a group, or change the one with this code. The code is what other modules and users_update '
                .'name it by: lowercase Latin letters and digits, words joined by «-».',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->saveGroup($arguments)),
                ['properties' => [
                    'code' => ['type' => 'string', 'description' => 'The group to change, or the code of a new one.'],
                    'name' => ['type' => 'string', 'description' => 'What the panel calls it, in one language.'],
                    'new_code' => ['type' => 'string', 'description' => 'Give an existing group another code.'],
                ], 'required' => ['code', 'name']],
            ),

            Tool::mutating(
                'groups_delete',
                'Delete a group. Its customers stay, only out of the group. A dry run says how many are in it.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->deleteGroup($arguments)),
                ['properties' => ['code' => ['type' => 'string']], 'required' => ['code']],
            ),

            Tool::read(
                'settings_get',
                'The settings of the customers: whether anyone may register, which fields the forms ask for '
                .'(hidden, optional, required), and per social network — switched on, shown on the site, what it '
                .'still lacks. The keys of the networks are answered only as «set» or «not set».',
                fn (): array => $this->settingsGet(),
                permission: UsersModule::SETTINGS,
            ),

            Tool::mutating(
                'settings_set',
                'Open or close the registration, say how much the forms ask for, switch a social network on or off. '
                .'The keys and secrets of the networks are not set here — only in the panel.',
                fn (array $arguments, ?Authenticatable $caller = null): array => $this->attempt(fn (): array => $this->settingsSet($arguments, $caller)),
                ['properties' => [
                    'values' => ['type' => 'object', 'description' => '`users.registration`: "open" or "closed"; `users.fields.<name>`: "hidden", "optional" or "required" — the names users_settings_get answers under `values`.'],
                    'providers' => ['type' => 'object', 'description' => 'By the key of the network: { "google": { "enabled": true } }.'],
                ]],
                permission: UsersModule::SETTINGS,
            ),
        ], 'users_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $group = $arguments['group'] ?? null;

        if (is_string($group) && $group !== '') {
            $this->group($group);
        }

        foreach (['from', 'to'] as $day) {
            $value = $arguments[$day] ?? null;

            if ($value !== null && (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1)) {
                throw new ToolFailure("`{$day}` is a day as YYYY-MM-DD.");
            }
        }

        $sort = $arguments['sort'] ?? null;

        if ($sort !== null && ! in_array($sort, self::sorts(), true)) {
            throw new ToolFailure('`sort` is one of: '.implode(', ', self::sorts()).'.');
        }

        $perPage = max(1, min(self::MAX_PER_PAGE, (int) ($arguments['per_page'] ?? 25)));
        $page = $this->container->make(UserList::class)->page($arguments, $perPage, max(1, (int) ($arguments['page'] ?? 1)));
        $shown = $this->shown();

        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'users' => array_map(static function (User $user) use ($shown): array {
                $row = UserList::row($user, $shown);
                $row['groups'] = array_column($row['groups'], 'code');

                return $row;
            }, $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        return $this->describe($this->user($arguments['user'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments, ?Authenticatable $caller): array
    {
        $input = $this->input($arguments);
        $invite = ($arguments['invite'] ?? false) === true;

        if (! $this->dryRun($arguments)) {
            return $this->describe($this->form()->save(new User, $input, $this->can($caller), $invite));
        }

        $would = $this->rehearse(fn (): array => $this->describe($this->form()->save(new User, $input, $this->can($caller), letters: false)));
        // The id the rehearsal was given is nobody's once it is rolled back.
        $would['user']['id'] = null;

        return ['dry_run' => true, 'would_invite' => $invite] + $would;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments, ?Authenticatable $caller): array
    {
        $user = $this->user($arguments['user'] ?? null);
        $input = $this->input($arguments);
        $blocked = $arguments['blocked'] ?? null;

        if ($blocked !== null && ! is_bool($blocked)) {
            throw new ToolFailure('`blocked` is true or false.');
        }

        if ($input === [] && $blocked === null) {
            throw new ToolFailure('Nothing to change: send the profile, `fields`, `groups` or `blocked`.');
        }

        $work = function (bool $letters) use ($user, $input, $blocked, $caller): array {
            if ($input !== []) {
                $user = $this->form()->save($user, $input, $this->can($caller), letters: $letters);
            }

            if ($blocked !== null) {
                $user->blocked_at = $blocked ? ($user->blocked_at ?? Date::now()) : null;
                $user->save();
            }

            return $this->describe($user);
        };

        if (! $this->dryRun($arguments)) {
            return $work(true);
        }

        return ['dry_run' => true, 'changes' => array_keys([...$input, ...($blocked === null ? [] : ['blocked' => $blocked])])]
            + $this->rehearse(static fn (): array => $work(false));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $user = $this->user($arguments['user'] ?? null);
        $warnings = $this->container->make(DeletionWarnings::class)->for($user->toVisitor());

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_delete' => ['id' => (int) $user->getKey(), 'email' => $user->email], 'warnings' => $warnings];
        }

        $id = (int) $user->getKey();
        ($this->container->make(DeleteAccount::class))($user);

        return ['deleted' => true, 'id' => $id, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sendReset(array $arguments): array
    {
        $user = $this->user($arguments['user'] ?? null);
        $letter = $user->password === null ? 'invitation' : 'password_reset';

        if ($this->dryRun($arguments)) {
            if ($user->isBlocked()) {
                throw new ToolFailure((string) trans('webx-users::errors.blocked'));
            }

            return ['dry_run' => true, 'would_send' => $letter, 'to' => $user->email];
        }

        $this->form()->sendReset($user);

        return ['sent' => true, 'letter' => $letter, 'to' => $user->email];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function resendVerification(array $arguments): array
    {
        $user = $this->user($arguments['user'] ?? null);

        if ($user->hasVerifiedEmail()) {
            throw new ToolFailure((string) trans('webx-users::errors.already-verified'));
        }

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_send' => 'verification', 'to' => $user->email];
        }

        $this->container->make(Verification::class)->send($user);

        return ['sent' => true, 'letter' => 'verification', 'to' => $user->email];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function unlink(array $arguments): array
    {
        $user = $this->user($arguments['user'] ?? null);
        $provider = $arguments['provider'] ?? null;

        if (! is_string($provider) || trim($provider) === '') {
            throw new ToolFailure('`provider` is the key of a network: google, facebook.');
        }

        if ($provider === SocialAccounts::PASSWORD) {
            throw new ToolFailure('A password is not unlinked: the customer changes it, or sets one by the link of users_send_password_reset.');
        }

        $work = fn (): array => $this->describe($this->form()->unlink($user, trim($provider)));

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        return ['dry_run' => true, 'would_unlink' => trim($provider)] + $this->rehearse($work);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveGroup(array $arguments): array
    {
        $code = $arguments['code'] ?? null;

        if (! is_string($code) || trim($code) === '') {
            throw new ToolFailure('`code` is the code of the group: wholesale, club-gold.');
        }

        $group = Group::query()->where('code', strtolower(trim($code)))->first();
        $newCode = $arguments['new_code'] ?? null;

        if ($newCode !== null && $group === null) {
            throw new ToolFailure("There is no group {$code} to give another code. users_groups_list lists them.");
        }

        $input = ['code' => $newCode ?? $code, 'name' => $arguments['name'] ?? null];
        $work = fn (): array => ['created' => $group === null, 'group' => Groups::row($this->groups()->save($input, $group))];

        if (! $this->dryRun($arguments)) {
            return $work();
        }

        $would = $this->rehearse($work);

        if ($would['created']) {
            $would['group']['id'] = null;
        }

        return ['dry_run' => true] + $would;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deleteGroup(array $arguments): array
    {
        $group = $this->group($arguments['code'] ?? null);
        $count = $group->users()->count();

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'would_delete' => $group->code, 'users_in_it' => $count];
        }

        $this->groups()->delete($group);

        return ['deleted' => true, 'code' => $group->code, 'users_taken_out' => $count];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsGet(): array
    {
        $state = $this->container->make(SettingsState::class);
        $answer = $state->answer();
        $requirements = $this->container->make(Requirements::class);

        return [
            'stored' => $answer['stored'],
            // What the site does now — the stored value, or the config and the declaration under it.
            'registration' => SignIn::registrationOpen() ? 'open' : 'closed',
            'fields' => array_map(static fn ($requirement): string => $requirement->value, $requirements->all()),
            'values' => $answer['values'],
            'providers' => array_map(static fn (array $card): array => [
                'key' => $card['key'],
                'label' => $card['label'],
                'enabled' => $card['enabled'],
                'shown' => $card['shown'],
                'missing' => $card['missing'],
                'callback' => $card['callback'],
                // A secret is a word, never a value; an open field (the client id) is what the panel shows.
                'credentials' => array_values(array_map(
                    static fn (array $field): string => $field['name'].': '.($field['set'] ? 'set' : ($field['env'] ? 'set in .env' : 'not set')),
                    array_filter($card['fields'], static fn (array $field): bool => (bool) $field['secret']),
                )),
                'options' => array_column(array_map(
                    static fn (array $field): array => ['name' => $field['name'], 'value' => $field['value'] ?? ($field['env'] ? '(.env)' : null)],
                    array_values(array_filter($card['fields'], static fn (array $field): bool => ! $field['secret'])),
                ), 'value', 'name'),
                'signs_in_only_by_it' => $card['only'],
            ], $answer['providers']),
            'mail' => $answer['mail'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function settingsSet(array $arguments, ?Authenticatable $caller): array
    {
        $state = $this->container->make(SettingsState::class);
        $values = $arguments['values'] ?? [];
        $providers = $arguments['providers'] ?? [];

        if (! is_array($values) || ! is_array($providers)) {
            throw new ToolFailure('`values` and `providers` are objects.');
        }

        if ($values === [] && $providers === []) {
            throw new ToolFailure('Nothing to change: send `values` or `providers`.');
        }

        $stored = [];

        if ($values !== []) {
            if ($state->settings() === null) {
                throw new ToolFailure('This site keeps no settings (module-settings is not installed): the config webx-users decides, and a person changes it in the code.');
            }

            Arguments::refuseUnknown($values, $state->names(), 'users_settings_set', 'setting');
            $stored = $state->validate($values, $this->can($caller));
        }

        $switch = [];

        foreach ($providers as $key => $settings) {
            $provider = $this->container->make(SocialProviders::class)->find((string) $key)
                ?? throw new ToolFailure(sprintf(
                    'There is no network %s. Known: %s.',
                    $key,
                    implode(', ', $this->container->make(SocialProviders::class)->keys()) ?: '—',
                ));

            if (! is_array($settings) || array_keys($settings) !== ['enabled'] || ! is_bool($settings['enabled'])) {
                throw new ToolFailure("`providers.{$key}` takes { \"enabled\": true|false } and nothing else: the keys and secrets of a network are set in the panel only.");
            }

            $switch[$provider->key()] = [$provider, $settings['enabled']];
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would_store' => $stored,
                'would_switch' => array_map(static fn (array $pair): bool => $pair[1], $switch),
            ];
        }

        if ($stored !== []) {
            $state->save($stored);
        }

        foreach ($switch as [$provider, $enabled]) {
            /** @var Provider $provider */
            $state->enable($provider, $enabled);
        }

        return $this->settingsGet();
    }

    /**
     * One customer as the agent reads them: the card's summary, the profile, the site's fields and
     * the groups under their own keys (§10.4) — what users_update takes back under the same names.
     *
     * @return array<string, mixed>
     */
    private function describe(User $user): array
    {
        $described = $this->form()->describe($user);
        $values = $described['values'];

        return [
            'user' => $described['user'],
            'profile' => array_intersect_key($values, array_flip(UserForm::COLUMNS)),
            'fields' => array_diff_key($values, array_flip([...UserForm::COLUMNS, 'groups'])),
            'groups' => $values['groups'],
        ];
    }

    /**
     * The arguments as the card's values: the columns, the site's fields, the groups — each only
     * when sent, so that what is left out keeps what it had.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function input(array $arguments): array
    {
        // Trimmed as the panel's request is by the middleware: " Anna@Example.test " is an address.
        $input = array_map(
            static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            array_intersect_key($arguments, array_flip([...UserForm::COLUMNS, 'groups'])),
        );
        $fields = $arguments['fields'] ?? [];

        if (! is_array($fields)) {
            throw new ToolFailure('`fields` is an object of field name → value.');
        }

        if ($fields !== []) {
            Arguments::refuseUnknown($fields, $this->fields(), 'fields');
        }

        if (array_key_exists('groups', $input)) {
            if (! is_array($input['groups'])) {
                throw new ToolFailure('`groups` is a list of group codes.');
            }

            foreach ($input['groups'] as $code) {
                $this->group($code);
            }
        }

        return [...$fields, ...$input];
    }

    /**
     * What `fields` may name: the fields of the screen that are not the visitor's own columns —
     * the site's (§10) and any a module patched onto `users.form`.
     *
     * @return list<string>
     */
    private function fields(): array
    {
        $names = array_map(
            static fn (array $node): string => (string) $node['name'],
            $this->container->make(ScreenRegistry::class)->fields(User::SCREEN),
        );

        return array_values(array_unique(array_diff($names, [...UserForm::COLUMNS, 'groups'])));
    }

    /**
     * A customer by id or by the exact address they sign in with.
     */
    private function user(mixed $reference): User
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            return User::query()->find((int) $reference)
                ?? throw new ToolFailure((string) trans('webx-users::errors.not-found', ['id' => $reference]));
        }

        if (! is_string($reference) || ! str_contains($reference, '@')) {
            throw new ToolFailure('A customer is an id, or the e-mail they sign in with.');
        }

        return User::query()->byEmail($reference)->first()
            ?? throw new ToolFailure("No customer signs in with {$reference}. users_list searches by part of it.");
    }

    /**
     * A group by its code; an unknown one is refused with the codes there are.
     */
    private function group(mixed $code): Group
    {
        $group = is_string($code) ? Group::query()->where('code', strtolower(trim($code)))->first() : null;

        return $group ?? throw new ToolFailure((string) trans('webx-users::errors.group-unknown', [
            'code' => is_scalar($code) ? (string) $code : '(none given)',
            'known' => implode(', ', Group::query()->orderBy('code')->pluck('code')->all()) ?: '—',
        ]));
    }

    /**
     * @return list<string>
     */
    private static function sorts(): array
    {
        return [...UserList::SORTS, ...array_map(static fn (string $sort): string => '-'.$sort, UserList::SORTS)];
    }

    /**
     * The keys of the networks shown on the site — a link to one switched off is no way in.
     *
     * @return list<string>
     */
    private function shown(): array
    {
        return array_map(static fn (Provider $provider): string => $provider->key(), $this->container->make(ProviderKeys::class)->all());
    }

    private function form(): UserForm
    {
        return $this->container->make(UserForm::class);
    }

    private function groups(): Groups
    {
        return $this->container->make(Groups::class);
    }

    /**
     * The caller's permissions for the nodes of a screen behind `can`; none on the local stdio
     * server, whose caller runs artisan and may do anything already.
     *
     * @return (callable(string): bool)|null
     */
    private function can(?Authenticatable $caller): ?callable
    {
        return $caller instanceof HasPermissions
            ? static fn (string $permission): bool => $caller->hasPermission($permission)
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return ($arguments[Tool::DRY_RUN] ?? false) === true;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function rehearse(Closure $work): mixed
    {
        return Rehearsal::run($work, (new User)->getConnectionName());
    }

    /**
     * Run a change, and turn a refusal of the screen into words the agent can read.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }
}
