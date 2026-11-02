<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Mcp;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Symfony\Component\Intl\Countries as IntlCountries;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Panel\BindingQueue;
use WebxUi\Delivery\Panel\LocalityForm;
use WebxUi\Delivery\Panel\LocalityRows;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Delivery\Sync\StoredLeadingCarriers;
use WebxUi\Geography\DuplicateLocality;
use WebxUi\Geography\Editor;
use WebxUi\Geography\LocalityKind;
use WebxUi\Geography\LocalityReferences;
use WebxUi\Geography\Matching\LeadingCarriers;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Names;
use WebxUi\Geography\Normalizer;
use WebxUi\Geography\Removal;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The shared geography by hand, the popular towns and the binding queue for an agent (§15,
 * decisions 40 and 42) — through the doors the panel uses: every write of the geography is
 * {@see Editor}'s (the locality through {@see LocalityForm}, which checks the screen), the popular
 * towns are {@see PopularLocalities}, the queue is {@see BindingQueue}. A town made here runs into
 * the same duplicate a town made in the panel does.
 */
final class GeographyTools
{
    use ToolSupport;

    public function __construct(private readonly Container $container) {}

    /** @return list<Tool> */
    public function all(): array
    {
        $country = ['type' => 'string', 'description' => 'An ISO 3166-1 code: "XX".'];
        $locality = ['type' => ['integer', 'string'], 'description' => 'A locality: its id, or its exact name when only one place has it.'];
        $names = ['type' => ['string', 'object'], 'description' => 'One language as a string (the default one), or every language as { "en": "…" }.'];

        return Arguments::strictAll([
            Tool::read(
                'localities_search',
                'The shared localities, twenty-five a page, the heaviest first: by a prefix of any of their names (or '
                .'of a later word of one), country, region, source (manual, import, carrier) and state. Each with its '
                .'region, type, weight, where it came from, the carriers bound to it and how many points it has. '
                .'Switched-off ones are listed too, and say so.',
                fn (array $arguments): array => $this->search($arguments),
                ['properties' => [
                    'q' => ['type' => 'string', 'description' => 'The start of a name.'],
                    'country' => $country,
                    'region' => ['type' => 'integer', 'description' => 'A region\'s id, as delivery_regions_list gives it.'],
                    'source' => ['type' => 'string', 'enum' => ['manual', 'import', 'carrier']],
                    'state' => ['type' => 'string', 'enum' => ['active', 'inactive', 'all'], 'description' => 'all by default.'],
                    'page' => ['type' => 'integer', 'description' => '1 by default.'],
                ]],
            ),

            Tool::read(
                'countries_list',
                'The countries of the site — on or off, in the order of the checkout, each with its regions and '
                .'localities counted and its leading carrier — and the ISO codes with their names that can be '
                .'switched on.',
                fn (): array => $this->countries(),
            ),

            Tool::mutating(
                'countries_set',
                'Switch a country on (`active: true` — any ISO code, from the list of delivery_countries_list) or off, '
                .'move it (`position`), give it the site\'s own name (`names`; null — the standard one), choose its '
                .'leading carrier (`leader`; null — the next to sync it leads), or take it out (`remove: true` — gone '
                .'when it is empty, switched off when it has regions or localities).',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->setCountry($arguments)),
                ['properties' => [
                    'code' => $country,
                    'active' => ['type' => 'boolean'],
                    'position' => ['type' => 'integer'],
                    'names' => ['type' => ['object', 'null'], 'description' => 'The site\'s own name by language.'],
                    'leader' => ['type' => ['string', 'null'], 'description' => 'A carrier\'s key.'],
                    'remove' => ['type' => 'boolean'],
                ], 'required' => ['code']],
            ),

            Tool::read(
                'regions_list',
                'The regions of a country in their order, with their names in every language, their codes and how many '
                .'localities each has — the ids delivery_localities_save takes.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->regions($arguments)),
                ['properties' => ['country' => $country], 'required' => ['country']],
            ),

            Tool::mutating(
                'regions_save',
                'Create a region of a country (no `region`), rename it, give it a code or a place in the order '
                .'(`position`), or delete it (`delete: true` — only a region without localities). Two regions of one '
                .'name in a country are refused.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->saveRegion($arguments)),
                ['properties' => [
                    'country' => $country,
                    'region' => ['type' => 'integer', 'description' => 'The region to change or delete.'],
                    'names' => $names,
                    'code' => ['type' => ['string', 'null']],
                    'position' => ['type' => 'integer'],
                    'delete' => ['type' => 'boolean'],
                ]],
            ),

            Tool::mutating(
                'localities_save',
                'Create a locality by hand (no `locality`; it is marked created_by = manual) or change one. `values`: names '
                .'by language, kind ('.implode(', ', LocalityKind::values()).'), country, region_id, lat, lng, weight '
                .'(the higher, the earlier in the suggestions), active. A field left out keeps what it had. A locality '
                .'that looks like one already there — same country, region and kind, a name in common — is refused '
                .'with the similar ones (`duplicate: true`): take one of them, or send `force: true` to make it anyway. '
                .'A change of a carrier\'s record marks the field as changed by hand: the sync leaves it be.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->saveLocality($arguments)),
                ['properties' => [
                    'locality' => ['type' => 'integer', 'description' => 'The locality to change; a new one when omitted.'],
                    'values' => ['type' => 'object', 'description' => 'Field → value; see the description.'],
                    'force' => ['type' => 'boolean', 'description' => 'Make it although a similar one exists.'],
                ], 'required' => ['values']],
            ),

            Tool::mutating(
                'localities_remove',
                'Take a locality out: deleted when nothing holds it, switched off (out of the suggestions, kept) when '
                .'points, bindings, carts or orders do — the answer says which and who holds it. `action: "activate"` '
                .'switches it back on, `"deactivate"` switches it off whoever holds it.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->removeLocality($arguments)),
                ['properties' => [
                    'locality' => $locality,
                    'action' => ['type' => 'string', 'enum' => ['remove', 'deactivate', 'activate'], 'description' => 'remove by default.'],
                ], 'required' => ['locality']],
            ),

            Tool::read(
                'popular_list',
                'The popular towns of the checkout\'s dialog «Where to deliver the order?», in their order: id, the line '
                .'the buyer reads, whether it is on. One list for the site; a switched-off town stays in it and is not shown.',
                fn (): array => ['popular' => $this->popularRows($this->popular()->ids())],
            ),

            Tool::mutating(
                'popular_set',
                'Change the popular towns: the whole list in its new order (`localities`), or one town added '
                .'(`add`, at `position` counted from 0 — the end by default; one already there moves) or taken away '
                .'(`remove`). A dry run answers the list before and after.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->setPopular($arguments)),
                ['properties' => [
                    'localities' => ['type' => 'array', 'items' => $locality, 'description' => 'The whole list, first to last; [] empties it.'],
                    'add' => $locality,
                    'position' => ['type' => 'integer'],
                    'remove' => $locality,
                ]],
            ),

            Tool::read(
                'bindings_list',
                'The binding queue: carriers\' localities no rule placed, twenty-five a page — the record as the carrier '
                .'sent it, the reason, the candidates with their distance — and the counts by carrier and by reason.',
                fn (array $arguments): array => $this->bindings($arguments),
                ['properties' => [
                    'carrier' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                    'page' => ['type' => 'integer'],
                ]],
            ),

            Tool::mutating(
                'bindings_resolve',
                'Decide records of the binding queue: `bind` to a locality, `create` a shared locality of each record, or '
                .'`ignore` — the rows named (`ids`) or every row of a filter (`all: true` with `carrier`, `reason`), '
                .BindingQueue::BATCH.' at a time; the answer says how many are left. A binding learns the spellings: the '
                .'next sync places the same record without a person. A dry run says how many it would touch.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->resolve($arguments)),
                ['properties' => [
                    'action' => ['type' => 'string', 'enum' => [BindingQueue::BIND, BindingQueue::CREATE, BindingQueue::IGNORE]],
                    'ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'all' => ['type' => 'boolean'],
                    'carrier' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                    'locality' => $locality,
                ], 'required' => ['action']],
            ),
        ], 'delivery_');
    }

    // Localities

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function search(array $arguments): array
    {
        $locale = $this->locale();
        $country = strtoupper((string) ($arguments['country'] ?? ''));
        $key = Normalizer::key((string) ($arguments['q'] ?? ''));
        $query = GeoLocality::query()->with('region');

        if ($country !== '') {
            $query->where('country_code', $country);
        }

        if (isset($arguments['region'])) {
            $query->where('region_id', $this->int($arguments['region'], 'region', 1));
        }

        match ((string) ($arguments['source'] ?? '')) {
            'manual' => $query->where('created_by', GeoLocality::MANUAL),
            'import' => $query->where('created_by', GeoLocality::IMPORT),
            'carrier' => $query->where('created_by', 'like', 'carrier:%'),
            default => null,
        };

        match ((string) ($arguments['state'] ?? 'all')) {
            'active' => $query->where('active', true),
            'inactive' => $query->where('active', false),
            default => null,
        };

        if ($key !== '') {
            $query->whereIn('id', static function ($names) use ($key, $country): void {
                $names->select('locality_id')
                    ->from('geo_locality_names')
                    ->when($country !== '', static fn ($inner) => $inner->where('country_code', $country))
                    ->whereRaw("name_key like ? escape '!'", [Normalizer::likePrefix($key)]);
            });
        }

        $page = $query->orderByDesc('weight')->orderBy('id')->paginate(25, ['*'], 'page', $this->int($arguments['page'] ?? 1, 'page', 1));
        $ids = $page->getCollection()->modelKeys();

        // The carriers bound to the page and its points: a query each for the page, not one a row.
        $bindings = DeliveryCarrierLocality::query()
            ->whereIn('locality_id', $ids)
            ->whereNull('removed_at')
            ->get(['carrier', 'external_id', 'status', 'locality_id'])
            ->groupBy('locality_id');
        $points = DeliveryPoint::query()
            ->whereIn('locality_id', $ids)
            ->where('active', true)
            ->whereNull('removed_at')
            ->selectRaw('locality_id, count(*) as total')
            ->groupBy('locality_id')
            ->pluck('total', 'locality_id');

        $title = $this->carrierTitle($locale);

        return [
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'total' => $page->total(),
            'localities' => $page->getCollection()->map(static fn (GeoLocality $locality): array => [
                ...LocalityRows::row($locality, $locale, $bindings->get($locality->id) ?? collect(), $title),
                'names' => $locality->names,
                'points' => (int) ($points[$locality->id] ?? 0),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveLocality(array $arguments): array
    {
        $values = $arguments['values'] ?? null;

        if (! is_array($values)) {
            throw new ToolFailure('`values` is an object of field → value.');
        }

        Arguments::refuseUnknown($values, ['names', 'kind', 'country', 'region_id', 'lat', 'lng', 'weight', 'active'], 'delivery_localities_save');

        $form = $this->container->make(LocalityForm::class);
        $force = ($arguments['force'] ?? false) === true;
        $locality = null;

        if (isset($arguments['locality'])) {
            $locality = GeoLocality::query()->find($this->int($arguments['locality'], 'locality', 1))
                ?? throw new ToolFailure("Locality {$arguments['locality']} does not exist.");
            $current = LocalityRows::values($locality);

            if (array_key_exists('country', $values) && strtoupper((string) $values['country']) !== $locality->country_code) {
                throw new ToolFailure('A locality does not move to another country: make it there instead.');
            }
        } else {
            $current = ['active' => true];
        }

        if (array_key_exists('names', $values)) {
            $values['names'] = [...(array) ($current['names'] ?? []), ...$this->texts($values['names'], 'names')];
        }

        if (array_key_exists('country', $values)) {
            $values['country'] = strtoupper(trim((string) $values['country']));
        }

        $input = [...$current, ...$values];
        $work = fn (): array => $form->describe($locality === null ? $form->make($input, $force) : $form->change($locality, $input, $force));

        try {
            if ($this->dryRun($arguments)) {
                $would = $this->rehearse($work);

                if ($locality === null) {
                    $would['locality']['id'] = null;
                }

                return ['dry_run' => true, 'duplicate' => false, 'would' => $locality === null ? 'create' : 'update', ...$would];
            }

            return [($locality === null ? 'created' : 'updated') => true, 'duplicate' => false, ...$work()];
        } catch (DuplicateLocality $duplicate) {
            return [
                ...($this->dryRun($arguments) ? ['dry_run' => true] : []),
                ($locality === null ? 'created' : 'updated') => false,
                'duplicate' => true,
                'message' => 'A locality like this one is already there. Use one of the similar ones, or send force: true to make it anyway.',
                'similar' => $this->similar($duplicate->similar),
            ];
        }
    }

    /**
     * @param  Collection<int, GeoLocality>  $similar
     * @return list<array<string, mixed>>
     */
    private function similar(Collection $similar): array
    {
        $locale = $this->locale();

        // With their regions in one query: the editor's answer has them bare.
        return GeoLocality::query()->with('region')->whereKey($similar->map(static fn (GeoLocality $one): int => $one->id)->all())->get()
            ->map(static fn (GeoLocality $one): array => [...LocalityRows::brief($one, $locale), 'source' => LocalityRows::source($one), 'created_by' => $one->created_by])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function removeLocality(array $arguments): array
    {
        $locality = $this->locality($arguments['locality'] ?? null);
        $editor = $this->container->make(Editor::class);
        $action = (string) ($arguments['action'] ?? 'remove');
        $held = $this->container->make(LocalityReferences::class)->holding($locality);
        $brief = LocalityRows::brief($locality, $this->locale());

        $result = match ($action) {
            'remove' => $held === [] ? Removal::Deleted->value : Removal::Deactivated->value,
            'deactivate' => 'deactivated',
            'activate' => 'activated',
            default => throw new ToolFailure('`action` is remove, deactivate or activate.'),
        };

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, 'locality' => $brief, 'would' => $result, 'held_by' => $held];
        }

        match ($action) {
            'remove' => $editor->removeLocality($locality),
            'deactivate' => $editor->deactivate($locality),
            default => $editor->activate($locality),
        };

        return ['locality' => $brief['id'], 'label' => $brief['label'], 'result' => $result, 'held_by' => $held];
    }

    // Countries and regions

    /** @return array<string, mixed> */
    private function countries(): array
    {
        $locale = $this->locale();
        $leaders = $this->container->make(LeadingCarriers::class);
        $rows = GeoCountry::query()->withCount(['regions', 'localities'])
            ->orderByDesc('active')->orderBy('position')->orderBy('code')->get();
        $on = $rows->pluck('code')->all();

        return [
            'countries' => $rows->map(static fn (GeoCountry $country): array => [
                'code' => $country->code,
                'name' => $country->name($locale),
                'names' => $country->names ?? [],
                'active' => $country->active,
                'position' => $country->position,
                'regions' => (int) $country->getAttribute('regions_count'),
                'localities' => (int) $country->getAttribute('localities_count'),
                'leader' => $leaders->leader($country->code),
            ])->values()->all(),
            'available' => array_diff_key(IntlCountries::getNames($locale), array_flip($on)),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setCountry(array $arguments): array
    {
        $code = strtoupper(trim((string) ($arguments['code'] ?? '')));
        $editor = $this->container->make(Editor::class);
        $leader = array_key_exists('leader', $arguments) ? $arguments['leader'] : false;

        if ($leader !== false) {
            $this->assertLeader($code, $leader);
        }

        $work = function () use ($arguments, $code, $editor): array {
            if (($arguments['remove'] ?? false) === true) {
                return ['removal' => $editor->removeCountry($code)->value];
            }

            $names = array_key_exists('names', $arguments) ? (is_array($arguments['names']) ? $this->texts($arguments['names'], 'names') : []) : null;
            $position = isset($arguments['position']) ? $this->int($arguments['position'], 'position') : null;

            if (($arguments['active'] ?? null) === true) {
                $editor->enableCountry($code, $names, $position);
            } else {
                $country = GeoCountry::query()->find($code)
                    ?? throw new ToolFailure("There is no country \"{$code}\" on the site. Switch it on with active: true.");

                if (($arguments['active'] ?? null) === false) {
                    $editor->disableCountry($code);
                    $country->refresh();
                }

                if ($names !== null) {
                    $country->names = Names::clean($names) ?: null;
                }

                if ($position !== null) {
                    $country->position = $position;
                }

                $country->save();
            }

            $country = GeoCountry::query()->findOrFail($code);

            return ['country' => [
                'code' => $country->code,
                'name' => $country->name($this->locale()),
                'names' => $country->names ?? [],
                'active' => $country->active,
                'position' => $country->position,
            ]];
        };

        if ($this->dryRun($arguments)) {
            return ['dry_run' => true, ...$this->rehearse($work), ...($leader !== false ? ['leader' => $leader] : [])];
        }

        $done = $work();

        $leaders = $this->container->make(LeadingCarriers::class);

        // Checked above: only the stored leaders are chosen here; the config's are the config's.
        if ($leader !== false && $leaders instanceof StoredLeadingCarriers) {
            $leaders->choose($code, is_string($leader) ? $leader : null);
            $done['leader'] = $leader;
        }

        return $done;
    }

    private function assertLeader(string $code, mixed $leader): void
    {
        $carriers = $this->container->make(Carriers::class);
        $leaders = $this->container->make(LeadingCarriers::class);

        if ($leader !== null && (! is_string($leader) || ! $carriers->has($leader) || $leader === Carriers::STORE)) {
            throw new ToolFailure('`leader` is the key of a carrier of the site: '.implode(', ', array_diff($carriers->keys(), [Carriers::STORE])).'.');
        }

        if (! $leaders instanceof StoredLeadingCarriers || $leaders->configured($code)) {
            throw new ToolFailure("The leading carrier of {$code} is set in the site's config (webx-geography.countries.{$code}.source), not here.");
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function regions(array $arguments): array
    {
        $country = strtoupper(trim((string) ($arguments['country'] ?? '')));

        if (! GeoCountry::query()->whereKey($country)->exists()) {
            throw new ToolFailure("There is no country \"{$country}\" on the site. delivery_countries_list has them.");
        }

        return [
            'country' => $country,
            'regions' => GeoRegion::query()->where('country_code', $country)->withCount('localities')
                ->orderBy('position')->orderBy('name_key')->get()
                ->map(fn (GeoRegion $region): array => $this->region($region))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function region(GeoRegion $region): array
    {
        return [
            'id' => $region->id,
            'name' => $region->name($this->locale()),
            'names' => $region->names,
            'code' => $region->code,
            'country' => $region->country_code,
            'position' => $region->position,
            'localities' => (int) ($region->getAttribute('localities_count') ?? $region->localities()->count()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function saveRegion(array $arguments): array
    {
        $editor = $this->container->make(Editor::class);
        $region = null;

        if (isset($arguments['region'])) {
            $region = GeoRegion::query()->find($this->int($arguments['region'], 'region', 1))
                ?? throw new ToolFailure("Region {$arguments['region']} does not exist. delivery_regions_list has them.");
        }

        if (($arguments['delete'] ?? false) === true) {
            if ($region === null) {
                throw new ToolFailure('`delete` needs the `region` to delete.');
            }

            $count = $region->localities()->count();

            if ($this->dryRun($arguments)) {
                return ['dry_run' => true, 'would_delete' => $count === 0, 'localities' => $count];
            }

            $editor->deleteRegion($region);

            return ['deleted' => true, 'region' => $region->id];
        }

        $work = function () use ($arguments, $region, $editor): array {
            if ($region === null) {
                if (! isset($arguments['names'])) {
                    throw new ToolFailure('A new region needs `names` and `country`.');
                }

                $region = $editor->createRegion(
                    strtoupper(trim((string) ($arguments['country'] ?? ''))),
                    $this->texts($arguments['names'], 'names'),
                    is_string($arguments['code'] ?? null) ? $arguments['code'] : null,
                );
            } else {
                $changes = [];

                if (isset($arguments['names'])) {
                    $changes['names'] = [...$region->names, ...$this->texts($arguments['names'], 'names')];
                }

                if (array_key_exists('code', $arguments)) {
                    $changes['code'] = is_string($arguments['code']) ? $arguments['code'] : null;
                }

                $region = $editor->updateRegion($region, $changes);
            }

            if (isset($arguments['position'])) {
                $region->forceFill(['position' => $this->int($arguments['position'], 'position')])->save();
            }

            return $this->region($region->refresh());
        };

        if ($this->dryRun($arguments)) {
            $would = $this->rehearse($work);

            if ($region === null) {
                $would['id'] = null;
            }

            return ['dry_run' => true, 'would' => $region === null ? 'create' : 'update', 'region' => $would];
        }

        return [($region === null ? 'created' : 'updated') => true, 'region' => $work()];
    }

    // Popular towns

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setPopular(array $arguments): array
    {
        $given = array_filter(['localities', 'add', 'remove'], static fn (string $name): bool => array_key_exists($name, $arguments));

        if (count($given) !== 1) {
            throw new ToolFailure('Give one of `localities` (the whole list), `add` or `remove`.');
        }

        $popular = $this->popular();
        $before = $popular->ids();

        $work = function () use ($arguments, $popular): array {
            if (array_key_exists('localities', $arguments)) {
                if (! is_array($arguments['localities'])) {
                    throw new ToolFailure('`localities` is a list of locality ids or names.');
                }

                return $popular->set(array_map(fn (mixed $one): int => $this->locality($one)->id, $arguments['localities']));
            }

            if (array_key_exists('add', $arguments)) {
                $position = isset($arguments['position']) ? $this->int($arguments['position'], 'position') : null;

                return $popular->add($this->locality($arguments['add'], 'add')->id, $position);
            }

            return $popular->remove($this->locality($arguments['remove'], 'remove')->id);
        };

        if ($this->dryRun($arguments)) {
            $after = $this->rehearse(fn (): array => $this->popularRows($work()));

            return ['dry_run' => true, 'before' => $this->popularRows($before), 'after' => $after];
        }

        return ['popular' => $this->popularRows($work())];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function popularRows(array $ids): array
    {
        $locale = $this->locale();
        $localities = GeoLocality::query()->with('region')->whereKey($ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(
            static fn (int $id): ?array => $localities->has($id) ? LocalityRows::brief($localities->get($id), $locale) : null,
            $ids,
        )));
    }

    private function popular(): PopularLocalities
    {
        return $this->container->make(PopularLocalities::class);
    }

    // The binding queue

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function bindings(array $arguments): array
    {
        $locale = $this->locale();
        $queue = $this->container->make(BindingQueue::class);
        $carrier = (string) ($arguments['carrier'] ?? '');
        $reason = (string) ($arguments['reason'] ?? '');
        $title = $this->carrierTitle($locale);

        $page = $queue->query($carrier, $reason)->orderBy('carrier')->orderBy('id')
            ->paginate(25, ['*'], 'page', $this->int($arguments['page'] ?? 1, 'page', 1));

        $ids = $page->getCollection()
            ->flatMap(static fn (DeliveryCarrierLocality $binding): array => array_column($binding->candidates ?? [], 'locality_id'))
            ->unique()->values()->all();
        $candidates = GeoLocality::query()->with('region')->whereKey($ids)->get()->keyBy('id');

        return [
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'total' => $page->total(),
            'bindings' => $page->getCollection()->map(static fn (DeliveryCarrierLocality $binding): array => [
                'id' => $binding->id,
                'carrier' => $binding->carrier,
                'carrier_title' => $title($binding->carrier),
                'external_id' => $binding->external_id,
                'reason' => $binding->reason,
                'record' => $binding->record,
                'candidates' => array_values(array_filter(array_map(
                    static fn (array $candidate): ?array => $candidates->has($candidate['locality_id'])
                        ? [...LocalityRows::brief($candidates->get($candidate['locality_id']), $locale), 'distance_km' => $candidate['distance_km'] ?? null]
                        : null,
                    $binding->candidates ?? [],
                ))),
            ])->values()->all(),
            'by_carrier' => $queue->query()->selectRaw('carrier, count(*) as total')->groupBy('carrier')->orderBy('carrier')
                ->pluck('total', 'carrier')->map(static fn (mixed $total): int => (int) $total)->all(),
            'by_reason' => $queue->query($carrier)->selectRaw('reason, count(*) as total')->groupBy('reason')->orderBy('reason')
                ->pluck('total', 'reason')->map(static fn (mixed $total): int => (int) $total)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function resolve(array $arguments): array
    {
        $queue = $this->container->make(BindingQueue::class);
        $action = (string) ($arguments['action'] ?? '');

        if (! in_array($action, [BindingQueue::BIND, BindingQueue::CREATE, BindingQueue::IGNORE], true)) {
            throw new ToolFailure('`action` is bind, create or ignore.');
        }

        $locality = $action === BindingQueue::BIND ? $this->locality($arguments['locality'] ?? null) : null;
        $carrier = (string) ($arguments['carrier'] ?? '');
        $reason = (string) ($arguments['reason'] ?? '');
        $all = ($arguments['all'] ?? false) === true;
        $query = $queue->query($carrier, $reason);

        if (! $all) {
            $ids = array_values(array_filter(array_map('intval', (array) ($arguments['ids'] ?? []))));

            if ($ids === []) {
                throw new ToolFailure('Name the rows (`ids`), or send `all: true` with the filter.');
            }

            $query->whereKey($ids);
        }

        if ($this->dryRun($arguments)) {
            $count = (clone $query)->count();

            return ['dry_run' => true, 'action' => $action, 'would_touch' => min($count, BindingQueue::BATCH), 'matching' => $count, 'locality' => $locality?->id];
        }

        $result = $queue->apply($query->orderBy('id')->limit(BindingQueue::BATCH)->get(), $action, $locality);

        return [...$result, 'left' => $all ? $queue->query($carrier, $reason)->count() : 0];
    }

    /** @return Closure(string): string */
    private function carrierTitle(string $locale): Closure
    {
        $carriers = $this->container->make(Carriers::class);

        return static fn (string $key): string => $carriers->has($key) ? $carriers->get($key)->title($locale) : $key;
    }
}
