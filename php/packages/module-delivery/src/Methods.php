<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Support\Facades\DB;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\Contracts\MethodType;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryMethod;

/**
 * What a buyer is offered in a locality (§6.3, decision 7). Three reads at most, none of them per
 * method and none of them a carrier's API (decision 38): the methods and carriers from the cache
 * group `delivery`, the points of the locality counted by carrier and kind, and the carriers'
 * bindings of the locality with their features and streets.
 */
final class Methods
{
    public const string GROUP = 'delivery';

    public function __construct(
        private readonly MethodTypes $types,
        private readonly Carriers $carriers,
        private readonly CacheGroups $groups,
        private readonly Quotes $quotes,
    ) {}

    /**
     * The methods offered, in the order of the panel. A carrier's courier without streets in the
     * locality is among them, `disabled` with a hint (§8.2).
     *
     * @return list<MethodOption>
     */
    public function forLocality(DeliveryContext $context): array
    {
        return array_values(array_filter(
            $this->evaluate($context),
            static fn (MethodOption $option): bool => $option->offered(),
        ));
    }

    /**
     * Every enabled method with its price or the reason it is not offered — what MCP explains.
     *
     * @return list<MethodOption>
     */
    public function evaluate(DeliveryContext $context): array
    {
        return $this->judgeAll($context, null);
    }

    /**
     * One method of the site as the locality and the cart see it — what the chain of the cart
     * prices (§9.3) without pricing every other; null — no such method enabled.
     */
    public function option(DeliveryContext $context, int $method): ?MethodOption
    {
        return $this->judgeAll($context, $method)[0] ?? null;
    }

    /** @return list<MethodOption> */
    private function judgeAll(DeliveryContext $context, ?int $only): array
    {
        ['methods' => $rows, 'carriers' => $enabled] = $this->catalogue();
        $candidates = [];

        foreach ($rows as $row) {
            $method = Method::fromArray($row);

            if ($only !== null && $method->id !== $only) {
                continue;
            }

            if (! $this->types->has($method->type)) {
                $candidates[] = new MethodOption($method, null, unavailable: MethodOption::TYPE_MISSING);

                continue;
            }

            $type = $this->types->get($method->type);
            $carrier = $type->carrier();

            if (! ($enabled[$carrier] ?? false) || ! $this->carriers->has($carrier)) {
                $candidates[] = new MethodOption($method, $type, unavailable: MethodOption::CARRIER_OFF);

                continue;
            }

            $countries = $method->countries ?? $type->countries();

            if ($countries !== null && ! in_array($context->country, $countries, true)) {
                $candidates[] = new MethodOption($method, $type, unavailable: MethodOption::COUNTRY);

                continue;
            }

            $candidates[] = new MethodOption($method, $type);
        }

        $live = array_filter($candidates, static fn (MethodOption $option): bool => $option->offered());
        $points = $this->pointsOf($context->locality->id, $live);
        $bindings = $this->bindingsOf($context->locality->id, $live);

        return array_map(
            fn (MethodOption $option): MethodOption => $option->offered()
                ? $this->judge($option, $context, $points, $bindings)
                : $option,
            $candidates,
        );
    }

    /**
     * The enabled methods in the order of the panel and the carriers that are on: arrays, as the
     * cache keeps no objects.
     *
     * @return array{methods: list<array<string, mixed>>, carriers: array<string, bool>}
     */
    public function catalogue(): array
    {
        return $this->groups->remember('delivery.methods', [self::GROUP], static fn (): array => [
            'methods' => DeliveryMethod::query()
                ->where('enabled', true)
                ->with('localities:id')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(static fn (DeliveryMethod $method): array => $method->toValueArray())
                ->values()
                ->all(),
            'carriers' => DeliveryCarrier::query()
                ->pluck('enabled', 'key')
                ->map(static fn (mixed $on): bool => (bool) $on)
                ->all(),
        ], localized: false);
    }

