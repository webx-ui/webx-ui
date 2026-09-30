<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Catalog;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Localization\Locales;

/**
 * The reference book of a property written (§3.3, §7.3 of the properties spec): creating, editing
 * and moving a value, and deleting one no product holds. The panel's API and the agent's tools both
 * come through here, so a refusal reads the same from either side; merging is {@see ValueMerger}.
 */
final class ValueBook
{
    /** The fields of a value, as the API and the tools take them. */
    public const RULES = [
        'title' => ['sometimes', 'nullable'],
        'slug' => ['sometimes', 'nullable'],
        'parent_id' => ['sometimes', 'nullable', 'integer'],
        'color' => ['sometimes', 'nullable', 'string', 'max:7'],
        'image_id' => ['sometimes', 'nullable', 'integer', 'exists:media_files,id'],
    ];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Locales $locales,
    ) {}

    /**
     * A value of this property by id, or by its slug in any language — the one an agent read off an
     * address.
     */
    public function find(Property $owner, int|string $key): ?PropertyValue
    {
        $query = PropertyValue::query()->where('property_id', $owner->id);

        if (is_int($key) || ctype_digit($key)) {
            return $query->find((int) $key);
        }

        // Nothing but a slug's own letters reaches `like`, where `_` and `%` would mean any letter.
        $key = strtolower(trim($key));

        return preg_match(Property::CODE, $key) === 1 ? $query->whereTranslationLikeAny('slug', $key)->first() : null;
    }

    /**
     * The values of a property with the number of products each holds, in the book's order:
     * «alphabetical» in the alphabet of the current language, «by hand» in the tree's.
     *
     * @return Builder<PropertyValue>
     */
    public function listing(Property $owner): Builder
    {
        $query = PropertyValue::query()->where('property_id', $owner->id)->with('image');
        $query->addSelect(['catalog_property_values.*', 'products_count' => DB::table(ProductValues::TABLE)
            ->selectRaw('count(distinct product_id)')
            ->whereColumn('value_id', 'catalog_property_values.id')]);

        return $owner->value_order === Property::MANUAL
            ? $query->orderBy('lft')
            : $query->orderByTranslation('title', 'asc', $this->locales->current())->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $fields  checked by {@see RULES}
     *
     * @throws ValidationException
     */
    public function create(Property $owner, array $fields): PropertyValue
    {
        if (! $owner->isSelect()) {
            throw ValidationException::withMessages(['property' => [(string) __('webx-catalog-properties::errors.values-select')]]);
        }

        $parent = isset($fields['parent_id']) ? $this->node($owner, (int) $fields['parent_id']) : null;

        if ($parent !== null && ! $owner->is_tree) {
            throw ValidationException::withMessages(['parent_id' => [(string) __('webx-catalog-properties::errors.not-tree')]]);
        }

        $value = new PropertyValue(['property_id' => $owner->id]);
        $this->fill($value, $fields);

        DB::transaction(static function () use ($value, $parent): void {
            $parent === null ? $value->saveAsRoot() : $value->appendTo($parent);
        });

        return $value->refresh();
    }

    /**
     * @param  array<string, mixed>  $fields  checked by {@see RULES}; `parent_id` is {@see move()}'s
     */
    public function update(PropertyValue $value, array $fields): PropertyValue
    {
        $this->fill($value, $fields);
        $value->save();

        return $value->refresh();
    }

    /**
     * Put a value under `$parent` (null — the top), before `$before` (null — last).
     *
     * @throws ValidationException
     */
    public function move(Property $owner, PropertyValue $value, ?int $parent, ?int $before): PropertyValue
    {
        $under = $parent === null ? null : $this->node($owner, $parent);
        $next = $before === null ? null : $this->node($owner, $before);

        if ($under !== null && ! $owner->is_tree) {
            throw ValidationException::withMessages(['parent_id' => [(string) __('webx-catalog-properties::errors.not-tree')]]);
        }

        if ($under !== null && $under->lft >= $value->lft && $under->rgt <= $value->rgt) {
            throw ValidationException::withMessages(['parent_id' => [(string) __('webx-catalog-properties::errors.move-into-itself')]]);
        }

        DB::transaction(static function () use ($value, $under, $next): void {
            if ($next !== null && $next->id !== $value->id) {
                $value->insertBefore($next);
            } elseif ($under !== null) {
                $value->appendTo($under);
            } else {
                $value->saveAsRoot();
            }
        });

        // A value moved under another is found under it: its products' documents list ancestors.
        $this->catalog->touchQuery($value->affectedProducts());

        return $value->refresh();
    }

    /**
     * Delete a value no product holds. With products behind it nothing happens and the number is
     * the answer — the caller says «merge it» in its own way.
     *
     * @return int the products that hold the value, zero when it is gone
     */
    public function delete(PropertyValue $value): int
    {
        $count = $value->productCount();

        if ($count === 0) {
            $value->delete();
        }

        return $count;
    }

    /**
     * @throws ValidationException
     */
    public function node(Property $owner, int $id): PropertyValue
    {
        return PropertyValue::query()->where('property_id', $owner->id)->find($id)
            ?? throw ValidationException::withMessages(['value' => [(string) __('webx-catalog-properties::errors.unknown-value')]]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function fill(PropertyValue $value, array $fields): void
    {
        foreach (['title', 'slug'] as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            // Languages laid over those there; one sent empty is emptied, which for a slug means
            // «make it again from the name».
            $merged = $value->getTranslations($field);
            $sent = is_array($fields[$field]) ? $fields[$field] : [$this->locales->current() => $fields[$field]];

            foreach ($sent as $locale => $words) {
                if (is_string($words) && trim($words) !== '') {
                    $merged[(string) $locale] = trim($words);
                } else {
                    unset($merged[(string) $locale]);
                }
            }

            $value->setTranslations($field, $merged);
        }

        foreach (['color', 'image_id'] as $field) {
            if (array_key_exists($field, $fields)) {
                $value->setAttribute($field, $fields[$field] === '' ? null : $fields[$field]);
            }
        }
    }
}
