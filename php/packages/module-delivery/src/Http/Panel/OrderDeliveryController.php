<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cart\Money;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\DeliverySnapshot;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\Panel\DeliverySection;
use WebxUi\Delivery\Shipping\OrderDeliveryChange;
use WebxUi\Delivery\Shipping\Shipments;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Shipping\WaybillRefused;
use WebxUi\Geography\Names;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Panel\OrdersModule;

/**
 * The tab «Delivery» of the order's card (§14.2): the snapshot as it was placed, «Create waybill»
 * with the request to look over first, the parcels with their statuses, «Refresh tracking» and
 * the label. The tab is there for `delivery.view`; making, cancelling and tracking need
 * `delivery.ship`, which a manager of orders gets without the right to change the settings (§14.1).
 */
final class OrderDeliveryController
{
    public function __construct(
        private readonly Shipments $shipments,
        private readonly Locales $locales,
        private readonly Money $money,
        private readonly OrderDeliveryChange $change,
    ) {}

    public function show(Request $request, int $order): JsonResponse
    {
        return ApiResponse::data($this->card($request, $this->order($order)));
    }

    /** «Create waybill»: with `dry_run` — the request the carrier would get, nothing sent. */
    public function store(Request $request, int $order): JsonResponse
    {
        $model = $this->order($order);
        $changes = $this->changes($request);

        try {
            if ($request->boolean('dry_run')) {
                return ApiResponse::data(['request' => $this->shipments->request($model, $changes)->toArray()]);
            }

            $this->shipments->create($model, $changes, 'panel');
        } catch (WaybillRefused $refusal) {
            // A refusal about one field marks that field of the dialog; the rest stands above it.
            throw ValidationException::withMessages([$refusal->field ?? 'waybill' => [$refusal->getMessage()]]);
        }

        return ApiResponse::data($this->card($request, $model->refresh()), 201);
    }

    public function cancel(Request $request, int $shipment): JsonResponse
    {
        $row = DeliveryShipment::query()->findOrFail($shipment);

        try {
            $this->shipments->cancel($row, 'panel');
        } catch (WaybillRefused $refusal) {
            throw ValidationException::withMessages(['waybill' => [$refusal->getMessage()]]);
        }

        return ApiResponse::data($this->card($request, $this->order($row->order_id)));
    }

    /** «Refresh tracking»: the order's parcels now, whatever their time. */
    public function track(Request $request, int $order): JsonResponse
    {
        $model = $this->order($order);
        $this->shipments->track($this->shipments->ofOrder($model->id));

        return ApiResponse::data($this->card($request, $model));
    }

    /**
     * The printable label, asked of the carrier when it is opened: its page's address, or the PDF
     * it gave as base64 — the panel opens either without a second request.
     */
    public function label(int $shipment): JsonResponse
    {
        $label = $this->shipments->label(DeliveryShipment::query()->findOrFail($shipment));

        abort_if($label === null, 404);

        return ApiResponse::data($label->url !== null
            ? ['url' => $label->url]
            : ['pdf' => base64_encode((string) $label->pdf), 'filename' => $label->filename]);
    }

    private function order(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }

    /** @return array{recipient_first_name?: string|null, recipient_last_name?: string|null, recipient_phone?: string|null, declared_value?: int|null, cod?: int|null, extra?: array<string, mixed>} */
    private function changes(Request $request): array
    {
        /** @var array{recipient_first_name?: string|null, recipient_last_name?: string|null, recipient_phone?: string|null, declared_value?: int|null, cod?: int|null, extra?: array<string, mixed>} $valid */
        $valid = $request->validate([
            'recipient_first_name' => ['sometimes', 'nullable', 'string', 'max:190'],
            'recipient_last_name' => ['sometimes', 'nullable', 'string', 'max:190'],
            'recipient_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'declared_value' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2000000000'],
            'cod' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2000000000'],
            'extra' => ['sometimes', 'array'],
        ]);

