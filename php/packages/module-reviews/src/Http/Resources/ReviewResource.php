<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Reviews\Panel\ReviewNames;

/**
 * One review as a row of the panel's list (§4.7).
 *
 * `locales` is where the review has a text — not where it is seen: publishing is a flag of its
 * own, so the list can say "published, and seen in no language" about the row that is.
 *
 * The photo is only its thumbnail: a list of forty reviews has no use for forty sets of sizes.
 * The controller loads the library rows of the whole list first, so this is not a query per row.
 *
 * @mixin Review
 */
final class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        $locales = app(Locales::class);
        $locale = $locales->current();

        return [
            'id' => (int) $review->getKey(),
            'name' => ReviewNames::of($review, $locales),
            'job_title' => $review->wordsIn('job_title', $locale, $locales->defaultCode()),
            'rating' => $review->rating,
            'photo' => $this->photo($review, $locale),
            'published' => $review->published,
            'position' => (int) $review->position,
            'locales' => array_values(array_filter(
                $locales->codes(),
                static fn (string $code): bool => $review->writtenIn($code),
            )),
            'categories' => $review->categories
                ->map(static fn (ReviewCategory $category): array => [
                    'id' => (int) $category->getKey(),
                    'title' => $category->displayName($locale),
                ])
                ->values()
                ->all(),
            'updated_at' => $review->updated_at?->toAtomString(),
            'deleted_at' => $review->deleted_at?->toAtomString(),
        ];
    }

    /** @return array{thumb: string}|null */
    private function photo(Review $review, string $locale): ?array
    {
        if ($review->photoPath() === null) {
            return null;
        }

        $photo = app(MediaValues::class)->resolve($review->photo, $locale);
        $thumb = $photo['thumb'] ?? $photo['url'] ?? null;

        return is_string($thumb) && $thumb !== '' ? ['thumb' => $thumb] : null;
    }
}
