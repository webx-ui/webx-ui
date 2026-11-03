<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Directories;

use WebxUi\Delivery\Sync\Limits;
use WebxUi\Delivery\Sync\PointRecord;
use WebxUi\Delivery\Sync\Schedule;

/**
 * The carrier's points from `Address.getWarehouses` (§6.3) in the base's shape (§7.2 of the
 * delivery spec). Which points are taken is the decision of 02.11.2026 (decision 20):
 *
 * - `Branch` and `Store` («Пункт приймання-видачі») are branches, `Postomat` is a locker;
 * - `DropOff` and `Fulfillment` are the carrier's own — nobody picks a parcel up there — and a
 *   point with `DenyToSelect` is closed to choosing: both left out, counted by why;
 * - a locker for the residents of a block (`PostMachineType = ForResidentsOfEntrance`) is kept:
 *   who lives there picks it. It carries the base's restriction `residents`, said beside it, and
 *   goes after the others in the list.
 *
 * On the key (stage 3): the hours are an object by days, «08:00-19:00», «-» for a day off; the
 * weight limit of a point is `TotalMaxWeightAllowed` when `PlaceMaxWeightAllowed` is 0; the sizes
 * are centimetres; the phone is the carrier's one hotline or nothing, so no point has its own.
 */
final class Points
{
    /** `CategoryOfWarehouse` → the base's kinds; any other is left out. */
    public const array KINDS = [
        'Branch' => 'branch',
        'Store' => 'branch',
        'Postomat' => 'locker',
    ];

    /** Why a point the carrier gave is left out: the run counts them in its warnings. */
    public const string SKIP_KIND = 'novaposhta_service_point';

    public const string SKIP_DENIED = 'novaposhta_deny_to_select';

    public const string SKIP_INVALID = 'novaposhta_invalid_point';

    /** A locker for the residents of a block: open to them only. */
    public const string RESIDENTS = 'ForResidentsOfEntrance';

    /** The carrier's points keep the time of Ukraine, whatever the site's zone. */
    public const string TIMEZONE = 'Europe/Kyiv';

    private const array DAYS = [
        'Monday' => 'mon', 'Tuesday' => 'tue', 'Wednesday' => 'wed', 'Thursday' => 'thu',
        'Friday' => 'fri', 'Saturday' => 'sat', 'Sunday' => 'sun',
    ];

    /** Languages the carrier names a point in. */
    private const array TITLES = ['uk' => 'Description', 'ru' => 'DescriptionRu'];

    private const array ADDRESSES = ['uk' => 'ShortAddress', 'ru' => 'ShortAddressRu'];

    /** @var array<string, list<string>> why → the refs left out */
    private array $skipped = [];

    /** @param  list<string>  $locales  the site's languages: titles and addresses only in those */
    public function __construct(private readonly array $locales) {}

