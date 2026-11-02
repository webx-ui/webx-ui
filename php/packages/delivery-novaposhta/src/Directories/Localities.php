<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Directories;

use WebxUi\Geography\Matching\LocalityRecord;

/**
 * The carrier's towns from `Address.getCities` (§6.2) in the base's shape (§4.2 of the delivery
 * spec): the name without the qualifier in brackets and without a type prefix, the type from
 * `SettlementTypeDescription`, the region by the name of its area, `courier` where the carrier
 * goes to doors on some day of the week.
 *
 * The carrier's own words: on the key (stage 3) the names carry no prefix and namesakes are told
 * apart by a qualifier in brackets — «Абазівка (Полтавський р-н, Полтавська обл)»; the types are
 * the four below; the capital's area is «Київська», like the region around it.
 *
 * The district of the qualifier («Бориспільський р-н») is the record's `district`: the buyer's
 * suggestions show it beside namesakes of one region («Щасливе, Бориспільський р-н»), and the
 * matcher tells them apart by it (decision of 02.11.2026). A qualifier naming only the area
 * gives none.
 */
final class Localities
{
    public const string COUNTRY = 'UA';

    /** The capital: a region of its own, named after it — its area is the region around it. */
    public const string CAPITAL = '8d5a980d-391c-11dd-90d9-001a92567626';

    /** `SettlementTypeDescription` → the base's kinds; any other word is `other`, kept in `meta.kind`. */
    public const array KINDS = [
        'місто' => 'city',
        'селище міського типу' => 'township',
        'селище' => 'settlement',
        'село' => 'village',
    ];

    /**
     * A type written into a name (`м. Київ`, `смт Ворзель`, `г. Киев`): the carrier does not do it
     * on the key, but a name with one would be a different town to the matcher — taken off, and the
     * contract test of the package keeps every name clear of it.
     */
    public const string PREFIX = '/^(?:(?:с\.м\.т|с-ще|сел|пос|м|с|г)\.\s*|(?:смт|пгт)\.?\s+)/iu';

    /** Languages the carrier names a town in: `Description` and `DescriptionRu`. */
    private const array NAMES = ['uk' => 'Description', 'ru' => 'DescriptionRu'];

    /** @param  list<string>  $locales  the site's languages: names only in those */
    public function __construct(private readonly array $locales) {}

    /**
     * The records of one answer; an entry without a `Ref` or a name is not a town and is left out.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<LocalityRecord>
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

    /** @param  array<string, mixed>  $entry */
    public function record(array $entry): ?LocalityRecord
    {
        $ref = self::string($entry['Ref'] ?? null);
        [$name, $qualifier] = self::name(self::string($entry['Description'] ?? null));

        if ($ref === '' || $name === '') {
            return null;
        }

        $type = self::string($entry['SettlementTypeDescription'] ?? null);
        $capital = $ref === self::CAPITAL;
        $names = [];
        $regions = [];

        foreach (self::NAMES as $locale => $field) {
            $spelling = self::name(self::string($entry[$field] ?? null))[0];
            $region = self::region(self::string($entry[$field === 'Description' ? 'AreaDescription' : 'AreaDescriptionRu'] ?? null));

            if (in_array($locale, $this->locales, true) && $spelling !== '') {
                $names[$locale] = $spelling;
            }

            if (in_array($locale, $this->locales, true) && ($capital ? $spelling : $region) !== '') {
                $regions[$locale] = $capital ? $spelling : $region;
            }
        }

        $region = $capital ? $name : self::region(self::string($entry['AreaDescription'] ?? null));

        return new LocalityRecord(
            externalId: $ref,
            country: self::COUNTRY,
            name: $name,
            kind: self::KINDS[mb_strtolower($type)] ?? 'other',
            region: $region === '' ? null : $region,
            district: self::district($qualifier),
            names: $names === [] ? null : $names,
            regionNames: $regions === [] ? null : $regions,
            features: self::courier($entry) ? ['courier'] : [],
            meta: array_filter([
                'kind' => $type,
                'area_ref' => self::string($entry['Area'] ?? null),
                'city_id' => self::string($entry['CityID'] ?? null),
                'qualifier' => $qualifier,
            ], static fn (string $value): bool => $value !== ''),
        );
    }

    /**
     * The name and the qualifier in brackets that tells namesakes apart.
     *
     * @return array{0: string, 1: string}
     */
    private static function name(string $description): array
    {
        $qualifier = '';

        if (preg_match('/^(.*?)\s*\(([^()]*)\)\s*$/u', $description, $match) === 1 && trim($match[1]) !== '') {
            $description = $match[1];
            $qualifier = trim($match[2]);
        }

        return [trim((string) preg_replace(self::PREFIX, '', trim($description))), $qualifier];
    }

    /** «Сосницький р-н., Чернігівська обл.» → «Сосницький р-н»; no district in it — null. */
    private static function district(string $qualifier): ?string
    {
        foreach (explode(',', $qualifier) as $part) {
            if (preg_match('/^(.+?)\s+(?:р-н|район)\.?$/u', trim($part), $match) === 1) {
                return trim($match[1]).' р-н';
            }
        }

        return null;
    }

    /** The area without the word for it: «Полтавська», not «Полтавська область». */
    private static function region(string $area): string
    {
        return trim((string) preg_replace('/\s+(?:область|обл\.?)$/iu', '', $area));
    }

    /**
     * `Delivery1…7` are the days of the week the carrier goes to doors; one is enough.
     *
     * @param  array<string, mixed>  $entry
     */
    private static function courier(array $entry): bool
    {
        for ($day = 1; $day <= 7; $day++) {
            if (self::string($entry['Delivery'.$day] ?? null) === '1') {
                return true;
            }
        }

        return false;
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
