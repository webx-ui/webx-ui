<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Http\Resources\ProductResource;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ProductForm;
use WebxUi\Localization\Locales;

/**
 * The products of the panel (§11.2): the list, one product as its editor opens it, a save, a
 * delete and a restore.
 *
 * The list is the database with its indexes for now; the engine takes it over with the facets
 * (decision 14 of the architecture), and the parameters stay the same. Sorting and filtering are
 * a white list — an arbitrary column name in a query string is refused, not obeyed.
 */
final class ProductController
{
    /** The sorts of the list. The engine's registry replaces this with the same keys. */
    public const SORTS = ['default', 'new', 'name', 'price_asc', 'price_desc'];

    public const STATES = ['published', 'unpublished', 'no-category'];

    public function __construct(private readonly ProductForm $form) {}

    /**
     * A page in Laravel's own paginator format, and beside it the number of products without a
     * main category — the filter "no category" shows it even when it is not on (decision 3).
     */
    public function index(Request $request, Locales $locales): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'state' => ['nullable', Rule::in(self::STATES)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Product::query()->with(['category', 'images', 'routes']);
        $term = trim((string) ($validated['q'] ?? ''));

        if ($term !== '') {
            $query->matching($term);
        }

        match ($validated['state'] ?? null) {
            'published' => $query->where('is_published', true),
            'unpublished' => $query->where('is_published', false),
            'no-category' => $query->whereNull('category_id'),
            default => null,
        };

        $this->sort($query, (string) ($validated['sort'] ?? 'default'), $locales->current());

        $page = $query->paginate((int) ($validated['per_page'] ?? 20));

        /** @var list<Product> $products */
        $products = $page->items();
        $visible = Product::query()->whereKey(array_map(static fn (Product $product): int => $product->id, $products))
            ->visible()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($products as $product) {
            $product->setAttribute('is_visible', in_array($product->id, $visible, true));
        }

        return new JsonResponse([
            ...$page->through(static fn (Product $product): array => (new ProductResource($product))->resolve($request))->toArray(),
            'counts' => ['no_category' => Product::query()->whereNull('category_id')->count()],
        ]);
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

    /**
     * @param  Builder<Product>  $query
     */
    private function sort(Builder $query, string $sort, string $locale): void
    {
        match ($sort) {
            'new' => $query->orderByDesc('created_at'),
            'name' => $query->orderByTranslation('name', 'asc', $locale),
            'price_asc' => $query->orderByRaw('price is null')->orderBy('price'),
            'price_desc' => $query->orderByRaw('price is null')->orderByDesc('price'),
            default => $query->orderByDesc('priority')->orderByDesc('created_at'),
        };

        $query->orderByDesc('id');
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
