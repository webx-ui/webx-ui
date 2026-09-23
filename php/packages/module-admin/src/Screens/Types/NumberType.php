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
        return self::cast($value);
    }

    /**
     * A number as a number: `'5'` becomes `5`, `'2.5'` becomes `2.5`. What is not a number at all
     * becomes null rather than the zero a cast would make of it — a block's values are kept
     * without rules, and a zero nobody typed reads as a value somebody chose.
     */
    public static function cast(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        return is_string($value) && (str_contains($value, '.') || stripos($value, 'e') !== false) || is_float($value)
            ? (float) $value
            : (int) $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
