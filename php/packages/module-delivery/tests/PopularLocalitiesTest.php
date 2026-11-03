<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Geography\Editor;
use WebxUi\Geography\LocalitySuggestion;
use WebxUi\Geography\Removal;

/** The popular towns of «Where to deliver?» (decision 40). */
final class PopularLocalitiesTest extends TestCase
{
    private function popular(): PopularLocalities
    {
        return $this->app->make(PopularLocalities::class);
    }

    /** @return list<string> */
    private function buttons(string $country = 'XX'): array
    {
        return array_map(
            static fn (LocalitySuggestion $suggestion): string => $suggestion->label,
            $this->popular()->suggestions($country, 'en'),
        );
    }

    #[Test]
    public function an_empty_list_gives_no_buttons(): void
    {
        $this->town('Town A');

        $this->assertSame([], $this->popular()->ids());
        $this->assertSame([], $this->buttons());
    }

    #[Test]
    public function the_list_keeps_the_order_it_was_given(): void
    {
        $a = $this->town('Town A');
        $b = $this->town('Town B', 'Region B');
        $c = $this->town('Town C');

        $this->assertSame([$c->id, $a->id, $b->id], $this->popular()->set([$c->id, $a->id, $b->id, $a->id]));
        $this->assertSame(['Town C, Region A', 'Town A, Region A', 'Town B, Region B'], $this->buttons());

        // Dragged into another order.
        $this->popular()->set([$b->id, $c->id]);
        $this->assertSame(['Town B, Region B', 'Town C, Region A'], $this->buttons());
    }

    #[Test]
    public function a_town_is_added_at_a_place_and_removed(): void
    {
        $a = $this->town('Town A');
        $b = $this->town('Town B');
        $c = $this->town('Town C');

        $this->popular()->add($a->id);
        $this->popular()->add($b->id);
        $this->assertSame([$c->id, $a->id, $b->id], $this->popular()->add($c->id, 0));
        // One already in the list moves.
        $this->assertSame([$c->id, $b->id, $a->id], $this->popular()->add($a->id));
        $this->assertSame([$c->id, $a->id], $this->popular()->remove($b->id));
    }

    #[Test]
    public function an_id_that_is_no_locality_changes_nothing(): void
    {
        $a = $this->town('Town A');
        $this->popular()->set([$a->id]);

        try {
            $this->popular()->set([$a->id, 999]);
            $this->fail('An unknown locality was taken.');
        } catch (DeliveryException $refusal) {
            $this->assertSame('No locality with id 999: the popular towns are not changed.', $refusal->getMessage());
        }

        $this->assertSame([$a->id], $this->popular()->ids());
    }

    #[Test]
    public function a_deleted_town_leaves_the_list_and_a_switched_off_one_waits_in_it(): void
    {
        $a = $this->town('Town A');
        $b = $this->town('Town B');
        $c = $this->town('Town C');
        $this->popular()->set([$a->id, $b->id, $c->id]);
        $editor = $this->app->make(Editor::class);

        // Nothing but the list holds it: a popular town is not a reason to keep a locality.
        $this->assertSame(Removal::Deleted, $editor->removeLocality($b));
        $this->assertSame([$a->id, $c->id], $this->popular()->ids());
        $this->assertSame(['Town A, Region A', 'Town C, Region A'], $this->buttons());

        $editor->deactivate($a);
        $this->assertSame([$a->id, $c->id], $this->popular()->ids());
        $this->assertSame(['Town C, Region A'], $this->buttons());

        $editor->activate($a);
        $this->assertSame(['Town A, Region A', 'Town C, Region A'], $this->buttons());
    }

    #[Test]
    public function the_buttons_are_the_countrys_own_in_two_queries(): void
    {
        $a = $this->town('Town A');
        $elsewhere = $this->app->make(Editor::class)->createLocality(['country' => 'XY', 'kind' => 'town', 'names' => ['en' => 'Town Y']]);
        $this->popular()->set([$elsewhere->id, $a->id]);

        DB::enableQueryLog();
        $this->assertSame(['Town A, Region A'], $this->buttons('XX'));
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['Town Y'], $this->buttons('XY'));
    }
}
