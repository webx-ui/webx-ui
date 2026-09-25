<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Rendering\EventQuery;
use WebxUi\Events\Rendering\ListPage;
use WebxUi\Events\Rendering\Views;

/**
 * The index of the events: `{prefix}`, the categories as links to their pages and the events to
 * come under them, a page at a time (§4.4).
 *
 * A route rather than an entity: there is nothing to edit here. `Reserved` asks the router, so
 * the address is closed to pages while the index is on — and open to one when it is switched off.
 */
class IndexController
{
    public function __construct(
        private readonly Views $views,
        private readonly ListPage $list,
    ) {}

    public function __invoke(Request $request): Response
    {
        $listing = $this->list->build($request, new EventQuery);

        /** @var list<EventCategory> $categories */
        $categories = EventCategory::query()->visible()->ordered()->with('routes')->get()
            ->filter(static fn (EventCategory $category): bool => $category->hasUrlIn(app()->getLocale()))
            ->values()
            ->all();

        return response($this->views->make('index', [
            'categories' => $categories,
            'listing' => $listing,
        ])->render());
    }
}
