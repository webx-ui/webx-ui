<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Testing;

use WebxUi\Delivery\Shipping\Label;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Shipping\TrackingState;
use WebxUi\Delivery\Shipping\Waybill;
use WebxUi\Delivery\Shipping\WaybillRefused;
use WebxUi\Delivery\Shipping\WaybillRequest;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * The waybills and the tracking of «carrier A» (§18) for tests: what it was asked to make, the
 * numbers it gave (`A-0001`…), and where each parcel is — moved by the test with `move()`. A
 * refusal or a carrier that does not answer is there on request. A singleton of the test's
 * container; the method types of carrier A reach it through {@see ShipsByFakeParcels}.
 */
final class FakeParcels
{
    /** @var list<WaybillRequest> */
    public array $requests = [];

    /** @var list<string> numbers cancelled, in order */
    public array $cancelled = [];

    /** @var list<list<string|null>> numbers of each tracking call */
    public array $tracked = [];

    /** @var array<string, TrackingState> number → where the parcel is */
    private array $states = [];

    private ?string $refusal = null;

    private bool $down = false;

    private int $cost = 4500;

    /** Numbers given before this instance: a site keeps its parcels between requests, a test does not. */
    private int $issued = 0;

    /** Numbers go on after `$issued` earlier ones — on a site, the parcels of carrier A already in the table. */
    public function from(int $issued): self
    {
        $this->issued = max(0, $issued);

        return $this;
    }

    public function refuse(?string $reason = 'The carrier does not take parcels over 30 kg.'): self
    {
        $this->refusal = $reason;

        return $this;
    }

    /** The carrier does not answer: waybills and tracking fail as a network does. */
    public function down(bool $down = true): self
    {
        $this->down = $down;

        return $this;
    }

    public function move(string $number, ShipmentStatus $status, ?string $text = null): self
    {
        $this->states[$number] = new TrackingState($status, text: $text);

        return $this;
    }

    public function createWaybill(WaybillRequest $request): Waybill
    {
        $this->fail();

        if ($this->refusal !== null) {
            throw new WaybillRefused($this->refusal);
        }

        $this->requests[] = $request;
        $number = sprintf('A-%04d', $this->issued + count($this->requests));

        return new Waybill($number, $this->cost, meta: ['label' => "https://carrier-a.example.test/labels/{$number}.pdf"]);
    }

    public function label(Shipment $shipment): ?Label
    {
        $url = $shipment->meta['label'] ?? null;

        return is_string($url) ? Label::url($url) : null;
    }

    public function cancelWaybill(Shipment $shipment): void
    {
        $this->fail();
        $this->cancelled[] = (string) $shipment->number;
    }

    /**
     * @param  list<Shipment>  $shipments
     * @return array<int, TrackingState>
     */
    public function track(array $shipments): array
    {
        $this->fail();
        $this->tracked[] = array_map(static fn (Shipment $shipment): ?string => $shipment->number, $shipments);
        $states = [];

        foreach ($shipments as $shipment) {
            if ($shipment->number !== null && isset($this->states[$shipment->number])) {
                $states[$shipment->id] = $this->states[$shipment->number];
            }
        }

        return $states;
    }

    private function fail(): void
    {
        if ($this->down) {
            throw new CarrierUnavailable('carrier A did not answer');
        }
    }
}
