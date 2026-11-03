<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Directories;

use WebxUi\Delivery\Sync\StreetRecord;

/**
 * The streets of one town from `Address.getStreet` (§6.4) in the base's shape (§7.2 of the
 * delivery spec). On the key (stage 3) a street is `Description` without its type word, `Ref`,
 * `StreetsTypeRef` — an English word for the type — and `StreetsType`, the carrier's word for it
 * in Ukrainian; no other language, so the name is the main one and the base gives it to the site's
 * default language.
 *
 * The type is the base's kind where it has one; any other type — a boulevard, a descent, a line —
 * is `other`, and then the carrier's word goes after the name in brackets: «Шевченка (бульвар)».
 * Before the name it would hide the street from a search by the beginning of its name, and without
 * it a boulevard and a descent of one name would look the same in the list.
 */
final class Streets
{
    /** `StreetsTypeRef` → the base's kind (the dictionary of §6.4); any other is `other`. */
    public const array KINDS = [
        'Street' => 'street',
        'Prospectus' => 'avenue',
        'Avenue' => 'avenue',
        'Lane' => 'lane',
        'Square' => 'square',
    ];

    /**
     * @param  string  $town  the carrier's town (`CityRef`) the streets were asked for
     * @param  list<array<string, mixed>>  $entries
     * @return list<StreetRecord>
     */
    public static function records(string $town, array $entries): array
    {
        $records = [];

        foreach ($entries as $entry) {
            $record = self::record($town, $entry);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /** @param  array<string, mixed>  $entry */
    public static function record(string $town, array $entry): ?StreetRecord
    {
        $ref = self::string($entry['Ref'] ?? null);
        $name = self::string($entry['Description'] ?? null);

        if ($ref === '' || $name === '') {
            // Nothing to bind or to show: the base would refuse the whole page for it.
            return null;
        }

        $kind = self::KINDS[self::string($entry['StreetsTypeRef'] ?? null)] ?? 'other';
        $word = self::string($entry['StreetsType'] ?? null);

        if ($kind === 'other' && $word !== '') {
            $name .= " ({$word})";
        }

        return new StreetRecord(
            externalId: $ref,
            localityExternalId: $town,
            name: $name,
            kind: $kind,
        );
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) preg_replace('/\s+/u', ' ', (string) $value)) : '';
    }
}
