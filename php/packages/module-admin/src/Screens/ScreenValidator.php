<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * The same checks `validateScreen` / `validatePatch` make in `@webx-ui/schema`, so a description
 * that passes on one half passes on the other: required keys, the closed key set, value types,
 * unique ids, the shape of a condition.
 */
final class ScreenValidator
{
    private const NODE_KEYS = ['id', 'type', 'name', 'label', 'help', 'localized', 'props', 'children', 'slot', 'visible', 'can'];

    private const OPS = ['add', 'remove', 'replace', 'move', 'set'];

    /**
     * @return list<string>
     */
    public static function screen(mixed $root): array
    {
        if (! is_array($root) || ! array_is_list($root)) {
            return ['root must be a list of nodes'];
        }

        $problems = [];
        $seen = [];

        foreach ($root as $index => $node) {
            self::node($node, "root[{$index}]", $problems, $seen);
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    public static function patch(mixed $patch): array
    {
        if (! is_array($patch) || ! array_is_list($patch)) {
            return ['a patch must be a list of operations'];
        }

        $problems = [];

        foreach ($patch as $index => $op) {
            $path = "patch[{$index}]";

            if (! is_array($op) || array_is_list($op)) {
                $problems[] = "{$path}: an operation must be an object";

                continue;
            }

            if (! is_string($op['op'] ?? null) || ! in_array($op['op'], self::OPS, true)) {
                $problems[] = "{$path}: \"op\" must be one of add, remove, replace, move, set";

                continue;
            }

            if (! is_string($op['target'] ?? null) || $op['target'] === '') {
                $problems[] = "{$path}: \"target\" is required: the id of a node";
            }

            if (in_array($op['op'], ['add', 'replace'], true)) {
                if (! isset($op['node']) || ! is_array($op['node'])) {
                    $problems[] = "{$path}: \"{$op['op']}\" needs a \"node\"";
                } else {
                    $seen = [];
                    self::node($op['node'], "{$path}.node", $problems, $seen);
                }
            }

            if (array_key_exists('position', $op) && ! self::isPosition($op['position'])) {
                $problems[] = "{$path}: \"position\" must be first, last, before:<id> or after:<id>";
            }

            if ($op['op'] === 'move' && array_key_exists('to', $op) && ! is_string($op['to'])) {
                $problems[] = "{$path}: \"to\" must be the id of the new parent";
            }

            if ($op['op'] === 'set') {
                foreach (array_keys($op) as $key) {
                    if ($key === 'op' || $key === 'target') {
                        continue;
                    }

                    if ($key === 'id' || ! in_array($key, self::NODE_KEYS, true)) {
                        $problems[] = "{$path}: \"set\" cannot change \"{$key}\"";
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>  $problems
     * @param  array<string, true>  $seen
     */
    private static function node(mixed $node, string $path, array &$problems, array &$seen): void
    {
        if (! is_array($node) || array_is_list($node)) {
            $problems[] = "{$path}: a node must be an object";

            return;
        }

        foreach (array_keys($node) as $key) {
            if (! in_array($key, self::NODE_KEYS, true)) {
                $problems[] = "{$path}: unknown key \"{$key}\"";
            }
        }

        $id = $node['id'] ?? null;

        if (! is_string($id) || $id === '') {
            $problems[] = "{$path}: \"id\" is required and must be a non-empty string";
        } elseif (isset($seen[$id])) {
            $problems[] = "{$path}: duplicate id \"{$id}\"";
        } else {
            $seen[$id] = true;
        }

        if (! is_string($node['type'] ?? null) || $node['type'] === '') {
            $problems[] = "{$path}: \"type\" is required and must be a non-empty string";
        }

        foreach (['name', 'label', 'help'] as $key) {
            if (array_key_exists($key, $node) && ! is_string($node[$key])) {
                $problems[] = "{$path}: \"{$key}\" must be a string";
            }
        }

        if (array_key_exists('localized', $node) && ! is_bool($node['localized'])) {
            $problems[] = "{$path}: \"localized\" must be a boolean";
        }

        if (array_key_exists('props', $node) && (! is_array($node['props']) || ($node['props'] !== [] && array_is_list($node['props'])))) {
            $problems[] = "{$path}: \"props\" must be an object";
        }

        if (array_key_exists('slot', $node) && $node['slot'] !== null && ! is_string($node['slot'])) {
            $problems[] = "{$path}: \"slot\" must be a string or null";
        }

        if (array_key_exists('can', $node) && $node['can'] !== null && ! is_string($node['can'])) {
            $problems[] = "{$path}: \"can\" must be a string or null";
        }

        if (array_key_exists('visible', $node) && ! is_bool($node['visible'])) {
            self::condition($node['visible'], "{$path}.visible", $problems);
        }

        if (array_key_exists('children', $node)) {
            if (! is_array($node['children']) || ! array_is_list($node['children'])) {
                $problems[] = "{$path}: \"children\" must be a list";
            } else {
                foreach ($node['children'] as $index => $child) {
                    self::node($child, "{$path}.children[{$index}]", $problems, $seen);
                }
            }
        }
    }

    /**
     * @param  list<string>  $problems
     */
    private static function condition(mixed $value, string $path, array &$problems): void
    {
        if (! is_array($value) || array_is_list($value)) {
            $problems[] = "{$path}: visible must be a boolean or a condition object";

            return;
        }

        if (array_key_exists('all', $value) || array_key_exists('any', $value)) {
            $list = $value['all'] ?? $value['any'];

            if (! is_array($list) || ! array_is_list($list)) {
                $problems[] = "{$path}: \"all\" / \"any\" must be a list of conditions";

                return;
            }

            foreach ($list as $index => $item) {
                self::condition($item, "{$path}[{$index}]", $problems);
            }

            return;
        }

        if (! is_string($value['when'] ?? null)) {
            $problems[] = "{$path}: a condition needs \"when\": the name of a field";

            return;
        }

        $forms = array_values(array_filter(['is', 'in', 'not'], static fn (string $key): bool => array_key_exists($key, $value)));

        if (count($forms) !== 1) {
            $problems[] = "{$path}: a condition needs exactly one of \"is\", \"in\", \"not\"";
        } elseif ($forms[0] === 'in' && ! is_array($value['in'])) {
            $problems[] = "{$path}: \"in\" must be a list";
        }
    }

    public static function isPosition(mixed $value): bool
    {
        return is_string($value) && (
            $value === 'first'
            || $value === 'last'
            || (str_starts_with($value, 'before:') && strlen($value) > 7)
            || (str_starts_with($value, 'after:') && strlen($value) > 6)
        );
    }
}
