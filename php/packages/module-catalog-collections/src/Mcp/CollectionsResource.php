<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Mcp;

use WebxUi\CatalogCollections\Catalog\CollectionPreset;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\Mcp\McpResource;

/**
 * `catalog://collections` (§7 of the collections spec): the house rules — what a collection is and
 * is not, the three ways of placing one, the order of the work — and how many there are and in what
 * state, so an agent knows the ground before it writes.
 */
final class CollectionsResource
{
    private const RULES = [
        'what' => 'A collection is a named block of products a manager puts together once and the site prints wherever it is '
            .'placed: «Popular products» on the home page, «On sale» in a section, a choice of cameras in an article. It is '
            .'a piece of a page, with no address, SEO card or sitemap entry.',
        'not_a_landing' => 'A page with its own address, H1 and SEO card for a set of filters is a landing '
            .'(catalog://landings), not a collection.',
        'not_wx_collection' => 'The block field `wx-collection` and the source `products` are a developer\'s way of choosing records in '
            .'a template. A collection is a record a manager owns; a block chooses one with the field `wx-catalog-collection`.',
        'order' => 'catalog_collections_facets first, for the codes, slugs, presets and sorts; then catalog_collections_create; '
            .'then catalog_collections_preview, to see what the site would show.',
        'filling' => 'manual: a list in order, written with catalog_collections_products. filter: category, set, on_sale and sort, '
            .'and the list is not read. Each refuses what only the other reads.',
        'presets' => 'A preset fills ordinary fields and is not stored: new sorts by date added, on-sale keeps products with an old '
            .'price, label takes the labels from values.set. «Popular» is label with a label such as «Hit», or any selection '
            .'with the sort popular.',
        'what_shows' => 'Only what a reader may see: published, in a visible category, with an address in the language of the page. '
            .'only_purchasable (on by default) also keeps only what can be bought; with it off, a card says the status instead '
            .'of the button. The limit counts after that, so a product taken off sale is replaced by the next.',
        'placing' => [
            'block' => 'The block `product-collection` with the field `collection`, in a page or a region: the blocks_* and pages_* tools.',
            'text' => 'A paragraph of its own in a rich text: [product-collection key=cameras view=carousel limit=4].',
            'template' => 'In a theme file: <x-webx-catalog-collections::collection key="popular" view="carousel" :limit="4" title="-" />.',
            'limit' => 'A place can only lower the collection\'s limit, never raise it. An unknown view is ignored.',
        ],
        'key' => 'Made from the title or given on create, and fixed from then on: templates and texts name the collection by it. '
            .'A collection in the bin keeps its key, so restoring it never conflicts; catalog_collections_purge frees it.',
        'nothing_printed' => 'Switched off, in the bin, an unknown key and an empty collection all print nothing, heading included: '
            .'the page lives on, and a mistyped key in a text says nothing. Read catalog_collections_preview, not the page.',
        'deleting' => 'Delete goes to the bin without looking at the places: catalog_collections_restore brings every one back. '
            .'catalog_collections_purge, only from the bin, cannot be undone: the places print nothing, until a new collection takes the key. '
            .'catalog_collections_usage names blocks and regions only; a shortcode in a text and a tag in a template are not counted.',
        'attention' => 'value-deleted: a value of the set was deleted. If that left the set empty, the collection was switched off, or '
            .'it would have become the whole catalogue. Fix the set; saving clears the mark.',
    ];

    public function __construct(private readonly CollectionPresets $presets) {}

    public function resource(): McpResource
    {
        return new McpResource(
            'catalog://collections',
            'Product collections of the catalogue',
            'The house rules of collections — what one is, how it differs from a landing and from wx-collection, the three ways of '
            .'placing it with samples, the presets, the order of the work — and how many there are: on, off, needing attention, in the bin.',
            fn (): array => $this->read(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        return [
            'rules' => self::RULES,
            'presets' => array_map(static fn (CollectionPreset $preset): string => $preset->key, $this->presets->all()),
            'max_limit' => ProductCollection::maxLimit(),
            'counts' => [
                'all' => ProductCollection::query()->count(),
                'enabled' => ProductCollection::query()->where('is_enabled', true)->count(),
                'attention' => ProductCollection::query()->whereNotNull('attention')->count(),
                'trashed' => ProductCollection::onlyTrashed()->count(),
            ],
        ];
    }
}
