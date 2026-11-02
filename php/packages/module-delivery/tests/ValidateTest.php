<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Selection;
use WebxUi\Delivery\SelectionInput;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;

/** The default check of what the buyer entered (§6.1, `AbstractMethodType::validate()`). */
final class ValidateTest extends TestCase
{
    #[Test]
    public function a_point_has_to_be_the_methods_active_point_in_the_chosen_locality(): void
    {
        $townA = $this->town('Town A');
        $townB = $this->town('Town B');
        $method = Method::of($this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']));
        $type = $this->app->make(MethodTypes::class)->get(StorePickup::KEY);
        $context = $this->context($townA);

        $here = $this->point($townA);
        $elsewhere = $this->point($townB);
        $otherKind = $this->point($townA, 'pickup');
        $closed = $this->point($townA, 'store', ['active' => false]);
        $removed = $this->point($townA, 'store', ['removed_at' => now()]);

        $this->assertSame(['point' => 'Choose a pick-up point.'], $type->validate($method, $context, new SelectionInput));

        foreach ([$elsewhere, $otherKind, $closed, $removed] as $point) {
            $this->assertSame(
                ['point' => 'Pick-up point is no longer available, choose another one.'],
                $type->validate($method, $context, new SelectionInput(point: $point->id)),
            );
        }

        $selection = $type->validate($method, $context, new SelectionInput(point: $here->id));
        $this->assertInstanceOf(Selection::class, $selection);
        $this->assertSame($here->id, $selection->point?->id);
        $this->assertSame('1 Example street', $selection->point->address('en'));
        $this->assertNull($selection->address);
    }

    #[Test]
    public function the_shops_courier_takes_any_street_and_never_an_id(): void
    {
        $town = $this->town('Town A');
        $method = Method::of($this->method('our-courier', StoreCourier::KEY));
        $type = $this->app->make(MethodTypes::class)->get(StoreCourier::KEY);
        $context = $this->context($town);

        $this->assertSame(
            ['street' => 'Enter the street.', 'house' => 'Enter the house number.'],
            $type->validate($method, $context, new SelectionInput),
        );

        $selection = $type->validate($method, $context, SelectionInput::fromArray(['address' => [
            'street' => ' Any street the buyer types ', 'street_id' => 's-1', 'house' => '12', 'apartment' => '', 'note' => 'Ring twice',
        ]]));

        $this->assertInstanceOf(Selection::class, $selection);
        $this->assertSame([
            'street' => 'Any street the buyer types',
            'street_id' => null,
            'house' => '12',
            'apartment' => null,
            'postcode' => null,
            'note' => 'Ring twice',
        ], $selection->address?->toArray());
    }

    #[Test]
    public function a_long_field_is_refused_with_its_limit(): void
    {
        $town = $this->town('Town A');
        $method = Method::of($this->method('our-courier', StoreCourier::KEY));
        $type = $this->app->make(MethodTypes::class)->get(StoreCourier::KEY);

        $errors = $type->validate($method, $this->context($town), new SelectionInput(street: 'Example street', house: str_repeat('1', 33)));

        $this->assertSame(['house' => 'At most 32 characters.'], $errors);
    }
}
