<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Rendering\RecipePage;
use WebxUi\Recipes\Rendering\Views;
use WebxUi\Routing\RouteHandler;

/**
 * What answers once the registry has decided that this address is a recipe (§5.5).
 *
 * `isVisible()` is the whole of the decision, and the sitemap asks the same thing: a draft is a
 * 404, and the same recipe under a preview token is shown as it will be — the preview route of
 * `module-blocks` has already laid the draft over the columns by then. Without `module-blocks`
 * there is no preview, and no grant to ask about.
 *
 * A recipe in a hidden category answers all the same: it is not the category's property.
 */
class RecipeHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly RecipePage $page,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Recipe) {
            throw new NotFoundHttpException;
        }

        if (! $entity->isVisible() && ! $this->previewing($request)) {
            throw new NotFoundHttpException;
        }

        $entity->loadMissing(['categories', 'nutrients']);

        return response($this->views->make('recipe', $this->page->data($entity))->render());
    }

    private function previewing(Request $request): bool
    {
        return class_exists(PreviewGrant::class) && PreviewGrant::of($request) !== null;
    }
}
