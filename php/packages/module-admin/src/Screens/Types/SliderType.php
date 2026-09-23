<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-slider`: a number between `props.min` and `props.max` — 0 and 100 when the node does not
 * say, the component's own defaults. With `props.range` the value is a pair, `[from, to]`.
 */
final class SliderType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        [$min, $max] = self::bounds($node);

        if (! self::isRange($node)) {
            return ['nullable', 'numeric', 'min:'.$min, 'max:'.$max];
        }

        return ['nullable', 'array', 'list', 'size:2', static function (string $attribute, mixed $value, Closure $fail) use ($min, $max): void {
            $pair = is_array($value) ? $value : [];

            foreach ($pair as $end) {
                if (! is_numeric($end)) {
                    $fail('validation.numeric')->translate();

                    return;
                }

                if ($end < $min || $end > $max) {
                    $fail('validation.between.numeric')->translate(['min' => (string) $min, 'max' => (string) $max]);

                    return;
                }
            }

            if (count($pair) === 2 && $pair[0] > $pair[1]) {
                $fail('validation.in')->translate();
            }
        }];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (! self::isRange($node)) {
            return NumberType::cast($value);
        }

        if (! is_array($value) || count($value) !== 2) {
            return null;
        }

        [$from, $to] = array_values($value);
        $from = NumberType::cast($from);
        $to = NumberType::cast($to);

        return $from === null || $to === null ? null : [$from, $to];
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
     */
    private static function isRange(array $node): bool
    {
        return ($node['props']['range'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{int|float, int|float}
     */
    private static function bounds(array $node): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];

        return [
            NumberType::cast($props['min'] ?? null) ?? 0,
            NumberType::cast($props['max'] ?? null) ?? 100,
        ];
    }
}
