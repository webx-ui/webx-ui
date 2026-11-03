<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Http\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Intl\Countries as IntlCountries;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Panel\LocalityForm;
use WebxUi\Delivery\Panel\LocalityRows;
use WebxUi\Delivery\Sync\StoredLeadingCarriers;
use WebxUi\Geography\Editor;
use WebxUi\Geography\GeographyException;
use WebxUi\Geography\Matching\LeadingCarriers;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Normalizer;
use WebxUi\Localization\Locales;

/**
 * The shared geography by hand (decision 42, §4.6, §14.2): the countries of the site, their
 * regions, and the localities — listed, searched, made, changed, switched off or deleted. Every
 * write goes through {@see Editor}, the same service the MCP tools use (stage 11), so the panel
 * and an agent make the same town and run into the same duplicate.
 */
final class GeographyController
{
    public function __construct(
        private readonly Editor $editor,
        private readonly Locales $locales,
    ) {}

    // Countries

    /**
     * The site's countries with their counts and leading carrier, and the ISO list to switch one
     * on from — named in the administrator's language by `symfony/intl`.
     */
    public function countries(Carriers $carriers, LeadingCarriers $leaders): JsonResponse
    {
        $locale = $this->locales->current();
        $rows = GeoCountry::query()
            ->withCount(['regions', 'localities'])
            ->orderByDesc('active')
            ->orderBy('position')
            ->orderBy('code')
            ->get();

        $configured = $leaders instanceof StoredLeadingCarriers
            ? static fn (string $code): bool => $leaders->configured($code)
            : static fn (string $code): bool => true;

        return ApiResponse::data([
            'countries' => $rows->map(static fn (GeoCountry $country): array => [
                'code' => $country->code,
                'name' => $country->name($locale),
                'active' => $country->active,
                'position' => $country->position,
                'regions' => (int) $country->getAttribute('regions_count'),
                'localities' => (int) $country->getAttribute('localities_count'),
                'leader' => $leaders->leader($country->code),
                'leader_fixed' => $configured($country->code),
            ])->values()->all(),
            'choices' => collect(IntlCountries::getNames($locale))
                ->map(static fn (string $name, string $code): array => ['code' => $code, 'name' => $name])
                ->values()
                ->all(),
            // The carriers that may lead, by their titles: the select is read, the key is stored.
            'carriers' => array_values(array_map(
                static fn (string $key): array => ['value' => $key, 'label' => $carriers->get($key)->title($locale)],
                array_filter($carriers->keys(), static fn (string $key): bool => $key !== Carriers::STORE),
            )),
        ]);
    }

