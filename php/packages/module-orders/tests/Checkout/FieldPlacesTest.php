<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\FieldPlace;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Tests\TestCase;

/**
 * The registry of the places of the extra fields (WEBX_UI_ORDER_FIELDS.md §4.1, §4.2): the three of
 * the first version, a module's own, and the order a buyer meets them in.
 */
final class FieldPlacesTest extends TestCase
{
    #[Test]
    public function the_first_version_has_three_places_in_two_steps(): void
    {
        $places = $this->app->make(FieldPlaces::class);
        $steps = $this->app->make(CheckoutSteps::class)->positions();

        $this->assertSame(
            [FieldPlaces::CONTACTS => 'contacts', FieldPlaces::BEFORE_COMMENT => 'comment', FieldPlaces::AFTER_COMMENT => 'comment'],
            array_column(array_map(static fn (FieldPlace $place): array => (array) $place, $places->all($steps)), 'step', 'key'),
        );
        $this->assertSame([FieldPlaces::BEFORE_COMMENT, FieldPlaces::AFTER_COMMENT], array_map(static fn (FieldPlace $place): string => $place->key, $places->of('comment')));
        $this->assertSame('Additional, after the comment', $places->find(FieldPlaces::AFTER_COMMENT)?->name());
        $this->assertSame(FieldPlaces::BEFORE_COMMENT, FieldPlaces::DEFAULT);
    }

    #[Test]
    public function a_module_adds_a_place_ordered_by_its_step(): void
    {
        $this->app->afterResolving(FieldPlaces::class, static function (FieldPlaces $places): void {
            $places->register('delivery-after', step: 'delivery', label: 'After the delivery');
        });
        $this->app->forgetInstance(FieldPlaces::class);
        $places = $this->app->make(FieldPlaces::class);

        $this->assertTrue($places->has('delivery-after'));
        $this->assertSame('After the delivery', $places->find('delivery-after')?->name());
        // Registered last, it stands between the contacts (100) and the comment (900) by its step.
        $this->assertSame(
            [FieldPlaces::CONTACTS, 'delivery-after', FieldPlaces::BEFORE_COMMENT, FieldPlaces::AFTER_COMMENT],
            array_map(static fn (FieldPlace $place): string => $place->key, $places->all(['contacts' => 100, 'delivery' => 200, 'comment' => 900])),
        );
        // A step that is not there puts its places last.
        $this->assertSame('delivery-after', $places->all(['contacts' => 100, 'comment' => 900])[3]->key);
    }

    #[Test]
    public function a_place_registered_again_is_replaced(): void
    {
        $places = new FieldPlaces;
        $places->register('contacts', step: 'contacts', label: 'Contacts');
        $places->register('contacts', step: 'my-contacts', label: 'Contacts');

        $this->assertSame('my-contacts', $places->find('contacts')?->step);
        $this->assertSame(['contacts'], $places->keys());
    }

    #[Test]
    public function a_key_is_the_shape_of_a_step_key(): void
    {
        foreach ([['extra_after', 'comment'], ['Extra', 'comment'], [str_repeat('a', 33), 'comment'], ['extra', 'confirm']] as [$key, $step]) {
            try {
                (new FieldPlaces)->register($key, step: $step, label: 'Place');
                $this->fail("A place \"{$key}\" in \"{$step}\" was taken.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
