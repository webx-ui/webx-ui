<?php

declare(strict_types=1);

namespace WebxUi\Themes\Blocks;

/**
 * Whether a block schema can stand in for another one (spec §9.2, rule 16): every field of the
 * lower schema is in the upper one, under the same key and of the same type. New fields are
 * fine — content written for the lower type simply has no value for them yet. A field the upper
 * one does not describe is not lost: {@see inherit()} brings it over before the two are compared,
 * so of the two problems only a retype reaches a theme.
 *
 * A field's key is its `id`, under the ids of the fields it sits in (`numbers.value` inside a
 * repeater). Layout nodes are not fields even with an id — a row or a tab names nothing in the
 * values, its fields are keyed as if it were not there — the same list as `WebxUi\Blocks\Schema::LAYOUT`,
 * which a test of `module-blocks` keeps equal. `localized` is not compared: values are read by
 * their shape, and a field that becomes translatable keeps what it had (pitfalls, «Флаг
 * `localized`»).
 */
final class SchemaCompatibility
{
    public const LAYOUT = ['wx-card', 'wx-tabs', 'wx-tab', 'wx-row', 'wx-col', 'wx-divider', 'wx-heading', 'wx-text', 'wx-alert'];

    /**
     * @param  list<mixed>  $lower
     * @param  list<mixed>  $upper
     * @return list<string>
     */
    public static function problems(array $lower, array $upper): array
    {
        $before = self::fields($lower);
        $after = self::fields($upper);
        $problems = [];

        foreach ($before as $key => $type) {
            if (! array_key_exists($key, $after)) {
                $problems[] = "field \"{$key}\" ({$type}) is gone";
            } elseif ($after[$key] !== $type) {
                $problems[] = "field \"{$key}\" was {$type}, is {$after[$key]}";
            }
        }

        return $problems;
    }

    /**
     * The upper schema with every field of the lower one it does not describe (rule 16): a field a
     * package adds to its type in a minor release reaches each site whose theme overrides the type,
     * instead of failing it. The field comes over whole — label, `default`, its own children — after
     * the field it follows in the lower schema, or at the end of its list when nothing before it is
     * there. A field the upper schema describes is the upper one's, whatever the lower says; under a
     * field it retyped nothing is brought, the retype is the finding.
     *
     * @param  list<mixed>  $lower
     * @param  list<mixed>  $upper
     * @return array{schema: list<mixed>, inherited: list<string>}
     */
    public static function inherit(array $lower, array $upper): array
    {
        $before = self::fields($lower);
        $after = self::fields($upper);
        $inherited = [];
        $previous = [];

        foreach (self::nodes($lower) as $key => [$node, $scope]) {
            $parent = $scope === '' ? null : substr($scope, 0, -1);
            $follows = $previous[$scope] ?? null;
            $previous[$scope] = $key;

            if (array_key_exists($key, $after)) {
                continue;
            }

            // Brought along with its parent, or under a parent the upper schema retyped.
            if ($parent !== null && (in_array($parent, $inherited, true) || ($after[$parent] ?? null) !== $before[$parent])) {
                continue;
            }

            $placed = false;
            $upper = self::place($upper, '', $scope, $follows === null ? null : substr($follows, strlen($scope)), $node, $placed);

            if (! $placed) {
                $upper = self::place($upper, '', $scope, null, $node, $placed);
            }

            $inherited[] = $key;
            $after = self::fields($upper);
        }

        return ['schema' => array_values($upper), 'inherited' => $inherited];
    }

    /**
     * Key → [node, the key prefix of the list it is in] of every field, in the order of the schema.
     *
     * @param  array<mixed>  $nodes
     * @return array<string, array{0: array<mixed>, 1: string}>
     */
    private static function nodes(array $nodes, string $prefix = ''): array
    {
        $found = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = is_string($node['type'] ?? null) ? $node['type'] : '';
            $id = is_string($node['id'] ?? null) && $node['id'] !== '' ? $node['id'] : null;
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            if ($id === null || in_array($type, self::LAYOUT, true)) {
                $found += self::nodes($children, $prefix);

                continue;
            }

            $found[$prefix.$id] = [$node, $prefix];
            $found += self::nodes($children, $prefix.$id.'.');
        }

        return $found;
    }

    /**
     * Puts the node into the list of `$scope` right after the field `$follows`, wherever a layout
     * node holds it; with no `$follows`, at the end of the scope's own list — the schema's, or the
     * children of the field the scope names — not inside a row of it.
     *
     * @param  array<mixed>  $nodes
     * @param  array<mixed>  $node
     * @return array<mixed>
     */
    private static function place(array $nodes, string $prefix, string $scope, ?string $follows, array $node, bool &$placed, bool $own = true): array
    {
        $out = [];

        foreach ($nodes as $child) {
            $out[] = $child;

            if ($placed || ! is_array($child)) {
                continue;
            }

            $type = is_string($child['type'] ?? null) ? $child['type'] : '';
            $id = is_string($child['id'] ?? null) && $child['id'] !== '' ? $child['id'] : null;
            $layout = $id === null || in_array($type, self::LAYOUT, true);

            if (! $layout && $prefix === $scope && $follows !== null && $id === $follows) {
                $out[] = $node;
                $placed = true;

                continue;
            }

            if (is_array($child['children'] ?? null)) {
                $child['children'] = $layout
                    ? self::place($child['children'], $prefix, $scope, $follows, $node, $placed, false)
                    : self::place($child['children'], $prefix.$id.'.', $scope, $follows, $node, $placed);
                $out[count($out) - 1] = $child;
            }
        }

        if (! $placed && $own && $follows === null && $prefix === $scope) {
            $out[] = $node;
            $placed = true;
        }

        return $out;
    }

    /**
     * Key → type of every field of a schema.
     *
     * @param  array<mixed>  $nodes
     * @return array<string, string>
     */
    public static function fields(array $nodes, string $prefix = ''): array
    {
        $fields = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = is_string($node['type'] ?? null) ? $node['type'] : '';
            $id = is_string($node['id'] ?? null) && $node['id'] !== '' ? $node['id'] : null;
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            if ($id === null || in_array($type, self::LAYOUT, true)) {
                $fields += self::fields($children, $prefix);

                continue;
            }

            $fields[$prefix.$id] = $type;
            $fields += self::fields($children, $prefix.$id.'.');
        }

        return $fields;
    }
}
