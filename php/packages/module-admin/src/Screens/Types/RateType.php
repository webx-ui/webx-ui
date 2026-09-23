<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-rate`: stars, from 0 to `props.max` (5 by default). Whole stars unless `props.allowHalf`.
 * Zero is a value the component itself produces — a cleared rating — so it is kept as zero.
 */
final class RateType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $max = NumberType::cast($props['max'] ?? null) ?? 5;
        $step = ($props['allowHalf'] ?? false) === true ? 'multiple_of:0.5' : 'integer';

        return ['nullable', 'numeric', 'min:0', 'max:'.$max, $step];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return NumberType::cast($value);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
