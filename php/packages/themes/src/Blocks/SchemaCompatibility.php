<?php

declare(strict_types=1);

namespace WebxUi\Themes\Blocks;

/**
 * Whether a block schema can stand in for another one (spec §9.2, rule 16): every field of the
 * lower schema is in the upper one, under the same key and of the same type. New fields are
 * fine — content written for the lower type simply has no value for them yet.
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
