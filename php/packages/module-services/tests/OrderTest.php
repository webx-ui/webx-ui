<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Services\Models\Service;

/**
 * The two orders of decision 5: `position` for the whole list, `item_position` for the place of a
 * service inside one category — and the public pages, which list each in its own.
 */
final class OrderTest extends TestCase
{
    #[Test]
    public function a_new_service_goes_to_the_end_of_the_list(): void
    {
        $first = $this->service('first');
        $second = $this->service('second');

        $this->assertGreaterThan($first->position, $second->position);
    }

    #[Test]
    public function dragging_without_a_filter_writes_the_whole_list_and_nothing_else(): void
    {
        $category = $this->category('implants');
        [$a, $b, $c] = [$this->service('a'), $this->service('b'), $this->service('c')];

        foreach ([$a, $b, $c] as $service) {
            $service->syncCategories([$category->getKey()]);
        }

        $before = $this->placesIn($category->getKey());

        Ordering::move(Service::class, [$c->getKey(), $a->getKey(), $b->getKey()]);

        $this->assertSame([$c->getKey(), $a->getKey(), $b->getKey()], Service::query()->orderedIn()->pluck('id')->all());
        $this->assertSame($before, $this->placesIn($category->getKey()));
    }

    #[Test]
    public function dragging_with_a_category_writes_only_that_category(): void
    {
        $implants = $this->category('implants');
        $surgery = $this->category('surgery');
        [$a, $b] = [$this->service('a'), $this->service('b')];

        foreach ([$a, $b] as $service) {
            $service->syncCategories([$implants->getKey(), $surgery->getKey()]);
        }

        Ordering::move(Service::class, [$b->getKey(), $a->getKey()], $implants->getKey());

        $this->assertSame([$b->getKey(), $a->getKey()], Service::query()->orderedIn($implants->getKey())->pluck('id')->all());
        $this->assertSame([$a->getKey(), $b->getKey()], Service::query()->orderedIn($surgery->getKey())->pluck('id')->all());
        $this->assertSame([$a->getKey(), $b->getKey()], Service::query()->orderedIn()->pluck('id')->all());
    }

    #[Test]
    public function a_service_filed_into_a_category_takes_its_place_by_the_whole_list(): void
    {
        $category = $this->category('implants');
        [$a, $b, $c] = [$this->service('a'), $this->service('b'), $this->service('c')];

        $a->syncCategories([$category->getKey()]);
        $c->syncCategories([$category->getKey()]);
        // Nobody dragged inside the category, so b — between a and c in the list — lands between them.
        $b->syncCategories([$category->getKey()]);

        $this->assertSame([$a->getKey(), $b->getKey(), $c->getKey()], Service::query()->orderedIn($category->getKey())->pluck('id')->all());
    }

    #[Test]
    public function the_index_and_a_category_page_list_each_in_its_own_order(): void
    {
        $implants = $this->category('implants');
        $surgery = $this->category('surgery');
        [$a, $b, $c] = [$this->service('crowns'), $this->service('bridges'), $this->service('veneers')];

        foreach ([$a, $b] as $service) {
            $service->syncCategories([$implants->getKey(), $surgery->getKey()]);
        }

        // One order in each category, another in the list; c is in none.
        Ordering::move(Service::class, [$b->getKey(), $a->getKey()], $implants->getKey());
        Ordering::move(Service::class, [$c->getKey(), $a->getKey(), $b->getKey()]);

        $this->assertSame(['Bridges', 'Crowns'], $this->cards((string) $this->get('/services/implants')->assertOk()->getContent()));
        $this->assertSame(['Crowns', 'Bridges'], $this->cards((string) $this->get('/services/surgery')->assertOk()->getContent()));

        // The index: implants, then surgery, then what is in no category.
        $this->assertSame(
            ['Bridges', 'Crowns', 'Crowns', 'Bridges', 'Veneers'],
            $this->cards((string) $this->get('/services')->assertOk()->getContent()),
        );

        // Dragging the categories reorders the groups.
        Ordering::move($implants::class, [$surgery->getKey(), $implants->getKey()]);

        $this->assertSame(
            ['Crowns', 'Bridges', 'Bridges', 'Crowns', 'Veneers'],
            $this->cards((string) $this->get('/services')->getContent()),
        );
    }

    /**
     * @return array<int, int>
     */
    private function placesIn(int $category): array
    {
        /** @var array<int, int> $places */
        $places = DB::table('service_category')->where('category_id', $category)->orderBy('service_id')->pluck('item_position', 'service_id')->all();

        return $places;
    }

    /**
     * The titles of the cards on a page, in the order printed.
     *
     * @return list<string>
     */
    private function cards(string $page): array
    {
        preg_match_all('#<h3>([^<]+)</h3>#', $page, $matches);

        return $matches[1];
    }
}
