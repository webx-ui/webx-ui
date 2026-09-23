<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Collection as People;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Grants\Grant;
use WebxUi\Mcp\Grants\Grants;

/**
 * The agents people have let into this panel, and the one button that ends one.
 *
 * Two audiences on one endpoint. Everybody signed in sees their own connections — they are
 * the person who made them, and a list they cannot reach is a decision they cannot undo.
 * Everybody else's is `admins.manage`, the permission that is already about other people's
 * accounts: an agent is somebody's account with a program at the far end of it.
 *
 * Ending one is not a permission of its own, for the same reason. It takes access away, and
 * a right needed to take access away is a right to be careless without.
 */
final class ConnectionController
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Grants $grants,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->caller();

        if ($user === null) {
            return self::unauthenticated();
        }

        $everybody = $request->boolean('all');

        if ($everybody && ! $user->hasPermission('admins.manage')) {
            return self::forbidden();
        }

        $query = Grant::query()
            // Live ones first and then by when they were last used: the list is read to find
            // something to end, and what has already ended is history.
            ->orderByRaw('case when revoked_at is null then 0 else 1 end')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id');

        if (! $everybody) {
            $query->where('cms_user_id', $user->getKey());
        }

        /** @var People<int, Grant> $grants */
        $grants = $query->get();
        $people = $this->people($grants->pluck('cms_user_id')->all());

        return new JsonResponse([
            'data' => $grants->map(fn (Grant $grant): array => $this->row($grant, $people))->values(),
            'meta' => [
                'scope' => $everybody ? 'all' : 'mine',
                // Whether there is a second view to offer at all, so the screen does not
                // draw a switch that answers 403.
                'can_see_everybody' => $user->hasPermission('admins.manage'),
            ],
        ]);
    }

    public function destroy(string $connection): JsonResponse
    {
        $user = $this->caller();

        if ($user === null) {
            return self::unauthenticated();
        }

        /** @var Grant $grant */
        $grant = Grant::query()->findOrFail($connection);

        if ((int) $grant->cms_user_id !== (int) $user->getKey() && ! $user->hasPermission('admins.manage')) {
            return self::forbidden();
        }

        $this->grants->revoke($grant);

        return new JsonResponse([
            'data' => $this->row($grant->refresh(), $this->people([$grant->cms_user_id])),
        ]);
    }

    /**
     * @param  People<int, CmsUser>  $people
     * @return array<string, mixed>
     */
    private function row(Grant $grant, People $people): array
    {
        return [
            'id' => $grant->id,
            // What the client called itself, and where the code was sent. Both, always: the
            // name is the client's own choice and proves nothing, the host does not lie.
            'client' => $grant->client_name,
            'host' => $grant->redirect_host,
            'read_only' => $grant->read_only,
            'user' => [
                'id' => (int) $grant->cms_user_id,
                'name' => $people->get((int) $grant->cms_user_id)?->name,
            ],
            'connected_at' => $grant->created_at?->toIso8601String(),
            'last_used_at' => $grant->last_used_at?->toIso8601String(),
            'revoked_at' => $grant->revoked_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int|null>  $ids
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

    private static function unauthenticated(): JsonResponse
    {
        return new JsonResponse(['message' => __('webx-auth::errors.unauthenticated')], 401);
    }

    private static function forbidden(): JsonResponse
    {
        return new JsonResponse([
            'message' => __('webx-auth::errors.forbidden'),
            'required' => ['admins.manage'],
        ], 403);
    }
}
