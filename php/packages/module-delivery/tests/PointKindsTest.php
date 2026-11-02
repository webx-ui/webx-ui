<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\PointKinds;
use WebxUi\Delivery\Types\StorePickup;

/** Kinds of points, and a kind the shop names itself becoming a method of its own (decision 9). */
final class PointKindsTest extends TestCase
{
    #[Test]
    public function the_built_in_kinds_speak_the_dictionary(): void
    {
        $kinds = $this->app->make(PointKinds::class);

        $this->assertSame(['branch' => 'Branch', 'locker' => 'Parcel locker', 'store' => 'Store', 'pickup' => 'Pick-up point'], $kinds->all('en'));
        $this->assertSame('Paczkomat', $kinds->label('locker', 'pl'));
    }

    #[Test]
    public function the_shop_names_a_kind_of_its_own_and_a_pick_up_of_it_works(): void
    {
        $kinds = $this->app->make(PointKinds::class);
        $kinds->define('showroom', ['en' => 'Showroom', 'pl' => 'Salon']);

        $this->assertTrue($kinds->has('showroom'));
        $this->assertSame('Salon', $kinds->label('showroom', 'pl'));
        $this->assertSame('Showroom', $kinds->all('en')['showroom']);

        $town = $this->town('Town A');
        $this->point($town, 'showroom');
        $this->method('showroom-pickup', StorePickup::KEY, ['point_kind' => 'showroom']);

        $offered = $this->app->make(Methods::class)->forLocality($this->context($town));
        $this->assertSame(['showroom-pickup'], array_map(static fn ($option): string => $option->method->key, $offered));
    }

    #[Test]
    public function a_built_in_kind_keeps_its_word(): void
    {
        $this->expectException(DeliveryException::class);

        $this->app->make(PointKinds::class)->define('store', ['en' => 'Shop']);
    }
}
