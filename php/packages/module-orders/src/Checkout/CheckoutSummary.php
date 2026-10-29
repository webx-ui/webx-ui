<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;

/**
 * The column of the totals (spec §23.1) from the one count of the request: the amounts as
 * `Money::format()` writes them — the checkout formats nothing itself — and only the rows there is
 * something to say in. Delivery's own row comes from its slot (§22.2): its amount is in the chain
 * already, as the adjustment `meta.kind = delivery`, which is why the discount leaves it out.
 */
final readonly class CheckoutSummary
{
    public function __construct(
        private Money $money,
        private CheckoutSettings $settings,
        private CheckoutSteps $steps,
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
            'delivery' => $this->delivery($context),
            'payable' => $state->payable,
            'payable_formatted' => $array['payable_formatted'],
            'terms' => $this->settings->terms(),
            'documents' => $this->settings->terms() === CheckoutSettings::TERMS_NONE ? [] : $this->settings->documents($context->locale),
        ];
    }

    /**
     * The line «Delivery cost» (§23.1): null — no delivery step for this checkout; `choose` — the
     * step is there and nothing is chosen yet. Amounts as the cart formats them.
     *
     * @return array{kind: string, amount: string|null, was: string|null, estimate: string|null}|null
     */
    private function delivery(CheckoutContext $context): ?array
    {
        $step = $this->steps->find(CheckoutSlots::DELIVERY, $context);

        if (! $step instanceof SlotStep || $step->shipping($context) === null) {
            return null;
        }

        $line = $step->totalsLine($context);
        $currency = $context->state->currency;
        $format = fn (?int $minor): ?string => $minor !== null && $currency !== null ? $this->money->format($minor, $currency) : null;

        return $line === null
            ? ['kind' => 'choose', 'amount' => null, 'was' => null, 'estimate' => null]
            : [
                'kind' => $line->kind,
                'amount' => $line->kind === ShippingLine::FIXED ? $format($line->amount) : null,
                'was' => $line->kind === ShippingLine::FREE ? $format($line->was) : null,
                'estimate' => $line->kind === ShippingLine::CARRIER_RATES ? $format($line->estimate) : null,
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
