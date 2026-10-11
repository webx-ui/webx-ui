<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Themes;

use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\BlockInput;
use WebxUi\Blocks\Panel\Exchange;

/**
 * One hash for a block type, whether it is read from a layer's file or from the site's tables
 * (THEMES §9.2) — so that «the site has not touched what the theme put» is one comparison.
 *
 * Not the hash of the file's bytes: an export writes `version` and `exported_at`, which change on
 * every export of the same type, and the panel's save normalises what it is sent (trims, types,
 * fills defaults). Both sides go through the same normalising — {@see BlockInput} over the row's
 * keys of {@see Exchange::ROW} and the content of a version, the defaults a new row and a first
 * version get — and the result is hashed with its keys sorted.
 */
final class TypeHash
{
    /** What a row the document says nothing about gets, as a new {@see Block} does. */
    private const ROW_DEFAULTS = [
        'slug' => null, 'kind' => Block::KIND_BLOCK, 'title' => null, 'description' => null, 'icon' => null,
        'group' => 'content', 'sort' => 0, 'allow' => null, 'allowed_in' => null, 'max_per_entity' => null,
        'is_enabled' => true,
    ];

    private const CONTENT_DEFAULTS = ['schema' => [], 'template' => '', 'styles' => '', 'script' => null, 'sample' => []];

    /**
     * @param  array<string, mixed>  $document  A file of `webx:blocks:export`.
     */
    public static function ofDocument(array $document): string
    {
        $row = BlockInput::values(array_intersect_key($document, array_flip(Exchange::ROW))) + self::ROW_DEFAULTS;
        $content = BlockInput::content($document) + self::CONTENT_DEFAULTS;

        return self::hash($row, $content);
    }

    /** The type as the site prints it: the row and its published version. Null when nothing is published. */
    public static function ofPublished(Block $block): ?string
    {
        $version = $block->publishedVersion;

        if (! $version instanceof BlockVersion) {
            return null;
        }

        $row = [];

        foreach (Exchange::ROW as $key) {
            $row[$key] = $block->getAttribute($key);
        }

        return self::hash(
            BlockInput::values($row) + self::ROW_DEFAULTS,
            BlockInput::content($version->content()) + self::CONTENT_DEFAULTS,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $content
     */
    private static function hash(array $row, array $content): string
    {
        $row = array_intersect_key($row, self::ROW_DEFAULTS);
        $content = array_intersect_key($content, self::CONTENT_DEFAULTS);

        return hash('sha256', (string) json_encode(self::sorted($row + $content), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::sorted(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
