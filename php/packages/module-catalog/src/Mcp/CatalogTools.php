<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Catalog\Bulk\BulkRunner;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Http\Resources\CategoryResource;
use WebxUi\Catalog\Http\Resources\ProductResource;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\CategoryForm;
use WebxUi\Catalog\Panel\CategoryMover;
use WebxUi\Catalog\Panel\CategoryTree;
use WebxUi\Catalog\Panel\ChosenFacets;
use WebxUi\Catalog\Panel\ProductForm;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\Catalog\Storefront\Listing;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * What an agent can do with the catalogue (§12.1).
 *
 * The same doors the panel uses: `ProductForm` and `CategoryForm` check values against the
 * described screens and write every satellite's part in the same transaction, the engine answers
 * the list, `BulkRunner` does bulk actions. A product an agent wrote is a product the panel would
 * have accepted, with `mcp` in its history.
 *
 * Deleting is named `catalog.delete` explicitly: the default would have been `catalog.manage`,
 * and an editor's agent would delete products the editor cannot. `dry_run` of a write does the
 * write inside a transaction and takes it back, so the answer is what would really have happened
 * — the refusal included — rather than a guess at it.
 */
final class CatalogTools
{
    private const DELETE = 'catalog.delete';

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $product = ['type' => 'integer', 'description' => 'The product id, as catalog_products_list returns it.'];
        $category = ['type' => 'integer', 'description' => 'The category id, as catalog_categories_tree returns it.'];
        $values = [
            'type' => 'object',
            'description' => 'Field name → value, as catalog_products_get returns them. Text fields take one language as a string '
                .'or every language as { "en": "…" }. A satellite\'s field is "<part>.<field>" — read catalog://product-parts first.',
        ];
        $selection = [
            'type' => 'object',
            'description' => 'Which products: { "ids": [1, 2] }, or { "query": { "q", "state", "facets" } } — the same query as '
                .'catalog_products_list, turned into ids the moment the action starts.',
        ];

        return [
            Tool::read(
                'products_list',
                'Find products the way the panel\'s list does: a search over name, article number and barcode, the '
                .'facets of catalog://facets, a state (published, unpublished, no-category) and a sort. Every '
                .'product, on the site or not; with each page, what every facet counts for the list as filtered.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->list($arguments)),
                ['properties' => [
                    'q' => ['type' => 'string', 'description' => 'Words in the name, the article number or the barcode; an id.'],
                    'state' => ['type' => 'string', 'enum' => [CatalogQuery::STATE_PUBLISHED, CatalogQuery::STATE_UNPUBLISHED, CatalogQuery::STATE_NO_CATEGORY]],
                    'facets' => ['type' => 'object', 'description' => 'Facet key → chosen values: { "category": ["3"], "price": { "min": 100 } }.'],
                    'sort' => ['type' => 'string', 'description' => 'A sort key from catalog://facets; the default order when omitted.'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer', 'description' => 'At most 100; 20 when omitted.'],
                ]],
            ),

            Tool::read(
                'products_get',
                'One product in full: the record, the values of its editor with every satellite\'s part under '
                .'"<part>.<field>", its pictures and its address on the site.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->form()->describe($this->product($arguments))),
                ['properties' => ['product' => $product], 'required' => ['product']],
            ),

            Tool::read(
                'categories_tree',
                'Every category as a tree: name, slug, whether it is published and visible, how many live products '
                .'it holds with its subcategories, its address. The ids products are filed under.',
                fn (): array => ['categories' => $this->container->make(CategoryTree::class)->build()],
            ),

            Tool::mutating(
                'products_create',
                'Create a product from the values of its editor. It is not on the site until it is published, and '
                .'it cannot be published without a main category. The address is made from the name when no slug is given.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->form()->describe(
                    $this->form()->save(new Product, $this->values($arguments)),
                )),
                ['properties' => ['values' => $values], 'required' => ['values']],
            ),

