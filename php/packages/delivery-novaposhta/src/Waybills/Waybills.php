<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Waybills;

use Carbon\CarbonImmutable;
use Throwable;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\ApiError;
use WebxUi\Delivery\NovaPoshta\Api\ApiResponse;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\WriteRefused;
use WebxUi\Delivery\NovaPoshta\Sending\SenderOptions;
use WebxUi\Delivery\Shipping\Shipment;
use WebxUi\Delivery\Shipping\Waybill;
use WebxUi\Delivery\Shipping\WaybillRefused;
use WebxUi\Delivery\Shipping\WaybillRequest;
use WebxUi\Delivery\Sync\CarrierUnavailable;

/**
 * The waybill of Nova Poshta (§8, decisions 4–5): checked before anything goes to the carrier,
 * then the recipient (`Counterparty.save`, a contact person if the answer has none), the address
 * for a courier (`Address.save`) and the waybill itself (`InternetDocument.save`); cancelled by
 * `InternetDocument.delete`.
 *
 * Sending is only from a branch (decision 14): `WarehouseWarehouse` to a branch or a parcel
 * locker, `WarehouseDoors` to a courier. No cash on delivery (decision 5), no label (decision 12).
 *
 * The names of the writing methods and of their answers are after the carrier's documentation:
 * they are never called on a client's key (decision 19) — the tests run on answers written after
 * the documentation, marked so in `tests/Fixtures/api`.
 */
final class Waybills
{
    /** The carrier's limits of a parcel locker, cm: `ReceivingLimitationsOnDimensions` (stage 3). */
    public const array LOCKER_SIZE = ['locker_width' => 40, 'locker_height' => 30, 'locker_length' => 60];

    /** A waybill may be dated up to three months ahead (checked, since 04.05.2026). */
    public const int MONTHS_AHEAD = 3;

    /** The cargo types of a waybill; a parcel locker takes only the first and the last. */
    public const array CARGO_TYPES = ['Parcel', 'Cargo', 'Documents'];

    public const array LOCKER_CARGO_TYPES = ['Parcel', 'Documents'];

    public function __construct(
        private readonly Client $client,
        private readonly AccountKey $key,
        private readonly SenderOptions $sender,
    ) {}

