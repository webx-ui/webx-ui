<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Reviews\Panel\ReviewsModule;
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Models\SeoMeta;
use WebxUi\Services\Models\Service;

/**
 * Two categories and eight reviews (§4.9), and the reviews block put where a site would put it.
 *
 * Each review past the first few is there to show one rule: one is filed under both categories
 * and stands in a different place in each (decision 6), one is not published, one has its text in
 * one language and so is seen in no other (decision 7), one has a name in one language and is
 * shown under it everywhere (decision 7 again), one has no stars. None has a photo: the block draws
 * the initials instead, and the demo is how that gets seen.
 *
 * Then the block. Its type is the one this module offers, installed here when the site has not
 * taken it yet. With `module-pages`, a page `/reviews` with every review in a grid and the filter —
 * a page of reviews is an ordinary page (decision 2). With `module-services`, a slider of one
 * category at the end of one of the demo services: two of the four layouts on a live site.
 */
final class ReviewsDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
        private readonly BlockOffers $offers,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // Reviews with anything in them are somebody's reviews.
        if (Review::withTrashed()->exists() || ReviewCategory::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();

        /** @var array<string, Review> $reviews */
        $reviews = [];

        // In the order of the file, which is the order of the whole list.
        foreach ($this->list($document['reviews'] ?? null) as $input) {
            $review = $this->review($input, $ledger);

            if ($review !== null) {
                $reviews[(string) $input['key']] = $review;
            }
        }

        /** @var array<string, ReviewCategory> $categories */
        $categories = [];
        $position = 0;

        foreach ($this->list($document['categories'] ?? null) as $input) {
            $category = $this->category($input, $position++, $reviews, $ledger);

            if ($category !== null) {
                $categories[(string) $input['key']] = $category;
            }
        }

        if (! $this->blockType($ledger)) {
            return;
        }

        $page = $document['page'] ?? null;

        if (is_array($page) && $this->modules->has('pages') && class_exists(Page::class)) {
            $this->page($page, $ledger);
        }

        $service = $document['service'] ?? null;

        if (is_array($service) && $this->modules->has('services') && class_exists(Service::class)) {
            $this->service($service, $categories, $ledger);
        }
    }

    /**
     * The modules whose demo has to be there before this one's: the page and the service the
     * block goes into. Named only when installed — a name `requires()` gives that is not
     * installed skips the whole demo, and reviews have something to show without either.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [
            'blocks',
            ...array_values(array_filter(['pages', 'services'], $this->modules->has(...))),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function review(array $input, DemoLedger $ledger): ?Review
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $name = $this->words($input['name'] ?? null);

        if ($key === '' || $name === null) {
            return null;
        }

        $rating = $input['rating'] ?? null;

        $review = Review::query()->create([
            'name' => $name,
            'job_title' => $this->words($input['job_title'] ?? null),
            'text' => $this->words($input['text'] ?? null),
            'rating' => is_int($rating) && $rating >= 1 && $rating <= Review::MAX_RATING ? $rating : null,
            'reviewed_on' => is_string($input['reviewed_on'] ?? null) ? $input['reviewed_on'] : null,
            'profile_url' => is_string($input['profile_url'] ?? null) ? $input['profile_url'] : null,
            'published' => ($input['published'] ?? true) !== false,
        ]);

        $ledger->created($review, $key);

        return $review;
    }

    /**
     * A category, and its reviews in the order the file lists them — its own order, not the order
     * of the whole list.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, Review>  $reviews
     */
    private function category(array $input, int $position, array $reviews, DemoLedger $ledger): ?ReviewCategory
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($key === '' || $title === null) {
            return null;
        }

        $category = ReviewCategory::query()->create([
            'title' => $title,
            'is_visible' => true,
            'position' => $position,
        ]);

        $ledger->created($category, $key);

        $members = [];

        foreach ($this->list($input['reviews'] ?? null, strings: true) as $name) {
            if (isset($reviews[$name])) {
                $members[] = $reviews[$name];
            }
        }

        foreach ($members as $review) {
            $ids = $review->categories()->pluck('id')->map(intval(...))->all();
            $review->syncCategories([...$ids, (int) $category->getKey()]);
        }

        Ordering::move(
            Review::class,
            array_map(static fn (Review $review): int => (int) $review->getKey(), $members),
            (int) $category->getKey(),
        );

        return $category;
    }

    /**
     * The offered type, installed now if the site has not taken it — `webx:setup` usually has.
     * A type by that slug the site already has is left as it is, whatever it was made into.
     */
    private function blockType(DemoLedger $ledger): bool
    {
        foreach ($this->offers->documents([ReviewsModule::ID]) as $offer) {
            if ($this->offers->install($offer) !== BlockOffers::PRESENT) {
                $block = Block::query()->where('slug', $offer['slug'])->first();

                if ($block instanceof Block) {
                    $ledger->created($block, 'the '.$offer['slug'].' block type');
                }
            }
        }

        if (! Block::query()->where('slug', 'reviews')->where('is_enabled', true)->exists()) {
            $ledger->note('the reviews are in the panel, but no reviews block was put anywhere: the site has no enabled block type "reviews".');

            return false;
        }

        return true;
    }

    /**
     * `/reviews` under the home page: every review, a grid, the filter.
     *
     * @param  array<string, mixed>  $input
     */
    private function page(array $input, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : 'reviews';
        $home = Page::home();

        if (! $home instanceof Page) {
            return;
        }

        if (app(Reserved::class)->taken($slug) || Page::withTrashed()->whereTranslationLikeAny('slug', $slug)->exists()) {
            $ledger->note("no page of reviews was made: /{$slug} is already taken on this site.");

            return;
        }

        $block = is_array($input['block'] ?? null) ? $input['block'] : [];
        $title = $this->words($input['title'] ?? null) ?? [$this->locales->defaultCode() => 'Reviews'];

        $page = new Page([
            'title' => $title,
            // The same slug in every language the page is written in: a page with no slug in a
            // language has no address in it, and the second language is where decision 7 shows.
            'slug' => array_fill_keys(array_keys($title), $slug),
            'blocks' => [[
                'key' => 'reviews-all',
                'type' => 'reviews',
                'values' => array_filter([
                    'title' => $this->words($block['title'] ?? null),
                    'reviews' => ['categories' => [], 'limit' => null, 'filter' => true, 'markup' => false],
                    'layout' => 'grid',
                    'columns' => 3,
                    'all_label' => $this->words($block['all_label'] ?? null),
                ], static fn (mixed $value): bool => $value !== null),
            ]],
        ]);

        try {
            $page->appendTo($home);
        } catch (Throwable $refused) {
            $ledger->note("no page of reviews was made: {$refused->getMessage()}");

            return;
        }

        $ledger->created($page, $slug);
        $page->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $seo = $input['seo'] ?? null;

        if (is_array($seo)) {
            // A page with an empty card has no <title> at all (CLAUDE.md §4).
            $card = array_filter([
                'title' => $this->words($seo['title'] ?? null),
                'description' => $this->words($seo['description'] ?? null),
            ]);

            if ($card !== []) {
                $page->saveSeo($card);
                $meta = $page->seo()->first();

                if ($meta instanceof SeoMeta) {
                    $ledger->created($meta, "what /{$slug} says about itself");
                }
            }
        }

        $ledger->createdVersionsOf($page);
    }

    /**
     * A slider of one category at the end of one demo service — only a service the services demo
     * made a moment ago: somebody's real service is not the place for an example.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, ReviewCategory>  $categories
     */
    private function service(array $input, array $categories, DemoLedger $ledger): void
    {
        $category = $categories[(string) ($input['category'] ?? '')] ?? null;
        $ids = $ledger->idsOf('services', Service::class);

        if ($category === null || $ids === []) {
            return;
        }

        $slug = (string) ($input['slug'] ?? '');
        $service = Service::query()->whereKey($ids)->where('slug->'.$this->locales->defaultCode(), $slug)->first();

        if (! $service instanceof Service || $service->hasDraft()) {
            return;
        }

        $block = is_array($input['block'] ?? null) ? $input['block'] : [];

        $ledger->changed($service, ['blocks', 'draft', 'published_at'], "the reviews block in /{$slug}");

        $before = $service->versions()->pluck('id')->all();

        $service->setAttribute('blocks', [...$service->blocksTree(), [
            'key' => $slug.'-reviews',
            'type' => 'reviews',
            'values' => array_filter([
                'title' => $this->words($block['title'] ?? null),
                'reviews' => ['categories' => [(int) $category->getKey()], 'limit' => null, 'filter' => false, 'markup' => false],
                'layout' => 'slider',
                'columns' => 2,
                'autoplay' => true,
            ], static fn (mixed $value): bool => $value !== null),
        ]]);
        $service->save();
        $service->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        foreach ($service->versions()->whereKeyNot($before)->get() as $version) {
            $ledger->created($version, 'Service history');
        }
    }

    /**
     * The languages of the demo that the site has. A site with neither gets the English under its
     * own default, as the other demos do, rather than nameless reviews.
     *
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        if (! is_array($text)) {
            return null;
        }

        $codes = $this->locales->codes();
        $words = [];

        foreach ($text as $locale => $value) {
            if (is_string($value) && trim($value) !== '' && in_array((string) $locale, $codes, true)) {
                $words[(string) $locale] = trim($value);
            }
        }

        if ($words === [] && is_string($text['en'] ?? null) && trim($text['en']) !== '') {
            return [$this->locales->defaultCode() => trim($text['en'])];
        }

        return $words === [] ? null : $words;
    }

    /**
     * @return ($strings is true ? list<string> : list<array<string, mixed>>)
     */
    private function list(mixed $value, bool $strings = false): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, $strings ? is_string(...) : is_array(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/reviews.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('reviews.json is not a set of reviews.');
        }

        return $document;
    }
}
