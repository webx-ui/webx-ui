<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Cart\Money;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Shipping\WaybillRequest;
use WebxUi\Delivery\Sync\Schedule;
use WebxUi\Geography\Names;

/**
 * The order's block `delivery` (§11.1): what was chosen, copied whole in every language of the
 * moment — the order never reads the live method, point or locality again (principle 3 of the
 * map), so a method renamed or repriced tomorrow does not change an order placed today. Ids are
 * kept for the shipment and analytics, never for showing.
 *
 * The waybill adds `waybill` to it later, through the order's `patchBlock()` (§11.2).
 */
final readonly class DeliverySnapshot
{
    /**
     * @param  array<string, string>  $title
     * @param  array{kind: string, amount: int|null, was: int|null, estimate: int|null, currency: string|null, in_total: bool}  $price
     * @param  array<string, mixed>  $locality
     * @param  array<string, mixed>|null  $point
     * @param  array<string, string|null>|null  $address
     */
    public function __construct(
        public string $method,
        public string $type,
        public string $carrier,
        public array $title,
        public array $price,
        public bool $paidByStore,
        /** WaybillRequest::SENDER | WaybillRequest::RECIPIENT — who pays the carrier (§9.3). */
        public string $payer,
        public string $country,
        public array $locality,
        public ?array $point = null,
        public ?array $address = null,
    ) {}

    public static function of(Selection $selection, Quote $quote, ?string $currency): self
    {
        $locality = $selection->locality;
        $point = $selection->point;
        $schedule = null;

        if ($point !== null) {
            $row = DeliveryPoint::query()->whereKey($point->id)->first(['schedule']);
            $schedule = Schedule::fromArray($row?->schedule);
        }

        return new self(
            method: $selection->method->key,
            type: $selection->method->type,
            carrier: $selection->method->carrier(),
            title: $selection->method->titles,
            price: [
                'kind' => $quote->kind->value,
                'amount' => match ($quote->kind) {
                    QuoteKind::Fixed => $quote->amount,
                    QuoteKind::Free => 0,
                    default => null,
                },
                'was' => $quote->was,
                'estimate' => $quote->estimate,
                'currency' => $currency,
                'in_total' => $quote->inTotal,
            ],
            paidByStore: $quote->isPaidByStore(),
            payer: $quote->payer === Payer::Store ? WaybillRequest::SENDER : WaybillRequest::RECIPIENT,
            country: $locality->country,
            locality: [
                'id' => $locality->id,
                'name' => $locality->names,
                'kind' => $locality->kind,
                'region' => $locality->regionNames,
            ],
            point: $point === null ? null : [
                'id' => $point->id,
                'external_id' => $point->externalId,
                'kind' => $point->kind,
                'number' => $point->number,
                'title' => $point->titles,
                'address' => $point->address,
                'schedule_text' => $schedule === null || $schedule->text === [] ? null : $schedule->text,
                'week' => $schedule?->week,
            ],
            address: $selection->address?->toArray(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'type' => $this->type,
            'carrier' => $this->carrier,
            'title' => $this->title,
            'price' => $this->price,
            'paid_by_store' => $this->paidByStore,
            'payer' => $this->payer,
            'country' => $this->country,
            'locality' => $this->locality,
            'point' => $this->point,
            'address' => $this->address,
        ];
    }

    /**
     * What the order accepts as its block (§6.4 of the orders spec): the common part it reads
     * itself — the method, its title, the price — and the rest the shape this class writes.
     *
     * @param  array<string, mixed>  $block
     * @return list<string>
     */
    public static function validate(array $block): array
    {
        $errors = [];

        if (! is_string($block['method'] ?? null) || $block['method'] === '') {
            $errors[] = 'method is a key';
        }

        if (! is_string($block['type'] ?? null) || ! str_contains($block['type'], '.')) {
            $errors[] = 'type is «carrier.kind»';
        }

        if (! is_array($block['title'] ?? null) || $block['title'] === []) {
            $errors[] = 'title is a text by language';
        }

        $price = $block['price'] ?? null;

        if (! is_array($price) || QuoteKind::tryFrom((string) ($price['kind'] ?? '')) === null || ! is_bool($price['in_total'] ?? null)) {
            $errors[] = 'price has a kind and in_total';
        }

        if (! in_array($block['payer'] ?? null, [WaybillRequest::SENDER, WaybillRequest::RECIPIENT], true)) {
            $errors[] = 'payer is sender or recipient';
        }

        if (! is_array($block['locality'] ?? null)) {
            $errors[] = 'locality is a copy of the locality';
        }

        if (isset($block['waybill']) && (! is_array($block['waybill']) || ! is_string($block['waybill']['number'] ?? null))) {
            $errors[] = 'waybill has a number';
        }

        return $errors;
    }

    /**
     * One line for the order's list and letters: «Pick up at a store, Town A · Free».
     *
     * @param  array<string, mixed>  $block
     */
    public static function summary(array $block, string $locale): string
    {
        $title = Names::pick(is_array($block['title'] ?? null) ? $block['title'] : null, $locale) ?? (string) ($block['method'] ?? '');
        $locality = is_array($block['locality'] ?? null) ? $block['locality'] : [];
        $where = Names::pick(is_array($locality['name'] ?? null) ? $locality['name'] : null, $locale);

        return implode(' · ', array_filter([
            implode(', ', array_filter([$title, $where])),
            self::priceText(is_array($block['price'] ?? null) ? $block['price'] : [], $locale),
        ]));
    }

    /**
     * «5,00 €», «Free» or «By carrier rates», as the step said it.
     *
     * @param  array<string, mixed>  $price
     */
    public static function priceText(array $price, string $locale): ?string
    {
        $word = static fn (string $key): string => (string) trans("webx-delivery::delivery.step.{$key}", [], $locale);
        $currency = is_string($price['currency'] ?? null) ? $price['currency'] : null;

        return match (QuoteKind::tryFrom((string) ($price['kind'] ?? ''))) {
            QuoteKind::Fixed => $currency === null
                ? (string) ($price['amount'] ?? '')
                : app(Money::class)->format((int) ($price['amount'] ?? 0), $currency),
            QuoteKind::Free => $word('free'),
            QuoteKind::CarrierRates => $word('carrier_rates'),
            default => null,
        };
    }
}
