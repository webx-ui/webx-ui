<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Http;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebxUi\Cache\CacheGroups;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\LiveCollections;

/**
 * A collection written from the panel: what the model cannot see (the facets, the sort, the
 * category, the hand-picked list) checked here, the rest by the model's own rules, so a refusal is
 * always 422 under its field. A save clears the mark of attention: the editor has looked (§3.5).
 */
final class CollectionForm
{
    /** The fields a write may carry; a partial update leaves the others as they are. */
    public const FIELDS = [
        'key', 'title', 'source', 'category_id', 'filters', 'on_sale', 'sort', 'only_purchasable',
        'limit', 'view', 'link', 'link_text', 'is_enabled',
    ];

    public function __construct(
        private readonly Facets $facets,
        private readonly Sorts $sorts,
        private readonly CacheGroups $groups,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(ProductCollection $collection, array $input): ProductCollection
    {
        $this->validate($input);
        $this->fill($collection, $input);
        $this->check($collection, $input);

        $collection->attention = null;

        DB::transaction(function () use ($collection, $input): void {
            $collection->save();

            if (array_key_exists('products', $input)) {
                $this->saveProducts($collection, array_map('intval', (array) $input['products']));
            }
        });

        return $collection->refresh();
    }

    /**
     * The values as a collection nobody saved, for the preview: what is being typed is what it
     * shows (§6.3).
     *
     * @param  array<string, mixed>  $input
     */
    public function draft(array $input): ProductCollection
    {
        $this->validate($input);

        $collection = new ProductCollection;
        $this->fill($collection, $input);
        // Not the list: a product gone since it was picked is what the preview is there to say.
        $this->check($collection, []);

        return $collection;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validate(array $input): void
    {
        Validator::make($input, [
            'category_id' => ['nullable', 'integer'],
            'filters' => ['nullable', 'array'],
            // `filled`: the validator skips `boolean` on a blank string, which then reaches the column.
            'on_sale' => ['sometimes', 'filled', 'boolean'],
            'only_purchasable' => ['sometimes', 'filled', 'boolean'],
            'is_enabled' => ['sometimes', 'filled', 'boolean'],
            'sort' => ['nullable', 'string', 'max:32'],
            'source' => ['sometimes', 'string', 'max:16'],
            'view' => ['sometimes', 'string', 'max:16'],
            'limit' => ['nullable', self::wholeNumber(...)],
            'title.*' => ['nullable', 'string'],
            'link_text.*' => ['nullable', 'string'],
            'products' => ['sometimes', 'nullable', 'array'],
            'products.*' => ['integer'],
        ])->validate();
    }

    /**
     * The shape only: the range is the model's, which asks it of a limit being changed. The model
     * cannot ask this one, because its cast has made `[1]` and `1.5` into a 1 by the time it looks.
     *
     * @param  Closure(string): mixed  $fail
     */
    private static function wholeNumber(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            $fail((string) __('webx-catalog-collections::errors.limit-range', ['max' => ProductCollection::maxLimit()]));
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fill(ProductCollection $collection, array $input): void
    {
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $collection->{$field} = $input[$field];
            }
        }
    }

    /**
     * The conditions are only read by a filter, so only a filter is held to them: a facet the site
     * has lost must not stop a hand-picked collection from being saved.
     *
     * @param  array<string, mixed>  $input
     */
    private function check(ProductCollection $collection, array $input): void
    {
        $errors = [];

        if ($collection->source === ProductCollection::FILTER) {
            $errors = $this->conditionErrors($collection);
        }

        $picked = array_values(array_unique(array_map('intval', (array) ($input['products'] ?? []))));
        $max = ProductCollection::maxLimit();

        if (count($picked) > $max) {
            $errors['products'][] = (string) __('webx-catalog-collections::errors.products-too-many', ['max' => $max]);
        } elseif ($picked !== []) {
            $known = Product::withTrashed()->whereKey($picked)->pluck('id')->map(intval(...))->all();

            foreach (array_diff($picked, $known) as $id) {
                $errors['products'][] = (string) __('webx-catalog-collections::errors.unknown-product', ['id' => $id]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function conditionErrors(ProductCollection $collection): array
    {
        $errors = [];

        if ($collection->category_id !== null && ! Category::withTrashed()->whereKey($collection->category_id)->exists()) {
            $errors['category_id'][] = (string) __('validation.exists', ['attribute' => 'category_id']);
        }

        foreach (array_keys((array) $collection->filters) as $key) {
            if ($key === CategoryFacet::KEY) {
                $errors['filters'][] = (string) __('webx-catalog-collections::errors.category-facet');
            } elseif ($this->facets->find((string) $key) === null) {
                $errors['filters'][] = (string) __('webx-catalog-collections::errors.unknown-facet', ['key' => (string) $key]);
            }
        }

        $sort = $collection->sort;

        if ($sort !== null && $sort !== Sorts::DEFAULT && $this->sorts->find($sort) === null) {
            $errors['sort'][] = (string) __('webx-catalog-collections::errors.unknown-sort', ['sort' => $sort]);
        }

        return $errors;
    }

    /**
     * The list has no model of its own, so a change of it alone is a save the group never hears.
     *
     * @param  list<int>  $ids  in their order
     */
    private function saveProducts(ProductCollection $collection, array $ids): void
    {
        $rows = [];

        foreach (array_values(array_unique($ids)) as $position => $id) {
            $rows[$id] = ['position' => $position + 1];
        }

        $collection->products()->sync($rows);
        $this->groups->bump(LiveCollections::GROUP);
    }
}
