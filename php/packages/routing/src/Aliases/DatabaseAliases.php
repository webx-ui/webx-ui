<?php

declare(strict_types=1);

namespace WebxUi\Routing\Aliases;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\UrlNormaliser;

/**
 * The aliases as they are: rows of `routes`, newest first.
 *
 * Newest first because the list is read after something moved — "what did I just break" is the
 * question it answers, and the answer is at the top of the table for about a day.
 */
class DatabaseAliases implements RouteAliases
{
    public function __construct(private readonly Resolver $resolver) {}

    /**
     * @return Contract<int, Alias>
     */
    public function search(?string $term = null, ?string $locale = null, int $perPage = 25, int $page = 1): Contract
    {
        // The key spelling, so that a search typed as `/About/` finds the row stored as `about`.
        $needle = addcslashes(UrlNormaliser::key((string) $term), '%_\\');

        $paginator = Route::query()
            ->alias()
            ->with('target')
            ->when($needle !== '', fn (Builder $query) => $query->where(
                fn (Builder $where) => $where
                    ->where('path', 'like', "%{$needle}%")
                    // Where it leads counts as the address too: an editor looking at a page
                    // knows the address it has now, not the ones it used to have.
                    ->orWhereHas('target', fn (Builder $target) => $target->where('path', 'like', "%{$needle}%")),
            ))
            ->when((string) $locale !== '', fn (Builder $query) => $query->where('locale', $locale))
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);

        /** @var list<Route> $rows */
        $rows = $paginator->items();

        return new LengthAwarePaginator(
            array_map($this->view(...), $rows),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }

    private function view(Route $route): Alias
    {
        $target = $route->target;

        return new Alias(
            id: $route->id,
            locale: $route->locale,
            path: $route->path,
            url: $this->resolver->publicUrl($route),
            target: $target?->path,
            targetUrl: $target instanceof Route ? $this->resolver->publicUrl($target) : null,
            entityType: $route->entity_type,
            entityId: $route->entity_id,
            createdAt: $route->created_at,
        );
    }
}
