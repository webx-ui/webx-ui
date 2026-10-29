<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Orders\CartNotOrderable;

/**
 * The column of the totals (spec §23.1) from the one count of the request: the amounts as
 * `Money::format()` writes them — the checkout formats nothing itself — and only the rows there is
 * something to say in. Delivery's own row comes with its slot (§22).
 */
final readonly class CheckoutSummary
{
    public function __construct(
        private Money $money,
        private CheckoutSettings $settings,
    ) {}

    /** @return array<string, mixed> what `webx-orders::checkout.summary` prints */
    public function of(CheckoutContext $context): array
    {
        $state = $context->state;
        $array = $state->toArray();
        $discounts = array_values(array_filter(
            $state->adjustments,
            static fn (Adjustment $adjustment): bool => ($adjustment->meta['kind'] ?? null) !== 'delivery',
        ));
        $discount = array_sum(array_map(static fn (Adjustment $adjustment): int => $adjustment->amount, $discounts));
        $currency = $state->currency;

        return [
            'units' => $array['units'],
            'subtotal' => $array['subtotal_formatted'],
            'lines' => $array['lines'],
            'discount' => $discounts !== [] && $currency !== null ? $this->money->format($discount, $currency) : null,
            'discounts' => array_map(static fn (Adjustment $adjustment): string => $adjustment->label, $discounts),
            'benefits' => array_column($array['benefits'], 'label'),
            'hints' => array_column($array['hints'], 'text'),
            'tenders' => $array['tenders'],
            'payable' => $state->payable,
            'payable_formatted' => $array['payable_formatted'],
            'terms' => $this->settings->terms(),
            'documents' => $this->settings->terms() === CheckoutSettings::TERMS_NONE ? [] : $this->settings->documents($context->locale),
        ];
    }

    /**
     * The lines that keep the cart from becoming an order (§20.4): gone, unavailable, over the
     * limit, in another currency. A price that moved is not one of them — it shows by its line.
     *
     * @return list<string>
     */
    public function warnings(CheckoutContext $context): array
    {
        $messages = [];

        foreach ($context->state->warnings as $warning) {
            if ($warning->line !== null && in_array($warning->code, CartNotOrderable::BLOCKING, true)) {
                $messages[] = $warning->message;
            }
        }

        return array_values(array_unique($messages));
    }
}
