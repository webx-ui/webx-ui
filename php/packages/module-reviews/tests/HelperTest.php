<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Localization\Locales;
use WebxUi\Reviews\Collections\ReviewsSource;

/**
 * `reviews()` — what a template may show of the section, as cards (§4.4) — and the
 * `wx-collection` source that hands over the same cards (§4.3, decision 8).
 */
final class HelperTest extends TestCase
{
    #[Test]
    public function every_visible_review_in_the_order_of_the_whole_list(): void
    {
        $this->review('Anna');
        $this->review('Draft', published: false);
        $this->review('Boris');
        $this->review('Binned')->delete();

        $this->assertSame(['Anna', 'Boris'], $this->names(reviews()));
        $this->assertCount(2, reviews());
        $this->assertFalse(reviews()->isEmpty());
    }

    #[Test]
    public function a_card_carries_what_a_template_prints_and_nothing_to_query(): void
    {
        $category = $this->category('Clinic');
        $this->picture();
        $review = $this->review('Anna Petrova', categories: [$category], attributes: [
            'job_title' => ['en' => 'CEO, Acme'],
            'rating' => 5,
            'reviewed_on' => '2026-09-20',
            'profile_url' => 'https://example.com/anna',
            'photo' => ['path' => 'media/ab/cd/anna.jpg'],
        ]);
        $review->mergeExtra(['city' => 'Kyiv'])->save();

        $card = reviews()->first();

        $this->assertIsArray($card);
        $this->assertSame((int) $review->getKey(), $card['id']);
        $this->assertSame('review-'.$review->getKey(), $card['anchor']);
        $this->assertSame([(int) $category->getKey()], $card['categories']);
        $this->assertSame('Anna Petrova', $card['name']);
        $this->assertSame('AP', $card['initials']);
        $this->assertSame('CEO, Acme', $card['job_title']);
        $this->assertSame('Anna Petrova liked it.', $card['text']);
        $this->assertSame(5, $card['rating']);
        $this->assertSame('2026-09-20', $card['date']);
        $this->assertSame('https://example.com/anna', $card['profile']);
        $this->assertIsArray($card['photo']);
        $this->assertIsString($card['photo']['url']);
        $this->assertSame(400, $card['photo']['width']);
        $this->assertSame(['city' => 'Kyiv'], $card['fields']);

        $plain = reviews()->except($review)->first();
        $this->assertNull($plain);

        $bare = $this->review('Boris');
        $card = reviews()->only([$bare->getKey()])->first();
        $this->assertIsArray($card);
        $this->assertSame('', $card['job_title']);
        $this->assertNull($card['rating']);
        $this->assertNull($card['date']);
        $this->assertNull($card['profile']);
        $this->assertNull($card['photo']);
    }

    #[Test]
    public function without_a_text_in_the_language_there_is_no_card_and_a_name_stands_in_from_the_default(): void
    {
        $both = $this->review('Anna', 'Анна довольна.');
        $this->review('English only');

        $this->assertSame(['Anna', 'English only'], $this->names(reviews()->locale('en')));

        $russian = reviews()->locale('ru')->get();

        $this->assertSame([(int) $both->getKey()], array_column($russian, 'id'), 'no other language stands in for the text');
        $this->assertSame('Anna', $russian[0]['name'], 'a name nobody translated is the name in the default language');
        $this->assertSame('Анна довольна.', $russian[0]['text']);

        $both->setTranslation('name', 'ru', 'Анна')->save();
        $this->assertSame('Анна', reviews()->locale('ru')->first()['name'] ?? null);
        $this->assertSame('А', reviews()->locale('ru')->first()['initials'] ?? null);
    }

    #[Test]
    public function one_category_reads_in_its_own_order_and_two_in_the_order_of_the_whole_list(): void
    {
        $clinic = $this->category('Clinic');
        $implants = $this->category('Implants');
        $anna = $this->review('Anna', categories: [$clinic, $implants]);
        $boris = $this->review('Boris', categories: [$clinic]);
        $vera = $this->review('Vera', categories: [$implants]);
        $this->review('Unfiled');

        // Inside the clinic, Boris was dragged above Anna.
        DB::table('review_category_review')->where('review_id', $boris->getKey())->update(['item_position' => -1]);

        $this->assertSame(['Boris', 'Anna'], $this->names(reviews()->in($clinic)));
        $this->assertSame(['Boris', 'Anna'], $this->names(reviews()->in($clinic->getKey())));
        $this->assertSame(['Boris', 'Anna'], $this->names(reviews()->in((string) $clinic->getKey())));
        $this->assertSame(
            ['Anna', 'Boris', 'Vera'],
            $this->names(reviews()->in([$clinic, $implants])),
            'each once, by the whole list',
        );
        $this->assertSame((int) $vera->getKey(), reviews()->in([$implants->getKey()])->get()[1]['id'] ?? null);
        $this->assertSame((int) $anna->getKey(), reviews()->in($implants)->first()['id'] ?? null);
    }

