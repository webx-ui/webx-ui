<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Panel\Revision;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Routing\Models\Route;

/**
 * One article as the panel knows it: a row of the list, and the record the editor opens.
 *
 * Two answers that look like one and are not, the same pair `module-pages` has. The title is
 * the draft's — what the editor is working on, which is what they look for in a list — while
 * the address is the registry's, because that is what the site answers at right now. An article
 * renamed in a draft and not yet published shows its new title beside its old address, and that
 * is the truth rather than a bug.
 *
 * @mixin Article
 */
final class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;

        $locale = app(Locales::class)->current();
        $shown = $article->hasDraft() ? $article->withDraft() : $article;
        $canonical = $this->canonical($article, $locale);

        return [
            'id' => (int) $article->getKey(),
            'title' => $this->title($shown, $locale),
            'slug' => (string) $shown->getTranslation('slug', $locale, fallback: false),
            'lead' => (string) $shown->getTranslation('lead', $locale, fallback: false),
            // Null rather than an empty string where the article names no slug in this
            // language: the two mean different things, and an article translated into one
            // language has no address in the others (§9).
            'path' => $canonical?->path,
            'url' => $canonical === null ? null : $article->url($locale),
            'status' => $article->status(),
            'pinned' => (bool) $article->pinned,
            'published_at' => $article->published_at?->toAtomString(),
            'updated_at' => $article->updated_at?->toAtomString(),
            'deleted_at' => $article->deleted_at?->toAtomString(),
            'author' => $this->author($article),
            'cover' => $this->cover($article),
            'rubrics' => $this->rubrics($article, $locale),
            'tags' => $this->tags($article, $locale),
            'revision' => Revision::of($article),
        ];
    }

    /**
     * The title to show, and something to show when there is none.
     *
     * An article started in another language and never translated into this one would otherwise
     * be an empty row nobody can click on the right part of; the slug is what somebody called
     * it somewhere, and failing that its number is at least an identity.
     */
    private function title(Article $article, string $locale): string
    {
        foreach ([$article->getTranslation('title', $locale), $article->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$article->getKey();
    }

    /**
     * The address the registry holds for this language, out of what was loaded with the article.
     *
     * Read from the relation rather than asked for row by row: a page of twenty articles is one
     * query for every address on it.
     */
    private function canonical(Article $article, string $locale): ?Route
    {
        if (! $article->relationLoaded('routes')) {
            return $article->routeCanonical($locale);
        }

        return $article->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function author(Article $article): ?array
    {
        $author = $article->author;

        return $author === null ? null : ['id' => (int) $author->getKey(), 'name' => (string) $author->name];
    }

    /**
     * The picture, with the two addresses `module-media` distinguishes.
     *
     * `url` is what a picture is shown by; `source` is the same bytes off the panel's own
     * origin, which is what an editor drawing onto a canvas needs and what a signed link to a
     * private bucket can never be. Neither is stored anywhere: both are worked out from the
     * file on every read (CLAUDE.md §4).
     *
     * @return array<string, mixed>|null
     */
    private function cover(Article $article): ?array
    {
        $cover = $article->cover;

        if (! $cover instanceof MediaFile) {
            return null;
        }

        $urls = app(FileUrls::class);

        return [
            'id' => (int) $cover->getKey(),
            'path' => $cover->path,
            'url' => $urls->url($cover),
            'thumb' => $urls->thumbUrl($cover),
        ];
    }

    /**
     * The rubrics in the order they were dragged into: the first is the main one (§2.6).
     *
     * @return list<array<string, mixed>>
     */
    private function rubrics(Article $article, string $locale): array
    {
        return $article->rubrics
            ->map(static fn (Rubric $rubric): array => [
                'id' => (int) $rubric->getKey(),
                'title' => (string) $rubric->getTranslation('title', $locale),
                'slug' => (string) $rubric->getTranslation('slug', $locale, fallback: false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tags(Article $article, string $locale): array
    {
        return $article->tags
            ->map(static fn (Tag $tag): array => [
                'id' => (int) $tag->getKey(),
                'title' => (string) $tag->getTranslation('title', $locale),
                'slug' => (string) $tag->getTranslation('slug', $locale, fallback: false),
            ])
            ->values()
            ->all();
    }
}
