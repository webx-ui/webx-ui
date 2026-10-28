<?php

declare(strict_types=1);

namespace WebxUi\Users\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Users\Account\DeleteAccount;
use WebxUi\Users\Account\DeletionWarnings;
use WebxUi\Users\Emails\Verification;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\UserForm;
use WebxUi\Users\Panel\UserList;
use WebxUi\Users\Social\Provider;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialProviders;

/**
 * `/api/cms/users` (spec §16.2): the list with its filters, the card, its save, and the actions of
 * the card — the letters, the block, a confirmation by hand, a link taken away, the deletion.
 *
 * No answer here carries a password, a hash, a token or the id of a person at a provider; a
 * letter is answered «sent», never with its link.
 */
final readonly class UserController
{
    public function __construct(
        private UserList $list,
        private UserForm $form,
    ) {}

    public function index(Request $request, ProviderKeys $keys): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:64'],
            'verified' => ['nullable', 'boolean'],
            'blocked' => ['nullable', 'boolean'],
            'provider' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = $this->list->page($validated, (int) ($validated['per_page'] ?? 25), (int) ($validated['page'] ?? 1));
        $shown = array_map(static fn (Provider $provider): string => $provider->key(), $keys->all());

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(static fn (User $user): array => UserList::row($user, $shown), $page->items()),
        ]);
    }

    /**
     * What the filters of the list choose from: the groups, and the ways to sign in the
     * registry knows — a provider switched off still filters the people it once let in.
     */
    public function meta(SocialProviders $providers, ProviderKeys $keys): JsonResponse
    {
        return ApiResponse::data([
            'groups' => Group::query()->orderBy('name')->get(['code', 'name'])->map(
                static fn (Group $group): array => ['code' => $group->code, 'name' => $group->name],
            )->all(),
            'providers' => array_values(array_map(static fn (Provider $provider): array => [
                'key' => $provider->key(),
                'label' => (string) __($provider->label()),
                'shown' => $keys->shown($provider),
            ], $providers->all())),
        ]);
    }

    public function show(int $user): JsonResponse
    {
        return ApiResponse::data($this->form->describe(self::find($user)));
    }

    /**
     * A new visitor from the panel: an address and a profile, no password (§16.2). `invite`
     * sends the letter with the link of «Set a password».
     */
    public function store(Request $request): JsonResponse
    {
        $user = $this->form->save(new User, $this->values($request), $this->can($request), $request->boolean('invite'));

        return ApiResponse::data($this->form->describe($user), 201);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $user = $this->form->save(self::find($user), $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($user));
    }

    /** What the modules say before a visitor is deleted (§14.1) — the confirmation shows it. */
    public function warnings(int $user, DeletionWarnings $warnings): JsonResponse
    {
        return ApiResponse::data(['warnings' => $warnings->for(self::find($user)->toVisitor())]);
    }

    /** Deleted for good, through the same listeners as the visitor's own deletion. */
    public function destroy(int $user, DeleteAccount $delete): JsonResponse
    {
        $delete(self::find($user));

        return ApiResponse::noContent();
    }

    public function block(int $user): JsonResponse
    {
        $found = self::find($user);
        $found->blocked_at ??= Date::now();
        $found->save();

        return ApiResponse::data($this->form->describe($found));
    }

    public function unblock(int $user): JsonResponse
    {
        $found = self::find($user);
        $found->blocked_at = null;
        $found->save();

        return ApiResponse::data($this->form->describe($found));
    }

    /** «Confirm by hand»: the administrator has the visitor's word that the address is theirs. */
    public function verify(int $user): JsonResponse
    {
        $found = self::find($user);

        if (! $found->hasVerifiedEmail()) {
            $found->markEmailAsVerified();
        }

        return ApiResponse::data($this->form->describe($found));
    }

    /** A link of «Forgot your password?», or the invitation to an account without a password. */
    public function sendReset(int $user): JsonResponse
    {
        $this->form->sendReset(self::find($user));

        return ApiResponse::data(['sent' => true]);
    }

    public function sendVerification(int $user, Verification $verification): JsonResponse
    {
        $found = self::find($user);

        if ($found->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => (string) trans('webx-users::errors.already-verified')]);
        }

        $verification->send($found);

        return ApiResponse::data(['sent' => true]);
    }

    /** A network unlinked by the administrator — the last way to sign in stays (§9.6). */
    public function unlink(int $user, string $provider): JsonResponse
    {
        return ApiResponse::data($this->form->describe($this->form->unlink(self::find($user), $provider)));
    }

    private static function find(int $id): User
    {
        $user = User::query()->find($id);

        if (! $user instanceof User) {
            abort(404, (string) trans('webx-users::errors.not-found', ['id' => $id]));
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
