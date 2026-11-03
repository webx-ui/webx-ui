<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel\Fields;

use WebxUi\Admin\Screens\FieldType;

/** `wx-delivery-region`: a region of the locality's country, by id; none — a locality without a region. */
final class RegionField implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'integer', 'exists:geo_regions,id'];
    }

    /** @param  array<string, mixed>  $node */
    public function store(mixed $value, array $node): mixed
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** @param  array<string, mixed>  $node */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
