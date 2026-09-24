<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use WebxUi\Services\Models\Service;

/**
 * What an editor read, as a short string, so a save can say whether somebody wrote in between.
 *
 * A hash of the service as it is being edited — the draft where there is one, the columns where
 * there is not — plus its categories, because a category added by somebody else is a change to
 * the service as much as a retitled paragraph is. Not `position`: the list is dragged by other
 * people while this one is open, and a reorder is not an edit of the text anybody could lose.
 *
 * The categories are in the order the editor put them in, and deliberately so: the first is the
 * main one, it goes in the breadcrumbs, and swapping the first two is an edit like any other.
 */
final class Revision
{
    public static function of(Service $service): string
    {
        $shown = $service->hasDraft() ? $service->withDraft() : $service;

        /** @var list<int> $categories */
        $categories = $service->categories()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        $content = [
            'title' => $shown->getAttribute('title'),
            'slug' => $shown->getAttribute('slug'),
            'lead' => $shown->getAttribute('lead'),
            'blocks' => $shown->getAttribute('blocks'),
            'cover_id' => $shown->cover_id,
            'extra' => $shown->getAttribute('extra'),
            'published_at' => $service->published_at?->toAtomString(),
            'categories' => $categories,
        ];

        return substr(sha1(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }
}
