<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Demo;

use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Pages\Models\Page;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;
use WebxUi\Recipes\Panel\RecipesModule;
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Services\Models\Service;

/**
 * Three categories, five nutrients and six recipes (§5.12), and the recipes block put where a
 * site would put it.
 *
 * Each recipe past the first is there to show one rule: one is filed under two categories, one is
 * a draft, one is written in English only and so has no Russian address, one has similar recipes
 * chosen by hand while the rest get theirs picked, one has no nutrition — the table is not
 * printed — and three have no gallery, so a card without a cover gets seen too. The pictures are the
 * two the library's demo seeded a moment ago, as the services' covers are.
 *
 * What else there is decides the rest, which is why {@see requires()} is worked out rather than
 * written down: with `module-services`, two recipes are linked to the demo services; with
 * `module-blocks` and `module-pages`, a page `/recipes-showcase` with both views of the block —
 * a showcase of one category, and the whole catalogue a few at a time below it.
 */
final class RecipesDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
        private readonly Container $container,
    ) {}

    /**
     * The modules whose demo has to be there before this one's. Named only when installed — a
     * name `requires()` gives that is not installed skips the whole demo, and recipes have
     * something to show without any of them but the library.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        $requires = ['media'];

        if ($this->modules->has('services')) {
            $requires[] = 'services';
        }

        // The page is made of the block, so it takes both.
        if ($this->modules->has('blocks') && $this->modules->has('pages')) {
            $requires[] = 'blocks';
            $requires[] = 'pages';
        }

        return $requires;
    }

    public function seed(DemoLedger $ledger): void
    {
        // Recipes with anything in them are somebody's recipes.
        if (Recipe::withTrashed()->exists() || RecipeCategory::withTrashed()->exists() || RecipeNutrient::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();
        $pictures = $this->pictures($ledger);
        $services = $this->services($ledger);

        /** @var array<string, RecipeNutrient> $nutrients */
        $nutrients = [];
        $position = 0;

        foreach ($this->list($document['nutrients'] ?? null) as $input) {
            $nutrient = $this->nutrient($input, $position++, $ledger);

            if ($nutrient !== null) {
                $nutrients[(string) $input['key']] = $nutrient;
            }
        }

        /** @var array<string, Recipe> $recipes */
        $recipes = [];
        /** @var array<string, array<string, mixed>> $inputs */
        $inputs = [];

        // In the order of the file, which is the order of the whole list.
        foreach ($this->list($document['recipes'] ?? null) as $input) {
            $recipe = $this->recipe($input, $pictures, $nutrients, $services, $ledger);

            if ($recipe !== null) {
                $recipes[(string) $input['slug']] = $recipe;
                $inputs[(string) $input['slug']] = $input;
            }
        }

        /** @var array<string, RecipeCategory> $categories */
        $categories = [];
        $position = 0;

        foreach ($this->list($document['categories'] ?? null) as $input) {
            $category = $this->category($input, $position++, $recipes, $ledger);

            if ($category !== null) {
                $categories[(string) $input['key']] = $category;
            }
        }

        foreach ($recipes as $slug => $recipe) {
            // Chosen by hand, once every recipe exists to be chosen.
            $related = array_values(array_filter(array_map(
                static fn (string $one): ?int => isset($recipes[$one]) ? (int) $recipes[$one]->getKey() : null,
                $this->list($inputs[$slug]['related'] ?? null, strings: true),
            )));

            if ($related !== []) {
                $recipe->syncRelated('related', Recipe::TYPE, $related);
            }

            if (($inputs[$slug]['draft'] ?? false) === true) {
                continue;
            }

            // Published after the categories and the links, so the first version records them.
            $recipe->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');
            $ledger->createdVersionsOf($recipe);
        }

        $page = $document['page'] ?? null;

        if (is_array($page) && in_array('pages', $this->requires(), true) && $this->blockType($ledger)) {
            $this->page($page, $categories, $ledger);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function nutrient(array $input, int $position, DemoLedger $ledger): ?RecipeNutrient
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($key === '' || $title === null) {
            return null;
        }

        $nutrient = RecipeNutrient::query()->create(['title' => $title, 'is_visible' => true, 'position' => $position]);
        $ledger->created($nutrient, $key);

        return $nutrient;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<MediaFile>  $pictures
     * @param  array<string, RecipeNutrient>  $nutrients
     * @param  array<string, int>  $services
     */
    private function recipe(array $input, array $pictures, array $nutrients, array $services, DemoLedger $ledger): ?Recipe
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($slug === '' || $title === null) {
            return null;
        }

        $gallery = [];

        foreach ($this->list($input['gallery'] ?? null, numbers: true) as $index) {
            if (isset($pictures[$index])) {
                $gallery[] = ['path' => $pictures[$index]->path];
            }
        }

        $nutrition = [];

        foreach (is_array($input['nutrition'] ?? null) ? $input['nutrition'] : [] as $key => $words) {
            $nutrition[(string) $key] = $this->words($words) ?? [];
        }

        $recipe = Recipe::query()->create([
            'title' => $title,
            // The same slug in every language the recipe is written in: no slug, no address there.
            'slug' => array_fill_keys(array_keys($title), $slug),
            'lead' => $this->words($input['lead'] ?? null),
            'gallery' => $gallery === [] ? null : $gallery,
            'ingredients' => $this->words($input['ingredients'] ?? null),
            'method' => $this->words($input['method'] ?? null),
            'nutrition' => $nutrition === [] ? null : $nutrition,
            'total_minutes' => is_int($input['total_minutes'] ?? null) ? $input['total_minutes'] : null,
            'servings' => is_int($input['servings'] ?? null) ? $input['servings'] : null,
        ]);

        $ledger->created($recipe, $slug);

        $rich = [];

        foreach ($this->list($input['nutrients'] ?? null, strings: true) as $key) {
            if (isset($nutrients[$key])) {
                $rich[] = (int) $nutrients[$key]->getKey();
            }
        }

        if ($rich !== []) {
            $recipe->syncCategories($rich, 'nutrients');
        }

        $linked = array_values(array_filter(array_map(
            static fn (string $one): ?int => $services[$one] ?? null,
            $this->list($input['services'] ?? null, strings: true),
        )));

        if ($linked !== []) {
            $recipe->syncRelated('services', 'service', $linked);
        }

        return $recipe;
    }

    /**
     * A category, and its recipes — appended to what they are filed under already, so the one
     * named first stays each recipe's main category.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, Recipe>  $recipes
     */
    private function category(array $input, int $position, array $recipes, DemoLedger $ledger): ?RecipeCategory
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($key === '' || $title === null) {
            return null;
        }

        $category = RecipeCategory::query()->create([
            'title' => $title,
            'slug' => array_fill_keys(array_keys($title), $key),
            'lead' => $this->words($input['lead'] ?? null),
            'is_visible' => true,
            'position' => $position,
        ]);

        $ledger->created($category, $key);

        foreach ($this->list($input['recipes'] ?? null, strings: true) as $slug) {
            $recipe = $recipes[$slug] ?? null;

            if ($recipe !== null) {
                $recipe->syncCategories([...$recipe->categoryIds(), (int) $category->getKey()]);
            }
        }

        return $category;
    }

    /**
     * The demo services by slug — only the ones the services demo made a moment ago: somebody's
     * real service is not the place to hang an example.
     *
     * @return array<string, int>
     */
    private function services(DemoLedger $ledger): array
    {
        if (! in_array('services', $this->requires(), true) || ! class_exists(Service::class)) {
            return [];
        }

        $ids = $ledger->idsOf('services', Service::class);

        if ($ids === []) {
            return [];
        }

        $default = $this->locales->defaultCode();
        $services = [];

        foreach (Service::query()->whereKey($ids)->get() as $service) {
            $slug = $service->getTranslation('slug', $default, false);

            if (is_string($slug) && $slug !== '') {
                $services[$slug] = (int) $service->getKey();
            }
        }

        return $services;
    }

    /**
     * The pictures the library seeded a moment ago: the wide one first, then the square. A site
     * whose library already held those bytes gets recipes without pictures rather than somebody
     * else's.
     *
     * @return list<MediaFile>
     */
    private function pictures(DemoLedger $ledger): array
    {
        $ids = $ledger->idsOf('media', MediaFile::class);

        if ($ids === []) {
            return [];
        }

        /** @var list<MediaFile> $files */
        $files = MediaFile::query()->whereKey($ids)->orderBy('id')->get()->all();

        return $files;
    }

    /**
     * The offered type, installed now if the site has not taken it — `webx:setup` usually has.
     * A type by that slug the site already has is left as it is, whatever it was made into.
     */
    private function blockType(DemoLedger $ledger): bool
    {
        $offers = $this->container->make(BlockOffers::class);

        foreach ($offers->documents([RecipesModule::ID]) as $offer) {
            if ($offers->install($offer) !== BlockOffers::PRESENT) {
                $block = Block::query()->where('slug', $offer['slug'])->first();

                if ($block instanceof Block) {
                    $ledger->created($block, 'the '.$offer['slug'].' block type');
                }
            }
        }

        if (! Block::query()->where('slug', 'recipes')->where('is_enabled', true)->exists()) {
            $ledger->note('the recipes are in the panel, but no recipes block was put anywhere: the site has no enabled block type "recipes".');

            return false;
        }

        return true;
    }

    /**
     * `/recipes-showcase` under the home page: three recipes of one category, and every recipe a
     * few at a time below it — both views of the block on one page.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, RecipeCategory>  $categories
     */
    private function page(array $input, array $categories, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : 'recipes-showcase';
        $home = Page::home();

        if (! $home instanceof Page) {
            return;
        }

        if ($this->container->make(Reserved::class)->taken($slug) || Page::withTrashed()->whereTranslationLikeAny('slug', $slug)->exists()) {
            $ledger->note("no page of recipes was made: /{$slug} is already taken on this site.");

            return;
        }

        $showcase = is_array($input['showcase'] ?? null) ? $input['showcase'] : [];
        $catalog = is_array($input['catalog'] ?? null) ? $input['catalog'] : [];
        $category = $categories[(string) ($showcase['category'] ?? '')] ?? null;
        $title = $this->words($input['title'] ?? null) ?? [$this->locales->defaultCode() => 'Recipes'];
        $all = static fn (array $categories, ?int $limit): array => [
            'categories' => $categories,
            'limit' => $limit,
            'filter' => false,
            'markup' => null,
            'related' => null,
        ];

        $page = new Page([
            'title' => $title,
            'slug' => array_fill_keys(array_keys($title), $slug),
            'blocks' => [
                [
                    'key' => 'recipes-showcase',
                    'type' => 'recipes',
                    'values' => array_filter([
                        'title' => $this->words($showcase['title'] ?? null),
                        'recipes' => $all($category === null ? [] : [(int) $category->getKey()], 3),
                        'mode' => 'showcase',
                        'columns' => 3,
                    ], static fn (mixed $value): bool => $value !== null),
                ],
                [
                    'key' => 'recipes-catalog',
                    'type' => 'recipes',
                    'values' => array_filter([
                        'title' => $this->words($catalog['title'] ?? null),
                        'recipes' => $all([], null),
                        'mode' => 'catalog',
                        'per_page' => is_int($catalog['per_page'] ?? null) ? $catalog['per_page'] : null,
                        'columns' => 2,
                    ], static fn (mixed $value): bool => $value !== null),
                ],
            ],
        ]);

        try {
            $page->appendTo($home);
        } catch (Throwable $refused) {
            $ledger->note("no page of recipes was made: {$refused->getMessage()}");

            return;
        }

        $ledger->created($page, $slug);
        $page->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $seo = $input['seo'] ?? null;

        if (is_array($seo)) {
            // A page with an empty card has no <title> at all (CLAUDE.md §4).
            $card = array_filter([
                'title' => $this->words($seo['title'] ?? null),
                'description' => $this->words($seo['description'] ?? null),
            ]);

            if ($card !== []) {
                $page->saveSeo($card);
                $meta = $page->seo()->first();

                if ($meta instanceof SeoMeta) {
                    $ledger->created($meta, "what /{$slug} says about itself");
                }
            }
        }

        $ledger->createdVersionsOf($page);
    }

    /**
     * The languages of the demo that the site has. A site with neither gets the English under its
     * own default, as the other demos do, rather than nameless recipes.
     *
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        if (! is_array($text)) {
            return null;
        }

        $codes = $this->locales->codes();
        $words = [];

        foreach ($text as $locale => $value) {
            if (is_string($value) && trim($value) !== '' && in_array((string) $locale, $codes, true)) {
                $words[(string) $locale] = trim($value);
            }
        }

        if ($words === [] && is_string($text['en'] ?? null) && trim($text['en']) !== '') {
            return [$this->locales->defaultCode() => trim($text['en'])];
        }

        return $words === [] ? null : $words;
    }

    /**
     * @return ($strings is true ? list<string> : ($numbers is true ? list<int> : list<array<string, mixed>>))
     */
    private function list(mixed $value, bool $strings = false, bool $numbers = false): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keep = match (true) {
            $strings => is_string(...),
            $numbers => is_int(...),
            default => is_array(...),
        };

        return array_values(array_filter($value, $keep));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/recipes.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('recipes.json is not a set of recipes.');
        }

        return $document;
    }
}
