<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Seo\Rendering\Seo;

/**
 * The catalogue of the index and of a category page: one query for the recipes, the page worked
 * out of them, whole cards for that page only, and an `ItemList` of what it shows.
 *
 * The filter and the pages are worked out of light stand-ins — an id and the nutrients — so a
 * catalogue of three hundred recipes builds twenty-four cards, not three hundred.
 */
final class CatalogPage
{
    public function __construct(
        private readonly Cards $cards,
        private readonly Seo $seo,
        private readonly Config $config,
    ) {}

    /**
     * @param  iterable<Model>  $categories  The categories the page around the catalogue has
     *                                       loaded with their addresses — the navigation of the
     *                                       index, the category of its own page — so that a card
     *                                       under one of them does not ask the registry for it
     *                                       again.
     *
     * @throws NotFoundHttpException A page past the last one.
     */
    public function build(Request $request, RecipeQuery $query, iterable $categories = []): Catalog
    {
        $locale = $query->resolvedLocale();
        $recipes = $query->models()->keyBy(static fn (Recipe $recipe): int => (int) $recipe->getKey());

        $stubs = $recipes
            ->map(static fn (Recipe $recipe): array => [
                'id' => (int) $recipe->getKey(),
                'nutrients' => $recipe->nutrients
                    ->filter(static fn (RecipeNutrient $nutrient): bool => $nutrient->is_visible)
                    ->map(static fn (RecipeNutrient $nutrient): array => ['id' => (int) $nutrient->getKey(), 'title' => $nutrient->displayName($locale)])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $catalog = Catalog::paged($stubs, (int) $this->config->get('webx-recipes.per-page', 24), $request);

        if ($catalog->outOfRange()) {
            throw new NotFoundHttpException;
        }

        $shown = [];

        foreach ($catalog->items as $stub) {
            $recipe = $recipes->get($stub['id']);

            if ($recipe instanceof Recipe) {
                $shown[] = $recipe;
            }
        }

        $this->shareRoutes($shown, $categories);
        $cards = $this->cards->recipes($shown, $locale);

        $this->pushItemList($cards);

        return $catalog->withItems($cards);
    }

    /**
     * The registry rows the page already holds for a category, handed to the same category under
     * the recipes shown — another instance of the same row, which would otherwise be read again.
     *
     * @param  list<Recipe>  $recipes
     * @param  iterable<Model>  $categories
     */
    private function shareRoutes(array $recipes, iterable $categories): void
    {
        $known = [];

        foreach ($categories as $category) {
            if ($category->relationLoaded('routes')) {
                $known[(int) $category->getKey()] = $category->getRelation('routes');
            }
        }

        if ($known === []) {
            return;
        }

        foreach ($recipes as $recipe) {
            if (! $recipe->relationLoaded('categories')) {
                continue;
            }

            foreach ($recipe->getRelation('categories') as $category) {
                if (! $category->relationLoaded('routes') && isset($known[(int) $category->getKey()])) {
                    $category->setRelation('routes', $known[(int) $category->getKey()]);
                }
            }
        }
    }

    /**
     * An `ItemList` of what this page shows — about the page rather than about an entity, so the
     * page pushes it.
     *
     * @param  list<array<string, mixed>>  $cards
     */
    private function pushItemList(array $cards): void
    {
        $urls = array_values(array_filter(array_map(
            static fn (array $card): ?string => is_string($card['url'] ?? null) ? $card['url'] : null,
            $cards,
        )));

        if ($urls === []) {
            return;
        }

        $this->seo->push([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(
                static fn (string $url, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $url],
                $urls,
                array_keys($urls),
            ),
        ]);
    }
}
