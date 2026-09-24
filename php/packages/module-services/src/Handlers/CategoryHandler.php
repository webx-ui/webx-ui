<?php

declare(strict_types=1);

namespace WebxUi\Services\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Routing\RouteHandler;
use WebxUi\Services\Models\ServiceCategory;
use WebxUi\Services\Rendering\Catalogue;
use WebxUi\Services\Rendering\Views;

/**
 * A category page: its heading, its introduction, its blocks, and its services in the order the
 * editor dragged them into inside it (§4.4).
 *
 * Hidden is a 404 here and nothing more: the services it lists go on answering at their own
 * addresses.
 */
class CategoryHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly Catalogue $catalogue,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof ServiceCategory || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        $services = $this->catalogue->in($entity);

        $this->catalogue->pushItemList($services);

        return response($this->views->make('category', [
            'category' => $entity,
            'services' => $services,
        ])->render());
    }
}