    /**
     * @param  array<string, array<string, int>>  $points  carrier → kind → count
     * @param  array<string, array{external_id: string, features: list<string>, streets: int}>  $bindings  by carrier
     */
    private function judge(MethodOption $option, DeliveryContext $context, array $points, array $bindings): MethodOption
    {
        /** @var MethodType $type */
        $type = $option->type;
        $method = $option->method;
        $carrier = $type->carrier();
        $binding = $bindings[$carrier] ?? null;
        $count = null;
        $disabled = false;
        $hint = null;

        if ($type->asks() === Asks::Point) {
            $kind = $method->pointKind ?? ($type->pointKinds()[0] ?? '');
            $count = $points[$carrier][$kind] ?? 0;

            if ($count === 0) {
                return new MethodOption($method, $type, points: 0, unavailable: MethodOption::NO_POINTS);
            }
        } elseif ($carrier !== Carriers::STORE) {
            // A carrier's courier: the carrier knows the locality and goes to doors there.
            $feature = explode('.', $type->key(), 2)[1];

            if ($binding === null || ! in_array($feature, $binding['features'], true)) {
                return new MethodOption($method, $type, unavailable: MethodOption::NOT_BOUND);
            }

            if ($type->asks() === Asks::Address && $binding['streets'] === 0) {
                $disabled = true;
                $hint = (string) trans('webx-delivery::delivery.hints.no_streets', [], $context->locale);
            }
        }

        $context = $context->forCarrier($binding['external_id'] ?? null);

        if (! $type->available($method, $context)) {
            return new MethodOption($method, $type, points: $count, unavailable: MethodOption::NOT_AVAILABLE);
        }

        $quote = $this->quotes->quote($method, $type, $context);

        if (! $quote->available()) {
            return new MethodOption($method, $type, $quote, $count, MethodOption::NO_QUOTE);
        }

        return new MethodOption($method, $type, $quote, $count, disabled: $disabled, hint: $hint);
    }

    /**
     * Active points of the locality not removed, by carrier and kind — one query for every method
     * (§6.3); none when no method asks for a point.
     *
     * @param  array<int, MethodOption>  $options
     * @return array<string, array<string, int>>
     */
    private function pointsOf(int $locality, array $options): array
    {
        $asking = array_filter($options, static fn (MethodOption $option): bool => $option->type?->asks() === Asks::Point);

        if ($asking === []) {
            return [];
        }

        $counts = [];
        $rows = DB::table('delivery_points')
            ->select('carrier', 'kind', DB::raw('count(*) as aggregate'))
            ->where('locality_id', $locality)
            ->where('active', true)
            ->whereNull('removed_at')
            ->groupBy('carrier', 'kind')
            ->get();

        foreach ($rows as $row) {
            $counts[(string) $row->carrier][(string) $row->kind] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * The carriers' bindings of the locality: their own id of it, what they do there and how many
     * streets they have — one query, none when every method is the shop's.
     *
     * @param  array<int, MethodOption>  $options
     * @return array<string, array{external_id: string, features: list<string>, streets: int}>
     */
    private function bindingsOf(int $locality, array $options): array
    {
        $carriers = array_values(array_unique(array_filter(
            array_map(static fn (MethodOption $option): ?string => $option->type?->carrier(), $options),
            static fn (?string $carrier): bool => $carrier !== null && $carrier !== Carriers::STORE,
        )));

        if ($carriers === []) {
            return [];
        }

        $bindings = [];
        $rows = DB::table('delivery_carrier_localities')
            ->select('carrier', 'external_id', 'features', 'streets')
            ->where('locality_id', $locality)
            ->whereIn('carrier', $carriers)
            ->where('status', 'matched')
            ->whereNull('removed_at')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $features = json_decode((string) $row->features, true);
            // One binding a carrier and locality is the rule; should two say so, the first stays.
            $bindings[(string) $row->carrier] ??= [
                'external_id' => (string) $row->external_id,
                'features' => is_array($features) ? array_values(array_filter($features, is_string(...))) : [],
                'streets' => (int) $row->streets,
            ];
        }

        return $bindings;
    }
}
