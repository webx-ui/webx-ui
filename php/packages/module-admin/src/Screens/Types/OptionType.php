<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Illuminate\Validation\Rule;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-select`, `wx-radio-group`: the value must be one of `props.options`. Options are either
 * `{ label, value }` objects or bare strings — both forms the components take.
 */
final class OptionType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $values = self::values($node);

        return $values === [] ? ['nullable'] : ['nullable', Rule::in($values)];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        return $value;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node): mixed
    {
        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public static function values(array $node): array
    {
        $options = $node['props']['options'] ?? null;

        if (! is_array($options)) {
            return [];
        }

        $values = [];

        foreach ($options as $option) {
            $values[] = is_array($option) ? ($option['value'] ?? null) : $option;
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
    }
}
