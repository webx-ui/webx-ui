<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Shipping;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebxUi\Delivery\CarrierLocalities;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\DescribesWaybill;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\Events\ShipmentCreated;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Geography\Names;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Refusal;

/**
 * The parcels of orders (§11.2–§11.4): a waybill made by the manager's button or an agent —
 * never by itself (decision 34) — its cancellation, its label, and the tracking the schedule
 * asks for. Only where `module-orders` is installed: without orders there is nothing to ship.
 *
 * The order is told through its own API: the waybill's number into its block `delivery`
 * (`patchBlock`), «received» as its milestone (`mark`), a parcel refused or come back as a line
 * of its history (`note`) — the manager decides what follows.
 */
final class Shipments
{
    /** Shipments a type is asked about at once (§11.3). */
    public const int BATCH = 100;

    /** A parcel nothing has happened to for this long is not asked about again. */
    public const int STALE_DAYS = 60;

    public function __construct(
        private readonly MethodTypes $types,
        private readonly Orders $orders,
        private readonly Dispatcher $events,
        private readonly CarrierLocalities $localities,
    ) {}

    /** @return Collection<int, DeliveryShipment> newest first */
    public function ofOrder(int $order): Collection
    {
        return DeliveryShipment::query()->where('order_id', $order)->orderByDesc('id')->get();
    }

    /** The order's method type, when it can make a waybill; the shop's own methods cannot. */
    public function waybills(Order $order): ?CreatesWaybills
    {
        $type = $order->delivery['type'] ?? null;

        if (! is_string($type) || ! $this->types->has($type)) {
            return null;
        }

        $made = $this->types->get($type);

        return $made instanceof CreatesWaybills ? $made : null;
    }

    /** Whether the method type makes waybills — and so gives labels and cancels them. */
    public function makesWaybills(string $type): bool
    {
        return $this->types->has($type) && $this->types->get($type) instanceof CreatesWaybills;
    }

    /**
     * What a waybill of this order would be (§11.2), with what the manager changed in the dialog:
     * the recipient's names and phone, the declared value, the cash on delivery and the carrier's own
     * fields of its type ({@see DescribesWaybill}).
     *
     * @param  array{recipient_first_name?: string|null, recipient_last_name?: string|null, recipient_phone?: string|null, declared_value?: int|null, cod?: int|null, extra?: array<string, mixed>}  $changes
     *
     * @throws WaybillRefused the order has no delivery its carrier makes waybills for
     */
    public function request(Order $order, array $changes = []): WaybillRequest
    {
        $block = $order->delivery;
        $locale = $order->locale;

        if ($block === null || $block === []) {
            throw new WaybillRefused(self::word('no_delivery', $locale));
        }

        if ($this->waybills($order) === null) {
            throw new WaybillRefused(self::word('no_waybills', $locale));
        }

        // The order keeps the names apart (decision 40 of the orders' spec): the recipient when
        // another person gets the parcel, the buyer otherwise — never split by spaces.
        $recipient = $order->recipient;
        $first = trim((string) ($recipient === null ? $order->customer_first_name : ($recipient['first_name'] ?? '')));
        $last = trim((string) ($recipient === null ? $order->customer_last_name : ($recipient['last_name'] ?? '')));
        $phone = $recipient !== null ? ($recipient['phone'] ?? $order->customer_phone) : $order->customer_phone;
        $carrier = (string) ($block['carrier'] ?? '');
        $price = is_array($block['price'] ?? null) ? $block['price'] : [];
        $payer = $block['payer'] ?? null;

        if (! in_array($payer, [WaybillRequest::SENDER, WaybillRequest::RECIPIENT], true)) {
            // A block from before the snapshot said it: who pays is what §9.3 says of its price.
            $payer = ($price['in_total'] ?? false) || ($block['paid_by_store'] ?? false) || ($price['kind'] ?? null) === 'free'
                ? WaybillRequest::SENDER
                : WaybillRequest::RECIPIENT;
        }

        $lines = $order->items()
            ->where('shippable', true)
            ->whereNull('removed_at')
            ->orderBy('position')
            ->get(['name', 'sku', 'qty', 'refunded_qty', 'price'])
            ->map(static fn (OrderItem $item): array => [
                'name' => (string) Names::pick($item->name, $locale),
                'sku' => $item->sku,
                'qty' => max(0, $item->qty - $item->refunded_qty),
                'price' => $item->price,
            ])
            ->filter(static fn (array $line): bool => $line['qty'] > 0)
            ->values()
            ->all();

        $type = $this->waybills($order);
        $sender = DeliveryCarrier::query()->find($carrier)->settings ?? [];
        $cod = isset($changes['cod']) ? max(0, (int) $changes['cod']) : null;

        if ($cod !== null && $type instanceof DescribesWaybill && ! $type->supportsCod()) {
            throw new WaybillRefused(self::word('no_cod', $locale));
        }

        $localityId = is_array($block['locality'] ?? null) && isset($block['locality']['id']) ? (int) $block['locality']['id'] : null;

        return new WaybillRequest(
            orderId: $order->id,
            orderNumber: $order->number,
            delivery: $block,
            recipientFirstName: self::filled($changes['recipient_first_name'] ?? null) ?? $first,
            recipientLastName: array_key_exists('recipient_last_name', $changes) ? (self::filled($changes['recipient_last_name']) ?? '') : $last,
            recipientPhone: array_key_exists('recipient_phone', $changes) ? self::filled($changes['recipient_phone']) : $phone,
            recipientEmail: $order->customer_email,
            sender: $sender,
            lines: $lines,
            declaredValue: max(0, (int) ($changes['declared_value'] ?? $order->total)),
            currency: $order->currency,
            payer: $payer,
            cod: $cod,
            // A waybill names the carrier's own town; the snapshot keeps only the shared one.
            carrierLocality: $localityId === null ? null : $this->localities->externalId($carrier, $localityId),
            extra: $type instanceof DescribesWaybill ? self::extra($type, $sender, (array) ($changes['extra'] ?? []), $locale) : [],
        );
    }

