<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Rendering\CatalogPage;
use WebxUi\Recipes\Rendering\RecipeQuery;
use WebxUi\Recipes\Rendering\Views;

/**
 * The index of the recipes: `{prefix}`, the categories as links to their pages and the whole
 * catalogue under them, a page at a time (§5.4).
 *
 * A route rather than an entity: there is nothing to edit here. `Reserved` asks the router, so
 * the address is closed to pages while the index is on — and open to one when it is switched off.
 */
class IndexController
{
    public function __construct(
        private readonly Views $views,
        private readonly CatalogPage $catalog,
    ) {}

    public function __invoke(Request $request): Response
    {
        $catalog = $this->catalog->build($request, new RecipeQuery);

        /** @var list<RecipeCategory> $categories */
        $categories = RecipeCategory::query()->visible()->ordered()->with('routes')->get()
            ->filter(static fn (RecipeCategory $category): bool => $category->hasUrlIn(app()->getLocale()))
            ->values()
            ->all();

        return response($this->views->make('index', [
            'categories' => $categories,
            'catalog' => $catalog,
        ])->render());
    }
}
