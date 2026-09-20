<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Blog\Models\Article;

/**
 * What an editor read, as a short string, so a save can say whether somebody wrote in between.
 *
 * A hash of the article as it is being edited — the draft where there is one, the columns where
 * there is not — plus what is held in the pivots, because a rubric added by somebody else is a
 * change to the article as much as a retitled paragraph is.
 *
 * The order of the fields is fixed, and the relation ids are sorted, so that two reads of one
 * unchanged article are the same string however the values arrived. Twelve characters: this is
 * "is it still the one I read", not a checksum against corruption.
 */
final class Revision
{
    public static function of(Article $article): string
    {
        $shown = $article->hasDraft() ? $article->withDraft() : $article;

        $content = [
            'title' => $shown->getAttribute('title'),
            'slug' => $shown->getAttribute('slug'),
            'lead' => $shown->getAttribute('lead'),
            'blocks' => $shown->getAttribute('blocks'),
            'cover_id' => $shown->cover_id,
            'author_id' => $shown->author_id,
            'pinned' => $shown->pinned,
            'published_at' => $article->published_at?->toAtomString(),
            // The day an article that has never been on the site is meant to go out. It lives
            // in the draft rather than in the column, and moving it is an edit like any other:
            // two editors who each picked a different Tuesday have to find out about it.
            'planned' => $article->draftValues()['published_at'] ?? null,
            'rubrics' => self::keys($article, 'rubrics'),
            'tags' => self::keys($article, 'tags'),
            'related' => self::keys($article, 'related'),
        ];

        return substr(sha1(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }

    /**
     * @return list<int>
     */
    private static function keys(Article $article, string $relation): array
    {
        /** @var list<int> $ids */
        $ids = $article->{$relation}()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();

        return $ids;
    }
}
