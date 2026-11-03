<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Carbon\CarbonImmutable;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentLine;
use WebxUi\Payments\StartRequest;
use WebxUi\Payments\Words;

/**
 * What the base tells a gateway to open (spec §6.3): the attempt, the order's number and an
 * address back — and of the buyer and the order's lines only what the type's `needs()` names
 * (decisions 42, 43).
 */
final readonly class StartRequests
{
    public function __construct(private PaymentLinks $links) {}

    public function of(Order $order, Payment $payment, ChargesOnline $type, Method $method): StartRequest
    {
        $needs = $type->needs();
        $locale = (string) $order->locale;
        $number = (string) $order->number;
        $minutes = $method->settings['expires_minutes'] ?? null;

        return new StartRequest(
            uuid: $payment->uuid,
            orderNumber: $number,
            amount: $payment->amount,
            currency: $payment->currency,
            description: (string) trans('webx-payments::buyer.pay.description', ['number' => $number], $locale),
            returnUrl: $this->links->returnUrl($payment),
            notifyUrl: $this->links->notifyUrl($method->key),
            locale: $locale,
            hold: $payment->hold && $type instanceof CapturesPayments,
            expiresAt: is_numeric($minutes) && (int) $minutes > 0 ? CarbonImmutable::now()->addMinutes((int) $minutes) : null,
            customerEmail: $needs->email ? self::text($order->customer_email) : null,
            customerPhone: $needs->phone ? self::text($order->customer_phone) : null,
            customerName: $needs->name ? self::text($order->customerName()) : null,
            lines: $needs->lines ? $this->lines($order, $payment) : [],
        );
    }

    /**
     * The order's lines as its snapshot keeps them — less what was refunded. When they do not add
     * up to the attempt's amount (a top-up, a tender, a delivery price), one line «Order N» for the
     * whole amount: a gateway checks the sum of its receipt (decision 43).
     *
     * @return list<PaymentLine>
     */
    private function lines(Order $order, Payment $payment): array
    {
        $lines = [];
        $sum = 0;

        /** @var iterable<OrderItem> $items */
        $items = $order->items()->whereNull('parent_id')->orderBy('position')->get();

        foreach ($items as $item) {
            $qty = (int) $item->qty - (int) $item->refunded_qty;

            if ($qty <= 0 || (int) $item->qty <= 0) {
                continue;
            }

            $total = (int) $item->total;

            if ($total % (int) $item->qty !== 0) {
                $lines = null;
                break;
            }

            $unit = intdiv($total, (int) $item->qty);
            $name = Words::pick(is_array($item->name) ? $item->name : [], (string) $order->locale) ?? (string) $item->sku;
            $lines[] = new PaymentLine($name, $qty, $unit, $unit * $qty, $item->sku);
            $sum += $unit * $qty;
        }

        if ($lines === null || $lines === [] || $sum !== $payment->amount) {
            $number = (string) $order->number;

            return [new PaymentLine((string) trans('webx-payments::buyer.pay.description', ['number' => $number], (string) $order->locale), 1, $payment->amount, $payment->amount)];
        }

        return $lines;
    }

    private static function text(?string $text): ?string
    {
        $text = $text !== null ? trim($text) : null;

        return $text === '' ? null : $text;
    }
}
