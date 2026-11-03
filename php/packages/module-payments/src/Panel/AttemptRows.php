<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use WebxUi\Admin\Support\Authors;
use WebxUi\Cart\Money;
use WebxUi\Localization\Locales;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Words;

/**
 * Attempts as the panel shows them (spec §13.2, §13.4): the journal of attempts, one attempt, and
 * the table of the order's tab. Money is a whole number in minor units and a `*_formatted` string.
 */
final class AttemptRows
{
    /** @var array<string, string>|null method key → title (empty: none in this language), read once per request */
    private ?array $titles = null;

    public function __construct(
        private readonly Money $money,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  Collection<int, Payment>  $payments  with `order` loaded where the number is wanted
     * @return list<array<string, mixed>>
     */
    public function lines(Collection $payments): array
    {
        return $payments->map(fn (Payment $payment): array => $this->line($payment))->values()->all();
    }

    /** @return array<string, mixed> */
    public function line(Payment $payment): array
    {
        $meta = $payment->meta ?? [];
        $currency = $payment->currency;

        return [
            'id' => $payment->id,
            'uuid' => $payment->uuid,
            'order_id' => $payment->order_id,
            'order_number' => $payment->relationLoaded('order') ? $payment->order?->number : null,
            'method_key' => $payment->method_key,
            'method_title' => $this->title($payment->method_key, $payment->type),
            'type' => $payment->type,
            'amount' => $payment->amount,
            'amount_formatted' => $this->money->format($payment->amount, $currency),
            'currency' => $currency,
            'status' => $payment->status->value,
            'problem' => $payment->problem,
            'resolved' => isset($meta['resolved_at']),
            'gateway_ref' => $payment->gateway_ref,
            'paid_amount' => $payment->paid_amount,
            'paid_amount_formatted' => $this->money->formatOrNull($payment->paid_amount, $currency),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'hold' => $payment->hold,
            'authorized_amount' => $payment->authorized_amount,
            'authorized_amount_formatted' => $this->money->formatOrNull($payment->authorized_amount, $currency),
            'hold_expires_at' => $payment->hold_expires_at?->toIso8601String(),
            'failure_reason' => $payment->failure_reason,
            'reference' => is_string($meta['reference'] ?? null) ? $meta['reference'] : null,
            'source' => $payment->source,
            'admin_id' => $payment->admin_id,
            'admin_name' => null,
            'created_at' => $payment->created_at?->toIso8601String(),
            'unfinished' => in_array($payment->status, [PaymentStatus::Created, PaymentStatus::Pending], true),
        ];
    }

    /**
     * Who marked the attempts paid by hand, by name — one query for the lines.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function named(array $lines, mixed $reader): array
    {
        $names = Authors::names($reader, array_column($lines, 'admin_id'));

        return array_map(static fn (array $line): array => [
            ...$line,
            'admin_name' => is_int($line['admin_id'] ?? null) && ($names[$line['admin_id']] ?? '') !== '' ? $names[$line['admin_id']] : null,
        ], $lines);
    }

    /**
     * The method's title; a method that is not in the list — a «Mark as paid» of an order placed
     * without one goes as a bank transfer — says its type's own title rather than its key.
     */
    private function title(string $key, string $type): string
    {
        $this->titles ??= PaymentMethod::query()->get(['key', 'title'])
            ->mapWithKeys(fn (PaymentMethod $method): array => [$method->key => Words::pick($method->title, $this->locales->current()) ?? ''])
            ->all();

        if (($this->titles[$key] ?? '') !== '') {
            return $this->titles[$key];
        }

        $own = 'webx-payments::methods.'.substr($type, (int) strrpos('.'.$type, '.')).'.title';

        return Lang::has($own) ? (string) __($own) : $key;
    }
}