    /**
     * What the dialog draws besides the base's lines: the carrier's own fields of the order's type
     * with their values to start from, and whether the cash on delivery is asked at all.
     *
     * @return array{fields: list<array<string, mixed>>, cod: bool}
     */
    public function describe(Order $order): array
    {
        $type = $this->waybills($order);

        if (! $type instanceof DescribesWaybill) {
            return ['fields' => [], 'cod' => true];
        }

        $sender = DeliveryCarrier::query()->find((string) ($order->delivery['carrier'] ?? ''))->settings ?? [];
        $fields = [];

        foreach ($type->waybillFields() as $field) {
            if (is_string($field['name'] ?? null)) {
                $fields[] = [...$field, 'default' => $sender[$field['name']] ?? $field['default'] ?? null];
            }
        }

        return ['fields' => $fields, 'cod' => $type->supportsCod()];
    }

    /**
     * The carrier's own fields of a waybill: what the manager gave, or the carrier's setting of that
     * name, or the field's default; a name the type does not declare is dropped.
     *
     * @param  array<string, mixed>  $sender
     * @param  array<array-key, mixed>  $given
     * @return array<string, mixed>
     *
     * @throws WaybillRefused a required field is empty, a number is not one
     */
    private static function extra(DescribesWaybill $type, array $sender, array $given, ?string $locale): array
    {
        $extra = [];

        foreach ($type->waybillFields() as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name)) {
                continue;
            }

            $value = array_key_exists($name, $given) ? $given[$name] : ($sender[$name] ?? $field['default'] ?? null);
            $label = self::fieldLabel($field, $name, $locale);

            if ($value === null || $value === '') {
                if (($field['required'] ?? false) === true) {
                    throw WaybillRefused::field('extra.'.$name, self::word('field_required', $locale, ['field' => $label]));
                }

                $extra[$name] = null;

                continue;
            }

            if (($field['type'] ?? null) === 'number') {
                if (! is_numeric($value)) {
                    throw WaybillRefused::field('extra.'.$name, self::word('field_number', $locale, ['field' => $label]));
                }

                $value = $value + 0;
            }

