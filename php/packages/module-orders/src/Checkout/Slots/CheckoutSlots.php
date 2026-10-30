<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Slots;

use InvalidArgumentException;
use LogicException;

/**
 * The slots of the checkout (spec §22.1): two keys, `delivery` and `payment`, filled by the modules
 * that are installed. A third slot would be a new block of the order — a decision, not a
 * registration (§6.4). The registry is in the package always; with the checkout off nobody asks it.
 */
final class CheckoutSlots
{
    public const string DELIVERY = 'delivery';

    public const string PAYMENT = 'payment';

    /** The places of the slots among the steps (§20.2): payment after delivery, it may depend on it. */
    public const array POSITIONS = [self::DELIVERY => 200, self::PAYMENT => 300];

    /** @var array<string, Slot> */
    private array $slots = [];

    public function register(Slot $slot): void
    {
        $key = $slot->key();

        if (! array_key_exists($key, self::POSITIONS)) {
            throw new InvalidArgumentException("The checkout has no slot «{$key}»: only ".implode(', ', array_keys(self::POSITIONS)).'.');
        }

        if ($key === self::DELIVERY && ! $slot instanceof ShippingSlot) {
            throw new InvalidArgumentException('The slot «delivery» is a '.ShippingSlot::class.'.');
        }

        if ($key === self::PAYMENT && ! $slot instanceof PaymentSlot) {
            throw new InvalidArgumentException('The slot «payment» is a '.PaymentSlot::class.'.');
        }

        if (isset($this->slots[$key])) {
            throw new LogicException("Two modules register the slot «{$key}» of the checkout.");
        }

        $this->slots[$key] = $slot;
    }

    public function get(string $key): ?Slot
    {
        return $this->slots[$key] ?? null;
    }

    public function shipping(): ?ShippingSlot
    {
        $slot = $this->get(self::DELIVERY);

        return $slot instanceof ShippingSlot ? $slot : null;
    }

    public function payment(): ?PaymentSlot
    {
        $slot = $this->get(self::PAYMENT);

        return $slot instanceof PaymentSlot ? $slot : null;
    }

    /** @return array<string, Slot> by key */
    public function all(): array
    {
        return $this->slots;
    }
}
