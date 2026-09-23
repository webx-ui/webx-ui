<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Blog\Http\Resources\ArticleResource;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Seo\Fields;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes.
 *
 * The screen is `blog.article-form` and the values are keyed by field name, so what an article
 * is made of is decided by the description rather than by this class — the SEO card arrives as
 * a patch from `module-seo` (§12) and is saved here by being on the screen at all. What this
 * class knows is which of those names are the article's own, and {@see ArticleWriter} knows the
 * part that matters most: which of them go into the draft and which take effect at once.
 *
 * Two fields are named differently on the screen and in the table, and both for the same
 * reason. `cover` holds what the media field edits — a library key — while the column holds the
 * id of a row, because an article's cover is a foreign key and not a copy of a path. The
 * mapping lives here, in one direction on the way in and the other on the way out.
 */
final class ArticleForm
{
    public const SCREEN = 'blog.article-form';

    /**
     * The article's own text, in this order, so that the revision of one article is the same
     * string whichever way the values came in.
     *
     * @var list<string>
     */
    private const OWN = ['title', 'slug', 'lead', 'blocks', 'cover', 'author_id', 'pinned', 'published_at'];

    /** As many administrators as an author dropdown is worth reading. */
    private const AUTHORS = 200;

    public function __construct(
        private readonly ScreenValues $values,
        private readonly ArticleWriter $writer,
        private readonly MediaFiles $files,
        private readonly Locales $locales,
    ) {}

    /**
     * An article and everything its editor needs around it: the record, the values of the
     * screen, the revision those values are, what the dropdowns on it can be set to, and a link
     * to the draft.
     *
     * The preview link is minted per response rather than kept on the article: it is signed and
     * short-lived, and an editor who has had the form open all morning would otherwise press
     * "preview" and get a 403 from a token that expired before lunch.
     *
     * @return array<string, mixed>
     */
    public function describe(Article $article, ?int $adminId = null): array
    {
        return [
            'article' => new ArticleResource($article),
            'values' => $this->values($article),
            'revision' => Revision::of($article),
            // The address of an article is the prefix of the blog and its slug, and the prefix
            // is the same in every language: the blog is one flat space (§2.2), and the part
            // the field edits is the only part that differs.
            'prefix' => (string) config('webx-blog.prefix', 'blog'),
            'preview_url' => Preview::url($article, $adminId),
            'options' => [
                'rubrics' => $this->rubrics(),
                'authors' => $this->authors(),
            ],
            // The ids in `values.related` with something to draw beside them. The resource
            // carries the rubrics and the tags already; nothing carries these.
            'related' => $this->related($article),
        ];
    }

    /**
     * What the form opens with: the draft laid over the columns, which is what the editor was
     * last working on rather than what the site is currently showing.
     *
     * The pivots are never drafted (see {@see ArticleWriter}), so they are read straight off
     * the article — and so is the publication date, except for one case: an article that has
     * never been on the site keeps the day it is meant to go out in its draft, because there is
     * no column for a date that has not happened yet.
     *
     * @return array<string, mixed>
     */
    public function values(Article $article): array
    {
        $shown = $article->hasDraft() ? $article->withDraft() : $article;
        $draft = $article->draftValues();

        // With the offset on it, always. A wall clock with no zone is read by the server in the
        // application's timezone and by the picker in the reader's, so an article scheduled for
        // eleven would be listed at two — one record showing two different times on one screen.
        $planned = $article->published_at?->toAtomString();

        if ($planned === null && is_string($draft['published_at'] ?? null)) {
            $planned = $draft['published_at'];
        }

        return [
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'lead' => $shown->getTranslations('lead'),
            'blocks' => $shown->blocksTree(),
            'cover' => $this->cover($shown->cover_id),
            'author_id' => $shown->author_id === null ? null : (int) $shown->author_id,
            'pinned' => (bool) $article->pinned,
            'published_at' => $planned,
            'rubrics' => $this->keys($article, 'rubrics'),
            'tags' => $this->keys($article, 'tags'),
            'related' => $this->keys($article, 'related'),
            // Never from the draft. What an article says about itself is saved when it is saved
            // (`HasSeo`), so the card shows what is on the site rather than what is waiting.
            Fields::SCREEN => $article->seoValue(),
        ];
    }

