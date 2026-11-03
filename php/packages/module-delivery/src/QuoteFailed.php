<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Throwable;

/**
 * The price of the chosen method could not be had while an order was being made (§9.2): no cache,
 * no fallback — the order is not created, and the buyer is asked to choose another method.
 */
final class QuoteFailed extends DeliveryException
{
    public function __construct(public readonly string $method, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function for(string $method, string $locale, ?Throwable $previous = null): self
    {
        return new self($method, (string) trans('webx-delivery::delivery.errors.no_quote', [], $locale), $previous);
    }
}
