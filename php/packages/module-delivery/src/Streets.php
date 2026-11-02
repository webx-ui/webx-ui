<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use WebxUi\Geography\Names;
use WebxUi\Geography\Normalizer;

/**
 * The streets of the carriers' directories as the delivery step reads them (§8): suggestions by
 * a prefix inside one locality, and the street a carrier's courier was given. The site's own
 * tables only — nothing here calls a carrier or queues a job (decision 38). Not cached: a query
 * by the index is cheaper than writing the cache (§17).
 */
final class Streets
{
    public const int LIMIT = 10;

    /**
     * For a carrier's courier: the carrier's streets in force in the locality whose name begins
     * with the query, in order of the name — two queries, the binding and the streets. An empty
     * query gives the first streets: the list opens before the buyer types.
     *
     * @return list<StreetRef>
     */
    public function forCarrier(string $carrier, int $locality, string $query, ?string $locale = null, int $limit = self::LIMIT): array
    {
        $binding = $this->binding($carrier, $locality);

        if ($binding === null) {
            return [];
        }

        $rows = $this->prefix(DB::table('delivery_streets')->where('carrier_locality_id', $binding), $query)
            ->select(['external_id', 'kind', 'names', 'name_key'])
            ->orderBy('name_key')
            ->orderBy('id')
            ->limit(max(1, min($limit, 50)))
            ->get();

        return $rows->map(fn (object $row): StreetRef => $this->ref($row, $locale, (string) $row->external_id))->values()->all();
    }

    /**
     * For the shop's courier: the streets every carrier bound to the locality has there, a street
     * two carriers know shown once (§8.3). Only text — the buyer may type anything at all; no
     * query gives nothing, and a locality no carrier knows gives nothing, just the field.
     *
     * @return list<StreetRef>
     */
    public function forStore(int $locality, string $query, ?string $locale = null, int $limit = self::LIMIT): array
    {
        $limit = max(1, min($limit, 50));

        if (Normalizer::key($query) === '') {
            return [];
        }

        $bindings = DB::table('delivery_carrier_localities')
            ->where('locality_id', $locality)
            ->where('status', 'matched')
            ->whereNull('removed_at')
            ->pluck('id')
            ->all();

        if ($bindings === []) {
            return [];
        }

        // Each carrier may give the same street: enough rows that the glued ones still fill the list.
        $rows = $this->prefix(DB::table('delivery_streets')->whereIn('carrier_locality_id', $bindings), $query)
            ->select(['kind', 'names', 'name_key'])
            ->orderBy('name_key')
            ->orderBy('id')
            ->limit($limit * count($bindings))
            ->get();

        $streets = [];

        foreach ($rows as $row) {
            $streets[$row->name_key."\0".$row->kind] ??= $this->ref($row, $locale, null);
        }

        return array_slice(array_values($streets), 0, $limit);
    }

    /**
     * The street of the carrier's directory in the locality, in force — what a carrier's courier
     * may be sent to (decision 31). One query.
     */
    public function find(string $carrier, int $locality, string $streetId, ?string $locale = null): ?StreetRef
    {
        $row = DB::table('delivery_streets as s')
            ->join('delivery_carrier_localities as b', 'b.id', '=', 's.carrier_locality_id')
            ->select(['s.external_id', 's.kind', 's.names', 's.name_key'])
            ->where('b.carrier', $carrier)
            ->where('b.locality_id', $locality)
            ->where('b.status', 'matched')
            ->whereNull('b.removed_at')
            ->where('s.external_id', $streetId)
            ->whereNull('s.removed_at')
            ->first();

        return $row === null ? null : $this->ref($row, $locale, (string) $row->external_id);
    }

    /** The carrier's streets in force in the locality, as the last streets pass counted them; 0 — not bound. */
    public function count(string $carrier, int $locality): int
    {
        return (int) DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->where('locality_id', $locality)
            ->where('status', 'matched')
            ->whereNull('removed_at')
            ->orderBy('id')
            ->value('streets');
    }

    /** The carrier's binding of the locality in force: one a carrier and locality is the rule, the first stays. */
    private function binding(string $carrier, int $locality): ?int
    {
        $id = DB::table('delivery_carrier_localities')
            ->where('carrier', $carrier)
            ->where('locality_id', $locality)
            ->where('status', 'matched')
            ->whereNull('removed_at')
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function prefix(Builder $query, string $text): Builder
    {
        $query->whereNull('removed_at');
        $key = Normalizer::key($text);

        if ($key !== '') {
            $query->whereRaw("name_key like ? escape '!'", [Normalizer::likePrefix($key)]);
        }

        return $query;
    }

    private function ref(object $row, ?string $locale, ?string $id): StreetRef
    {
        $names = json_decode((string) $row->names, true);
        $name = (string) Names::pick(is_array($names) ? $names : [], $locale);
        $kind = (string) $row->kind;

        return new StreetRef(
            streetId: $id,
            name: self::label($name, $kind, $locale),
            kind: $kind,
            key: (string) $row->name_key,
        );
    }

    /** "Example" of kind `street` → "Example street" in English, the type word where the language puts it. */
    public static function label(string $name, string $kind, ?string $locale = null): string
    {
        $key = "webx-delivery::delivery.street_kinds.{$kind}";
        $label = trans($key, ['name' => $name], $locale);

        return is_string($label) && $label !== $key ? $label : $name;
    }
}
