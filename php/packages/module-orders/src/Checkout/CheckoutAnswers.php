<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

/**
 * The codes the confirmation answers with when no order comes of it (spec §24.2, §28): the script
 * reads them off the JSON, an agent off `orders://checkout` and `orders_checkout_preview`. A step
 * that lacks something is a 422 with its errors by field — `StepError::code`, not one of these.
 */
final class CheckoutAnswers
{
    public const string ORIGIN = 'checkout.origin';

    public const string RETRY = 'checkout.retry';

    public const string STALE = 'checkout.stale';

    public const string TOO_MANY = 'checkout.too_many';

    public const string NOT_ORDERABLE = 'checkout.cart_not_orderable';

    public const string PAYABLE_CHANGED = 'checkout.payable_changed';

    public const string REFUSED = 'checkout.refused';

    /** @var array<string, array{status: int, when: string}> */
    public const array ALL = [
        self::ORIGIN => ['status' => 403, 'when' => 'the request came from another site (Origin, Referer)'],
        self::RETRY => ['status' => 422, 'when' => 'sent sooner than a person reads the page, or without its mark of time'],
        self::STALE => ['status' => 409, 'when' => 'the key of the page is not the cart\'s now: another tab, another sign-in'],
        self::TOO_MANY => ['status' => 429, 'when' => 'the cart made too many orders this hour'],
        self::NOT_ORDERABLE => ['status' => 409, 'when' => 'a line cannot be bought (gone, unavailable, over its limit, another currency)'],
        self::PAYABLE_CHANGED => ['status' => 409, 'when' => 'the order comes to more than the page showed — the buyer confirms the new amount; less is placed at once'],
        self::REFUSED => ['status' => 409, 'when' => 'a module refused the order inside its transaction (OrderRefused)'],
    ];
}
