<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens\Types;

use Closure;
use WebxUi\Admin\Screens\FieldType;

/**
 * `wx-tree-select`: the key of one node of `props.nodes`, or a list of keys with
 * `props.multiple`. Any node may be chosen, a branch as well as a leaf.
 *
 * Keys and children are read from the fields the node names — `props.nodeKey` (`id`) and
 * `props.childrenKey` (`children`) — the same ones the component reads. A `props.lazy` tree is
 * fetched by the page, so the server checks the shape and nothing else.
 */
final class TreeSelectType implements FieldType
{
    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $multiple = ($props['multiple'] ?? false) === true;
        $nodes = is_array($props['nodes'] ?? null) && ($props['lazy'] ?? false) !== true ? $props['nodes'] : [];
        $keyField = is_string($props['nodeKey'] ?? null) ? $props['nodeKey'] : 'id';
        $childrenField = is_string($props['childrenKey'] ?? null) ? $props['childrenKey'] : 'children';

        $keys = [];
        self::walk($nodes, $keyField, $childrenField, $keys);

        $shape = $multiple ? ['nullable', 'array', 'list'] : ['nullable', OptionType::single()];

        if ($keys === []) {
            return $shape;
        }

        $shape[] = static function (string $attribute, mixed $value, Closure $fail) use ($keys): void {
            foreach (is_array($value) ? $value : [$value] as $key) {
                if (! OptionType::holds($keys, $key)) {
                    $fail('validation.in')->translate();

                    return;
                }
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
            $keys = array_values(array_filter($value, 'is_scalar'));

            return $keys === [] ? null : $keys;
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
     * @param  array<array-key, mixed>  $nodes
     * @param  list<mixed>  $keys
     */
    private static function walk(array $nodes, string $keyField, string $childrenField, array &$keys): void
    {
        foreach ($nodes as $one) {
            if (! is_array($one)) {
                continue;
            }

            // The component falls back to `key` too, before it invents a key from the position.
            $key = $one[$keyField] ?? $one['key'] ?? null;

            if (is_scalar($key)) {
                $keys[] = $key;
            }

            if (is_array($one[$childrenField] ?? null)) {
                self::walk($one[$childrenField], $keyField, $childrenField, $keys);
            }
        }
    }
}
