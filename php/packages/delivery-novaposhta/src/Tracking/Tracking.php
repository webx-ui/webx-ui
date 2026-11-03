<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tracking;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Shipping\TrackingState;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * Where the parcels of Nova Poshta are (§9, decisions 9, 16): one `getStatusDocuments` for the
 * base's batch of up to a hundred, the carrier's codes translated by the table of §9.2.
 *
 * When to stop asking is the base's: a final status ({@see ShipmentStatus::final()}) ends the
 * tracking, «received» sets the order's milestone once (§11.4 of the delivery spec), 60 days
 * without a move make a parcel final. A return is a new waybill at the carrier, so it is not
 * recognised (decision 16): the parcel stays `returning` until the 60 days.
 *
 * The tracking may be switched off (`webx-delivery-novaposhta.tracking`): then nothing is asked
 * and every parcel is «unknown, ask later». A read-only key — somebody else's account, whose
 * parcels are its buyers' data — tracks nothing unless said otherwise.
 */
final class Tracking
{
    /** At most this many numbers in one request: the carrier's limit and the base's batch. */
    public const int BATCH = 100;

    /** The carrier's code → the base's status (§9.2). Code 3, «not found», is not here: left out. */
    public const array STATUSES = [
        '1' => ShipmentStatus::Created,
        '2' => ShipmentStatus::Cancelled,
        '12' => ShipmentStatus::Created,
        '4' => ShipmentStatus::InTransit,
        '41' => ShipmentStatus::InTransit,
        '5' => ShipmentStatus::InTransit,
        '6' => ShipmentStatus::InTransit,
        '7' => ShipmentStatus::AtPoint,
        '8' => ShipmentStatus::AtPoint,
        '9' => ShipmentStatus::Received,
        '10' => ShipmentStatus::Received,
        '11' => ShipmentStatus::Received,
        '106' => ShipmentStatus::Received,
        '101' => ShipmentStatus::OutForDelivery,
        '111' => ShipmentStatus::OutForDelivery,
        '104' => ShipmentStatus::InTransit,
        '112' => ShipmentStatus::InTransit,
        '102' => ShipmentStatus::Refused,
        '103' => ShipmentStatus::Refused,
        '105' => ShipmentStatus::Returning,
    ];

    /** «Number not found»: the carrier does not know it (yet) — asked again later. */
    public const string NOT_FOUND = '3';

    /**
     * Where the time of the last status may stand — the name is not confirmed on a key (§9.2),
     * so the first one that reads as a date wins; none — now.
     */
    private const array TIMES = ['TrackingUpdateDate', 'DateCreated'];

    /** The time a parcel was handed over, for «received»: the milestone's own date. */
    private const array RECEIVED_TIMES = ['RecipientDateTime', 'ActualDeliveryDate', 'TrackingUpdateDate'];

    public function __construct(
        private readonly Client $client,
        private readonly AccountKey $key,
        private readonly bool $enabled = true,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param  list<Shipment>  $shipments
     * @return array<int, TrackingState> shipment id → state; one the carrier did not name is left out
     *
     * @throws CarrierUnavailable
     */
    public function track(array $shipments): array
    {
        if (! $this->enabled) {
            return [];
        }

        /** @var array<string, list<Shipment>> $byNumber */
        $byNumber = [];

        foreach ($shipments as $shipment) {
            $number = trim((string) $shipment->number);

            if ($number !== '') {
                $byNumber[$number][] = $shipment;
            }
        }

        if ($byNumber === []) {
            return [];
        }

        $key = $this->key->get();

        if ($key === null) {
            throw CarrierUnavailable::keyRejected('No API key of Nova Poshta is stored.');
        }

        $states = [];

        // The base hands a hundred at a time; a caller with more still gets one request per hundred.
        foreach (array_chunk(array_keys($byNumber), self::BATCH) as $numbers) {
            $documents = array_map(static fn (int|string $number): array => ['DocumentNumber' => (string) $number], $numbers);
            $response = $this->client->call($key, 'TrackingDocument', 'getStatusDocuments', ['Documents' => $documents]);

            foreach ($response->records() as $record) {
                $number = trim((string) ($record['Number'] ?? ''));

                foreach ($byNumber[$number] ?? [] as $shipment) {
                    $state = $this->state($shipment, $record);

                    if ($state !== null) {
                        $states[$shipment->id] = $state;
                    }
                }
            }
        }

        return $states;
    }

    /**
     * One record of the carrier's answer in the base's words; null — «not found», ask later.
     *
     * @param  array<string, mixed>  $record
     */
    public function state(Shipment $shipment, array $record): ?TrackingState
    {
        $code = trim((string) ($record['StatusCode'] ?? ''));

        if ($code === '' || $code === self::NOT_FOUND) {
            return null;
        }

        $text = trim((string) ($record['Status'] ?? ''));
        $text = $text === '' ? null : $text;
        $status = self::STATUSES[$code] ?? null;

        if ($status === null) {
            // A code the table does not know: the status stays, the carrier's words go to the
            // events, and a line to the log so that the table can grow (§9.2).
            Log::warning("Nova Poshta: unknown tracking status code {$code} of shipment {$shipment->id}; kept «{$shipment->status->value}».");

            return new TrackingState($shipment->status, null, $text);
        }

        return new TrackingState($status, self::time($record, $status === ShipmentStatus::Received ? self::RECEIVED_TIMES : self::TIMES), $text);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $fields
     */
    private static function time(array $record, array $fields): ?DateTimeImmutable
    {
        foreach ($fields as $field) {
            $value = trim((string) ($record[$field] ?? ''));

            if ($value === '' || str_starts_with($value, '0000')) {
                continue;
            }

            // «02.11.2026 10:15:00», «02-11-2026 10:15:00» or «2026-11-02 10:15:00», Kyiv time.
            foreach (['d.m.Y H:i:s', 'd-m-Y H:i:s', 'Y-m-d H:i:s', 'd.m.Y H:i', 'Y-m-d'] as $format) {
                try {
                    $time = CarbonImmutable::createFromFormat('!'.$format, $value, 'Europe/Kyiv');
                } catch (Throwable) {
                    $time = null;
                }

                if ($time instanceof CarbonImmutable) {
                    return $time->toDateTimeImmutable();
                }
            }
        }

        return null;
    }
}