            $extra[$name] = $value;
        }

        return $extra;
    }

    /** @param  array<string, mixed>  $field */
    private static function fieldLabel(array $field, string $name, ?string $locale): string
    {
        $label = $field['label'] ?? null;

        if (is_array($label)) {
            $label = $label[$locale ?? app()->getLocale()] ?? $label['en'] ?? reset($label);
        }

        return is_string($label) && $label !== '' ? $label : $name;
    }

    /**
     * Make the waybill at the carrier (§11.2): a row of `delivery_shipments`, the number in the
     * order's block and its history, `ShipmentCreated`. One waybill an order at a time: a second
     * one comes after the first is cancelled.
     *
     * @param  array{recipient_first_name?: string|null, recipient_last_name?: string|null, recipient_phone?: string|null, declared_value?: int|null, cod?: int|null, extra?: array<string, mixed>}  $changes
     *
     * @throws WaybillRefused
     */
    public function create(Order $order, array $changes, string $source): DeliveryShipment
    {
        $request = $this->request($order, $changes);

        if (trim($request->recipientFirstName) === '') {
            throw WaybillRefused::field('recipient_first_name', self::word('no_name', $order->locale));
        }
        $type = $this->waybills($order);
        $locale = $order->locale;

        if ($type === null) {
            throw new WaybillRefused(self::word('no_waybills', $locale));
        }

        if ($this->active($order->id) !== null) {
            throw new WaybillRefused(self::word('made_already', $locale));
        }

        try {
            $waybill = $type->createWaybill($request);
        } catch (CarrierUnavailable $failure) {
            Log::warning("Delivery: the carrier «{$request->carrier()}» did not make a waybill for order {$order->id}: {$failure->getMessage()}");

            throw new WaybillRefused($failure->failure->words($locale), previous: $failure);
        }

        $now = CarbonImmutable::now();

        $shipment = DB::transaction(function () use ($order, $request, $waybill, $now): DeliveryShipment {
            return DeliveryShipment::query()->create([
                'order_id' => $order->id,
                'carrier' => $request->carrier(),
                'type' => $request->type(),
                'method_key' => (string) ($request->delivery['method'] ?? ''),
                'number' => $waybill->number,
                'payer' => $request->payer,
                'cost' => $waybill->cost,
                'status' => $waybill->status,
                'status_at' => $now,
                'final' => $waybill->status->final(),
                'next_check_at' => $now->addMinutes($waybill->status->nextCheckMinutes()),
                'events' => [['status' => $waybill->status->value, 'at' => $now->toIso8601String(), 'text' => null]],
                'meta' => $waybill->meta,
            ]);
        });

        $this->orders->patchBlock($order, 'delivery', ['waybill' => [
            'number' => $waybill->number,
            'shipment_id' => $shipment->id,
            'payer' => $request->payer,
        ]], $source);

        $this->events->dispatch(new ShipmentCreated(Shipment::of($shipment), $source));

        return $shipment;
    }

    /**
     * Cancel the waybill at the carrier: the parcel is «cancelled» and the tracking stops; a
     * line in the order's history says so. A parcel that is final already is not cancelled.
     *
     * @throws WaybillRefused
     */
    public function cancel(DeliveryShipment $shipment, string $source): DeliveryShipment
    {
        $order = Order::query()->find($shipment->order_id);
        $locale = $order->locale ?? null;
        $type = $this->typeOf($shipment);

        if ($shipment->final) {
            throw new WaybillRefused(self::word('final', $locale));
        }

        if (! $type instanceof CreatesWaybills) {
            throw new WaybillRefused(self::word('no_waybills', $locale));
        }

        try {
            $type->cancelWaybill(Shipment::of($shipment));
        } catch (CarrierUnavailable $failure) {
            throw new WaybillRefused($failure->failure->words($locale), previous: $failure);
        }

        $this->move($shipment, new TrackingState(ShipmentStatus::Cancelled), CarbonImmutable::now());

        if ($order !== null) {
            $this->orders->note($order, self::word('cancelled_note', $order->locale, ['number' => (string) $shipment->number]), $source);
        }

        return $shipment;
    }

    /** The printable label, when the carrier gives one. */
    public function label(DeliveryShipment $shipment): ?Label
    {
        $type = $this->typeOf($shipment);

        return $type instanceof CreatesWaybills ? $type->label(Shipment::of($shipment)) : null;
    }

    /**
     * Ask the carriers about these parcels now — «Refresh tracking» of the order's tab — or, with
     * null, about every parcel that is due (§11.3): not final, its `next_check_at` passed. A
     * hundred at a time to a type; a type that fails is logged and asked again at its next time.
     *
     * @param  iterable<DeliveryShipment>|null  $shipments
     * @return int how many changed their status
     */
    public function track(?iterable $shipments = null, ?DateTimeInterface $now = null): int
    {
        $now = $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now();
        $changed = 0;

        if ($shipments !== null) {
            $list = collect($shipments)->filter(static fn (DeliveryShipment $shipment): bool => ! $shipment->final);

            foreach ($list->groupBy('type') as $type => $group) {
                foreach ($group->chunk(self::BATCH) as $batch) {
                    $changed += $this->ask((string) $type, $batch->values()->all(), $now);
                }
            }

            return $changed;
        }

        // By the index (final, next_check_at); a type at a time, so a batch is one carrier's.
        $types = DeliveryShipment::query()->where('final', false)->where('next_check_at', '<=', $now)->distinct()->pluck('type');

        foreach ($types as $type) {
            DeliveryShipment::query()
                ->where('final', false)
                ->where('next_check_at', '<=', $now)
                ->where('type', $type)
                ->orderBy('id')
                ->chunkById(self::BATCH, function (Collection $batch) use ($type, $now, &$changed): void {
                    /** @var list<DeliveryShipment> $list */
                    $list = $batch->values()->all();
                    $changed += $this->ask((string) $type, $list, $now);
                });
        }

        return $changed;
    }

    /** The order's waybill that still counts: not cancelled. */
    public function active(int $order): ?DeliveryShipment
    {
        return DeliveryShipment::query()
            ->where('order_id', $order)
            ->where('status', '!=', ShipmentStatus::Cancelled->value)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<DeliveryShipment>  $batch
     */
    private function ask(string $type, array $batch, CarbonImmutable $now): int
    {
        $tracker = $this->types->has($type) ? $this->types->get($type) : null;

        if (! $tracker instanceof TracksShipments) {
            // A type that cannot track — or is gone — is not asked again until a person does.
            foreach ($batch as $shipment) {
                $shipment->forceFill(['next_check_at' => null])->save();
            }

            return 0;
        }

        try {
            $states = $tracker->track(array_map(Shipment::of(...), $batch));
        } catch (Throwable $failure) {
            Log::warning("Delivery: tracking of «{$type}» failed: {$failure->getMessage()}");

            foreach ($batch as $shipment) {
                $shipment->forceFill(['next_check_at' => $now->addMinutes($shipment->status->nextCheckMinutes())])->save();
            }

            return 0;
        }

        $changed = 0;

        foreach ($batch as $shipment) {
            $state = $states[$shipment->id] ?? null;

            if ($state !== null && $this->move($shipment, $state, $now)) {
                $changed++;

                continue;
            }

            $stale = $shipment->status_at !== null && $shipment->status_at->lt($now->subDays(self::STALE_DAYS));
            $shipment->forceFill([
                'final' => $stale,
                'next_check_at' => $stale ? null : $now->addMinutes($shipment->status->nextCheckMinutes()),
            ])->save();
        }

        return $changed;
    }

    /** Write the state; tell the order of «received», a refusal or a return. True — the status changed. */
    private function move(DeliveryShipment $shipment, TrackingState $state, CarbonImmutable $now): bool
    {
        $at = $state->at !== null ? CarbonImmutable::instance($state->at) : $now;
        $changed = $state->status !== $shipment->status;

        $events = $shipment->events;

        if ($changed || ($state->text !== null && ($events[0]['text'] ?? null) !== $state->text)) {
            array_unshift($events, ['status' => $state->status->value, 'at' => $at->toIso8601String(), 'text' => $state->text]);
        }

        $shipment->forceFill([
            'status' => $state->status,
            'status_at' => $changed ? $at : $shipment->status_at,
            'final' => $state->final,
            'next_check_at' => $state->final ? null : $now->addMinutes($state->status->nextCheckMinutes()),
            'events' => array_slice($events, 0, DeliveryShipment::EVENTS),
        ])->save();

        if ($changed && $state->status !== ShipmentStatus::Cancelled) {
            $this->tellOrder($shipment, $state, $at);
        }

        return $changed;
    }

    private function tellOrder(DeliveryShipment $shipment, TrackingState $state, CarbonImmutable $at): void
    {
        $order = Order::query()->find($shipment->order_id);

        if ($order === null) {
            return;
        }

        $source = 'delivery:'.$shipment->carrier;

        try {
            match ($state->status) {
                // Set once: a manager's mark made before stays, and the answer says so (§11.4).
                ShipmentStatus::Received => $this->orders->mark($order, Milestone::Received, $source, $at),
                ShipmentStatus::Refused, ShipmentStatus::Returned => $this->orders->note(
                    $order,
                    self::word('status_note', $order->locale, [
                        'number' => (string) $shipment->number,
                        'status' => $state->status->label($order->locale),
                        'text' => (string) $state->text,
                    ]),
                    $source,
                ),
                default => null,
            };
        } catch (Refusal $refusal) {
            // A cancelled order is not «received»: the line in its history is what is left to say.
            $this->orders->note($order, self::word('status_note', $order->locale, [
                'number' => (string) $shipment->number,
                'status' => $state->status->label($order->locale),
                'text' => $refusal->getMessage(),
            ]), $source);
        }
    }

    private function typeOf(DeliveryShipment $shipment): ?object
    {
        return $this->types->has($shipment->type) ? $this->types->get($shipment->type) : null;
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param  array<string, string>  $replace */
    private static function word(string $key, ?string $locale, array $replace = []): string
    {
        return rtrim((string) trans('webx-delivery::delivery.shipments.'.$key, $replace, $locale));
    }
}
