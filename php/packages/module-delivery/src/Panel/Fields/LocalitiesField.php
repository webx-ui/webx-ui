<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel\Fields;

use Closure;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Geography\Models\GeoLocality;

/** `wx-delivery-localities`: the localities the shop's courier goes to (§10.2), by id, in the order chosen. */
final class LocalitiesField implements FieldType
{
    /** A courier of a whole country is a method without a list, not a list of every town. */
    public const int MAX = 500;

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'array', 'max:'.self::MAX, static function (string $attribute, mixed $value, Closure $fail): void {
            foreach ((array) $value as $id) {
                if (! is_numeric($id)) {
                    $fail(__('validation.integer', ['attribute' => $attribute]));

                    return;
                }
            }

            $ids = array_values(array_unique(array_map('intval', (array) $value)));

            // One query for the whole list, not one per id as `exists` on `*` would make.
            if ($ids !== [] && GeoLocality::query()->whereKey($ids)->count() !== count($ids)) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<int>
     */
    public function store(mixed $value, array $node): array
    {
        return array_values(array_unique(array_map('intval', array_filter((array) $value, 'is_numeric'))));
    }

    /** @param  array<string, mixed>  $node */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