    /**
     * @param  string  $kind  'branch', 'locker' or 'courier'
     *
     * @throws WaybillRefused
     * @throws CarrierUnavailable
     */
    public function create(string $kind, WaybillRequest $request): Waybill
    {
        // The words of the panel: the manager's language, not the order's.
        $locale = null;
        $draft = $this->draft($kind, $request, $locale);
        $this->writable($locale);
        $key = $this->apiKey();

        $contacts = $this->ask(fn (): array => $this->sender->contactRecords($key, $draft['sender']['sender']), $locale);
        $contact = null;

        foreach ($contacts as $record) {
            if (($record['Ref'] ?? null) === $draft['sender']['contact']) {
                $contact = $record;
            }
        }

        // The account's own phone of the contact; in the shape the carrier takes when it can be.
        $phones = (string) ($contact['Phones'] ?? '');
        $sendersPhone = $contact === null ? null : (Phone::normalise($phones) ?? (preg_replace('/\D+/', '', $phones) ?: null));

        if ($sendersPhone === null) {
            throw new WaybillRefused(self::word('errors.contact_gone', $locale));
        }

        $recipient = $this->recipient($key, $request, $draft, $locale);
        $address = $kind === 'courier'
            ? $this->address($key, $recipient['ref'], $request, $locale)
            : $draft['point'];

        $properties = [
            'PayerType' => $request->payer === WaybillRequest::SENDER ? 'Sender' : 'Recipient',
            // The recipient pays in cash at the branch; the shop pays as its contract says.
            'PaymentMethod' => $request->payer === WaybillRequest::SENDER ? $draft['payment_method'] : 'Cash',
            'DateTime' => $draft['date']->format('d.m.Y'),
            'CargoType' => $draft['cargo_type'],
            'Weight' => $draft['weight'],
            'ServiceType' => $kind === 'courier' ? 'WarehouseDoors' : 'WarehouseWarehouse',
            'SeatsAmount' => $draft['seats'],
            'Description' => $draft['description'],
            'Cost' => (int) round($request->declaredValue / 100),
            'CitySender' => $draft['sender']['sender_city'],
            'Sender' => $draft['sender']['sender'],
            'SenderAddress' => $draft['sender']['sender_point'],
            'ContactSender' => $draft['sender']['contact'],
            'SendersPhone' => $sendersPhone,
            'CityRecipient' => $draft['town'],
            'Recipient' => $recipient['ref'],
            'RecipientAddress' => $address,
            'ContactRecipient' => $recipient['contact'],
            'RecipientsPhone' => $draft['phone'],
        ];

        if ($kind === 'locker') {
            // The seat of a parcel locker by its sizes (decision 17); the name and the shape are
            // after the documentation, not checked on a key (decision 19).
            $properties['OptionsSeat'] = [[
                'volumetricWidth' => $draft['size']['locker_width'],
                'volumetricHeight' => $draft['size']['locker_height'],
                'volumetricLength' => $draft['size']['locker_length'],
                'volumetricVolume' => round($draft['size']['locker_width'] * $draft['size']['locker_height'] * $draft['size']['locker_length'] / 1_000_000, 4),
                'weight' => $draft['weight'],
            ]];
        }

        $answer = $this->write(fn (): ApiResponse => $this->client->call($key, 'InternetDocument', 'save', $properties), $locale);
        $document = $answer->records()[0] ?? [];
        $number = self::text($document['IntDocNumber'] ?? null);

        if ($number === null) {
            throw CarrierUnavailable::badResponse('Nova Poshta: InternetDocument.save answered without a number.');
        }

        $cost = $document['CostOnSite'] ?? null;
        $estimated = self::text($document['EstimatedDeliveryDate'] ?? null);

        return new Waybill(
            number: $number,
            cost: is_numeric($cost) ? (int) round((float) $cost * 100) : null,
            meta: array_filter([
                'ref' => self::text($document['Ref'] ?? null),
                'estimated_at' => $estimated === null ? null : self::date($estimated)?->toDateString(),
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @throws WaybillRefused the parcel is on its way already, or the waybill's id is not kept
     * @throws CarrierUnavailable
     */
    public function cancel(Shipment $shipment): void
    {
        $locale = null;
        $ref = self::text($shipment->meta['ref'] ?? null);

        if ($ref === null) {
            throw new WaybillRefused(self::word('errors.no_ref', $locale));
        }

        $this->writable($locale);
        $key = $this->apiKey();
        $this->write(fn (): ApiResponse => $this->client->call($key, 'InternetDocument', 'delete', ['DocumentRefs' => $ref]), $locale);
    }

    /**
     * Everything that can be said before a request (§8.2): no cash on delivery, the names in the
     * carrier's letters, the phone, the sender's settings, the town, the point or the street, the
     * carrier's own fields.
     *
     * @return array{sender: array{sender: string, contact: string, sender_city: string, sender_point: string}, payment_method: string, town: string, point: string|null, phone: string, first: string, last: string, weight: float, seats: int, cargo_type: string, description: string, date: CarbonImmutable, size: array<string, float>}
     *
     * @throws WaybillRefused
     */
    public function draft(string $kind, WaybillRequest $request, ?string $locale = null): array
    {
        if ($request->cod !== null) {
            throw WaybillRefused::field('cod', self::word('errors.no_cod', $locale));
        }

        $first = trim($request->recipientFirstName);
        $last = trim($request->recipientLastName);

        foreach (['recipient_first_name' => $first, 'recipient_last_name' => $last] as $field => $name) {
            if ($name === '') {
                throw WaybillRefused::field($field, self::word('errors.name_empty', $locale));
            }

            if (! RecipientName::valid($name)) {
                throw WaybillRefused::field($field, self::word('errors.name_letters', $locale));
            }
        }

        $phone = Phone::normalise((string) $request->recipientPhone);

        if ($phone === null) {
            throw WaybillRefused::field('recipient_phone', self::word('errors.phone', $locale));
        }

        $sender = [];

        foreach (['sender', 'contact', 'sender_city', 'sender_point'] as $name) {
            $value = self::text($request->sender[$name] ?? null);

            if ($value === null) {
                throw new WaybillRefused(self::word('errors.no_sender', $locale));
            }

            $sender[$name] = $value;
        }

        if ($request->carrierLocality === null) {
            throw new WaybillRefused(self::word('errors.no_town', $locale));
        }

        $point = null;

        if ($kind === 'courier') {
            $address = is_array($request->delivery['address'] ?? null) ? $request->delivery['address'] : [];

            if (self::text($address['street_id'] ?? null) === null || self::text($address['house'] ?? null) === null) {
                throw new WaybillRefused(self::word('errors.no_street', $locale));
            }
        } else {
            $point = is_array($request->delivery['point'] ?? null) ? self::text($request->delivery['point']['external_id'] ?? null) : null;

            if ($point === null) {
                throw new WaybillRefused(self::word('errors.no_point', $locale));
            }
        }

        $extra = $request->extra;
        $weight = is_numeric($extra['weight'] ?? null) ? (float) $extra['weight'] : 0.0;

        if ($weight <= 0) {
            throw WaybillRefused::field('extra.weight', self::word('errors.weight', $locale));
        }

        $seats = $kind === 'locker' ? 1 : ($extra['seats'] ?? 1);

        if (! is_numeric($seats) || (float) $seats !== floor((float) $seats) || (int) $seats < 1) {
            throw WaybillRefused::field('extra.seats', self::word('errors.seats', $locale));
        }

        $cargo = self::text($extra['cargo_type'] ?? null) ?? 'Parcel';

        if (! in_array($cargo, $kind === 'locker' ? self::LOCKER_CARGO_TYPES : self::CARGO_TYPES, true)) {
            throw WaybillRefused::field('extra.cargo_type', self::word('errors.cargo_type', $locale));
        }

        $size = [];

        if ($kind === 'locker') {
            foreach (self::LOCKER_SIZE as $name => $most) {
                $value = is_numeric($extra[$name] ?? null) ? (float) $extra[$name] : 0.0;

                if ($value <= 0) {
                    throw WaybillRefused::field('extra.'.$name, self::word('errors.size', $locale));
                }

                if ($value > $most) {
                    throw WaybillRefused::field('extra.'.$name, self::word('errors.locker_size', $locale));
                }

                $size[$name] = $value;
            }
        }

        $today = CarbonImmutable::today('Europe/Kyiv');
        $typed = self::text($extra['date'] ?? null);
        $date = $typed === null ? $today : self::date($typed);

        if ($date === null || $date->lt($today) || $date->gt($today->addMonthsNoOverflow(self::MONTHS_AHEAD))) {
            throw WaybillRefused::field('extra.date', self::word('errors.date', $locale));
        }

        $payment = self::text($request->sender['payment_method'] ?? null);

        return [
            'sender' => $sender,
            'payment_method' => in_array($payment, ['Cash', 'NonCash'], true) ? $payment : 'Cash',
            'town' => $request->carrierLocality,
            'point' => $point,
            'phone' => $phone,
            'first' => $first,
            'last' => $last,
            'weight' => $weight,
            'seats' => (int) $seats,
            'cargo_type' => $cargo,
            'description' => self::text($extra['description'] ?? null) ?? (string) __('webx-delivery-novaposhta::novaposhta.settings.description_default', [], 'uk'),
            'date' => $date,
            'size' => $size,
        ];
    }

    /**
     * The recipient as a private person (§8.2): the carrier folds a repeated one into the one it
     * has and says so in a warning — that is fine. A contact person comes in the answer; if not,
     * one is saved for it.
     *
     * @param  array{first: string, last: string, phone: string}  $draft
     * @return array{ref: string, contact: string}
     */
    private function recipient(string $key, WaybillRequest $request, array $draft, ?string $locale): array
    {
        $answer = $this->write(fn (): ApiResponse => $this->client->call($key, 'Counterparty', 'save', array_filter([
            'FirstName' => $draft['first'],
            'MiddleName' => '',
            'LastName' => $draft['last'],
            'Phone' => $draft['phone'],
            'Email' => $request->recipientEmail ?? '',
            'CounterpartyType' => 'PrivatePerson',
            'CounterpartyProperty' => 'Recipient',
        ], static fn (mixed $value): bool => $value !== null)), $locale, recipient: true);

        $counterparty = $answer->records()[0] ?? [];
        $ref = self::text($counterparty['Ref'] ?? null);

        if ($ref === null) {
            throw CarrierUnavailable::badResponse('Nova Poshta: Counterparty.save answered without a Ref.');
        }

        $contacts = is_array($counterparty['ContactPerson'] ?? null) && is_array($counterparty['ContactPerson']['data'] ?? null)
            ? $counterparty['ContactPerson']['data']
            : [];
        $contact = is_array($contacts[0] ?? null) ? self::text($contacts[0]['Ref'] ?? null) : null;

        if ($contact === null) {
            $person = $this->write(fn (): ApiResponse => $this->client->call($key, 'ContactPerson', 'save', [
                'CounterpartyRef' => $ref,
                'FirstName' => $draft['first'],
                'LastName' => $draft['last'],
                'MiddleName' => '',
                'Phone' => $draft['phone'],
            ]), $locale, recipient: true);
            $contact = self::text($person->records()[0]['Ref'] ?? null);
        }

        if ($contact === null) {
            throw CarrierUnavailable::badResponse('Nova Poshta: no contact person of the recipient came back.');
        }

        return ['ref' => $ref, 'contact' => $contact];
    }

    /** The courier's address of the recipient (§8.2, after the documentation). */
    private function address(string $key, string $recipient, WaybillRequest $request, ?string $locale): string
    {
        $address = (array) ($request->delivery['address'] ?? []);
        $answer = $this->write(fn (): ApiResponse => $this->client->call($key, 'Address', 'save', [
            'CounterpartyRef' => $recipient,
            'StreetRef' => (string) $address['street_id'],
            'BuildingNumber' => (string) $address['house'],
            'Flat' => (string) ($address['apartment'] ?? ''),
            'Note' => '',
        ]), $locale);
        $ref = self::text($answer->records()[0]['Ref'] ?? null);

        if ($ref === null) {
            throw CarrierUnavailable::badResponse('Nova Poshta: Address.save answered without a Ref.');
        }

        return $ref;
    }

    /**
     * A read-only key (somebody else's account) makes no waybill: refused before even the reading
     * of the contact person, so not a single request goes for it.
     */
    private function writable(?string $locale): void
    {
        if ($this->client->readOnly()) {
            throw new WaybillRefused(self::word('errors.read_only', $locale));
        }
    }

    private function apiKey(): string
    {
        $key = $this->key->get();

        if ($key === null) {
            throw CarrierUnavailable::keyRejected('No API key of Nova Poshta is stored.');
        }

        return $key;
    }

    /**
     * A reading call of the waybill: the carrier's refusal in its words.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function ask(callable $call, ?string $locale): mixed
    {
        try {
            return $call();
        } catch (CarrierUnavailable $failure) {
            throw $this->refusal($failure, $locale) ?? $failure;
        }
    }

    /**
     * A writing call: refused by the client on a read-only key before any request, and the
     * carrier's refusal in words.
     *
     * @param  callable(): ApiResponse  $call
     */
    private function write(callable $call, ?string $locale, bool $recipient = false): ApiResponse
    {
        try {
            return $call();
        } catch (WriteRefused) {
            throw new WaybillRefused(self::word('errors.read_only', $locale));
        } catch (CarrierUnavailable $failure) {
            throw $this->refusal($failure, $locale, $recipient) ?? $failure;
        }
    }

    /**
     * The carrier's logical error (`success: false`) as a refusal the manager reads (§8.7); no
     * answer and a refused key stay `CarrierUnavailable`, which the base words itself.
     */
    private function refusal(CarrierUnavailable $failure, ?string $locale, bool $recipient = false): ?WaybillRefused
    {
        $error = $failure->getPrevious();

        if (! $error instanceof ApiError) {
            return null;
        }

        $known = CarrierErrors::known($error, $recipient);
        $said = CarrierErrors::said($error);

        if ($known !== null) {
            return new WaybillRefused(self::word('errors.'.$known['word'], $locale).' '.$said, $known['field'], $failure);
        }

        return new WaybillRefused((string) __('webx-delivery-novaposhta::novaposhta.errors.carrier_said', ['text' => $said], $locale), null, $failure);
    }

    private static function date(string $text): ?CarbonImmutable
    {
        foreach (['Y-m-d', 'd.m.Y', 'd.m.Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text, 'Europe/Kyiv');
            } catch (Throwable) {
                continue;
            }

            if ($date instanceof CarbonImmutable && $date->format($format) === $text) {
                return $date->startOfDay();
            }
        }

        return null;
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private static function word(string $key, ?string $locale): string
    {
        return (string) __('webx-delivery-novaposhta::novaposhta.'.$key, [], $locale);
    }
}