    /** Switch a country on — a new one from the ISO list, or one a carrier brought switched off. */
    public function enableCountry(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));

        $this->attempt('code', fn () => $this->editor->enableCountry($code));

        return ApiResponse::data(['code' => $code], 201);
    }

    /** Off, not gone: its localities stay where points and orders point at them. */
    public function disableCountry(string $code): JsonResponse
    {
        $this->attempt('code', fn () => $this->editor->disableCountry($code));

        return ApiResponse::noContent();
    }

    /**
     * Take a country out of the site (decision 42): gone when nothing is in it, switched off when
     * regions or localities are — points, selections and orders name those, and keep them.
     */
    public function removeCountry(string $code): JsonResponse
    {
        $removal = $this->attempt('code', fn () => $this->editor->removeCountry($code));

        return ApiResponse::data(['removal' => $removal->value]);
    }

    /** The order of the country choice in the checkout: the codes as dragged. */
    public function orderCountries(Request $request): JsonResponse
    {
        $codes = array_values(array_filter(array_map(
            static fn (mixed $code): string => strtoupper(trim((string) $code)),
            (array) $request->input('codes', []),
        )));

        foreach ($codes as $position => $code) {
            GeoCountry::query()->whereKey($code)->update(['position' => $position]);
        }

        // The update above fires no model events: the countries of the step are cached (§17).
        $first = GeoCountry::query()->whereKey($codes[0] ?? '')->first();
        $first?->touch();

        return ApiResponse::noContent();
    }

    /** The leading carrier of a country (§4.4); empty — the next one to sync it leads. */
    public function lead(Request $request, string $code, LeadingCarriers $leaders, Carriers $carriers): JsonResponse
    {
        $carrier = $request->input('carrier');
        $carrier = is_string($carrier) && $carrier !== '' ? $carrier : null;

        if ($carrier !== null && (! $carriers->has($carrier) || $carrier === Carriers::STORE)) {
            throw ValidationException::withMessages(['carrier' => [__('webx-delivery::panel.errors.unknown_carrier')]]);
        }

        if (! $leaders instanceof StoredLeadingCarriers || $leaders->configured($code)) {
            throw ValidationException::withMessages(['carrier' => [__('webx-delivery::panel.errors.leader_configured')]]);
        }

        $leaders->choose($code, $carrier);

        return ApiResponse::noContent();
    }

    // Regions

    public function regions(Request $request): JsonResponse
    {
        $locale = $this->locales->current();
        $country = strtoupper((string) $request->query('country', ''));

        $regions = GeoRegion::query()
            ->where('country_code', $country)
            ->withCount('localities')
            ->orderBy('position')
            ->orderBy('name_key')
            ->get();

        return ApiResponse::data($regions->map(static fn (GeoRegion $region): array => [
            'id' => $region->id,
            'name' => $region->name($locale),
            'names' => $region->names,
            'code' => $region->code,
            'country' => $region->country_code,
            'position' => $region->position,
            'localities' => (int) $region->getAttribute('localities_count'),
        ])->values()->all());
    }

    /**
     * The order of a country's regions: the ids as dragged. Only that country's are moved — an id
     * of another one in the list is passed over, not pulled across.
     */
    public function orderRegions(Request $request): JsonResponse
    {
        $country = strtoupper(trim((string) $request->input('country')));
        $ids = array_values(array_filter(array_map(intval(...), (array) $request->input('ids', []))));

        foreach ($ids as $position => $id) {
            GeoRegion::query()->whereKey($id)->where('country_code', $country)->update(['position' => $position]);
        }

        return ApiResponse::noContent();
    }

    public function storeRegion(Request $request): JsonResponse
    {
        $region = $this->attempt('names', fn () => $this->editor->createRegion(
            (string) $request->input('country'),
            (array) $request->input('names', []),
            $request->filled('code') ? (string) $request->input('code') : null,
        ));

        return ApiResponse::data(['id' => $region->id], 201);
    }

    public function updateRegion(Request $request, GeoRegion $region): JsonResponse
    {
        $changes = array_intersect_key($request->only(['names', 'code']), ['names' => true, 'code' => true]);

        $this->attempt('names', fn () => $this->editor->updateRegion($region, $changes));

        return ApiResponse::noContent();
    }

    /** A region with localities is refused, with the reason under `region`. */
    public function destroyRegion(GeoRegion $region): JsonResponse
    {
        $this->attempt('region', fn () => $this->editor->deleteRegion($region));

        return ApiResponse::noContent();
    }

    // Localities

    /**
     * The localities, a page at a time: by country (none — every country that is on), region,
     * source and a prefix of any of their names. Switched-off localities are listed too — this is
     * where they are switched back on.
     *
     * An empty page says where the same search and filters do find something
     * (`elsewhere`: the countries, on or off) — a town in a country that is off, or in another
     * country than the one chosen, is not «nothing».
     */
    public function localities(Request $request, Carriers $carriers): JsonResponse
    {
        $locale = $this->locales->current();
        $country = strtoupper((string) $request->query('country', ''));
        $key = Normalizer::key((string) $request->query('q', ''));
        $source = (string) $request->query('source', '');
        $state = (string) $request->query('state', '');

        // The same filters for these countries — the chosen one, those on, or the others. The names
        // are asked by country too: the index of the search rows starts with it.
        $filtered = static function (array $countries) use ($key, $source, $state): Builder {
            $query = GeoLocality::query()->whereIn('country_code', $countries);

            match ($source) {
                'manual' => $query->where('created_by', GeoLocality::MANUAL),
                'import' => $query->where('created_by', GeoLocality::IMPORT),
                'carrier' => $query->where('created_by', 'like', 'carrier:%'),
                default => null,
            };

            match ($state) {
                'active' => $query->where('active', true),
                'inactive' => $query->where('active', false),
                default => null,
            };

            if ($key !== '') {
                $query->whereIn('id', static function ($names) use ($key, $countries): void {
                    $names->select('locality_id')
                        ->from('geo_locality_names')
                        ->whereIn('country_code', $countries)
                        ->whereRaw("name_key like ? escape '!'", [Normalizer::likePrefix($key)]);
                });
            }

            return $query;
        };

        $all = GeoCountry::query()->get(['code', 'active']);
        $scope = $country !== '' ? [$country] : $all->where('active', true)->pluck('code')->all();
        $query = $filtered($scope)->with('region');

        if ($request->filled('region')) {
            $query->where('region_id', (int) $request->query('region'));
        }

        $page = $query->orderByDesc('weight')->orderBy('id')->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        // The carriers bound to the page's localities: one query for the page, not one a row.
        $bindings = DeliveryCarrierLocality::query()
            ->whereIn('locality_id', $page->getCollection()->modelKeys())
            ->whereNull('removed_at')
            ->get(['carrier', 'external_id', 'status', 'locality_id'])
            ->groupBy('locality_id');

        $title = static fn (string $key): string => $carriers->has($key) ? $carriers->get($key)->title($locale) : $key;

        $page->setCollection($page->getCollection()->map(
            static fn (GeoLocality $locality): array => LocalityRows::row($locality, $locale, $bindings->get($locality->id) ?? collect(), $title),
        ));

        $elsewhere = [];

        // A region belongs to one country: elsewhere it finds nothing, so it is not asked.
        if ($page->total() === 0 && ! $request->filled('region')) {
            $others = array_values(array_diff($all->pluck('code')->all(), $scope));
            $codes = $others === [] ? [] : $filtered($others)
                ->distinct()
                ->limit(10)
                ->pluck('country_code')
                ->all();

            $elsewhere = GeoCountry::query()->whereIn('code', $codes)->orderByDesc('active')->orderBy('position')->orderBy('code')->get()
                ->map(static fn (GeoCountry $one): array => ['code' => $one->code, 'name' => $one->name($locale), 'active' => $one->active])
                ->values()
                ->all();
        }

        return new JsonResponse([...$page->toArray(), 'elsewhere' => $elsewhere]);
    }

    /**
     * A picker's suggestions (§4.5) — the field of a point, the courier's list, the popular
     * towns: active localities of the country; without a country, of every country of the site.
     */
    public function suggest(Request $request): JsonResponse
    {
        $locale = $this->locales->current();
        $country = strtoupper((string) $request->query('country', ''));
        $key = Normalizer::key((string) $request->query('q', ''));
        $limit = max(1, min(20, (int) $request->query('limit', 10)));

        $countries = $country !== ''
            ? [$country]
            : GeoCountry::query()->where('active', true)->orderBy('position')->pluck('code')->all();

        $query = GeoLocality::query()->with('region')->where('active', true)->whereIn('country_code', $countries);

        if ($key !== '') {
            $query->whereIn('id', static function ($names) use ($key, $countries): void {
                $names->select('locality_id')
                    ->from('geo_locality_names')
                    ->whereIn('country_code', $countries)
                    ->whereRaw("name_key like ? escape '!'", [Normalizer::likePrefix($key)]);
            });
        }

        return ApiResponse::data($query->orderByDesc('weight')->orderByDesc('id')->limit($limit)->get()
            ->map(static fn (GeoLocality $locality): array => LocalityRows::brief($locality, $locale))
            ->values()
            ->all());
    }

    /** The short lines of chosen localities, by id — what a picker shows for a stored value. */
    public function brief(Request $request): JsonResponse
    {
        $locale = $this->locales->current();
        $ids = array_values(array_filter(array_map('intval', (array) $request->query('ids', []))));

        $rows = GeoLocality::query()->with('region')->whereKey($ids)->get()->keyBy('id');

        return ApiResponse::data(array_values(array_filter(array_map(
            static fn (int $id): ?array => $rows->has($id) ? LocalityRows::brief($rows->get($id), $locale) : null,
            $ids,
        ))));
    }

    public function locality(GeoLocality $locality, LocalityForm $form): JsonResponse
    {
        return ApiResponse::data($form->describe($locality));
    }

    /**
     * A locality by hand. One that looks like an existing one answers 409 with the similar ones,
     * so the form offers them; `force` creates it anyway (decision 42).
     */
    public function storeLocality(Request $request, LocalityForm $form): JsonResponse
    {
        $locality = $form->create((array) $request->input('values', []), $request->boolean('force'));

        return ApiResponse::data($form->describe($locality), 201);
    }

    public function updateLocality(Request $request, GeoLocality $locality, LocalityForm $form): JsonResponse
    {
        $locality = $form->update($locality, (array) $request->input('values', []), $request->boolean('force'));

        return ApiResponse::data($form->describe($locality));
    }

    /** Deleted when nothing holds it, switched off otherwise — the answer says which. */
    public function destroyLocality(GeoLocality $locality): JsonResponse
    {
        $removal = $this->editor->removeLocality($locality);

        return ApiResponse::data(['removal' => $removal->value]);
    }

    public function activate(GeoLocality $locality): JsonResponse
    {
        $this->editor->activate($locality);

        return ApiResponse::noContent();
    }

    /** Out of the suggestions and kept: the «Switch off» of a row, whoever holds the locality. */
    public function deactivate(GeoLocality $locality): JsonResponse
    {
        $this->editor->deactivate($locality);

        return ApiResponse::noContent();
    }

    /**
     * Run a write of the editor, turning its refusal into a 422 under the field the form shows it at.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function attempt(string $field, callable $write): mixed
    {
        try {
            return $write();
        } catch (GeographyException $refusal) {
            throw ValidationException::withMessages([$field => [$refusal->getMessage()]]);
        }
    }
}
