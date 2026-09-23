<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-cascader`: a place in the tree of `props.options` (`{ value, label, children }`).
 *
 * The value is the whole path of values from the root — `['content', 'news']` — the way the
 * component writes it; with `props.emitPath: false` it is the last value alone. Only a leaf
 * may be chosen unless `props.checkStrictly`, again as the component allows. A `props.lazy`
 * tree is fetched by the page, so the server checks the shape and nothing else.
 */
final class CascaderType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $emitPath = ($props['emitPath'] ?? true) !== false;
        $strict = ($props['checkStrictly'] ?? false) === true;
        $options = is_array($props['options'] ?? null) && ($props['lazy'] ?? false) !== true ? $props['options'] : [];

        // Every path that may be chosen. A path is compared whole: `news` under `shop` is not
        // `news` under `content`.
        $paths = [];
        self::walk($options, [], $strict, $paths);

        $shape = $emitPath ? ['nullable', 'array', 'list'] : ['nullable', OptionType::single()];

        if ($paths === []) {
            return $shape;
        }

        $shape[] = static function (string $attribute, mixed $value, Closure $fail) use ($paths, $emitPath): void {
            if ($value === [] || $value === null || $value === '') {
                return;
            }

            $found = false;

            foreach ($paths as $path) {
                $found = $emitPath ? self::samePath($path, (array) $value) : OptionType::holds([end($path)], $value);

                if ($found) {
                    break;
                }
            }

            if (! $found) {
                $fail('validation.in')->translate();
            }
        };

        return $shape;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): mixed
    {
        if (is_array($value)) {
            $path = array_values(array_filter($value, 'is_scalar'));

            return $path === [] ? null : $path;
        }

        return is_scalar($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): mixed
    {
        return $stored;
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @param  list<mixed>  $above
     * @param  list<list<mixed>>  $paths
     */
    private static function walk(array $options, array $above, bool $strict, array &$paths): void
    {
        foreach ($options as $option) {
            if (! is_array($option) || ! array_key_exists('value', $option)) {
                continue;
            }

            $path = [...$above, $option['value']];
            $children = is_array($option['children'] ?? null) ? $option['children'] : [];

            if ($strict || $children === []) {
                $paths[] = $path;
            }

            self::walk($children, $path, $strict, $paths);
        }
    }

    /**
     * @param  list<mixed>  $path
     * @param  array<array-key, mixed>  $value
     */
    private static function samePath(array $path, array $value): bool
    {
        $value = array_values($value);

        if (count($path) !== count($value)) {
            return false;
        }

        foreach ($path as $index => $step) {
            if (! OptionType::holds([$step], $value[$index])) {
                return false;
            }
        }

        return true;
    }
}
