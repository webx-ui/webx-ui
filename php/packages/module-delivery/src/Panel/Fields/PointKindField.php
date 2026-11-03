<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel\Fields;

use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-delivery-point-kind`: the kind of points of a method — a select of the kinds the type
 * allows, and for the shop's pick-up «New kind of points» right under it (decision 9). Whether
 * the kind is one the type allows is the form's check, which knows the type.
 */
final class PointKindField implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string', 'max:32'];
    }

    /** @param  array<string, mixed>  $node */
    public function store(mixed $value, array $node): mixed
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param  array<string, mixed>  $node */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
