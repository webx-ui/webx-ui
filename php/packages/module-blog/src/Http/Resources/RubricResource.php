<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileUrls;
use WebxUi\Routing\Models\Route;

/**
 * One rubric, as both halves of its screen need it (§10).
 *
 * The left column reads a name, an address and a number; the form beside it edits the same
 * record in every language at once. So the translated fields travel as maps — that is what a
 * localized field edits — and a display name is worked out beside them, because a rubric named
 * in one language and not in another must still be a row somebody can click.
 *
 * `articles_count` is why the delete button is greyed and what its explanation says, so it is
 * in the row rather than asked for when the button is pressed (§6).
 *
 * @mixin Rubric
 */
final class RubricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Rubric $rubric */
        $rubric = $this->resource;

        $locale = app(Locales::class)->current();
        $canonical = $this->canonical($rubric, $locale);

        return [
            'id' => (int) $rubric->getKey(),
            'name' => $this->name($rubric, $locale),
            'title' => $rubric->getTranslations('title'),
            'slug' => $rubric->getTranslations('slug'),
            'lead' => $rubric->getTranslations('lead'),
            // Null rather than an empty string where the rubric names no slug in this
            // language: the two mean different things, and a rubric translated into one
            // language has no address in the others (§9).
            'path' => $canonical?->path,
            'url' => $canonical === null ? null : $rubric->url($locale),
            'cover' => $this->cover($rubric),
            'is_visible' => (bool) $rubric->is_visible,
            'position' => (int) $rubric->position,
            'articles_count' => (int) ($rubric->getAttribute('articles_count') ?? $rubric->articleCount()),
            // What the rubric says about its own page, over whatever the SEO rules say (§12).
            // Empty until somebody opens the card, which is what keeps the rule the default.
            'seo' => $rubric->seoValue(),
        ];
    }

    /**
     * The name to show, and something to show when there is none.
     *
     * A rubric made in another language would otherwise be an empty row in this one; the slug
     * is what somebody called it somewhere, and failing that its number is at least an identity.
     */
    private function name(Rubric $rubric, string $locale): string
    {
        foreach ([$rubric->getTranslation('title', $locale), $rubric->getTranslation('slug', $locale)] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$rubric->getKey();
    }

    /**
     * The address the registry holds for this language, out of what was loaded with the rubric.
     */
    private function canonical(Rubric $rubric, string $locale): ?Route
    {
        if (! $rubric->relationLoaded('routes')) {
            return $rubric->routeCanonical($locale);
        }

        return $rubric->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL);
    }

    /**
     * The picture as `wx-media` holds it: a library key, and the addresses worked out from it.
     *
     * Nothing about the address is stored — a signed link to a private bucket expires within
     * the hour and a cropped picture keeps its key (CLAUDE.md §4).
     *
     * @return array<string, mixed>|null
     */
    private function cover(Rubric $rubric): ?array
    {
        $cover = $rubric->cover;

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
}
