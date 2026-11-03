<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use InvalidArgumentException;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;

/**
 * At which delivery a method is offered (spec §7.3). Payments never asks the delivery module: it
 * reads only the neutral `ShippingChoice` the checkout hands it.
 *
 * | mode      | offered when                                                        |
 * | --------- | ------------------------------------------------------------------- |
 * | `any`     | always                                                              |
 * | `on_hand` | the carrier collects cash, or the buyer collects at the shop's point |
 * | `methods` | the delivery method is in the list                                  |
 * | `except`  | the delivery method is not in the list                              |
 *
 * Without a delivery at all (only services in the cart, no delivery module) `on_hand` stands for
 * «as agreed» and passes, `methods` does not, `except` does.
 */
final readonly class DeliveryRule
{
    public const string ANY = 'any';

    public const string ON_HAND = 'on_hand';

    public const string METHODS = 'methods';

    public const string EXCEPT = 'except';

    public const array MODES = [self::ANY, self::ON_HAND, self::METHODS, self::EXCEPT];

    /** @param  list<string>  $methods  delivery method keys */
    public function __construct(
        public string $mode = self::ANY,
        public array $methods = [],
    ) {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("[{$mode}] is not a delivery rule: one of ".implode(', ', self::MODES).'.');
        }
    }

    /**
     * A rule as stored; an unknown mode is the strictest reading — offered at no delivery the rule
     * could have meant, so a broken row hides a method rather than offering it wrongly.
     *
     * @param  array<array-key, mixed>  $rule
     */
    public static function fromArray(array $rule): self
    {
        $mode = $rule['mode'] ?? self::ANY;
        $methods = array_values(array_filter((array) ($rule['methods'] ?? []), 'is_string'));

        return in_array($mode, self::MODES, true)
            ? new self($mode, $methods)
            : new self(self::METHODS, []);
    }

    /** @return array{mode: string, methods?: list<string>} */
    public function toArray(): array
    {
        return in_array($this->mode, [self::METHODS, self::EXCEPT], true)
            ? ['mode' => $this->mode, 'methods' => $this->methods]
            : ['mode' => $this->mode];
    }

    /** Whether the answer changes with the chosen delivery: such a method waits for the delivery step. */
    public function dependsOnDelivery(): bool
    {
        return $this->mode !== self::ANY;
    }

    /** @param  ShippingChoice|null  $shipping  null — the order has no delivery at all */
    public function allows(?ShippingChoice $shipping): bool
    {
        return match ($this->mode) {
            self::ANY => true,
            self::ON_HAND => $shipping === null || $shipping->cashOnDelivery || $shipping->storePickup,
            self::METHODS => $shipping !== null && in_array($shipping->method, $this->methods, true),
            self::EXCEPT => $shipping === null || ! in_array($shipping->method, $this->methods, true),
            // The constructor lets no other mode in.
            default => false,
        };
    }
}
