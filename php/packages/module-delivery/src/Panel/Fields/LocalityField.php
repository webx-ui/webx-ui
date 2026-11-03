<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel\Fields;

use WebxUi\Admin\Screens\FieldType;

/** `wx-delivery-locality`: one locality of the shared geography, by id — the place of a point (§14.2). */
final class LocalityField implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'integer', 'exists:geo_localities,id'];
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
