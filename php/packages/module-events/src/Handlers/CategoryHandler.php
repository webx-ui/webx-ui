<?php

declare(strict_types=1);

namespace WebxUi\Events\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Events\Models\EventCategory;
use WebxUi\Events\Rendering\EventQuery;
use WebxUi\Events\Rendering\ListPage;
use WebxUi\Events\Rendering\Views;
use WebxUi\Routing\RouteHandler;

/**
 * A category page: its heading, introduction and picture, and the events of it that are still to
 * come, a page at a time (§4.4). The past ones are not listed here (decision 6).
 *
 * Hidden is a 404 here and nothing more: the events it lists go on answering at their own
 * addresses.
 */
class CategoryHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly ListPage $list,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof EventCategory || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        $listing = $this->list->build($request, (new EventQuery)->in((int) $entity->getKey()));

        return response($this->views->make('category', [
            'category' => $entity,
            'listing' => $listing,
        ])->render());
    }
}
