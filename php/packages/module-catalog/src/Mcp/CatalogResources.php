<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Mcp;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use WebxUi\Catalog\Bulk\BulkActions;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Sorts\Sort;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it writes into the catalogue (§12.2): what there is to filter and
 * sort by, which fields this site switched off, how addresses are made, the categories in brief,
 * the satellites' parts of the product form and the bulk actions.
 *
 * The parts are gathered from the registry, so the core knows nothing of stock or brands and an
 * agent still learns their fields, types and allowed values the moment a satellite is installed.
 */
final class CatalogResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'catalog://facets',
                'Facets and sorts',
                'The facets products are filtered by — key, code in the address, kind, label, whether its first '
                .'level is indexed — and the sorts, by key. What catalog_products_list takes in facets and sort.',
                fn (): array => $this->facets(),
            ),
            new McpResource(
                'catalog://fields',
                'Units and switched-off fields',
                'The units of measure this site uses and whether price and barcode are on. A switched-off field '
                .'is not on the form and is refused — do not offer it.',
                fn (): array => $this->fields(),
            ),
            new McpResource(
                'catalog://addresses',
                'Addresses',
                'How products and categories get their addresses: flat category slugs, a product\'s slug with its id, '
                .'what happens to the address of an unpublished or a deleted product.',
                fn (): array => $this->addresses(),
            ),
            new McpResource(
                'catalog://categories',
                'Categories in brief',
                'Every live category: id, name, slug, parent, published — to file a product under the right one.',
                fn (): array => $this->categories(),
            ),
            new McpResource(
                'catalog://product-parts',
                'Parts of the product form',
                'The satellites\' shares of the product form: key, label, module and fields with type, rules and '
                .'allowed values. A field of a part is written as "<key>.<field>" in catalog_products_update.',
                fn (): array => $this->parts(),
            ),
            new McpResource(
                'catalog://bulk-actions',
                'Bulk actions',
                'What catalog_bulk can do, with the params each action asks for and the permission it needs.',
                fn (): array => ['actions' => $this->container->make(BulkActions::class)->describe()],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facets(): array
    {
        return [
            'facets' => array_map(static fn (Facet $facet): array => [
                'key' => $facet->key(),
                'code' => $facet->code(),
                'kind' => $facet->kind()->value,
                'label' => $facet->label(),
                'indexable' => $facet->indexable(),
            ], array_values($this->container->make(Facets::class)->all())),
            'sorts' => array_map(static fn (Sort $sort): array => ['key' => $sort->key(), 'label' => $sort->label()], array_values($this->container->make(Sorts::class)->all())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        $config = $this->config();
        $units = array_values(array_map('strval', (array) $config->get('webx-catalog.units', [])));

        return [
            'price' => (bool) $config->get('webx-catalog.price.enabled', true),
            'currency' => $config->get('webx-catalog.price.currency'),
            'barcode' => (bool) $config->get('webx-catalog.fields.barcode', true),
            'units' => array_map(static fn (string $unit): array => ['value' => $unit, 'label' => (string) __('webx-catalog::units.'.$unit)], $units),
            'default_unit' => (string) $config->get('webx-catalog.default_unit', 'pcs'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function addresses(): array
    {
        $root = $this->config()->get('webx-catalog.root.enabled', false) ? '/'.trim((string) $this->config()->get('webx-catalog.root.prefix', 'catalog'), '/').'/' : null;

        return [
            'category' => [
                'pattern' => '/{slug}/',
                'rules' => [
                    'Flat at any depth: moving a category does not change its address.',
                    'Chosen by hand and unique among pages and categories; a taken one is refused with who holds it.',
                    'No "_": it marks a filter segment in the address.',
                    'Made from the name when empty.',
                ],
            ],
            'product' => [
                'pattern' => '/{slug}-{id}',
                'rules' => [
                    'The slug need not be unique: the id makes the address unique. Made from the name when empty.',
                    'Any other spelling, an old slug included, redirects 301 to the canonical address.',
                    'Unpublished: the page answers 200, trimmed and noindex.',
                    'Deleted: 301 to its main category, or the first visible additional one; 410 without either.',
                ],
            ],
            'root' => $root,
            'filter' => 'A category address takes filter segments after it, each "<code>_<value>"; codes are in catalog://facets.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categories(): array
    {
        return [
            'categories' => Category::query()->orderBy('lft')->get()->map(static fn (Category $category): array => [
                'id' => (int) $category->getKey(),
                'name' => $category->displayName(),
                'slug' => $category->getTranslations('slug'),
                'parent_id' => $category->parent_id,
                'is_published' => (bool) $category->is_published,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parts(): array
    {
        $parts = [];

        foreach ($this->container->make(ProductParts::class)->all() as $key => $part) {
            $parts[] = ['key' => $key, ...$part->describe()->toArray()];
        }

        return ['parts' => $parts];
    }

    private function config(): Config
    {
        return $this->container->make('config');
    }
}
