<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Types;

use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Contracts\CreatesWaybills;
use WebxUi\Delivery\Contracts\DescribesWaybill;
use WebxUi\Delivery\Contracts\LabelledMethodType;
use WebxUi\Delivery\Contracts\TracksShipments;
use WebxUi\Delivery\DeliveryContext;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\Tracking\Tracking;
use WebxUi\Delivery\NovaPoshta\Waybills\Waybills;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\Shipping\Label;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\TrackingState;
use WebxUi\Delivery\Shipping\Waybill;
use WebxUi\Delivery\Shipping\WaybillRequest;

/**
 * What the three types of the carrier share (§7): named by the dictionary, Ukraine only, and the
 * price «by the carrier's rates» without asking its API (decision 3) — unless the site gave the
 * method a price of its own, which the shop takes from the buyer and pays the carrier itself.
 *
 * Each makes a waybill (§8): the base's dialog draws the carrier's own fields of the kind
 * ({@see waybillFields()}) and no cash on delivery (decision 5); there is no label to print
 * (decision 12) — the manager prints it in the carrier's account by the number. Its parcels are
 * tracked by the base's schedule, a batch to one request (§9).
 */
abstract class NovaPoshtaType extends AbstractMethodType implements CreatesWaybills, DescribesWaybill, LabelledMethodType, TracksShipments
{
    /** 'branch', 'locker', 'courier': the kind after the dot and the key of its words. */
    abstract protected function kind(): string;

    public function key(): string
    {
        return NovaPoshtaCarrier::KEY.'.'.$this->kind();
    }

    public function carrier(): string
    {
        return NovaPoshtaCarrier::KEY;
    }

    public function countries(): array
    {
        return ['UA'];
    }

    public function quote(Method $method, DeliveryContext $context): Quote
    {
        return $method->price === null ? Quote::carrierRates() : Quote::fixed($method->price);
    }

    public function title(string $locale): string
    {
        return NovaPoshtaCarrier::word('types.'.$this->kind().'.title', $locale);
    }

    public function description(string $locale): ?string
    {
        return NovaPoshtaCarrier::word('types.'.$this->kind().'.description', $locale);
    }

    public function createWaybill(WaybillRequest $request): Waybill
    {
        return app(Waybills::class)->create($this->kind(), $request);
    }

    /** No printable form in v1 (decision 12): its link carries the key and must not reach a browser. */
    public function label(Shipment $shipment): ?Label
    {
        return null;
    }

    public function cancelWaybill(Shipment $shipment): void
    {
        app(Waybills::class)->cancel($shipment);
    }

    /**
     * The fields of §8.1 beyond the base's request, filled from the carrier's setting of the same
     * name: the weight, the seats (a parcel locker takes one), the cargo type (a locker takes no
     * cargo), the description, the date of sending and, for a locker, the sizes of its seat
     * (decision 17) up to the locker's 40 × 30 × 60 cm.
     */
    public function waybillFields(): array
    {
        $locker = $this->kind() === 'locker';
        $cargo = $locker ? Waybills::LOCKER_CARGO_TYPES : Waybills::CARGO_TYPES;
        $fields = [
            ['name' => 'weight', 'type' => 'number', 'required' => true, 'default' => 0.5, 'min' => 0.1, 'step' => 0.1, 'precision' => 1, 'label' => NovaPoshtaCarrier::words('waybill.weight')],
        ];

        if (! $locker) {
            $fields[] = ['name' => 'seats', 'type' => 'number', 'required' => true, 'default' => 1, 'min' => 1, 'step' => 1, 'precision' => 0, 'label' => NovaPoshtaCarrier::words('waybill.seats')];
        }

        $fields[] = [
            'name' => 'cargo_type',
            'type' => 'select',
            'required' => true,
            'default' => 'Parcel',
            'label' => NovaPoshtaCarrier::words('waybill.cargo_type'),
            'options' => array_map(static fn (string $value): array => ['value' => $value, 'label' => NovaPoshtaCarrier::word('settings.cargo_types.'.$value)], $cargo),
        ];

        if ($locker) {
            foreach (Waybills::LOCKER_SIZE as $name => $most) {
                $fields[] = ['name' => $name, 'type' => 'number', 'required' => true, 'min' => 1, 'max' => $most, 'step' => 1, 'precision' => 0, 'label' => NovaPoshtaCarrier::words('waybill.'.substr($name, 7))];
            }
        }

        $fields[] = ['name' => 'description', 'type' => 'text', 'required' => true, 'default' => NovaPoshtaCarrier::word('settings.description_default', 'uk'), 'label' => NovaPoshtaCarrier::words('waybill.description')];
        // Empty — today; the carrier takes a date up to three months ahead.
        $fields[] = ['name' => 'date', 'type' => 'text', 'label' => NovaPoshtaCarrier::words('waybill.date')];

        return $fields;
    }

    /**
     * @param  list<Shipment>  $shipments
     * @return array<int, TrackingState>
     */
    public function track(array $shipments): array
    {
        return app(Tracking::class)->track($shipments);
    }

    public function supportsCod(): bool
    {
        return false;
    }
}
