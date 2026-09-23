<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-select`, `wx-radio-group`, `wx-segmented`: the value must be one of `props.options`.
 * Options are either `{ label, value }` objects or bare strings — both forms the components take.
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
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public static function values(array $node, string $prop = 'options'): array
    {
        $options = $node['props'][$prop] ?? null;

        if (! is_array($options)) {
            return [];
        }

        $values = [];

        foreach ($options as $option) {
            $values[] = is_array($option) ? ($option['value'] ?? null) : $option;
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
    }

    /**
     * A rule for a value that is one choice rather than several: a string, a number or a boolean.
     * Laravel has no such rule of its own, and `string` would refuse the numeric keys of a tree.
     */
    public static function single(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! is_scalar($value)) {
                $fail('validation.in')->translate();
            }
        };
    }

    /**
     * Whether `$value` is one of `$values`, compared the way `Rule::in` compares: as strings. A
     * value comes back from the browser as the JSON the option was written in, and `'2'` against
     * `2` is the same choice.
     *
     * @param  list<mixed>  $values
     */
    public static function holds(array $values, mixed $value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        foreach ($values as $one) {
            if (is_scalar($one) && (string) $one === (string) $value) {
                return true;
            }
        }

        return false;
    }
}
