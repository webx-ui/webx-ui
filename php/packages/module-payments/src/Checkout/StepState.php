<?php

declare(strict_types=1);

namespace WebxUi\Payments\Checkout;

use WebxUi\Payments\MethodOption;
use WebxUi\Payments\PaymentContext;

/**
 * The payment step of one checkout (spec §10.1): the methods offered, the one chosen, and what to
 * tell the buyer — that the delivery comes first, or that their choice was dropped.
 */
final readonly class StepState
{
    /**
     * @param  list<MethodOption>  $options
     * @param  string|null  $selected  the stored choice, still valid or waiting for the delivery
     * @param  bool  $dropped  the delivery chosen made the stored choice unavailable: it is gone
     * @param  bool  $waiting  some methods wait for the delivery the buyer has not chosen yet
     */
    public function __construct(
        public PaymentContext $context,
        public array $options,
        public ?MethodOption $chosen,
        public ?string $selected,
        public bool $dropped,
        public bool $waiting,
    ) {}
}
