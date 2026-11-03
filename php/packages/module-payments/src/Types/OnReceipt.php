<?php

declare(strict_types=1);

namespace WebxUi\Payments\Types;

use WebxUi\Payments\Method;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentSnapshot;
use WebxUi\Payments\Presentation;
use WebxUi\Payments\Words;

/**
 * `store.on_receipt` — payment on receipt (spec §12.1). The order does not wait for payment; the
 * manager marks it once the money is in. What the buyer reads follows the delivery: «Pay at the
 * counter» at the shop's own point, the carrier's fee under it for cash on delivery.
 */
final class OnReceipt extends StoreType
{
    public const string KEY = 'store.on_receipt';

    public const string METHOD = 'on-receipt';

    public function key(): string
    {
        return self::KEY;
    }

    public function methodKey(): string
    {
        return self::METHOD;
    }

    public function awaitsPayment(): bool
    {
        return false;
    }

    public function settings(): array
    {
        return [
            self::localizedField('title_pickup', 'webx-payments::methods.on_receipt.settings.title_pickup'),
            self::localizedField('cod_note', 'webx-payments::methods.on_receipt.settings.cod_note', multiline: true),
        ];
    }

    public function present(Method $method, PaymentContext $context): Presentation
    {
        $shipping = $context->shipping;

        if ($shipping?->storePickup === true) {
            return new Presentation(
                $method->words('title_pickup', $context->locale) ?? (string) trans('webx-payments::methods.on_receipt.pickup', [], $context->locale),
                $method->description($context->locale),
            );
        }

        $presented = parent::present($method, $context);

        if ($shipping?->cashOnDelivery === true) {
            // The carrier's fee for collecting the money is not in the sum: it says so (§7.3).
            return new Presentation(
                $presented->title,
                $method->words('cod_note', $context->locale) ?? (string) trans('webx-payments::methods.on_receipt.cod_note', [], $context->locale),
            );
        }

        return $presented;
    }

    public function snapshot(Method $method, PaymentContext $context): PaymentSnapshot
    {
        $snapshot = parent::snapshot($method, $context);

        if ($context->shipping?->storePickup !== true) {
            return $snapshot;
        }

        // The order reads the way the buyer chose it: «Pay at the counter».
        return new PaymentSnapshot(
            $snapshot->method,
            $snapshot->type,
            $snapshot->kind,
            Words::everywhere(is_array($method->settings['title_pickup'] ?? null) ? $method->settings['title_pickup'] : null, 'webx-payments::methods.on_receipt.pickup'),
            $snapshot->details,
        );
    }

    protected function words(): string
    {
        return 'webx-payments::methods.on_receipt';
    }
}
