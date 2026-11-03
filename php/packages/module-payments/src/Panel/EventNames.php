<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use Throwable;
use WebxUi\Admin\Support\Authors;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Words;

/**
 * What a person reads in place of the journal's keys (spec §20.7): the gateway's and the method's
 * titles beside their keys, the order's number and the attempt's uuid, and an administrator's name
 * where a decision says «admin #N». Only the screen changes — the event keeps what it stored.
 *
 * A few queries for a whole page of lines, never one per line.
 */
final class EventNames
{
    /** The journal writes who marked a payment by id; the screen says the name. */
    public const string ADMIN = '/\badmin #(\d+)\b/';

    public function __construct(
        private readonly Gateways $gateways,
        private readonly Locales $locales,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines  lines of `EventRows`
     * @return list<array<string, mixed>>
     */
    public function apply(array $lines, mixed $reader): array
    {
        if ($lines === []) {
            return [];
        }

        $locale = $this->locales->current();
        $orders = array_values(array_unique(array_filter(array_column($lines, 'order_id'))));
        $numbers = $orders === [] ? [] : Order::query()->whereKey($orders)->pluck('number', 'id')->all();
        $payments = array_values(array_unique(array_filter(array_column($lines, 'payment_id'))));
        $uuids = $payments === [] ? [] : Payment::query()->whereKey($payments)->pluck('uuid', 'id')->all();
        $keys = array_values(array_unique(array_filter(array_column($lines, 'method_key'))));
        $methods = $keys === [] ? [] : PaymentMethod::query()->whereIn('key', $keys)->get(['key', 'title'])
            ->mapWithKeys(static fn (PaymentMethod $method): array => [$method->key => Words::pick($method->title, $locale)])
            ->all();

        $admins = [];

        foreach ($lines as $line) {
            if (is_string($line['message'] ?? null) && preg_match_all(self::ADMIN, $line['message'], $found) > 0) {
                foreach ($found[1] as $id) {
                    $admins[] = (int) $id;
                }
            }
        }

        $names = $admins === [] ? [] : Authors::names($reader, $admins);

        return array_map(function (array $line) use ($numbers, $uuids, $methods, $names, $locale): array {
            $message = $line['message'] ?? null;

            if (is_string($message) && $names !== []) {
                $message = preg_replace_callback(self::ADMIN, static fn (array $m): string => isset($names[(int) $m[1]]) && $names[(int) $m[1]] !== '' ? $names[(int) $m[1]] : $m[0], $message);
            }

            return [
                ...$line,
                'message' => $message,
                'order_number' => $numbers[$line['order_id'] ?? 0] ?? null,
                'payment_uuid' => $uuids[$line['payment_id'] ?? 0] ?? null,
                'gateway_title' => is_string($line['gateway'] ?? null) ? $this->gateway($line['gateway'], $locale) : null,
                'method_title' => is_string($line['method_key'] ?? null) ? ($methods[$line['method_key']] ?? null) : null,
            ];
        }, $lines);
    }

    private function gateway(string $key, string $locale): ?string
    {
        try {
            return $this->gateways->has($key) ? $this->gateways->get($key)->title($locale) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
