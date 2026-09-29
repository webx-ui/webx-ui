<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\FacetResult;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Http\Resources\ProductResource;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ChosenFacets;
use WebxUi\Catalog\Panel\ProductColumns;
use WebxUi\Catalog\Panel\ProductForm;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\Catalog\Storefront\Listing;
use WebxUi\Localization\Locales;

/**
 * The products of the panel (§11.2): the list, one product as its editor opens it, a save, a
 * delete and a restore.
 *
 * The list is the engine's, like the storefront's (decision 14 of the architecture): on
 * `SqlEngine` that is the database with its indexes, on Manticore the index — the same query
 * either way. Sorting and filtering are a white list — an arbitrary column name in a query string
 * is refused, not obeyed.
 */
final class ProductController
{
    public const STATES = ['published', 'unpublished', 'no-category'];

    public function __construct(private readonly ProductForm $form) {}

    /**
     * A page in Laravel's own paginator format, found by the engine (decision 14 of the
     * architecture) — on `SqlEngine` the same database — and beside it:
     *
     * - `counts.no_category`, the products without a main category, which the filter "no
     *   category" shows even when it is not on (decision 3);
     * - `facets`, what each facet counts for the list as filtered, a facet's own choice aside;
     * - `columns`, the satellites' columns (§7.4), whose values each row carries under `columns`.
     *
     * The facets come as `facets[category][]=3`, `facets[price][min]=100` — a key of the registry
     * or nothing. Sorting and filtering are a white list: an arbitrary name is refused, not obeyed.
     */
    public function index(Request $request, Locales $locales, Catalog $catalog, Facets $facets, ChosenFacets $chosenFacets, Sorts $sorts, ProductColumns $columns, Listing $listing): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'state' => ['nullable', Rule::in(self::STATES)],
            'sort' => ['nullable', Rule::in($sorts->keys())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'facets' => ['nullable', 'array'],
            'facets.*' => ['array'],
        ]);

        $locale = $locales->current();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);
        $chosen = $chosenFacets->read((array) ($validated['facets'] ?? []));

        $result = $catalog->engine()->search(new CatalogQuery(
            locale: $locale,
            context: 'panel',
            facets: $chosen,
            count: $facets->keys(),
            search: trim((string) ($validated['q'] ?? '')),
            sort: (string) ($validated['sort'] ?? Sorts::DEFAULT),
            page: $page,
            perPage: $perPage,
            withUnpublished: true,
            state: $validated['state'] ?? null,
        ));

        $products = $listing->products($result->ids);
        $visible = Product::query()->whereKey($result->ids)->visible()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $extra = $columns->values($products);

        foreach ($products as $product) {
            $product->setAttribute('is_visible', in_array($product->id, $visible, true));
        }

        $paginator = new LengthAwarePaginator($products, $result->total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return new JsonResponse([
            ...$paginator->through(static fn (Product $product): array => [
                ...(new ProductResource($product))->resolve($request),
                'columns' => $extra[(int) $product->id] ?? [],
            ])->toArray(),
            'counts' => ['no_category' => Product::query()->whereNull('category_id')->count()],
            'facets' => $this->counted($facets, $result->facets, $locale),
            'columns' => $columns->describe(),
        ]);
    }

    /**
     * Each facet's counts with the words for its values, so the panel draws a filter without a
     * second request per facet.
     *
     * @param  array<string, FacetResult>  $results
     * @return array<string, array<string, mixed>>
     */
    private function counted(Facets $facets, array $results, string $locale): array
    {
        $counted = [];

        foreach ($results as $key => $result) {
            $facet = $facets->find($key);

            if ($facet === null) {
                continue;
            }

            if ($result->kind === FacetKind::Range || $result->kind === FacetKind::Toggle) {
                $counted[$key] = $result->toArray();

                continue;
            }

            $labels = $facet->labels(array_map('strval', array_keys($result->counts)), $locale);
            $values = [];

            foreach ($result->counts as $value => $count) {
                $values[] = ['value' => (string) $value, 'label' => $labels[(string) $value] ?? (string) $value, 'count' => $count];
            }

            $counted[$key] = ['key' => $key, 'kind' => $result->kind->value, 'values' => $values];
        }

        return $counted;
    }

    public function show(int $product): JsonResponse
    {
        return ApiResponse::data($this->form->describe($this->find($product)));
    }

    /** A new product from the same values the editor saves: every door, one path. */
    public function store(Request $request): JsonResponse
    {
        $product = $this->form->save(new Product, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($product), 201);
    }

    public function update(Request $request, int $product): JsonResponse
    {
        $saved = $this->form->save($this->find($product), $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($saved));
    }

    /**
     * Into «Deleted» (§5): a soft delete for ever. The address stops being the registry's and
     * starts answering 301 or 410; the pictures stay on the disk, since a restore brings them back.
     */
    public function destroy(int $product, Catalog $catalog): JsonResponse
    {
        $found = $this->find($product);

        DB::transaction(static function () use ($found, $catalog): void {
            $found->delete();
            $catalog->touch([$found->id]);
        });

        return ApiResponse::noContent();
    }

    /**
     * Back out of «Deleted», under its address. A product whose main category is gone comes back
     * without one and unpublished (§6.3).
     */
    public function restore(int $product, Catalog $catalog): JsonResponse
    {
        $found = Product::onlyTrashed()->find($product) ?? throw new NotFoundHttpException;

        DB::transaction(static function () use ($found, $catalog): void {
            $found->restore();
            $catalog->touch([$found->id]);
        });

        return ApiResponse::data(new ProductResource($found->refresh()->load(['category', 'images', 'routes'])));
    }

    private function find(int $id): Product
    {
        return Product::query()->find($id) ?? throw new NotFoundHttpException;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
