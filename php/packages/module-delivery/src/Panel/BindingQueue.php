<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Cache\CacheGroups;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Geography\GeographyException;
use WebxUi\Geography\Matcher;
use WebxUi\Geography\Matching\BindingStatus;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Geography\Matching\MatchResult;
use WebxUi\Geography\Models\GeoLocality;

/**
 * The binding queue (§4.4, §14.2): the records of the carriers no rule placed, and what a person
 * does with them — «Bind» to a locality, «Make a shared locality», «Do not bind» — one at a time
 * or a whole filter at once. Each decision goes through {@see Matcher}, so a binding learns the
 * spellings and the next sync places the same record without a person.
 */
final class BindingQueue
{
    public const string BIND = 'bind';

    public const string CREATE = 'create';

    public const string IGNORE = 'ignore';

    /** One request does at most this many: a bigger filter is the next request's. */
    public const int BATCH = 500;

    public function __construct(
        private readonly Matcher $matcher,
        private readonly CacheGroups $groups,
    ) {}

    /**
     * The pending records, by carrier and reason.
     *
     * @return Builder<DeliveryCarrierLocality>
     */
    public function query(?string $carrier = null, ?string $reason = null): Builder
    {
        return DeliveryCarrierLocality::query()
            ->whereNull('removed_at')
            ->where('status', BindingStatus::Pending->value)
            ->when($carrier !== null && $carrier !== '', static fn (Builder $query) => $query->where('carrier', $carrier))
            ->when($reason !== null && $reason !== '', static fn (Builder $query) => $query->where('reason', $reason));
    }

    /**
     * Apply one decision to these bindings. A record that refuses — a region of another country,
     * a record that no longer reads — is reported and the rest go on.
     *
     * @param  Collection<int, DeliveryCarrierLocality>  $bindings
     * @return array{done: int, failed: list<array{id: int, message: string}>}
     */
    public function apply(Collection $bindings, string $action, ?GeoLocality $locality = null): array
    {
        $done = 0;
        $failed = [];

        foreach ($bindings as $binding) {
            try {
                $record = LocalityRecord::fromArray($binding->record);
                $result = match ($action) {
                    self::BIND => $this->matcher->bind($binding->carrier, $record, $locality ?? throw new GeographyException('Choose the locality to bind to.')),
                    self::CREATE => $this->matcher->create($binding->carrier, $record),
                    default => MatchResult::ignored(),
                };

                $this->write($binding, $result);
                $done++;
            } catch (GeographyException $refusal) {
                $failed[] = ['id' => $binding->id, 'message' => $refusal->getMessage()];
            }
        }

        if ($done > 0) {
            // The points of a newly bound locality are offered now, not after the next night.
            $this->groups->bump(Synchronizer::POINTS_GROUP);
        }

        return ['done' => $done, 'failed' => $failed];
    }

    /** The binding takes its decision, and its points follow it to the locality (§5). */
    private function write(DeliveryCarrierLocality $binding, MatchResult $result): void
    {
        DB::transaction(static function () use ($binding, $result): void {
            $binding->forceFill([
                'status' => $result->status->value,
                'locality_id' => $result->localityId,
                'method' => $result->method?->value,
                'reason' => $result->reason?->value,
                'candidates' => null,
            ])->save();

            DB::table('delivery_points')
                ->where('carrier', $binding->carrier)
                ->where('carrier_locality_id', $binding->id)
                ->update(['locality_id' => $result->localityId]);
        });
    }
}
