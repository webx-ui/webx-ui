<?php

declare(strict_types=1);

namespace WebxUi\Auth\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use WebxUi\Auth\Http\Requests\McpCallIndexRequest;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Calls\Call;

/**
 * The trail of what agents did through the panel, for the tab next to the administrators.
 *
 * `webx-ui/mcp` writes the rows and knows nobody by name; this is where a `cms_user_id`
 * becomes a person. Behind `admins.audit`, the permission the sign-in trail is behind: who
 * did what is the same question whether a hand or a program was at the other end.
 *
 * The filters offered are the ones the log can answer — the people and the tools that
 * actually appear in it — rather than every administrator and every tool on offer, most of
 * which would filter down to nothing.
 */
final class McpCallController
{
    private const PER_PAGE = 30;

    public function __invoke(McpCallIndexRequest $request): JsonResponse
    {
        $query = Call::query()->with('grant')->latest('id');

        if ($request->filled('user')) {
            $user = (string) $request->string('user');

            $user === 'none'
                ? $query->whereNull('cms_user_id')
                : $query->where('cms_user_id', (int) $user);
        }

        if ($request->filled('tool')) {
            $query->where('tool', (string) $request->string('tool'));
        }

        match ((string) $request->string('outcome')) {
            'ok' => $query->where('ok', true)->where('dry_run', false),
            'failed' => $query->where('ok', false),
            'dry' => $query->where('dry_run', true),
            default => null,
        };

        $page = $query->paginate($request->integer('per_page') ?: self::PER_PAGE);

        /** @var Collection<int, Call> $calls */
        $calls = $page->getCollection();
        $people = $this->people($calls->pluck('cms_user_id'));

        return new JsonResponse([
            'data' => $calls->map(fn (Call $call): array => $this->row($call, $people))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => $this->filters(),
        ]);
    }

    /**
     * @param  Collection<int, CmsUser>  $people
     * @return array<string, mixed>
     */
    private function row(Call $call, Collection $people): array
    {
        return [
            'id' => $call->id,
            'at' => $call->created_at?->toIso8601String(),
            'user' => $this->person($call->cms_user_id, $people),
            'client' => $call->grant?->client_name,
            'tool' => $call->tool,
            'arguments' => $call->arguments,
            'dry_run' => $call->dry_run,
            'ok' => $call->ok,
            'error' => $call->error,
            'duration_ms' => $call->duration_ms,
        ];
    }

    /**
     * The person behind an id, or the id alone for somebody deleted since: the row outlives
     * the administrator on purpose, and "administrator 7" is more than nothing.
     *
     * @param  Collection<int, CmsUser>  $people
     * @return array{id: int, name: string|null}|null
     */
    private function person(?int $id, Collection $people): ?array
    {
        if ($id === null) {
            return null;
        }

        return ['id' => $id, 'name' => $people->get($id)?->name];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|null>  $ids
     * @return Collection<int, CmsUser>
     */
    private function people(\Illuminate\Support\Collection $ids): Collection
    {
        $ids = $ids->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return CmsUser::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * What the log can be narrowed by: the people and the tools that appear in it.
     *
     * @return array{users: list<array{id: int|null, name: string|null}>, tools: list<string>}
     */
    private function filters(): array
    {
        /** @var \Illuminate\Support\Collection<int, int|null> $ids */
        $ids = Call::query()->distinct()->pluck('cms_user_id');
        $people = $this->people($ids);

        $users = $ids
            ->filter()
            ->unique()
            ->map(static fn (int $id): array => ['id' => $id, 'name' => $people->get($id)?->name])
            // People by name, then the ids of those deleted since, then the nobody of the
            // stdio server — the order a person scans a list of names in.
            ->sortBy(static fn (array $user): array => [
                $user['name'] === null ? 1 : 0,
                mb_strtolower((string) ($user['name'] ?? '')),
                $user['id'],
            ])
            ->values()
            ->all();

        if ($ids->contains(null)) {
            $users[] = ['id' => null, 'name' => null];
        }

        /** @var list<string> $tools */
        $tools = Call::query()->distinct()->orderBy('tool')->pluck('tool')->all();

        return ['users' => $users, 'tools' => $tools];
    }
}
