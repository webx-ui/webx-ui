<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Delivery\Contracts\MethodType;

/**
 * A method as the locality and the cart see it (§6.3): its price, the number of points it has
 * there, or why it is not offered. `forLocality()` gives the offered ones — `$disabled` among
 * them shown greyed out with a hint; `evaluate()` gives every one with the reason, for MCP.
 */
final readonly class MethodOption
{
    /** The method's type is not registered: its satellite was removed. */
    public const string TYPE_MISSING = 'type_missing';

    /** The carrier is switched off on the site, or not installed. */
    public const string CARRIER_OFF = 'carrier_off';

    /** The locality is in a country the method does not serve. */
    public const string COUNTRY = 'country';

    /** No active point of the method's carrier and kind in the locality. */
    public const string NO_POINTS = 'no_points';

    /** The carrier has no binding for the locality, or does not do this there. */
    public const string NOT_BOUND = 'not_bound';

    /** The type said the method does not serve this locality or cart. */
    public const string NOT_AVAILABLE = 'not_available';

    /** The quote was `Quote::unavailable()`; its reason is in `$quote`. */
    public const string NO_QUOTE = 'no_quote';

    /** Shown, but cannot be chosen: a carrier's courier where it has no streets (§8.2). */
    public const string NO_STREETS = 'no_streets';

    public function __construct(
        public Method $method,
        public ?MethodType $type,
        public ?Quote $quote = null,
        /** Points of the method in the locality; null — the method asks for none. */
        public ?int $points = null,
        /** Null — offered. */
        public ?string $unavailable = null,
        public bool $disabled = false,
        public ?string $hint = null,
    ) {}

    public function offered(): bool
    {
        return $this->unavailable === null;
    }

    /** Words for the agent: why a method is not offered (MCP `delivery_quote`, §15). */
    public function reason(): ?string
    {
        return match (true) {
            $this->unavailable === self::NO_QUOTE => $this->quote?->reason,
            $this->disabled => self::NO_STREETS,
            default => $this->unavailable,
        };
    }
}
