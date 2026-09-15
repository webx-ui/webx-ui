<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use WebxUi\Routing\Aliases\RouteAliases;
use WebxUi\Seo\Http\Resources\RouteAliasResource;

/**
 * The redirects nobody wrote.
 *
 * Read only, and that is the whole design: an alias is made and unmade by the entity that moved,
 * so a panel that could edit one would be a panel that can make the registry disagree with the
 * site. An editor who wants a different answer for an old address writes a rule of their own —
 * it is tried first — and the alias underneath stops mattering.
 *
 * The registry is asked through `RouteAliases`, not queried: `routes` belongs to another package
 * and the day its columns change should not be a day this module notices.
 */
final class RouteAliasController
{
    public function __construct(private readonly RouteAliases $aliases) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:16'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $aliases = $this->aliases->search(
            term: $request->filled('q') ? (string) $request->string('q') : null,
            locale: $request->filled('locale') ? (string) $request->string('locale') : null,
            perPage: (int) $request->integer('per_page', 25),
            page: max(1, (int) $request->integer('page', 1)),
        );

        return RouteAliasResource::collection($aliases);
    }
}
