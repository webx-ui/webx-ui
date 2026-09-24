<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Rendering\CatalogPage;
use WebxUi\Recipes\Rendering\RecipeQuery;
use WebxUi\Recipes\Rendering\Views;
use WebxUi\Routing\RouteHandler;

/**
 * A category page: its heading, introduction and picture, and the catalogue narrowed to it (§5.4)
 * — in the order of the whole list, because recipes have only that one (decision 4).
 *
 * Hidden is a 404 here and nothing more: the recipes it lists go on answering at their own
 * addresses.
 */
class CategoryHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly CatalogPage $catalog,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof RecipeCategory || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        $catalog = $this->catalog->build($request, (new RecipeQuery)->in((int) $entity->getKey()));

        return response($this->views->make('category', [
            'category' => $entity,
            'catalog' => $catalog,
        ])->render());
    }
}