        return $valid;
    }

    /** @return array<string, mixed> */
    public function card(Request $request, Order $order): array
    {
        $locale = $this->locales->current();
        $block = $order->delivery;
        $user = $request->user();
        $canShip = $user instanceof HasPermissions && $user->hasPermission(DeliverySection::SHIP);
        // «Change delivery» is an edit of the order: whoever edits the order may (orders.manage).
        $canChange = $user instanceof HasPermissions && $user->hasPermission(OrdersModule::MANAGE);
        $refusal = $block === null ? null : $this->change->refusal($order);
        $waybills = $block !== null && $this->shipments->waybills($order) !== null;
        $active = $this->shipments->active($order->id);
        $draft = null;

        if ($waybills && $canShip && $active === null) {
            try {
                // The carrier's own fields and whether it takes cash on delivery: the dialog draws them.
                $draft = [...$this->shipments->request($order)->toArray(), 'form' => $this->shipments->describe($order)];
            } catch (WaybillRefused) {
                $draft = null;
            }
        }

        return [
            'order_id' => $order->id,
            'currency' => $order->currency,
            'exponent' => $this->money->exponent($order->currency),
            'delivery' => $block === null ? null : $this->view($block, $locale),
            'waybills' => $waybills,
            'can_ship' => $canShip,
            'draft' => $draft,
            'active' => $active?->id,
            'change' => $block === null || ! $canChange ? null : [
                'refusal' => $refusal === null ? null : $this->change->refusalText($refusal, $locale),
                'locality' => is_array($block['locality'] ?? null) && isset($block['locality']['id']) ? [
                    'id' => (int) $block['locality']['id'],
                    'label' => (string) ($this->view($block, $locale)['locality'] ?? ''),
                ] : null,
                'method' => $block['method'] ?? null,
                'point' => is_array($block['point'] ?? null) && isset($block['point']['id']) ? (int) $block['point']['id'] : null,
                'address' => is_array($block['address'] ?? null) ? $block['address'] : null,
                'country' => $block['country'] ?? null,
            ],
            'shipments' => $this->shipments->ofOrder($order->id)->map(fn (DeliveryShipment $shipment): array => $this->shipment($shipment, $order->currency, $locale))->all(),
        ];
    }

    /**
     * The snapshot in the panel's language, from the copy alone — never from the live method.
     *
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function view(array $block, string $locale): array
    {
        $pick = static fn (mixed $names): ?string => Names::pick(is_array($names) ? $names : null, $locale);
        $locality = is_array($block['locality'] ?? null) ? $block['locality'] : [];
        $point = is_array($block['point'] ?? null) ? $block['point'] : null;
        $address = is_array($block['address'] ?? null) ? $block['address'] : null;
        $price = is_array($block['price'] ?? null) ? $block['price'] : [];
        $carrier = (string) ($block['carrier'] ?? '');
        $carriers = app(Carriers::class);
        $currency = is_string($price['currency'] ?? null) ? $price['currency'] : null;

        return [
            'method' => $pick($block['title'] ?? null) ?? (string) ($block['method'] ?? ''),
            'method_key' => $block['method'] ?? null,
            'carrier' => $carrier,
            'carrier_title' => $carrier !== '' && $carriers->has($carrier) ? $carriers->get($carrier)->title($locale) : $carrier,
            'locality' => implode(', ', array_filter([$pick($locality['name'] ?? null), $pick($locality['region'] ?? null)])),
            'point' => $point === null ? null : [
                'title' => $pick($point['title'] ?? null) ?? trim((string) ($point['number'] ?? '')),
                'number' => $point['number'] ?? null,
                'address' => $pick($point['address'] ?? null),
                'schedule' => $pick($point['schedule_text'] ?? null),
            ],
            'address' => $address === null ? null : implode(', ', array_filter([
                $address['street'] ?? null,
                $address['house'] ?? null,
                isset($address['apartment']) && $address['apartment'] !== '' ? $address['apartment'] : null,
                $address['postcode'] ?? null,
            ], static fn (mixed $part): bool => is_string($part) && $part !== '')),
            'note' => is_string($address['note'] ?? null) ? $address['note'] : null,
            'price' => DeliverySnapshot::priceText($price, $locale),
            'was' => isset($price['was']) && $currency !== null ? $this->money->format((int) $price['was'], $currency) : null,
            'estimate' => isset($price['estimate']) && $currency !== null ? $this->money->format((int) $price['estimate'], $currency) : null,
            'paid_by_store' => (bool) ($block['paid_by_store'] ?? false),
            'payer' => $block['payer'] ?? null,
            'waybill' => is_array($block['waybill'] ?? null) ? ($block['waybill']['number'] ?? null) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function shipment(DeliveryShipment $shipment, string $currency, string $locale): array
    {
        return [
            'id' => $shipment->id,
            'number' => $shipment->number,
            'carrier' => $shipment->carrier,
            'method' => $shipment->method_key,
            'payer' => $shipment->payer,
            'cost' => $shipment->cost,
            'cost_formatted' => $shipment->cost === null ? null : $this->money->format($shipment->cost, $currency),
            'status' => $shipment->status->value,
            'status_label' => $shipment->status->label($locale),
            'status_at' => $shipment->status_at?->toIso8601String(),
            'final' => $shipment->final,
            'next_check_at' => $shipment->next_check_at?->toIso8601String(),
            'cancellable' => ! $shipment->final,
            // The carrier is asked for the label only when it is opened: a list asks nobody.
            'label' => $shipment->status !== ShipmentStatus::Cancelled && $this->shipments->makesWaybills($shipment->type),
            'events' => array_map(static fn (array $event): array => [
                ...$event,
                'label' => ShipmentStatus::tryFrom((string) ($event['status'] ?? ''))?->label($locale) ?? (string) ($event['status'] ?? ''),
            ], $shipment->events),
            'created_at' => $shipment->created_at?->toIso8601String(),
        ];
    }
}
