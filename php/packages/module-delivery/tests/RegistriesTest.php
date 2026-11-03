<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Tests\Fakes\FakeBranchType;
use WebxUi\Delivery\Tests\Fakes\FakeCarrierA;
use WebxUi\Delivery\Tests\Fakes\FakeCourierType;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Delivery\UnknownMethodType;

final class RegistriesTest extends TestCase
{
    #[Test]
    public function the_shop_and_its_two_types_are_built_in(): void
    {
        $types = $this->app->make(MethodTypes::class);
        $carriers = $this->app->make(Carriers::class);

        $this->assertSame(['store'], $carriers->keys());
        $this->assertSame('The shop', $carriers->get('store')->title('en'));
        $this->assertSame([StorePickup::KEY, StoreCourier::KEY], $types->keys());
        $this->assertSame(Asks::Point, $types->get(StorePickup::KEY)->asks());
        $this->assertSame(['store', 'pickup'], $types->get(StorePickup::KEY)->pointKinds());
        $this->assertSame(Asks::Address, $types->get(StoreCourier::KEY)->asks());
        $this->assertSame([StorePickup::KEY, StoreCourier::KEY], array_map(static fn ($type): string => $type->key(), $types->ofCarrier('store')));
    }

    #[Test]
    public function a_satellite_registers_its_carrier_and_types_from_its_provider(): void
    {
        // What a satellite's boot() does: whatever order the providers ran in.
        $this->app->make(Carriers::class)->register(FakeCarrierA::class);
        $this->app->make(MethodTypes::class)->register(FakeBranchType::class);
        $this->app->make(MethodTypes::class)->register(new FakeCourierType);

        $types = $this->app->make(MethodTypes::class);
        $this->assertTrue($types->has('carrier-a.branch'));
        $this->assertSame(['carrier-a.courier', 'carrier-a.branch'], array_map(static fn ($type): string => $type->key(), $types->ofCarrier('carrier-a')));
        $this->assertSame('carrier-a', $types->get('carrier-a.courier')->carrier());
    }

    #[Test]
    public function an_unknown_type_names_the_known_ones(): void
    {
        $this->expectException(UnknownMethodType::class);
        $this->expectExceptionMessage('Delivery method type carrier-z.branch is not installed. Known types: store.pickup, store.courier.');

        $this->app->make(MethodTypes::class)->get('carrier-z.branch');
    }

    #[Test]
    public function a_type_registered_twice_is_refused(): void
    {
        $types = $this->app->make(MethodTypes::class);
        $types->keys();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('store.pickup is registered twice');

        $types->register(new StorePickup);
    }

    #[Test]
    public function a_type_without_its_carrier_is_a_mistake_of_the_build(): void
    {
        $this->app->make(MethodTypes::class)->register(FakeBranchType::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('belongs to carrier carrier-a, which is not registered');

        $this->app->make(MethodTypes::class)->all();
    }

    #[Test]
    public function a_key_is_carrier_dot_kind(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(MethodTypes::class)->register(new class extends AbstractMethodType
        {
            public function key(): string
            {
                return 'Store Pickup';
            }

            public function asks(): Asks
            {
                return Asks::Nothing;
            }
        });
    }

    #[Test]
    public function a_carrier_registered_twice_is_refused(): void
    {
        $this->expectException(LogicException::class);

        $carriers = $this->app->make(Carriers::class);
        $carriers->register(new FakeCarrierA);
        $carriers->register(new FakeCarrierA);
    }
}
