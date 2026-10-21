<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Schema;

/**
 * How a block type on the site differs from its file (BLOCK_SCHEMAS §4.1) — what an import shows
 * instead of overwriting the site's version.
 *
 * Both sides are normalised the way the panel's save normalises them ({@see BlockInput}), and
 * only what the document says is compared: a file that leaves `description` out says nothing
 * about it, the way an import of it would leave it alone. The site's side is the row and the
 * version being edited — the open draft, or the published version — the one an import would
 * write over.
 *
 * `+` is in the file and not on the site, `−` the other way round: the file is what replacing
 * would bring.
 *
 * @phpstan-type Lines array{added: int, removed: int}
 * @phpstan-type Diff array{
 *     fields?: array{added: list<string>, removed: list<string>, changed: list<array{key: string, what: string}>},
 *     template?: Lines, styles?: Lines, script?: Lines, sample?: Lines, row?: list<string>
 * }
 */
final class TypeDiff
{
    /** The row's keys an import writes, as the panel's form shows them. */
    private const ROW = ['kind', 'title', 'description', 'icon', 'group', 'sort', 'allow', 'allowed_in', 'max_per_entity', 'is_enabled'];

    private const TEXT = ['template', 'styles', 'script'];

    /**
     * Empty when the type is the file's.
     *
     * @param  array<string, mixed>  $document  A file of `webx:blocks:export`.
     * @return Diff
     */
    public static function of(array $document, Block $block): array
    {
        $diff = [];

        $row = self::row($document, $block);

        if ($row !== []) {
            $diff['row'] = $row;
        }

        $file = BlockInput::content($document);
        $site = BlockInput::content($block->currentVersion()?->content() ?? []);

        if (array_key_exists('schema', $file)) {
            $fields = self::fields($file['schema'], $site['schema'] ?? []);

            if ($fields['added'] !== [] || $fields['removed'] !== [] || $fields['changed'] !== []) {
                $diff['fields'] = $fields;
            }
        }

        foreach ([...self::TEXT, 'sample'] as $key) {
            if (! array_key_exists($key, $file) || $file[$key] == ($site[$key] ?? null)) {
                continue;
            }

            $diff[$key] = self::lines(self::text($site[$key] ?? null), self::text($file[$key]));
        }

        return $diff;
    }

    /**
     * The row's keys the document sets to something else.
     *
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private static function row(array $document, Block $block): array
    {
        $file = BlockInput::values(array_intersect_key($document, array_flip(self::ROW)));
        $current = [];

        foreach (array_keys($file) as $key) {
            $current[$key] = $block->getAttribute($key);
        }

        $site = BlockInput::values($current);
        $changed = [];

        foreach ($file as $key => $value) {
            if ($value != ($site[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * The fields of the two schemas by key; layout names nothing and is not compared.
     *
     * @param  array<mixed>  $file
     * @param  array<mixed>  $site
     * @return array{added: list<string>, removed: list<string>, changed: list<array{key: string, what: string}>}
     */
    private static function fields(array $file, array $site): array
    {
        $after = self::keyed($file);
        $before = self::keyed($site);
        $changed = [];

        foreach (array_intersect_key($after, $before) as $key => $node) {
            $what = self::what($before[$key], $node);

            if ($what !== null) {
                $changed[] = ['key' => (string) $key, 'what' => $what];
            }
        }

        return [
            'added' => array_map('strval', array_keys(array_diff_key($after, $before))),
            'removed' => array_map('strval', array_keys(array_diff_key($before, $after))),
            'changed' => $changed,
        ];
    }

    /**
     * Key → the field's own settings, its children left out (they are fields of their own).
     *
     * @param  array<mixed>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private static function keyed(array $nodes, string $prefix = ''): array
    {
        $fields = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = is_string($node['type'] ?? null) ? $node['type'] : '';
            $id = is_string($node['id'] ?? null) && $node['id'] !== '' ? $node['id'] : null;
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            if ($id === null || Schema::isLayout($type)) {
                $fields += self::keyed($children, $prefix);

                continue;
            }

            unset($node['children']);
            /** @var array<string, mixed> $node */
            $fields[$prefix.$id] = $node;
            $fields += self::keyed($children, $prefix.$id.'.');
        }

        return $fields;
    }

    /**
     * What changed in a field: `wx-input → wx-textarea` for its type, else the settings that differ.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private static function what(array $before, array $after): ?string
    {
        $from = is_string($before['type'] ?? null) ? $before['type'] : '';
        $to = is_string($after['type'] ?? null) ? $after['type'] : '';

        if ($from !== $to) {
            return "{$from} → {$to}";
        }

        $keys = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (($before[$key] ?? null) != ($after[$key] ?? null)) {
                $keys[] = (string) $key;
            }
        }

        sort($keys);

        return $keys === [] ? null : implode(', ', $keys);
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            $value === null => '',
            default => (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * Lines the file adds and removes, by the longest common run of lines.
     *
     * @return Lines
     */
    private static function lines(string $site, string $file): array
    {
        $before = $site === '' ? [] : explode("\n", rtrim($site, "\n"));
        $after = $file === '' ? [] : explode("\n", rtrim($file, "\n"));
        $n = count($before);
        $m = count($after);

        // A template of thousands of lines on both sides: counted as sets, not worth the table.
        if ($n * $m > 4_000_000) {
            $common = count(array_intersect($after, $before));

            return ['added' => max(0, $m - $common), 'removed' => max(0, $n - $common)];
        }

        $previous = array_fill(0, $m + 1, 0);

        for ($i = 1; $i <= $n; $i++) {
            $current = [0];

            for ($j = 1; $j <= $m; $j++) {
                $current[$j] = $before[$i - 1] === $after[$j - 1]
                    ? $previous[$j - 1] + 1
                    : max($previous[$j], $current[$j - 1]);
            }

            $previous = $current;
        }

        $common = $previous[$m];

        return ['added' => $m - $common, 'removed' => $n - $common];
    }

    /**
     * The diff as the lines under an import's row (§4.1).
     *
     * @param  Diff  $diff
     * @return list<array{string, string}>
     */
    public static function describe(array $diff): array
    {
        $lines = [];

        if (isset($diff['fields'])) {
            $parts = [
                ...array_map(static fn (string $key): string => "+ {$key}", $diff['fields']['added']),
                ...array_map(static fn (string $key): string => "− {$key}", $diff['fields']['removed']),
                ...array_map(static fn (array $one): string => "~ {$one['key']} ({$one['what']})", $diff['fields']['changed']),
            ];
            $lines[] = ['fields', implode('  ', $parts)];
        }

        foreach ([...self::TEXT, 'sample'] as $key) {
            if (isset($diff[$key])) {
                $verb = $key === 'styles' ? 'differ' : 'differs';
                $lines[] = [$key, "{$verb} (+{$diff[$key]['added']} −{$diff[$key]['removed']} lines)"];
            }
        }

        if (isset($diff['row'])) {
            $lines[] = ['row', implode(', ', $diff['row'])];
        }

        return $lines;
    }
}
