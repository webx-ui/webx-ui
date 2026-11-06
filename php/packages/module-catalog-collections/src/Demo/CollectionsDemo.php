<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Demo;

use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\DemoWords;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blog\Models\Article;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Http\CollectionForm;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\CollectionShortcode;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;

/**
 * The collections of the demo shop (§9 of the collections spec), each way of filling one and each
 * way of placing one: presets and a label as blocks on the home page, a hand-picked list in the
 * text of an article. One turned off and one with nothing in it stand beside them, so that what a
 * visitor must not see is there to be not seen.
 *
 * The products are the core demo's, which sells clothing and shoes, so the hand-picked list is a
 * wardrobe. The home page and the article are written through the journal and come back as they
 * were; a site that wrote its own is left alone, and the collections still appear in the panel.
 */
final class CollectionsDemo
{
    private const BLOCK = 'product-collection';

    /** key => [title, preset or null, label's code or null, view, limit, turned on] */
    private const COLLECTIONS = [
        'new-arrivals' => [
            ['en' => 'New arrivals', 'ru' => 'Новинки', 'pl' => 'Nowości'],
            CollectionPresets::NEW, null, ProductCollection::GRID, 8, true,
        ],
        'on-sale' => [
            ['en' => 'On sale', 'ru' => 'Со скидкой', 'pl' => 'Promocje'],
            CollectionPresets::ON_SALE, null, ProductCollection::CAROUSEL, 12, true,
        ],
        'bestsellers' => [
            ['en' => 'Bestsellers', 'ru' => 'Хиты', 'pl' => 'Bestsellery'],
            CollectionPresets::LABEL, 'top', ProductCollection::GRID, 4, true,
        ],
        'starter-wardrobe' => [
            ['en' => 'Starter wardrobe', 'ru' => 'Базовый гардероб', 'pl' => 'Podstawowa garderoba'],
            null, null, ProductCollection::GRID, 4, true,
        ],
        // Filled and off: placed below, and prints nothing until somebody turns it on.
        'seasonal-offer' => [
            ['en' => 'Seasonal offer', 'ru' => 'Сезонное предложение', 'pl' => 'Oferta sezonowa'],
            CollectionPresets::ON_SALE, null, ProductCollection::GRID, 4, false,
        ],
        // On and empty: nobody has picked anything yet.
        'coming-soon' => [
            ['en' => 'Coming soon', 'ru' => 'Скоро в продаже', 'pl' => 'Wkrótce w sprzedaży'],
            null, null, ProductCollection::GRID, 4, true,
        ],
    ];

    /** The hand-picked list's size. */
    private const PICKS = 4;

    /** What stands on the home page, in this order. */
    private const ON_HOME = ['new-arrivals', 'on-sale', 'bestsellers', 'seasonal-offer'];

    /** What stands in the text of the article, in this order. */
    private const IN_ARTICLE = ['starter-wardrobe', 'seasonal-offer', 'coming-soon'];

    private const ARTICLE_HEADING = [
        'en' => 'Picked for you',
        'ru' => 'Мы подобрали для вас',
        'pl' => 'Wybrane dla ciebie',
    ];

    private const ARTICLE_TEXT = [
        'en' => 'A collection stands in a text like any other line, and follows the catalogue from then on.',
        'ru' => 'Подборка стоит в тексте как обычная строка и дальше сама следует за каталогом.',
        'pl' => 'Kolekcja stoi w tekście jak każdy inny wiersz i dalej sama podąża za katalogiem.',
    ];

    public function __construct(
        private readonly CollectionForm $form,
        private readonly CollectionPresets $presets,
        private readonly DemoWords $words,
        private readonly Facets $facets,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $products = $ledger->idsOf('catalog', Product::class);

        if ($products === []) {
            $ledger->note('The catalogue demo is not seeded; the collections have no products to show.');

            return;
        }

        if (ProductCollection::withTrashed()->exists()) {
            $ledger->note('The site already has collections; the demo left them alone.');

            return;
        }

        // The satellites seeded a moment ago are the facets' sources, and the facets are kept for
        // a request: this one began before them.
        $this->facets->flush();

        $made = [];
        $skipped = [];

        foreach (self::COLLECTIONS as $key => [$title, $preset, $label, $view, $limit, $enabled]) {
            $fields = $this->filling($preset, $label);

            if ($fields === null) {
                $skipped[] = $title['en'];

                continue;
            }

            $collection = $this->form->save(new ProductCollection, [
                'key' => $key,
                'title' => $this->words->of($title),
                'view' => $view,
                'limit' => $limit,
                'is_enabled' => $enabled,
                ...$fields,
                ...$key === 'starter-wardrobe' ? $this->picks($products) : [],
            ]);
            $ledger->created($collection, 'Collection '.$title['en']);
            $made[] = $key;
        }

        if ($skipped !== []) {
            $ledger->note('Left out, the site has no facet for them: '.implode(', ', $skipped).'.');
        }

        $this->placeOnHome($made, $ledger);
        $this->placeInArticle($made, $ledger);
    }

