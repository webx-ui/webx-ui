<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Reviews\Panel\ReviewNames;

/**
 * What an agent reads before it writes a review (§4.8): every review in one message.
 *
 * Every category, hidden ones too, each with its reviews in that category's own order, and the
 * reviews filed nowhere at the end. Unpublished reviews are in it and say so — the point of reading
 * this first is not to type Anna's words in a second time beside the copy that is not out yet. A
 * review in two categories is listed under both: that is where a reader meets it.
 *
 * One name per row, in the language the agent works in; `visible_in` says where a reader sees the
 * review at all (decision 7), `written_in` where its text is, and `reviews_get` has every language
 * of the one it picks.
 */
final class ReviewsResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'reviews://catalog',
                'Reviews catalog',
                'Every review category in its order, with its reviews in that category\'s own order — who wrote '
                .'each, the stars, whether it is published, the languages its text is written in and the ones a '
                .'reader sees it in; the reviews in no category at the end. Read it before adding a review or a '
                .'category, so that you reuse rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $locale = $locales->current();

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'categories' => ReviewCategory::query()->ordered()->get()->map(fn (ReviewCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => $category->displayName($locale),
                'visible' => (bool) $category->is_visible,
                'reviews' => $this->rows(Review::query()->orderedIn((int) $category->getKey())->get()->all(), $locales),
            ])->values()->all(),
            'uncategorised' => $this->rows(
                Review::query()->whereDoesntHave('categories')->orderedIn()->get()->all(),
                $locales,
            ),
        ];
    }

    /**
     * @param  list<Review>  $reviews
     * @return list<array<string, mixed>>
     */
    private function rows(array $reviews, Locales $locales): array
    {
        $codes = $locales->codes();

        return array_map(static function (Review $review) use ($locales, $codes): array {
            return [
                'id' => (int) $review->getKey(),
                'name' => ReviewNames::of($review, $locales),
                'rating' => $review->rating,
                'published' => $review->published,
                'visible_in' => array_values(array_filter($codes, $review->visibleIn(...))),
                'written_in' => array_values(array_filter($codes, $review->writtenIn(...))),
            ];
        }, $reviews);
    }
}
