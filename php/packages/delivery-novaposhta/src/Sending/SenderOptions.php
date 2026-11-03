<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Sending;

use WebxUi\Delivery\CarrierLocalities;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\Points;
use WebxUi\Delivery\Settings\SettingOption;
use WebxUi\Delivery\Sync\CarrierUnavailable;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\Models\GeoLocality;

/**
 * The options of the sender's settings (§4.3, §5): the sender and its contact person come from the
 * carrier's account — reading methods only; the town and the branch of sending come from the
 * directories the base keeps already, so a search over tens of thousands of points is an index of
 * the base and not a request to the carrier.
 *
 * The base checks a stored value of a search field by asking with the value itself: a town's or a
 * branch's `Ref` typed as the query answers with that one option.
 */
final class SenderOptions
{
    /** At most this many options a field (§5.1). */
    public const int LIMIT = 50;

    /** Pages of contact persons read at most: their `totalCount` is not to be trusted (stage 3). */
    public const int CONTACT_PAGES = 3;

    private const string REF = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly Client $client,
        private readonly CarrierLocalities $towns,
        private readonly Points $points,
        private readonly LocalitySearch $search,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return list<SettingOption>
     *
     * @throws CarrierUnavailable
     */
    public function options(string $field, array $values, string $key, ?string $query, string $locale): array
    {
        return match ($field) {
            'sender' => $this->senders($key),
            'contact' => $this->contacts($key, self::text($values['sender'] ?? null)),
            'sender_city' => $this->towns(trim((string) $query), $locale),
            'sender_point' => $this->branches(self::text($values['sender_city'] ?? null), trim((string) $query), $locale),
            default => [],
        };
    }

    /**
     * The contact persons of a sender as the account has them, page by page until an empty or a
     * short one — or {@see CONTACT_PAGES}.
     *
     * @return list<array<string, mixed>>
     *
     * @throws CarrierUnavailable
     */
    public function contactRecords(string $key, string $sender): array
    {
        $records = [];

        for ($page = 1; $page <= self::CONTACT_PAGES; $page++) {
            $found = $this->client->call($key, 'Counterparty', 'getCounterpartyContactPersons', ['Ref' => $sender, 'Page' => $page])->records();
            array_push($records, ...$found);

            // The carrier gives up to 100 a page; a shorter page is the last one.
            if (count($found) < 100) {
                break;
            }
        }

        return $records;
    }

    /** @return list<SettingOption> */
    private function senders(string $key): array
    {
        $options = [];

        foreach ($this->client->call($key, 'Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => 1])->records() as $record) {
            $ref = self::text($record['Ref'] ?? null);

            if ($ref === null) {
                continue;
            }

            $form = self::text($record['OwnershipFormDescription'] ?? null);
            $name = self::text($record['Description'] ?? null) ?? $ref;
            $code = self::text($record['EDRPOU'] ?? null);
            $options[] = new SettingOption($ref, $form !== null && ! str_starts_with($name, $form) ? $form.' '.$name : $name, $code);
        }

        return array_slice($options, 0, self::LIMIT);
    }

    /** @return list<SettingOption> */
    private function contacts(string $key, ?string $sender): array
    {
        if ($sender === null) {
            return [];
        }

        $options = [];

        foreach ($this->contactRecords($key, $sender) as $record) {
            $ref = self::text($record['Ref'] ?? null);

            if ($ref === null) {
                continue;
            }

            $name = trim(((string) ($record['FirstName'] ?? '')).' '.((string) ($record['LastName'] ?? '')));
            $options[] = new SettingOption($ref, $name !== '' ? $name : (self::text($record['Description'] ?? null) ?? $ref), self::text($record['Phones'] ?? null));
        }

        return array_slice($options, 0, self::LIMIT);
    }

    /**
     * Towns of the carrier: the base's suggestions of Ukraine by the typed prefix, the heaviest
     * first, kept to those the carrier reaches. A `Ref` typed — that town.
     *
     * @return list<SettingOption>
     */
    private function towns(string $query, string $locale): array
    {
        if (preg_match(self::REF, $query) === 1) {
            $locality = $this->towns->localityId(NovaPoshtaCarrier::KEY, $query);
            $town = $locality === null ? null : GeoLocality::query()->with('region')->find($locality);

            return $town === null ? [] : [new SettingOption($query, $town->label($locale))];
        }

        $suggestions = $this->search->suggest('UA', $query, $locale, self::LIMIT);
        $refs = $this->towns->forLocalities(NovaPoshtaCarrier::KEY, array_map(static fn ($town): int => $town->id, $suggestions));
        $options = [];

        foreach ($suggestions as $town) {
            if (isset($refs[$town->id])) {
                $options[] = new SettingOption($refs[$town->id], $town->label);
            }
        }

        return $options;
    }

    /**
     * The branches of the town of sending: a parcel goes in only at a branch (decision 14) — a
     * locker takes none in. A `Ref` typed — that branch, if it is of the town.
     *
     * @return list<SettingOption>
     */
    private function branches(?string $town, string $query, string $locale): array
    {
        $locality = $town === null ? null : $this->towns->localityId(NovaPoshtaCarrier::KEY, $town);

        if ($locality === null) {
            return [];
        }

        if (preg_match(self::REF, $query) === 1) {
            $point = $this->points->refOf(NovaPoshtaCarrier::KEY, $query);

            if ($point === null || $point->kind !== 'branch' || $point->localityId !== $locality || $point->removed || ! $point->active) {
                return [];
            }

            $title = (string) ($point->title($locale) ?? $point->number);

            return [new SettingOption($query, $title, Points::line(NovaPoshtaCarrier::KEY, $title, $point->address($locale)))];
        }

        $page = $this->points->page(NovaPoshtaCarrier::KEY, 'branch', $locality, $query, 1, $locale);
        $refs = $this->points->externalIds(array_map(static fn (array $point): int => $point['id'], $page['points']));
        $options = [];

        foreach ($page['points'] as $point) {
            if (isset($refs[$point['id']])) {
                $options[] = new SettingOption($refs[$point['id']], $point['title'], $point['line']);
            }
        }

        return $options;
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