    /**
     * What a preset writes, a hand-picked collection's source, or null for a preset the site has no
     * facet for.
     *
     * @return array<string, mixed>|null
     */
    private function filling(?string $key, ?string $label): ?array
    {
        if ($key === null) {
            return ['source' => ProductCollection::MANUAL];
        }

        $preset = $this->presets->find($key);

        if ($preset === null) {
            return null;
        }

        if ($label === null) {
            return $this->presets->fields($preset);
        }

        $locale = $this->locales->defaultCode();
        $ids = $this->facets->byCode('label', $locale)?->resolveSlugs([$label], $locale) ?? [];

        return $ids === [] ? null : $this->presets->fields($preset, array_values($ids));
    }

    /**
     * The first published products of the demo and a link to the shelf of the first: a hand-picked
     * list says where the rest are as any other does.
     *
     * @param  list<int|string>  $products
     * @return array<string, mixed>
     */
    private function picks(array $products): array
    {
        $picks = Product::query()
            ->whereKey($products)
            ->where('is_published', true)
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->limit(self::PICKS)
            ->get(['id', 'category_id']);

        return [
            'products' => $picks->pluck('id')->all(),
            'link' => ['target' => 'entity', 'entity_type' => Category::TYPE, 'entity_id' => $picks->first()?->category_id],
            'link_text' => $this->words->of(['en' => 'All products', 'ru' => 'Все товары', 'pl' => 'Wszystkie produkty']),
        ];
    }

    /**
     * @param  list<string>  $made
     */
    private function placeOnHome(array $made, DemoLedger $ledger): void
    {
        if (! $this->modules->has('pages') || ! $this->modules->has('blocks')) {
            return;
        }

        if (! Block::query()->where('slug', self::BLOCK)->exists()) {
            $ledger->note('The home page was left alone: the block is not installed. Run webx:blocks:offered --install.');

            return;
        }

        $home = Page::home();

        if (! $home instanceof Page || $home->hasDraft()) {
            $ledger->note('The home page was left alone: it has a draft of somebody\'s.');

            return;
        }

        $blocks = [];

        foreach (array_intersect(self::ON_HOME, $made) as $key) {
            $blocks[] = ['key' => 'demo-collection-'.$key, 'type' => self::BLOCK, 'values' => ['collection' => $key]];
        }

        $ledger->changed($home, ['blocks', 'draft', 'published_at'], 'the collections on the home page');

        $before = $home->versions()->pluck('id')->all();

        $home->setAttribute('blocks', [...$home->blocksTree(), ...$blocks]);
        $home->save();
        // Under its own date: the demo adds to a page, it does not make it new.
        $home->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content', $home->published_at);

        foreach ($home->versions()->whereKeyNot($before)->get() as $version) {
            $ledger->created($version, 'Page history');
        }
    }

    /**
     * The blog demo's first article that is out: a text block closing it, with the brackets in
     * paragraphs of their own, as the panel's menu leaves them.
     *
     * @param  list<string>  $made
     */
    private function placeInArticle(array $made, DemoLedger $ledger): void
    {
        if (! $this->modules->has('articles')) {
            return;
        }

        $article = Article::query()
            ->whereKey($ledger->idsOf('articles', Article::class))
            ->where('published_at', '<=', now())
            ->orderBy('id')
            ->first();

        if (! $article instanceof Article || $article->hasDraft()) {
            $ledger->note('No article took the collections: the blog demo has none that is out and free of drafts.');

            return;
        }

        $brackets = '';

        foreach (array_intersect(self::IN_ARTICLE, $made) as $key) {
            $brackets .= sprintf('<p>[%s key=%s]</p>', CollectionShortcode::NAME, $key);
        }

        $body = [];

        foreach ($this->words->of(self::ARTICLE_TEXT) ?? [] as $locale => $text) {
            $body[$locale] = '<p>'.$text.'</p>'.$brackets;
        }

        $ledger->changed($article, ['blocks', 'draft', 'published_at'], 'the collections in an article');

        $before = $article->versions()->pluck('id')->all();

        $article->setAttribute('blocks', [...$article->blocksTree(), [
            'key' => 'demo-article-collections',
            'type' => 'text',
            'values' => ['heading' => $this->words->of(self::ARTICLE_HEADING), 'body' => $body],
        ]]);
        $article->save();
        $article->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content', $article->published_at);

        foreach ($article->versions()->whereKeyNot($before)->get() as $version) {
            $ledger->created($version, 'Article history');
        }
    }
}