    #[Test]
    public function nothing_chosen_is_everything_and_a_name_that_is_no_id_is_nothing(): void
    {
        $this->review('Anna');

        $this->assertCount(1, reviews()->in(null));
        $this->assertCount(1, reviews()->in([]));
        $this->assertCount(1, reviews()->in(''));
        $this->assertCount(0, reviews()->in('clinic'), 'review categories have no slugs, and a typo is not "all"');
        $this->assertCount(0, reviews()->in(999));
    }

    #[Test]
    public function only_keeps_the_order_it_was_given_and_except_and_take_narrow_it(): void
    {
        $anna = $this->review('Anna');
        $boris = $this->review('Boris');
        $vera = $this->review('Vera');

        $this->assertSame(['Vera', 'Anna'], $this->names(reviews()->only([$vera->getKey(), $anna->getKey()])));
        $this->assertSame(['Anna', 'Vera'], $this->names(reviews()->except($boris)));
        $this->assertSame(['Anna'], $this->names(reviews()->except([])->take(1)));
        $this->assertSame(['Anna', 'Boris', 'Vera'], $this->names(reviews()->take(0)));
        $this->assertSame(['Anna', 'Boris', 'Vera'], $this->names(reviews()->take(null)));
        $this->assertSame([], $this->names(reviews()->only([])));
    }

    #[Test]
    public function the_limit_counts_what_is_shown(): void
    {
        $this->review('Not in Russian');
        $a = $this->review('A', 'А');
        $b = $this->review('B', 'Б');
        $this->review('C', 'В');

        $this->assertSame([(int) $a->getKey(), (int) $b->getKey()], array_column(reviews()->locale('ru')->take(2)->get(), 'id'));
    }

    #[Test]
    public function the_language_is_the_one_being_rendered_unless_asked(): void
    {
        $this->review('Anna', 'Анна довольна.');
        $this->review('English only');

        $this->app->make(Locales::class)->use('ru');

        $this->assertCount(1, reviews());
        $this->assertCount(2, reviews()->locale('en'));
    }

    #[Test]
    public function the_catalogue_groups_by_visible_category_in_each_ones_order(): void
    {
        $clinic = $this->category('Clinic');
        $hidden = $this->category('Hidden', visible: false);
        $empty = $this->category('Empty');
        $anna = $this->review('Anna', categories: [$clinic, $hidden]);
        $this->review('Boris', categories: [$clinic]);

        $groups = reviews()->categories();

        $this->assertSame(['Clinic'], array_column($groups, 'title'), 'hidden and empty categories are no group');
        $this->assertSame((int) $clinic->getKey(), $groups[0]['id']);
        $this->assertSame(['Anna', 'Boris'], array_column($groups[0]['reviews'], 'name'));

        $this->assertSame(['Boris'], array_column(reviews()->except($anna)->categories()[0]['reviews'], 'name'));
        $this->assertSame(['Anna'], array_column(reviews()->take(1)->categories()[0]['reviews'], 'name'));
        $this->assertSame([], reviews()->in((int) $empty->getKey())->categories());
        $this->assertSame([], reviews()->locale('ru')->categories(), 'a group of reviews nobody wrote in Russian is no group there');
    }

    #[Test]
    public function the_number_of_queries_does_not_grow_with_the_list(): void
    {
        $category = $this->category('Clinic');
        $this->picture('media/ab/cd/a.jpg');
        $this->picture('media/ab/cd/b.jpg');

        foreach (['A', 'B'] as $name) {
            $this->review($name, categories: [$category], attributes: ['photo' => ['path' => 'media/ab/cd/a.jpg']]);
        }

        $few = $this->queries(static fn () => reviews()->get());

        foreach (['C', 'D', 'E', 'F'] as $name) {
            $this->review($name, categories: [$category], attributes: ['photo' => ['path' => 'media/ab/cd/b.jpg']]);
        }

        $this->assertSame($few, $this->queries(static fn () => reviews()->get()));
    }

    #[Test]
    public function the_collection_source_hands_over_the_same_cards(): void
    {
        $clinic = $this->category('Clinic');
        $this->review('Anna', categories: [$clinic]);
        $this->review('Boris');

        $source = $this->app->make(CollectionSources::class)->find('reviews');

        $this->assertInstanceOf(ReviewsSource::class, $source);
        $this->assertSame('reviews/categories', $source->categories());
        $this->assertFalse($source->supportsMarkup(), 'decision 3: no markup');
        $this->assertSame('reviews.view', $source->permission());
        $this->assertSame(reviews()->locale('en')->get(), $source->items(new Selection, 'en'));
        $this->assertSame(['Anna'], array_column($source->items(new Selection([(int) $clinic->getKey()]), 'en'), 'name'));
        $this->assertSame(['Anna'], array_column($source->items(new Selection(limit: 1), 'en'), 'name'));
    }

    #[Test]
    public function a_new_review_goes_to_the_end_of_the_list(): void
    {
        $first = $this->review('First');
        DB::table('reviews')->where('id', $first->getKey())->update(['position' => 40]);

        $this->assertSame(41, $this->review('Second')->position);
    }

    /**
     * @param  iterable<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function names(iterable $cards): array
    {
        $names = [];

        foreach ($cards as $card) {
            $names[] = (string) $card['name'];
        }

        return $names;
    }

    private function queries(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
