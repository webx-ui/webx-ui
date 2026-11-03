<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\Contracts\NamesPointLines;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Sync\Limits;
use WebxUi\Delivery\Sync\Schedule;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Geography\Names;
use WebxUi\Geography\Normalizer;

/**
 * The points as the base hands them out: copies of rows for method types, and the list of a
 * method in a locality for the step (§12.2) — a page of 20, searched inside the locality only.
 *
 * The search is `LIKE '%q%'` on the normalised `search` string of the rows the locality, the
 * carrier and the kind already narrowed — up to a couple of thousand rows, within the rule of
 * `LIKE` under 5 000 (decision 35); a number typed exactly comes first. The first page without
 * a query is the same for every buyer and lives in the group `delivery-points` (§17); «today
 * until 18:00» is worked out on each read, never cached.
 */
final class Points
{
    public function __construct(private readonly CacheGroups $groups) {}

    public function ref(int $id): ?PointRef
    {
        $point = DeliveryPoint::query()->find($id);

        return $point === null ? null : PointRef::of($point);
    }

    /** A carrier's point by the carrier's own id — removed or not — or null. */
    public function refOf(string $carrier, string $externalId): ?PointRef
    {
        $point = DeliveryPoint::query()->where('carrier', $carrier)->where('external_id', $externalId)->first();

        return $point === null ? null : PointRef::of($point);
    }

    /**
     * The carrier's own ids of these points, one query: point id → the carrier's id; a point of
     * the shop's own, without one, is left out.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function externalIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DeliveryPoint::query()
            ->whereKey($ids)
            ->whereNotNull('external_id')
            ->pluck('external_id', 'id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * A page of the points of a carrier's kind in a locality: two queries with a query or after
     * the first page (the rows and their count), none or one for the first page without one.
     *
     * @return array{points: list<array<string, mixed>>, total: int, page: int, more: bool}
     */
    public function page(string $carrier, string $kind, int $locality, string $query = '', int $page = 1, ?string $locale = null): array
    {
        $perPage = max(1, min(100, (int) config('webx-delivery.points_per_page', 20)));
        $page = max(1, $page);
        $key = Normalizer::key($query);

        $load = function () use ($carrier, $kind, $locality, $key, $page, $perPage): array {
            $base = $this->base($carrier, $kind, $locality);

            if ($key !== '') {
                $base->whereRaw("search like ? escape '!'", ['%'.self::escapeLike($key).'%']);
            }

            $total = (clone $base)->count();
            $rows = $base
                ->select(['id', 'number', 'titles', 'address', 'postcode', 'schedule', 'kind', 'restriction'])
                // A number typed in full first; then the points open to all before those for some
                // people only (a locker for the residents of a block); the short numbers before the
                // long ones: «2» before «10».
                ->when($key !== '', static fn (Builder $q) => $q->orderByRaw('case when number = ? then 0 else 1 end', [$key]))
                ->orderByRaw('case when restriction is null then 0 else 1 end')
                ->orderByRaw('length(coalesce(number, \'\'))')
                ->orderBy('number')
                ->orderBy('id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();

            return ['rows' => $rows, 'total' => $total];
        };

        $found = $key === '' && $page === 1
            ? $this->groups->remember("delivery.points:{$carrier}:{$kind}:{$locality}", [Synchronizer::POINTS_GROUP], $load, localized: false)
            : $load();

        return [
            'points' => array_values(array_map(fn (array $row): array => $this->item($row, $locale, $carrier), $found['rows'])),
            'total' => $found['total'],
            'page' => $page,
            'more' => $page * $perPage < $found['total'],
        ];
    }

    /**
     * The card of a point: the week, the limits, the phone. One query.
     *
     * @return array<string, mixed>|null
     */
    public function card(int $id, ?string $locale = null): ?array
    {
        $point = DeliveryPoint::query()->whereKey($id)->where('active', true)->whereNull('removed_at')->first();

        if ($point === null) {
            return null;
        }

        $schedule = Schedule::fromArray($point->schedule);

        return [
            ...$this->item([
                'id' => $point->id,
                'number' => $point->number,
                'titles' => $point->titles,
                'address' => $point->address,
                'postcode' => $point->postcode,
                'schedule' => $point->schedule,
                'kind' => $point->kind,
                'restriction' => $point->restriction,
            ], $locale, $point->carrier),
            'carrier' => $point->carrier,
            'locality_id' => $point->locality_id,
            'phone' => $point->phone,
            'week' => $schedule === null ? [] : $schedule->week,
            'limits' => Limits::fromArray($point->limits)?->toArray(),
            'lat' => $point->lat,
            'lng' => $point->lng,
        ];
    }

    /**
     * One row as the list shows it.
     *
     * @param  array<string, mixed>  $row
     * @return array{id: int, number: string|null, kind: string, title: string, address: string, line: string|null, postcode: string|null, today: string|null, restriction: string|null, restricted: string|null}
     */
    private function item(array $row, ?string $locale, ?string $carrier = null): array
    {
        $decode = static fn (mixed $value): ?array => is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : null);
        $number = isset($row['number']) ? (string) $row['number'] : null;
        $kind = (string) ($row['kind'] ?? '');
        $title = Names::pick($decode($row['titles'] ?? null), $locale);
        $schedule = Schedule::fromArray($decode($row['schedule'] ?? null));
        $restriction = isset($row['restriction']) && $row['restriction'] !== '' ? (string) $row['restriction'] : null;
        $title = $title ?? trim(app(PointKinds::class)->label($kind, $locale).' '.$number);
        $address = (string) Names::pick($decode($row['address'] ?? null), $locale);

        return [
            'id' => (int) $row['id'],
            'number' => $number,
            'kind' => $kind,
            'title' => $title,
            'address' => $address,
            // What a list puts under the title: the address, unless the carrier's titles say it.
            'line' => self::line($carrier, $title, $address),
            'postcode' => isset($row['postcode']) ? (string) $row['postcode'] : null,
            'today' => $schedule?->todayLabel($locale, null, (string) config('app.timezone', 'UTC')),
            'restriction' => $restriction,
            'restricted' => $restriction === null ? null : self::restriction($restriction, $locale),
        ];
    }

    /** The line under a point's title in a list: the carrier's own ({@see NamesPointLines}) or the address. */
    public static function line(?string $carrier, string $title, string $address): ?string
    {
        $carriers = app(Carriers::class);
        $one = $carrier !== null && $carriers->has($carrier) ? $carriers->get($carrier) : null;
        $line = $one instanceof NamesPointLines ? $one->pointLine($title, $address) : $address;

        return $line === null || trim($line) === '' ? null : $line;
    }

    /**
     * Who may use a point, in words: the known codes by name, any other as «limited access».
     */
    public static function restriction(string $code, ?string $locale = null): string
    {
        $key = 'webx-delivery::delivery.restrictions.'.$code;
        $words = __($key, [], $locale);

        return is_string($words) && $words !== $key ? $words : (string) __('webx-delivery::delivery.restrictions.other', [], $locale);
    }

    private function base(string $carrier, string $kind, int $locality): Builder
    {
        return DB::table('delivery_points')
            ->where('locality_id', $locality)
            ->where('active', true)
            ->whereNull('removed_at')
            ->where('carrier', $carrier)
            ->where('kind', $kind);
    }

    private static function escapeLike(string $text): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);
    }
}
