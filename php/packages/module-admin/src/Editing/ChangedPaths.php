<?php

declare(strict_types=1);

namespace WebxUi\Admin\Editing;

/**
 * The places two versions of a record differ, in the shape the panel's merge names them
 * (`merge.ts`, `changedPaths`): a list of steps, each a field — `{field: "title"}` — or a block
 * of a block list — `{block: "k3", type: "hero"}`. «Hero › Eyebrow · EN» is drawn from it on the
 * panel's side, with the labels of the screen and the block type.
 *
 * What a copy of the draft changed against the one before it, so that the person looking for the
 * edit that was lost finds «Hero › Eyebrow» rather than `title, slug, blocks` on every line.
 */
final class ChangedPaths
{
    /**
     * @return list<list<array{field: string}|array{block: string, type: string}>>
     */
    public static function between(mixed $before, mixed $after): array
    {
        $found = [];

        self::diff($before, $after, [], $found);

        return $found;
    }

    /**
     * @param  list<array{field: string}|array{block: string, type: string}>  $path
     * @param  list<list<array{field: string}|array{block: string, type: string}>>  $found
     */
    private static function diff(mixed $before, mixed $after, array $path, array &$found): void
    {
        if (self::same($before, $after)) {
            return;
        }

        if (self::isNodeList($before) && self::isNodeList($after)) {
            $old = self::byKey($before);
            $now = self::byKey($after);

            foreach (array_unique([...array_keys($old), ...array_keys($now)]) as $key) {
                $key = (string) $key;
                $node = $now[$key] ?? $old[$key];
                $at = [...$path, ['block' => $key, 'type' => is_string($node['type'] ?? null) ? $node['type'] : '']];

                if (isset($old[$key], $now[$key])) {
                    self::diff($old[$key], $now[$key], $at, $found);
                } else {
                    $found[] = $at;
                }
            }

            if (! self::sameOrder($before, $after)) {
                $found[] = $path;
            }

            return;
        }

        if (self::isMap($before) && self::isMap($after)) {
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                self::diff($before[$key] ?? null, $after[$key] ?? null, [...$path, ['field' => (string) $key]], $found);
            }

            return;
        }

        // One side a map and the other missing: name the fields inside rather than the whole map,
        // so that a title written for the first time reads «Title · EN» like any other.
        if (self::isMap($after) && $before === null || self::isMap($before) && $after === null) {
            foreach (array_keys((array) ($after ?? $before)) as $key) {
                self::diff($before[$key] ?? null, $after[$key] ?? null, [...$path, ['field' => (string) $key]], $found);
            }

            return;
        }

        $found[] = $path;
    }

    /** Equal as values: key order, and null against a missing key or an empty string, do not count. */
    private static function same(mixed $a, mixed $b): bool
    {
        if ($a === '' || $a === []) {
            $a = null;
        }

        if ($b === '' || $b === []) {
            $b = null;
        }

        if ($a === $b) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        if (is_array($a) && is_array($b)) {
            if (array_is_list($a) !== array_is_list($b)) {
                return false;
            }

            if (array_is_list($a) && count($a) !== count($b)) {
                return false;
            }

            foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $key) {
                if (! self::same($a[$key] ?? null, $b[$key] ?? null)) {
                    return false;
                }
            }

            return true;
        }

        // A number stored as text and the same number sent as one.
        return is_scalar($a) && is_scalar($b) && (string) $a === (string) $b;
    }

    /** @phpstan-assert-if-true array<array-key, mixed> $value */
    private static function isMap(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    private static function isNodeList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item) || ! is_string($item['key'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $list
     * @return array<string, array<string, mixed>>
     */
    private static function byKey(array $list): array
    {
        $map = [];

        foreach ($list as $node) {
            if (is_array($node) && is_string($node['key'] ?? null)) {
                $map[$node['key']] = $node;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $a
     * @param  array<int, mixed>  $b
     */
    private static function sameOrder(array $a, array $b): bool
    {
        $left = array_keys(self::byKey($a));
        $right = array_keys(self::byKey($b));
        $left = array_values(array_intersect($left, $right));
        $right = array_values(array_intersect($right, $left));

        return $left === $right;
    }
}
