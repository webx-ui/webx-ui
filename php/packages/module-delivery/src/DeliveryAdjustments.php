<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSource;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;

/**
 * Delivery in the cart's chain (§9.3): a surcharge after every discount, so that a discount in
 * per cent never touches it, and before the wallet, so that a certificate pays for it too
 * (decision 28). Only a price the buyer pays the shop is a line — `fixed(N > 0)`; «Free» and «By
 * carrier rates» add nothing.
 *
 * Silent in the widget of the cart (`Purpose::Cart`) and with nothing chosen. Benefits are counted
 * after the sums in the chain, so the source asks the benefit sources itself for
 * `shipping.paid_by_store` with what it sees — the lines and the discounts before it.
 */
final class DeliveryAdjustments implements AdjustmentSource
{
    /** After the discounts, which register with lower numbers. */
    public const int PRIORITY = 1000;

    public const string SOURCE = 'delivery';

    public function __construct(private readonly Container $app) {}

    public function adjustments(CartContext $cart): array
    {
        if ($cart->purpose === Purpose::Cart || $cart->cart->id === null) {
            return [];
        }

        // Scoped: resolved per request, never kept by this source, which lives in a registry.
        $chosen = $this->app->make(CartDelivery::class)->chosen($this->state($cart), $cart->cart->id, $cart->purpose);

        if ($chosen === null || $chosen->quote === null || ! $chosen->quote->inTotal) {
            return [];
        }

        return [new Adjustment(
            source: self::SOURCE,
            label: (string) trans('webx-delivery::delivery.totals.delivery', [], $cart->cart->locale),
            amount: (int) $chosen->quote->amount,
            meta: [
                'kind' => 'delivery',
                'method' => $chosen->method->key,
                'type' => $chosen->method->type,
                'quote' => $chosen->quote->kind->value,
            ],
        )];
    }

    /** The cart as far as the chain has counted it, with the benefits it would have now. */
    private function state(CartContext $cart): CartState
    {
        return new CartState(
            currency: $cart->cart->currency,
            locale: $cart->cart->locale,
            lines: $cart->lines,
            lineAdjustments: [],
            subtotal: $cart->subtotal,
            adjustments: $cart->adjustments,
            total: $cart->total,
            tenders: [],
            payable: $cart->total,
            benefits: $this->benefits($cart),
            hints: [],
            warnings: [],
            money: $this->app->make(Money::class),
        );
    }

    /**
     * What the benefit sources say of the cart so far. One that throws is skipped, as the chain
     * skips it — and the chain itself warns of it at its own step; making an order, it throws.
     *
     * @return list<Benefit>
     */
    private function benefits(CartContext $cart): array
    {
        $benefits = [];

        foreach ($this->app->make(BenefitSources::class)->all() as $source) {
            try {
                $found = $source->benefits($cart);
            } catch (Throwable $e) {
                if ($cart->purpose === Purpose::Order) {
                    throw $e;
                }

                $this->app->make(LoggerInterface::class)->warning('Delivery: the benefit source '.$source::class.' failed; delivery is priced without its benefits.', ['exception' => $e]);

                continue;
            }

            foreach ($found as $benefit) {
                if ($benefit instanceof Benefit) {
                    $benefits[] = $benefit;
                }
            }
        }

        return $benefits;
    }
}
