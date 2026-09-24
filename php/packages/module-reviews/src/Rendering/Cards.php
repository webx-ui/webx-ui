<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Rendering;

use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;

/**
 * A review as a template reads it — plain data, not the model (§4.3 of the reviews spec).
 *
 *     id, anchor, categories   what every element of a `wx-collection` carries
 *     name, job_title          in the language asked for, else in the default one; '' when none
 *     text                     plain text in the language asked for — without it there is no card
 *     rating, date, profile    an int from 1 to 5, `Y-m-d`, an address; each null when not given
 *     photo                    what a `wx-media` field hands over: url, thumb, width, height, alt…
 *     fields                   the project's own fields (a patch on `reviews.form`), by name
 *
 * Not the model, for the reason the services' cards give: every card is built from what was
 * loaded with the list, so a list of any length is the same few queries.
 */
final class Cards
{
    /** What a list of reviews is loaded with, so that no card goes back to the database. */
    public const RELATIONS = ['categories'];

    public function __construct(
        private readonly MediaFiles $files,
        private readonly MediaValues $media,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  list<Review>  $reviews
     * @return list<array<string, mixed>>
     */
    public function reviews(array $reviews, string $locale): array
    {
        // Every photo of the list in one query of the library rather than one per card.
        $this->files->load(array_values(array_filter(array_map(
            static fn (Review $review): ?string => $review->photoPath(),
            $reviews,
        ))));

        $default = $this->locales->defaultCode();

        return array_map(fn (Review $review): array => $this->review($review, $locale, $default), $reviews);
    }

    /**
     * A category with the reviews it lists, already chosen and ordered by the caller.
     *
     * @param  list<Review>  $reviews
     * @return array<string, mixed>
     */
    public function category(ReviewCategory $category, array $reviews, string $locale): array
    {
        return [
            'id' => (int) $category->getKey(),
            'title' => $category->displayName($locale),
            'reviews' => $this->reviews($reviews, $locale),
        ];
    }

    /** @return array<string, mixed> */
    private function review(Review $review, string $locale, string $default): array
    {
        $fields = [];

        foreach (array_keys((array) ($review->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $review->extra((string) $name, $locale);
        }

        $name = $review->wordsIn('name', $locale, $default);

        return [
            'id' => (int) $review->getKey(),
            'anchor' => 'review-'.$review->getKey(),
            'categories' => $review->categoryIds(),
            'name' => $name,
            'initials' => $this->initials($name),
            'job_title' => $review->wordsIn('job_title', $locale, $default),
            'text' => $review->textIn($locale),
            'rating' => $review->rating,
            'date' => $review->reviewed_on?->toDateString(),
            'profile' => $review->profile_url,
            'photo' => $this->photo($review, $locale),
            'fields' => $fields,
        ];
    }

    /**
     * What stands in for a photo: the first letters of the first two words, `AP` for Anna
     * Petrova. Here rather than in the template, because a template that has to cut a string by
     * characters rather than bytes is one `substr()` away from half a Cyrillic letter.
     */
    private function initials(string $name): string
    {
        $words = preg_split('/[\s\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials;
    }

    /**
     * The photo as the library knows it now, or null — also when its file was deleted from the
     * library: a card with an `<img>` that has no address is worse than one with the initials.
     *
     * @return array<string, mixed>|null
     */
    private function photo(Review $review, string $locale): ?array
    {
        if ($review->photoPath() === null) {
            return null;
        }

        $photo = $this->media->resolve($review->photo, $locale);

        return is_array($photo) && is_string($photo['url'] ?? null) ? $photo : null;
    }
}
