<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * A listener of `OrderPlacing` will not have the order (§6.3) — the certificate is spent, the
 * stock is gone. The transaction is rolled back whole, and the checkout shows `message` as it is,
 * so it is written for the buyer, in the order's language.
 */
final class OrderRefused extends Refusal
{
    public function __construct(string $code, string $message)
    {
        parent::__construct($code, $message);
    }
}