    /**
     * Check what came in against the screen and write it.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Article $article, array $input, ?callable $can = null, ?int $authorId = null): Article
    {
        $stored = $this->values->validate(self::SCREEN, $input, $can);

        $columns = [];

        foreach (self::OWN as $field) {
            if (! array_key_exists($field, $stored)) {
                continue;
            }

            // The one field whose name on the screen is not its name in the table: what the
            // media field edits is a key, and what an article holds is the row it belongs to.
            if ($field === 'cover') {
                $columns['cover_id'] = $this->coverId($stored['cover']);

                continue;
            }

            $columns[$field] = $stored[$field];
        }

        $article = $this->writer->save(
            $article,
            $columns,
            $this->ids($stored, 'rubrics'),
            $this->ids($stored, 'tags'),
            $this->ids($stored, 'related'),
            $authorId,
        );

        // The one field on this screen that is not the article: it belongs to `module-seo`,
        // which put it here, and it goes to its own table rather than into the draft. Only when
        // it travelled — a save of the content tab alone must not empty a card nobody opened.
        if (array_key_exists(Fields::SCREEN, $stored)) {
            $value = $stored[Fields::SCREEN];

            $article->saveSeo(is_array($value) ? $value : null);
        }

        return $article;
    }

    /**
     * The ids of one relation as they arrived, or null when the field was not sent at all.
     *
     * Null and an empty list are different answers — "leave the tags alone" and "this article
     * has no tags" — and the form does send an empty list when somebody removes the last one.
     *
     * @param  array<string, mixed>  $stored
     * @return list<int>|null
     */
    private function ids(array $stored, string $field): ?array
    {
        if (! array_key_exists($field, $stored)) {
            return null;
        }

        /** @var list<int> $ids */
        $ids = is_array($stored[$field]) ? array_values($stored[$field]) : [];

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cover(mixed $id): ?array
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        $file = MediaFile::query()->find((int) $id);

        // The key alone. The field asks the library where that key currently lives, which is
        // the only answer that survives a move to another disk.
        return $file instanceof MediaFile ? ['path' => $file->path] : null;
    }

    private function coverId(mixed $value): ?int
    {
        $path = is_array($value) ? $value['path'] ?? null : null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        $file = $this->files->find($path);

        return $file === null ? null : (int) $file->getKey();
    }

    /**
     * @return list<int>
     */
    private function keys(Article $article, string $relation): array
    {
        /** @var list<int> $ids */
        $ids = $article->{$relation}()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return $ids;
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function rubrics(): array
    {
        $locale = $this->locales->current();

        return Rubric::query()
            ->inMenuOrder()
            ->get()
            ->map(static fn (Rubric $rubric): array => [
                'id' => (int) $rubric->getKey(),
                'title' => (string) $rubric->getTranslation('title', $locale),
            ])
            ->values()
            ->all();
    }

    /**
     * Everybody who could be named as the author, not only those who already are: an article
     * written by one person and signed by another is an ordinary thing in a newsroom (§2.14).
     *
     * @return list<array{id: int, title: string}>
     */
    private function authors(): array
    {
        return CmsUser::query()
            ->orderBy('name')
            ->limit(self::AUTHORS)
            ->get(['id', 'name'])
            ->map(static fn (CmsUser $user): array => ['id' => (int) $user->getKey(), 'title' => (string) $user->name])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function related(Article $article): array
    {
        $locale = $this->locales->current();

        return $article->related()
            ->get()
            ->map(static fn (Article $one): array => [
                'id' => (int) $one->getKey(),
                'title' => (string) ($one->getTranslation('title', $locale) ?: $one->getTranslation('slug', $locale)),
            ])
            ->values()
            ->all();
    }
}
