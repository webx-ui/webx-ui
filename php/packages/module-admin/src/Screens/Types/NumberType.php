<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use WebxUi\Admin\Screens\FieldType;

/** `wx-input-number`. `props.min` and `props.max` become rules when the node sets them. */
final class NumberType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $rules = ['nullable', 'numeric'];
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];

        if (is_numeric($props['min'] ?? null)) {
            $rules[] = 'min:'.$props['min'];
        }

        if (is_numeric($props['max'] ?? null)) {
            $rules[] = 'max:'.$props['max'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && str_contains($value, '.') || is_float($value) ? (float) $value : (int) $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
