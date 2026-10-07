<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Http\Resources\ImageResource;
use WebxUi\Catalog\Http\Resources\ProductResource;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Parts\ProductParts;
use WebxUi\Localization\Locales;
use WebxUi\Seo\Fields;

/**
 * The product editor on the server side: what its fields hold, and what a save writes (§7.4,
 * §11.2).
 *
 * The screen is `catalog.product-form`, keyed by field name. The core's own fields are columns of
 * the product; the additional categories are a pivot; the SEO card is `module-seo`'s; everything
 * else on the screen belongs to a satellite's part, named `<part>.<field>`. A save is one
 * transaction: the product, its categories, its card, every part that was sent, one journal row
 * with all of their changes together, and one mark for the index. Any part that throws takes it
 * all back — a product saved without its stock status is a product the site sells wrongly.
 *
 * The panel's door and an agent's go through the same `save()`.
 */
final class ProductForm
{
    /** The product's own columns on the screen. */
    private const OWN = [
        'name', 'slug', 'sku', 'barcode', 'summary', 'description', 'category_id',
        'price', 'old_price', 'unit', 'priority', 'is_published',
    ];

    /** Fields stored somewhere other than a column: the pivot and the SEO card. */
    private const TAKEN = ['categories', Fields::SCREEN];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly ProductParts $parts,
        private readonly Catalog $catalog,
        private readonly Locales $locales,
    ) {}

    /**
     * A product and everything its editor needs around it: the record, the values of the screen
     * with every part's, the gallery, and where it is on the site — unpublished too, which is the
     * trimmed page (§11.1).
     *
     * @return array<string, mixed>
     */
    public function describe(Product $product): array
    {
        $product->loadMissing(['routes', 'category', 'images']);

        return [
            'product' => new ProductResource($product),
            'values' => $this->values($product),
            'images' => ImageResource::collection($product->images),
        ];
    }

    /**
     * What the form opens with.
     *
     * @return array<string, mixed>
     */
    public function values(Product $product): array
    {
        $values = [
            'name' => $product->getTranslations('name'),
            'slug' => $product->getTranslations('slug'),
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'summary' => $product->getTranslations('summary'),
            'description' => $product->getTranslations('description'),
            'category_id' => $product->category_id,
            'categories' => $this->extraCategoryIds($product),
            'price' => self::number($product->price),
            'old_price' => self::number($product->old_price),
            'unit' => $product->unit,
            'priority' => $product->priority,
            'is_published' => $product->is_published,
            Fields::SCREEN => $product->seoValue(),
        ];

        foreach ($this->parts->all() as $key => $part) {
            $read = $part->read(new Collection([$product]))[$product->getKey()] ?? [];

            foreach ($read as $field => $value) {
                $values[$key.'.'.$field] = $value;
            }
        }

        return $values;
    }

    /**
     * Check what came in against the screen and the parts, and write it all in one transaction.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Product $product, array $input, ?callable $can = null): Product
    {
        $split = $this->record->split(Product::SCREEN, $input, self::OWN, self::TAKEN, $can);
        $shares = $this->parts->split($split->extra);

        $this->validateShares($shares);

        $own = $split->own;
        $taken = $split->taken;

        DB::transaction(function () use ($product, $own, $taken, $shares): void {
            $created = ! $product->exists;
            $wasPublished = (bool) $product->getOriginal('is_published');

            $this->fill($product, $own);
            $this->assertNamed($product);
            $product->save();

            if ($created) {
                // What the insert left to the column defaults is not in the model until it is read
                // back, and the first edit would journal it as a change (docs/pitfalls).
                $product->refresh();
            }

            $changes = $created ? [] : $product->takeHistoryChanges();

            if (array_key_exists('categories', $taken)) {
                $changes = [...$changes, ...$this->syncCategories($product, $taken['categories'])];
            } elseif (array_key_exists('category_id', $own)) {
                // A new main category that was an additional one stops being additional (decision 2).
                $product->categories()->detach($product->category_id);
            }

            if (array_key_exists(Fields::SCREEN, $taken)) {
                $value = $taken[Fields::SCREEN];
                $product->saveSeo(is_array($value) ? $value : null);
            }

            foreach ($shares as $key => $share) {
                $part = $this->parts->find($key);

                if ($part !== null) {
                    $changes = [...$changes, ...$part->write($product, $share)];
                }
            }

            $this->catalog->touch([$product->getKey()]);

            if ($created) {
                return;
            }

            $published = $product->is_published;
            $event = match (true) {
                $published && ! $wasPublished => HistoryEntry::PUBLISHED,
                ! $published && $wasPublished => HistoryEntry::UNPUBLISHED,
                default => HistoryEntry::UPDATED,
            };

            $product->recordHistory($event, $changes);
        });

        return $product->refresh();
    }

    /**
     * @param  array<string, mixed>  $own
     */
    private function fill(Product $product, array $own): void
    {
        foreach ($own as $field => $value) {
            if ($product->isTranslatableAttribute($field)) {
                $this->fillTranslated($product, $field, $value);

                continue;
            }

            $product->setAttribute($field, match ($field) {
                'is_published' => (bool) $value,
                'priority' => (int) ($value ?? 0),
                default => $value,
            });
        }
    }

    /**
     * A map of languages is laid over what is there, language by language: the form sends every
     * language, an agent the ones it speaks, and neither means to delete the rest. A bare string
     * goes into the language the request is in.
     */
    private function fillTranslated(Product $product, string $field, mixed $value): void
    {
        if (is_array($value)) {
            $product->setTranslations($field, [...$product->getTranslations($field), ...$value]);

            return;
        }

        $product->setTranslation($field, $this->locales->content(), $value);
    }

    /** A product with no name in any language is a row nobody can find again. */
    private function assertNamed(Product $product): void
    {
        foreach ($product->getTranslations('name') as $name) {
            if (is_string($name) && trim($name) !== '') {
                return;
            }
        }

        throw ValidationException::withMessages(['name' => [(string) __('webx-catalog::errors.name-required')]]);
    }

    /**
     * The additional categories, the main one left out of them (decision 2).
     *
     * @return list<array{field: string, from: mixed, to: mixed}>
     */
    private function syncCategories(Product $product, mixed $value): array
    {
        $before = $this->extraCategoryIds($product);
        $ids = array_values(array_filter(
            is_array($value) ? array_map(intval(...), $value) : [],
            static fn (int $id): bool => $id !== $product->category_id,
        ));

        $product->categories()->sync($ids);

        $after = $this->extraCategoryIds($product);

        if ($before === $after) {
            return [];
        }

        return [['field' => 'categories', 'from' => $this->names($before), 'to' => $this->names($after)]];
    }

    /**
     * @return list<int>
     */
    private function extraCategoryIds(Product $product): array
    {
        if (! $product->exists) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = $product->categories()->orderBy('catalog_categories.id')->pluck('catalog_categories.id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    private function names(array $ids): array
    {
        return Category::withTrashed()->whereKey($ids)->orderBy('id')->get()
            ->map(static fn (Category $category): string => $category->displayName())
            ->values()
            ->all();
    }

    /**
     * Every part's own rules, on top of what the screen checked, with the errors under the names
     * the form knows the fields by.
     *
     * @param  array<string, array<string, mixed>>  $shares
     *
     * @throws ValidationException
     */
    private function validateShares(array $shares): void
    {
        $errors = [];

        foreach ($shares as $key => $share) {
            $part = $this->parts->find($key);
            $rules = array_intersect_key($part?->rules() ?? [], $share);

            if ($rules === []) {
                continue;
            }

            $validator = Validator::make($share, $rules);

            if (! $validator->fails()) {
                continue;
            }

            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[$key.'.'.$field] = $messages;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function number(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