            Tool::mutating(
                'products_update',
                'Change the values of a product, the satellites\' parts included. A field left out keeps what it had; '
                .'a language left out of a text keeps what it had.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->form()->describe(
                    $this->form()->save($this->product($arguments), $this->values($arguments)),
                )),
                ['properties' => ['product' => $product, 'values' => $values], 'required' => ['product', 'values']],
            ),

            Tool::mutating(
                'products_publish',
                'Put a product on the site. Refused without a main category. Ask a person first unless they asked you to publish.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->publication($arguments, true)),
                ['properties' => ['product' => $product], 'required' => ['product']],
            ),

            Tool::mutating(
                'products_unpublish',
                'Take a product off the site. Its page stays, trimmed and closed to search engines.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->publication($arguments, false)),
                ['properties' => ['product' => $product], 'required' => ['product']],
            ),

            Tool::mutating(
                'products_delete',
                'Move a product into «Deleted». Its address redirects to its category; catalog_products_restore brings it back.',
                fn (array $arguments): array => $this->write($arguments, function () use ($arguments): array {
                    $found = $this->product($arguments);
                    $found->delete();

                    return ['deleted' => $found->getKey()];
                }),
                ['properties' => ['product' => $product], 'required' => ['product']],
                permission: self::DELETE,
            ),

            Tool::mutating(
                'products_restore',
                'Bring a product back out of «Deleted». One whose main category is gone comes back without one, unpublished.',
                fn (array $arguments): array => $this->write($arguments, function () use ($arguments): array {
                    $found = Product::onlyTrashed()->find($this->id($arguments, 'product')) ?? throw new ToolFailure('There is no such product in «Deleted».');
                    $found->restore();

                    return ['product' => (new ProductResource($found->refresh()->load(['category', 'images', 'routes'])))->resolve()];
                }),
                ['properties' => ['product' => $product], 'required' => ['product']],
                permission: self::DELETE,
            ),

            Tool::mutating(
                'categories_create',
                'Create a category, at the end of its parent\'s children or of the top level.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->categoryForm()->describe(
                    $this->categoryForm()->create($this->values($arguments), isset($arguments['parent']) ? $this->category($arguments, 'parent')->id : null),
                )),
                ['properties' => [
                    'values' => ['type' => 'object', 'description' => 'name, slug, description (text fields: a string or { "en": "…" }), is_published, seo.'],
                    'parent' => $category + ['description' => 'The parent; the top level when omitted.'],
                ], 'required' => ['values']],
            ),

            Tool::mutating(
                'categories_update',
                'Change the values of a category. A slug is chosen by hand and a taken one is refused.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->categoryForm()->describe(
                    $this->categoryForm()->save($this->category($arguments, 'category'), $this->values($arguments)),
                )),
                ['properties' => ['category' => $category, 'values' => ['type' => 'object']], 'required' => ['category', 'values']],
            ),

            Tool::mutating(
                'categories_move',
                'Put a category under another parent or before a sibling. Addresses stay: slugs are flat.',
                fn (array $arguments): array => $this->write($arguments, function () use ($arguments): array {
                    $this->container->make(CategoryMover::class)->move(
                        $this->category($arguments, 'category'),
                        isset($arguments['parent']) ? $this->category($arguments, 'parent') : null,
                        isset($arguments['before']) ? $this->category($arguments, 'before') : null,
                    );

                    return ['categories' => $this->container->make(CategoryTree::class)->build()];
                }),
                ['properties' => [
                    'category' => $category,
                    'parent' => ['type' => 'integer', 'description' => 'The new parent; the top level when omitted.'],
                    'before' => ['type' => 'integer', 'description' => 'The sibling it goes before; last when omitted.'],
                ], 'required' => ['category']],
            ),

            Tool::mutating(
                'categories_delete',
                'Move an empty category into «Deleted». One with products or subcategories is refused with the numbers.',
                fn (array $arguments): array => $this->write($arguments, function () use ($arguments): array {
                    $found = $this->category($arguments, 'category');
                    $found->delete();

                    return ['deleted' => $found->getKey()];
                }),
                ['properties' => ['category' => $category], 'required' => ['category']],
                permission: self::DELETE,
            ),

            Tool::mutating(
                'categories_restore',
                'Bring a category back out of «Deleted», under its address, unless another one took it meanwhile.',
                fn (array $arguments): array => $this->write($arguments, function () use ($arguments): array {
                    $found = Category::onlyTrashed()->find($this->id($arguments, 'category')) ?? throw new ToolFailure('There is no such category in «Deleted».');
                    $found->restore();
                    $this->container->make(Catalog::class)->touchCategory($found);

                    return ['category' => (new CategoryResource($found->refresh()))->resolve()];
                }),
                ['properties' => ['category' => $category], 'required' => ['category']],
                permission: self::DELETE,
            ),

            Tool::mutating(
                'bulk',
                'Do one action to many products — catalog://bulk-actions lists them with what each asks. With dry_run, the '
                .'number of products it would touch and the first twenty; without, it runs: a few dozen at once, more in '
                .'the background, and the answer is the run to look at again. delete and restore need catalog.delete.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->bulk($arguments, $user)),
                ['properties' => [
                    'action' => ['type' => 'string', 'description' => 'The key of the action.'],
                    'params' => ['type' => 'object', 'description' => 'What the action asks for: { "category_id": 3 }.'],
                    'selection' => $selection,
                ], 'required' => ['action', 'selection']],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $sorts = $this->container->make(Sorts::class);
        $sort = is_string($arguments['sort'] ?? null) ? $arguments['sort'] : Sorts::DEFAULT;

        if ($sorts->find($sort) === null) {
            throw new ToolFailure("There is no sort [{$sort}]. Known: ".implode(', ', $sorts->keys()).'.');
        }

        $perPage = min(100, max(1, (int) ($arguments['per_page'] ?? 20)));
        $page = max(1, (int) ($arguments['page'] ?? 1));
        $state = $arguments['state'] ?? null;
        $locale = app()->getLocale();
        $facets = $this->container->make(Facets::class);

        $result = $this->container->make(Catalog::class)->engine()->search(new CatalogQuery(
            locale: $locale,
            context: 'panel',
            facets: $this->container->make(ChosenFacets::class)->read(is_array($arguments['facets'] ?? null) ? $arguments['facets'] : []),
            count: $facets->keys(),
            search: trim(is_string($arguments['q'] ?? null) ? $arguments['q'] : ''),
            sort: $sort,
            page: $page,
            perPage: $perPage,
            withUnpublished: true,
            state: is_string($state) ? $state : null,
        ));

        $products = $this->container->make(Listing::class)->products($result->ids);

        return [
            'total' => $result->total,
            'page' => $page,
            'per_page' => $perPage,
            'products' => $products->map(static fn (Product $product): array => (new ProductResource($product))->resolve())->values()->all(),
            'facets' => array_map(static fn ($facet): array => $facet->toArray(), $result->facets),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publication(array $arguments, bool $publish): array
    {
        return $this->form()->describe($this->form()->save($this->product($arguments), ['is_published' => $publish]));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function bulk(array $arguments, ?Authenticatable $user): array
    {
        $runner = $this->container->make(BulkRunner::class);
        $key = is_string($arguments['action'] ?? null) ? $arguments['action'] : '';
        $params = is_array($arguments['params'] ?? null) ? $arguments['params'] : [];
        $selection = is_array($arguments['selection'] ?? null) ? $arguments['selection'] : [];

        // The tool is behind `catalog.manage`; a delete or a restore is behind `catalog.delete`
        // as well, which only the action knows. Nobody to ask on the local stdio server.
        $can = $user === null ? null : static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);

        try {
            if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
                ['action' => $action, 'ids' => $ids] = $runner->prepare($key, $params, $selection, $can);

                $first = ($action->trashed() ? Product::onlyTrashed() : Product::query())
                    ->whereKey(array_slice($ids, 0, 20))->orderBy('id')->get()
                    ->map(static fn (Product $product): array => ['id' => $product->id, 'name' => $product->displayName(), 'sku' => $product->sku])
                    ->all();

                return ['dry_run' => true, 'action' => $action->key(), 'would_touch' => count($ids), 'first' => $first];
            }

            $run = $runner->start($key, $params, $selection, $user, $can);
        } catch (AccessDeniedHttpException $denied) {
            throw new ToolFailure($denied->getMessage());
        }

        return ['run' => $run->toResponse($runner->labelOf($run))];
    }

    /**
     * A write, or with `dry_run` the same write taken back: every check, the parts' included,
     * runs for real, and nothing stays.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function write(array $arguments, Closure $work): array
    {
        if (! (bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return $this->attempt(static fn (): array => DB::transaction($work));
        }

        return $this->attempt(static function () use ($work): array {
            DB::beginTransaction();

            try {
                return ['dry_run' => true, 'would_be' => $work()];
            } finally {
                DB::rollBack();
            }
        });
    }

    /**
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(Closure $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }

    /**
     * The values, with a satellite's field checked against the registered parts first: a part
     * nobody registered is an error that says which there are (§12.2), not a value dropped.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function values(array $arguments): array
    {
        $values = $arguments['values'] ?? null;

        if (! is_array($values)) {
            throw new ToolFailure('values is an object of field name → value.');
        }

        $parts = $this->container->make(ProductParts::class)->all();

        foreach (array_keys($values) as $name) {
            $prefix = explode('.', (string) $name, 2);

            if (count($prefix) === 2 && $prefix[0] !== 'seo' && ! isset($parts[$prefix[0]])) {
                throw new ToolFailure(sprintf(
                    'There is no part [%s] of the product form. Known parts: %s — see catalog://product-parts.',
                    $prefix[0],
                    $parts === [] ? 'none' : implode(', ', array_keys($parts)),
                ));
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function product(array $arguments): Product
    {
        return Product::query()->find($this->id($arguments, 'product')) ?? throw new ToolFailure('There is no such product; deleted ones are in «Deleted».');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function category(array $arguments, string $key): Category
    {
        return Category::query()->find($this->id($arguments, $key)) ?? throw new ToolFailure("There is no such category [{$key}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function id(array $arguments, string $key): int
    {
        $id = $arguments[$key] ?? null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : throw new ToolFailure("{$key} is an id.");
    }

    private function form(): ProductForm
    {
        return $this->container->make(ProductForm::class);
    }

    private function categoryForm(): CategoryForm
    {
        return $this->container->make(CategoryForm::class);
    }
}
