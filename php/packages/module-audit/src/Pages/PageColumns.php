<?php

declare(strict_types=1);

namespace WebxUi\Audit\Pages;

use WebxUi\Audit\Runs\AuditPage;

/**
 * The fields of the snapshot the pages screen shows, filters, sorts and exports (§8) — one list,
 * so the table, the filters and the CSV never disagree about what a column is.
 *
 * Types: `url`, `text`, `number`, `bool`, `status`, `source`. `h1` is the first H1; `issues` is
 * the number of the page's findings, counted by the query.
 */
final class PageColumns
{
    /** @var array<string, string> */
    public const ALL = [
        'url' => 'url',
        'status' => 'status',
        'final_status' => 'status',
        'redirect_to' => 'url',
        'source' => 'source',
        'depth' => 'number',
        'indexable' => 'bool',
        'issues' => 'number',
        'title' => 'text',
        'description' => 'text',
        'h1' => 'text',
        'canonical' => 'url',
        'robots_meta' => 'text',
        'x_robots_tag' => 'text',
        'lang' => 'text',
        'content_type' => 'text',
        'bytes' => 'number',
        'ttfb_ms' => 'number',
        'total_ms' => 'number',
        'compression' => 'text',
        'word_count' => 'number',
        'links_in' => 'number',
        'links_out_internal' => 'number',
        'links_out_external' => 'number',
        'images' => 'number',
        'images_without_alt' => 'number',
        'in_sitemap' => 'bool',
        'in_registry' => 'bool',
        'blocked_by_robots' => 'bool',
    ];

    /** What the screen shows before anybody picks. */
    public const DEFAULT = ['url', 'status', 'indexable', 'title', 'word_count', 'links_in', 'issues'];

    /** Columns that are not a plain column of the table: no SQL filter or sort on them. */
    private const COMPUTED = ['h1'];

    public static function has(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    public static function filterable(string $key): bool
    {
        return self::has($key) && ! in_array($key, self::COMPUTED, true);
    }

    /** One page as the screen and the CSV see it. */
    public static function value(AuditPage $page, string $key): mixed
    {
        return match ($key) {
            'h1' => $page->h1[0] ?? null,
            'issues' => (int) ($page->getAttribute('issues_count') ?? 0),
            default => $page->getAttribute($key),
        };
    }
}
