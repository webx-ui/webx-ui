<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use WebxUi\Blog\Models\Article;
use WebxUi\Localization\Locales;

/**
 * Where a saved article goes — which is two places, and the split is the interesting part.
 *
 * What the text of the article is goes into the draft: the title, the address, the lead, the
 * cover. The site keeps showing what was published until somebody publishes again, which is the
 * whole promise of `HasDraft`.
 *
 * What the article's place in the blog is does not, and cannot. A rubric is a row in a pivot,
 * not a value in a column, and there is no such thing as half a row: drafting one would mean a
 * second pivot with a flag on it, and every listing on the site would then have to remember to
 * ask for the published half. Pinning is the same kind of fact — `Article::unversionedAttributes()`
 * already says so, because restoring last week's version must not unpin what somebody pinned
 * this morning. So rubrics, tags, related articles and the pin take effect when they are saved.
 *
 * Said out loud because it is visible: an editor who moves an article between rubrics and does
 * not publish has moved it on the site. That is the right answer — the navigation is not a
 * draft — but it is not the one the "Save draft" button appears to promise, so the panel says
 * it beside the field rather than leaving it to be discovered.
 */
final class ArticleWriter
{
    /** The fields that are a map of languages rather than a value (§9). */
    private const TRANSLATED = ['title', 'slug', 'lead'];

    public function __construct(private readonly Locales $locales) {}

    /**
     * @param  array<string, mixed>  $columns  The article's own fields, only the ones that were sent.
     * @param  list<int>|null  $rubrics  Null leaves the rubrics alone; an empty list clears them.
     * @param  list<int>|null  $tags
     * @param  list<int>|null  $related
     */
    public function save(
        Article $article,
        array $columns,
        ?array $rubrics = null,
        ?array $tags = null,
        ?array $related = null,
        ?int $authorId = null,
    ): Article {
        $pinned = $columns['pinned'] ?? null;
        $date = array_key_exists('published_at', $columns) ? $columns['published_at'] : false;
        unset($columns['pinned'], $columns['published_at']);

        if ($pinned !== null) {
            $article->forceFill(['pinned' => (bool) $pinned])->save();
        }

        if (is_string($date) && $date !== '') {
            // The date of an article that is already on the site — or waiting for its day — is
            // the column, not the draft: it is what every listing orders by, and a date that
            // moved only in a draft would leave the article in the wrong place in the feed
            // until somebody published something unrelated. One that has never been on the site
            // has no column to move — `published_at` is what "on the site" means — so its day
            // waits in the draft, and `publish()` is handed it when the moment comes.
            if ($article->published_at !== null) {
                $article->forceFill(['published_at' => Instant::from($date)])->save();
            } else {
                $columns['published_at'] = $date;
            }
        }

        if ($columns !== []) {
            $article->saveDraft($this->draft($article, $columns), $authorId);
        }

        if ($rubrics !== null) {
            $article->rubrics()->sync($this->positioned($rubrics));
        }

        if ($tags !== null) {
            $article->tags()->sync($tags);
        }

        if ($related !== null) {
            // An article is never related to itself: the list under it would offer the reader
            // the page they are on.
            $article->related()->sync($this->positioned(array_values(array_filter(
                $related,
                static fn (int $id): bool => $id !== (int) $article->getKey(),
            ))));
        }

        return $article->refresh();
    }

    /**
     * The whole draft, with what was sent laid over what the editor is looking at.
     *
     * The whole of it rather than a merge on the way in: a field somebody emptied has to come
     * back empty, and `HasDraft::saveDraft()` replaces what it is given. That is also what lets
     * the form save one tab — the fields of the tabs nobody touched are not in the request and
     * must not be lost because of it.
     *
     * A translated field travels as its whole map, never as the string the panel is showing. It
     * is showing a fallback whenever this language has no value of its own, so writing that
     * string back would quietly copy the English title into the Russian slot — a save that
     * translates nothing and destroys the difference between "not translated" and "translated
     * identically".
     *
     * @param  array<string, mixed>  $columns
     * @return array<string, mixed>
     */
    private function draft(Article $article, array $columns): array
    {
        $locale = $this->locales->current();
        $values = $article->hasDraft() ? $article->draftValues() : $this->published($article);

        foreach ($columns as $field => $value) {
            if (! in_array($field, self::TRANSLATED, true)) {
                $values[$field] = $value;

                continue;
            }

            // The form edits every language at once and sends the whole map; an agent, or a
            // script, sends the words it has and means the language it is speaking. Both are
            // let through, and neither is ever written over the whole field: a map is laid over
            // what is there and a string goes into one slot. A language the editor emptied
            // still comes back empty — it travels as `''`, which is a key like any other —
            // while a language nobody mentioned is a language nobody meant to delete.
            if (is_array($value)) {
                $current = $values[$field] ?? [];
                $values[$field] = is_array($current) ? [...$current, ...$value] : $value;

                continue;
            }

            $map = $values[$field] ?? [];
            $map = is_array($map) ? $map : [$locale => $map];
            $map[$locale] = $value;

            $values[$field] = $map;
        }

        return $values;
    }

    /**
     * The article as the site has it — the starting point for a draft that does not exist yet.
     *
     * @return array<string, mixed>
     */
    private function published(Article $article): array
    {
        $values = [
            'blocks' => $article->getAttribute('blocks'),
            'cover_id' => $article->cover_id,
            'author_id' => $article->author_id,
        ];

        foreach (self::TRANSLATED as $field) {
            $values[$field] = $article->getTranslations($field);
        }

        return $values;
    }

    /**
     * Ids to `sync()` with their order written into the pivot: the first rubric is the main one
     * (§2.6), and "first" has to survive the trip to the database to mean anything.
     *
     * @param  list<int>  $ids
     * @return array<int, array{position: int}>
     */
    private function positioned(array $ids): array
    {
        $pivot = [];

        foreach (array_values($ids) as $position => $id) {
            $pivot[$id] = ['position' => $position];
        }

        return $pivot;
    }
}
