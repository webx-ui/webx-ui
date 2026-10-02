<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Demo;

use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogLandings\Http\LandingForm;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Panel\LandingsModule;
use WebxUi\Localization\Locales;

/**
 * The landings of the demo shop (§13): «Popular selections» on Clothing and Shoes, one landing on
 * the whole catalogue, an empty one (noindex, out of the sitemap, «0 products» in the panel), and
 * one with recommended products above its list.
 *
 * The sets are written in codes and slugs, the way an address and an agent spell them, and saved
 * through the panel's form. A set needs the satellite its facet comes from — a brand, a colour, a
 * label — and a landing whose facet the site does not have is left out rather than made of
 * something else; the module asks for those satellites' demos first when they are installed
 * ({@see LandingsModule::requires()}).
 */
final class LandingsDemo
{
    /** slug => [base category by name or null, set: code => slugs | [min, max], name, on the category, recommended] */
    private const LANDINGS = [
        'black-clothing' => ['Clothing', ['color' => ['black']], 'Black clothing', true, 3],
        'white-clothing' => ['Clothing', ['color' => ['white']], 'White clothing', true, 0],
        'adatum-clothing' => ['Clothing', ['brand' => ['adatum']], 'Adatum clothing', true, 0],
        'clothing-under-50' => ['Clothing', ['price' => [null, 50]], 'Clothing under 50', true, 0],
        'woodgrove-shoes' => ['Shoes', ['brand' => ['woodgrove']], 'Woodgrove shoes', true, 0],
        'black-shoes' => ['Shoes', ['color' => ['black']], 'Black shoes', true, 0],
        // No base: the whole catalogue.
        'new-arrivals' => [null, ['label' => ['new']], 'New arrivals', false, 0],
        // Nothing costs that much: the page lives, noindex, out of the sitemap.
        'luxury-shoes' => ['Shoes', ['price' => [1000, null]], 'Luxury shoes', false, 0],
    ];

    private const TEXT = '<p>Black never goes out of style: shirts, dresses and skirts that go with anything.</p>';

    public function __construct(
        private readonly Facets $facets,
        private readonly LandingForm $form,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $categoryIds = $ledger->idsOf('catalog', Category::class);

        if ($categoryIds === []) {
            $ledger->note('The catalogue demo is not seeded; the landings have no shelves to stand on.');

            return;
        }

        if (Landing::withTrashed()->exists()) {
            $ledger->note('The site already has landings; the demo left them alone.');

            return;
        }

        $locale = $this->locales->defaultCode();
        $categories = Category::query()->whereKey($categoryIds)->get()
            ->keyBy(static fn (Category $category): string => (string) $category->getTranslation('name', $locale, false));
        $products = $ledger->idsOf('catalog', Product::class);
        // The satellites seeded a moment ago are the facets' sources, and the facets are kept for
        // a request: this one began before them.
        $this->facets->flush();
        $position = 0;
        $skipped = [];

        foreach (self::LANDINGS as $slug => [$base, $set, $name, $onCategory, $recommended]) {
            $position++;
            $category = $base === null ? null : $categories->get($base);
            $filters = $this->filters($set, $locale);

            if (($base !== null && $category === null) || $filters === null) {
                $skipped[] = $name;

                continue;
            }

            $landing = $this->form->save(new Landing, [
                'category_id' => $category?->getKey(),
                'filters' => $filters,
                'name' => [$locale => $name],
                'slug' => [$locale => $slug],
                'on_category' => $onCategory,
                'position' => $position,
                'is_published' => true,
                ...$recommended > 0 && $category !== null ? [
                    'text_above' => [$locale => self::TEXT],
                    'recommended' => Product::query()
                        ->whereKey($products)
                        ->whereIn('category_id', $category->subtree()->pluck('id'))
                        ->where('is_published', true)
                        ->orderBy('id')
                        ->limit($recommended)
                        ->pluck('id')
                        ->all(),
                ] : [],
            ]);
            $ledger->created($landing, 'Landing '.$name);
        }

        if ($skipped !== []) {
            $ledger->note('Left out, the site has no facet for their sets: '.implode(', ', $skipped).'.');
        }
    }

    /**
     * A set as the form stores it — facet keys and ids; null when the catalogue has no such facet
     * or value.
     *
     * @param  array<string, list<string>|array{0: float|int|null, 1: float|int|null}>  $set
     * @return array<string, array<string, mixed>>|null
     */
    private function filters(array $set, string $locale): ?array
    {
        $filters = [];

        foreach ($set as $code => $choice) {
            $facet = $this->facets->byCode($code, $locale);

            if ($facet === null) {
                return null;
            }

            if ($facet->kind() === FacetKind::Range) {
                $filters[$facet->key()] = ['min' => $choice[0], 'max' => $choice[1]];

                continue;
            }

            /** @var list<string> $choice */
            $ids = array_values($facet->resolveSlugs($choice, $locale));

            if (count($ids) !== count($choice)) {
                return null;
            }

            $filters[$facet->key()] = ['values' => array_map('strval', $ids)];
        }

        return $filters;
    }
}