    /**
     * The records of one answer; what is left out is in {@see skipped()}.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<PointRecord>
     */
    public function records(array $entries): array
    {
        $records = [];

        foreach ($entries as $entry) {
            $record = $this->record($entry);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /** @return array<string, list<string>> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** @param  array<string, mixed>  $entry */
    public function record(array $entry): ?PointRecord
    {
        $ref = self::string($entry['Ref'] ?? null);
        $kind = self::KINDS[self::string($entry['CategoryOfWarehouse'] ?? null)] ?? null;

        if ($kind === null) {
            return $this->skip(self::SKIP_KIND, $ref);
        }

        if (self::string($entry['DenyToSelect'] ?? null) === '1') {
            return $this->skip(self::SKIP_DENIED, $ref);
        }

        $titles = $this->spellings($entry, self::TITLES);
        $addresses = $this->spellings($entry, self::ADDRESSES);
        $title = self::string($entry['Description'] ?? null);
        $address = self::string($entry['ShortAddress'] ?? null);
        $town = self::string($entry['CityRef'] ?? null);

        if ($address === '' && $addresses === []) {
            // No short address: the title has it after the number — «Відділення №1: вул. Українська, 36».
            $address = $title;
        }

        if ($ref === '' || $town === '' || ($address === '' && $addresses === [])) {
            return $this->skip(self::SKIP_INVALID, $ref);
        }

        $number = self::string($entry['Number'] ?? null);

        return new PointRecord(
            externalId: $ref,
            kind: $kind,
            localityExternalId: $town,
            address: $address,
            number: $number === '' ? null : $number,
            title: $title === '' ? null : $title,
            titles: $titles === [] ? null : $titles,
            addresses: $addresses === [] ? null : $addresses,
            postcode: self::postcode(self::string($entry['PostalCodeUA'] ?? null)),
            lat: self::coordinate($entry['Latitude'] ?? null),
            lng: self::coordinate($entry['Longitude'] ?? null),
            schedule: self::schedule($entry['Schedule'] ?? null),
            limits: self::limits($entry),
            // The carrier gives its one hotline or nothing: no point has a phone of its own.
            phone: null,
            active: self::string($entry['WarehouseStatus'] ?? null) === 'Working',
            meta: array_filter([
                'category' => self::string($entry['CategoryOfWarehouse'] ?? null),
                'type_ref' => self::string($entry['TypeOfWarehouse'] ?? null),
                'settlement_ref' => self::string($entry['SettlementRef'] ?? null),
                'post_machine_type' => self::string($entry['PostMachineType'] ?? null),
                'card' => self::string($entry['POSTerminal'] ?? null) === '1' ? true : null,
                'status' => self::string($entry['WarehouseStatus'] ?? null),
                'sending' => self::dimensions($entry['SendingLimitationsOnDimensions'] ?? null),
            ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []),
            restriction: self::string($entry['PostMachineType'] ?? null) === self::RESIDENTS ? 'residents' : null,
        );
    }

    private function skip(string $why, string $ref): null
    {
        $this->skipped[$why][] = $ref === '' ? '?' : $ref;

        return null;
    }

    /**
     * The spellings in the site's languages; an empty one is left to the main spelling.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function spellings(array $entry, array $fields): array
    {
        $spellings = [];

        foreach ($fields as $locale => $field) {
            $value = self::string($entry[$field] ?? null);

            if ($value !== '' && in_array($locale, $this->locales, true)) {
                $spellings[$locale] = $value;
            }
        }

        return $spellings;
    }

    /** Four digits on the key lost their leading zero: «7850» is «07850». */
    private static function postcode(string $code): ?string
    {
        if (preg_match('/^\d{4}$/', $code) === 1) {
            return '0'.$code;
        }

        return $code === '' ? null : $code;
    }

    private static function coordinate(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * «08:00-19:00» a day, «-» for a day off; «00:01-23:59» of a locker is around the clock.
     * A day in some other shape is said as the carrier wrote it.
     */
    private static function schedule(mixed $days): ?Schedule
    {
        if (! is_array($days) || $days === []) {
            return null;
        }

        $week = [];
        $odd = [];

        foreach (self::DAYS as $field => $day) {
            $hours = self::string($days[$field] ?? null);

            if ($hours === '') {
                continue;
            }

            if ($hours === '-') {
                $week[$day] = [];
            } elseif (preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $hours, $match) === 1) {
                $from = sprintf('%02d:%s', $match[1], $match[2]);
                $to = sprintf('%02d:%s', $match[3], $match[4]);
                $week[$day] = $from <= '00:01' && $to >= '23:59' ? [['00:00', '24:00']] : [[$from, $to]];
            } else {
                $week[$day] = null;
                $odd[] = "{$field}: {$hours}";
            }
        }

        if ($week === []) {
            return null;
        }

        return new Schedule($week, self::TIMEZONE, [], $odd === [] ? [] : ['uk' => implode('; ', $odd)]);
    }

    /**
     * The weight a point takes — of a place if the carrier says, of the point otherwise — in
     * grams, and the sizes it receives, in millimetres. Stored and shown, not checked (§7.4).
     *
     * @param  array<string, mixed>  $entry
     */
    private static function limits(array $entry): ?Limits
    {
        $place = self::number($entry['PlaceMaxWeightAllowed'] ?? null);
        $total = self::number($entry['TotalMaxWeightAllowed'] ?? null);
        $weight = $place > 0 ? $place : $total;
        $size = self::dimensions($entry['ReceivingLimitationsOnDimensions'] ?? null);

        $limits = new Limits(
            maxWeightG: $weight > 0 ? (int) round($weight * 1000) : null,
            maxLengthMm: isset($size['length']) ? $size['length'] * 10 : null,
            maxWidthMm: isset($size['width']) ? $size['width'] * 10 : null,
            maxHeightMm: isset($size['height']) ? $size['height'] * 10 : null,
        );

        return $limits->toArray() === [] ? null : $limits;
    }

    /**
     * `{Width, Height, Length}` in centimetres; a zero or missing side is not said.
     *
     * @return array<string, int>
     */
    private static function dimensions(mixed $sides): array
    {
        if (! is_array($sides)) {
            return [];
        }

        $size = [];

        foreach (['Width' => 'width', 'Height' => 'height', 'Length' => 'length'] as $field => $side) {
            $value = self::number($sides[$field] ?? null);

            if ($value > 0) {
                $size[$side] = (int) round($value);
            }
        }

        return $size;
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
