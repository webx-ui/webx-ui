<?php

declare(strict_types=1);

namespace WebxUi\Pages\Panel;

use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Panel\Authors;
use WebxUi\Pages\Models\Page;

/**
 * Who touched each page last, for the "Updated" column.
 *
 * "When" is on the page itself; "who" is not, because a page does not carry an author — the
 * versions do, and the newest of them is the last edit whether it was a publication or an
 * autosave. Two queries for the whole list rather than two per row: a name beside a date is
 * worth having, a hundred queries to get it is not.
 */
final class Editors
{
    /**
     * @param  iterable<array-key, Page>  $pages
     * @return array<int, string> Page id → the name of whoever wrote its newest version.
     */
    public static function of(iterable $pages): array
    {
        $ids = [];

        foreach ($pages as $page) {
            $key = $page->getKey();

            if (is_int($key)) {
                $ids[] = $key;
            }
        }

        if ($ids === []) {
            return [];
        }

        // Newest first, and the first one seen per page wins: `id` orders the same way
        // `created_at` does and does not tie inside a second, which two saves in one request
        // can easily do.
        $rows = EntityVersion::query()
            ->where('versionable_type', (new Page)->getMorphClass())
            ->whereIn('versionable_id', $ids)
            ->whereNotNull('author_id')
            ->orderByDesc('id')
            ->get(['versionable_id', 'author_id']);

        $latest = [];

        foreach ($rows as $row) {
            $latest[$row->versionable_id] ??= (int) $row->author_id;
        }

        $names = Authors::names($latest);

        return array_filter(array_map(
            static fn (int $author): ?string => $names[$author] ?? null,
            $latest,
        ));
    }
}
