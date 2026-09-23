<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * Several of a fixed set: `wx-checkbox-group` (`props.options`) and `wx-transfer` (`props.items`).
 * The value is the list of chosen option values, in the order the component gave them.
 *
 * `props.min` and `props.max` count the choices. They are the server's to enforce: a browser
 * reads `required` on each box of a group separately, so the number of ticks that is enough is
 * something only this side can say.
 */
final class OptionListType implements FieldType
{
    public function __construct(private readonly string $optionsProp = 'options') {}

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $rules = ['nullable', 'array', 'list'];
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];

        if (is_numeric($props['min'] ?? null)) {
            $rules[] = 'min:'.(int) $props['min'];
        }

        if (is_numeric($props['max'] ?? null)) {
            $rules[] = 'max:'.(int) $props['max'];
        }

        $values = OptionType::values($node, $this->optionsProp);

        // Options the node does not list are options it fetches, and those the server cannot check.
        if ($values !== []) {
            $rules[] = static function (string $attribute, mixed $value, Closure $fail) use ($values): void {
                foreach (is_array($value) ? $value : [] as $one) {
                    if (! OptionType::holds($values, $one)) {
                        $fail('validation.in')->translate();

                        return;
                    }
                }
            };
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $chosen = array_values(array_filter($value, static fn (mixed $one): bool => is_scalar($one)));

        return $chosen === [] ? null : $chosen;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }
}
