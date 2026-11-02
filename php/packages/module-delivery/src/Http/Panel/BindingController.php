<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Panel\BindingQueue;
use WebxUi\Delivery\Panel\LocalityRows;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Names;
use WebxUi\Localization\Locales;

/**
 * The binding queue on screen (§14.2): the record as the carrier sent it, the reason, the
 * candidates with their distance — and the decisions, for the rows ticked or for the whole filter.
 */
final class BindingController
{
    public function __construct(
        private readonly BindingQueue $queue,
        private readonly Locales $locales,
    ) {}

    public function index(Request $request, Carriers $carriers): JsonResponse
    {
        $locale = $this->locales->current();
        $carrier = (string) $request->query('carrier', '');
        $reason = (string) $request->query('reason', '');

        $page = $this->queue->query($carrier, $reason)
            ->orderBy('carrier')
            ->orderBy('id')
            ->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        // Every candidate of the page in one query.
        $ids = $page->getCollection()
            ->flatMap(static fn (DeliveryCarrierLocality $binding): array => array_column($binding->candidates ?? [], 'locality_id'))
            ->unique()
            ->values()
            ->all();
        $candidates = GeoLocality::query()->with('region')->whereKey($ids)->get()->keyBy('id');

        $page->setCollection($page->getCollection()->map(static function (DeliveryCarrierLocality $binding) use ($locale, $candidates, $carriers): array {
            $record = $binding->record;

            return [
                'id' => $binding->id,
                'carrier' => $binding->carrier,
                'carrier_title' => $carriers->has($binding->carrier) ? $carriers->get($binding->carrier)->title($locale) : $binding->carrier,
                'external_id' => $binding->external_id,
                'reason' => $binding->reason,
                'record' => [
                    'name' => Names::pick(is_array($record['names'] ?? null) ? $record['names'] : null, $locale) ?? (string) ($record['name'] ?? ''),
                    'region' => Names::pick(is_array($record['regionNames'] ?? null) ? $record['regionNames'] : null, $locale) ?? (is_string($record['region'] ?? null) ? $record['region'] : null),
                    'district' => is_string($record['district'] ?? null) ? $record['district'] : null,
                    // The type in words: a known one by the dictionary, a carrier's own word as it is.
                    'kind' => is_string($record['kind'] ?? null) ? LocalityRows::kindLabel($record['kind'], $locale) : null,
                    'country' => $record['country'] ?? null,
                    'lat' => $record['lat'] ?? null,
                    'lng' => $record['lng'] ?? null,
                ],
                'candidates' => array_values(array_filter(array_map(
                    static fn (array $candidate): ?array => $candidates->has($candidate['locality_id'])
                        ? [...LocalityRows::brief($candidates->get($candidate['locality_id']), $locale), 'distance_km' => $candidate['distance_km'] ?? null]
                        : null,
                    $binding->candidates ?? [],
                ))),
            ];
        }));

        return new JsonResponse([
            ...$page->toArray(),
            'filters' => [
                'carriers' => $this->queue->query()->selectRaw('carrier, count(*) as total')->groupBy('carrier')->orderBy('carrier')->get()
                    ->map(static fn (DeliveryCarrierLocality $row): array => [
                        'value' => $row->carrier,
                        'label' => $carriers->has($row->carrier) ? $carriers->get($row->carrier)->title($locale) : $row->carrier,
                        'count' => (int) $row->getAttribute('total'),
                    ])->values()->all(),
                'reasons' => $this->queue->query($carrier)->selectRaw('reason, count(*) as total')->groupBy('reason')->orderBy('reason')->get()
                    ->map(static fn (DeliveryCarrierLocality $row): array => ['value' => (string) $row->reason, 'count' => (int) $row->getAttribute('total')])
                    ->values()->all(),
            ],
        ]);
    }

    /**
     * One decision for many: the rows ticked (`ids`), or every row of the filter (`all` with
     * `carrier` and `reason`), at most {@see BindingQueue::BATCH} a request — the answer says how
     * many are left.
     */
    public function batch(Request $request): JsonResponse
    {
        $action = (string) $request->input('action');

        if (! in_array($action, [BindingQueue::BIND, BindingQueue::CREATE, BindingQueue::IGNORE], true)) {
            throw ValidationException::withMessages(['action' => [__('validation.in', ['attribute' => 'action'])]]);
        }

        $locality = null;

        if ($action === BindingQueue::BIND) {
            $locality = GeoLocality::query()->find((int) $request->input('locality_id'));

            if ($locality === null) {
                throw ValidationException::withMessages(['locality_id' => [__('webx-delivery::panel.errors.locality_required')]]);
            }
        }

        $query = $this->queue->query((string) $request->input('carrier', ''), (string) $request->input('reason', ''));

        if (! $request->boolean('all')) {
            $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

            if ($ids === []) {
                throw ValidationException::withMessages(['ids' => [__('webx-delivery::panel.errors.nothing_chosen')]]);
            }

            $query->whereKey($ids);
        }

        $result = $this->queue->apply($query->orderBy('id')->limit(BindingQueue::BATCH)->get(), $action, $locality);

        return ApiResponse::data([
            ...$result,
            'left' => $request->boolean('all')
                ? $this->queue->query((string) $request->input('carrier', ''), (string) $request->input('reason', ''))->count()
                : 0,
        ]);
    }
}
