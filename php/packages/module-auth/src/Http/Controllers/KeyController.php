<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Collection as People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Auth\Keys\KeyIssuer;
use WebxUi\Auth\Keys\KeyRefused;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Keys\Key;
use WebxUi\Mcp\Keys\Keys;
use WebxUi\Mcp\Scopes;

/**
 * Keys for programs: the list, issuing one, and switching one off (WEBX_UI_MCP_ACCESS.md §12).
 *
 * All three behind `admins.manage`, the permission that is about other people's accounts: a
 * key acts as the administrator it names, so issuing one is speaking for them. Issuing one for
 * yourself is no different — a key is a secret that leaves the panel, unlike a connection,
 * which a person makes for themselves without handing anybody anything.
 *
 * The secret is in the answer to the issue and nowhere else, ever: Passport keeps only the
 * token's id, and the list shows names, scopes and dates.
 */
final class KeyController
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly KeyIssuer $issuer,
        private readonly Keys $keys,
    ) {}

    public function index(): JsonResponse
    {
        /** @var People<int, Key> $keys */
        $keys = Key::query()
            // Live ones first, newest first: the list is read to find a key to switch off, or
            // to see whether the one a script uses is still there.
            ->orderByRaw('case when revoked_at is null then 0 else 1 end')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $people = $this->people([
            ...$keys->pluck('cms_user_id')->all(),
            ...$keys->pluck('created_by')->all(),
        ]);

        return new JsonResponse([
            'data' => $keys->map(fn (Key $key): array => $this->row($key, $people))->values(),
            'meta' => [
                // What a key may be issued with, so the form offers nothing the server refuses.
                'scopes' => $this->issuer->known(),
                'max_days' => KeyIssuer::MAX_DAYS,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'admin' => ['required', 'integer', Rule::exists((new CmsUser)->getTable(), 'id')],
            'scopes' => ['present', 'array'],
            'scopes.*' => ['string', Rule::in($this->issuer->known())],
            'days' => ['required', 'integer', 'min:1', 'max:'.KeyIssuer::MAX_DAYS],
        ]);

        /** @var CmsUser $admin */
        $admin = CmsUser::query()->findOrFail($input['admin']);

        try {
            $issued = $this->issuer->issue(
                $admin,
                (string) $input['name'],
                $this->issuer->scopes(array_values($input['scopes'])),
                (int) $input['days'],
                $this->caller(),
            );
        } catch (KeyRefused $refused) {
            throw ValidationException::withMessages(['admin' => $refused->getMessage()]);
        }

        return new JsonResponse([
            'data' => $this->row($issued->key, $this->people([$admin->getKey(), $issued->key->created_by])),
            // Once. The panel shows it with a copy button and forgets it with the dialog.
            'token' => $issued->token,
        ], 201);
    }

    public function destroy(string $key): JsonResponse
    {
        /** @var Key $found */
        $found = Key::query()->findOrFail($key);

        $this->keys->revoke($found);

        return new JsonResponse([
            'data' => $this->row($found->refresh(), $this->people([$found->cms_user_id, $found->created_by])),
        ]);
    }

    /**
     * @param  People<int, CmsUser>  $people
     * @return array<string, mixed>
     */
    private function row(Key $key, People $people): array
    {
        $person = static fn (?int $id): ?array => $id === null ? null : [
            'id' => $id,
            'name' => $people->get($id)?->name,
        ];

        return [
            'id' => $key->id,
            'name' => $key->name,
            'user' => $person((int) $key->cms_user_id),
            'created_by' => $person($key->created_by === null ? null : (int) $key->created_by),
            'scopes' => $key->scopes,
            // Said outright, because `mcp:use` is a word only this code knows.
            'everything' => in_array(Scopes::OAUTH, $key->scopes, true),
            'state' => $key->state(),
            'created_at' => $key->created_at?->toIso8601String(),
            'expires_at' => $key->expires_at?->toIso8601String(),
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'revoked_at' => $key->revoked_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return People<int, CmsUser>
     */
    private function people(array $ids): People
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return new People;
        }

        return CmsUser::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    private function caller(): ?CmsUser
    {
        $user = $this->auth->guard((string) config('webx-auth.guard'))->user();

        return $user instanceof CmsUser ? $user : null;
    }
}
