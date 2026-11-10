<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use InvalidArgumentException;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;

/**
 * Where the extra fields of the checkout may stand (WEBX_UI_ORDER_FIELDS.md §4.1): a point in the
 * form of one step, by key. A field keeps the key as a string, so a module adds a place of its own
 * without a migration and without touching the snapshot:
 *
 *     $this->callAfterResolving(FieldPlaces::class, static function (FieldPlaces $places): void {
 *         $places->register('delivery-after', step: 'delivery', label: 'webx-delivery::checkout.fields-after');
 *     });
 *
 * The step's view prints the place with `@include('webx-orders::checkout.parts.fields', ['place' => …])`;
 * saving, checking and the snapshot are the checkout's, not the step's.
 */
final class FieldPlaces
{
    /** The places of the first version (§4.2), in the order a buyer meets them. */
    public const string CONTACTS = 'contacts';

    public const string BEFORE_COMMENT = 'extra-before-comment';

    public const string AFTER_COMMENT = 'extra-after-comment';

    /** Where a new field goes when nobody says. */
    public const string DEFAULT = self::BEFORE_COMMENT;

    /** The shape of a step's key (`CheckoutSteps`), and the length of `order_fields.place`. */
    public const string KEY = '/^[a-z][a-z0-9-]{0,31}$/';

    /** @var array<string, FieldPlace> */
    private array $places = [];

    /** A place of this key that exists is replaced: a project moves a place into its own step this way. */
    public function register(string $key, string $step, string $label): void
    {
        if (preg_match(self::KEY, $key) !== 1) {
            throw new InvalidArgumentException("A place of the checkout's fields cannot be called \"{$key}\": lowercase letters, digits and dashes, at most 32.");
        }

        if (preg_match(self::KEY, $step) !== 1 || $step === CheckoutSteps::CONFIRM) {
            throw new InvalidArgumentException("The place \"{$key}\" names no step it could stand in: \"{$step}\".");
        }

        $this->places[$key] = new FieldPlace($key, $step, $label);
    }

    public function has(string $key): bool
    {
        return isset($this->places[$key]);
    }

    public function find(string $key): ?FieldPlace
    {
        return $this->places[$key] ?? null;
    }

    /**
     * Every place in the order of its step, and inside a step in the order registered (§4.1).
     *
     * @param  array<string, int>  $positions  the steps' positions by key (`CheckoutSteps::positions()`);
     *                                         a place of a step that is not there goes last
     * @return list<FieldPlace>
     */
    public function all(array $positions = []): array
    {
        $places = array_values($this->places);
        $order = array_flip(array_keys($this->places));

        usort($places, static fn (FieldPlace $a, FieldPlace $b): int => [$positions[$a->step] ?? PHP_INT_MAX, $order[$a->key]]
            <=> [$positions[$b->step] ?? PHP_INT_MAX, $order[$b->key]]);

        return $places;
    }

    /**
     * The places in the form of one step, in the order registered.
     *
     * @return list<FieldPlace>
     */
    public function of(string $step): array
    {
        return array_values(array_filter($this->places, static fn (FieldPlace $place): bool => $place->step === $step));
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->places);
    }
}
