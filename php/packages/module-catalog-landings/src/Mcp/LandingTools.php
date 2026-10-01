<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\CatalogLandings\Catalog\BaseFacets;
use WebxUi\CatalogLandings\Catalog\LandingCounter;
use WebxUi\CatalogLandings\Catalog\LandingGenerator;
use WebxUi\CatalogLandings\Catalog\LandingSet;
use WebxUi\CatalogLandings\Catalog\LandingWords;
use WebxUi\CatalogLandings\Http\LandingForm;
use WebxUi\CatalogLandings\Http\LandingResource;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogLandings\Models\LandingRun;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The landings to an agent (§11 of the landings spec), served as the catalogue's own tools —
 * `catalog_landings_*` — so they carry its scopes and permissions (decision 13).
 *
 * A set is spoken the way an address speaks it: a facet by its code, a value by its slug, in the
 * language the call names (the site's default when it names none), any other language tried after
 * it — `{"brand": ["apple"], "price": {"max": 50000}}`. It is stored by ids, as the panel stores it,
 * and every answer gives both: the ids under `filters`, the codes and slugs under `set`.
 *
 * The doors are the panel's: {@see LandingForm} for a write, {@see LandingGenerator} for «Create
 * in bulk», whose `dry_run` is its preview. Any other `dry_run` does the write inside a transaction
 * and takes it back, so the answer — a refusal included — is what would really have happened.
 */
final class LandingTools
{
    public function __construct(
        private readonly LandingForm $form,
        private readonly LandingResource $resource,
        private readonly LandingCounter $counter,
        private readonly LandingWords $words,
        private readonly LandingGenerator $generator,
        private readonly BaseFacets $baseFacets,
        private readonly Facets $facets,
        private readonly Locales $locales,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $landing = ['type' => ['integer', 'string'], 'description' => 'The landing: its id, or its slug in any language.'];
        $category = ['type' => ['integer', 'null'], 'description' => 'The base: a category id (catalog_categories_tree); null or left out — the whole catalogue.'];
        $set = ['type' => 'object', 'description' => 'Facet code → a list of value slugs, or { "min": …, "max": … } for a range: '
            .'{ "brand": ["apple"], "price": { "max": 50000 } }. Codes and slugs as in an address (catalog_landings_facets lists them); '
            .'never the category — it is the base.'];
        $locale = ['type' => 'string', 'description' => 'The language of the codes, slugs and one-language texts; the site\'s default when left out.'];
        $words = ['type' => ['string', 'object'], 'description' => 'One language (the call\'s) as a string, or several as { "en": "…" }; a language left out keeps what it had.'];
        $fields = [
            'name' => $words,
            'slug' => ['type' => ['string', 'object'], 'description' => 'The address, flat from the site root, per language. Left out on create — made from the base and the set: {category}-{value}.'],
            'h1' => $words,
            'text_above' => ['type' => ['string', 'object'], 'description' => 'HTML above the list, per language.'],
            'text_below' => ['type' => ['string', 'object'], 'description' => 'HTML below the list, per language.'],
            'sort' => ['type' => ['string', 'null'], 'description' => 'The default order, a key of the catalogue\'s sorts; null — the category\'s.'],
            'on_category' => ['type' => 'boolean', 'description' => 'Show it among «Popular selections» on its category.'],
            'position' => ['type' => 'integer', 'description' => 'Its place among the selections and the neighbours.'],
            'is_published' => ['type' => 'boolean'],
            'recommended' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Product ids for the strip above the list, in order — the whole list.'],
            'seo' => ['type' => ['object', 'null'], 'description' => 'The SEO card: { "title": { "en": "…" }, "description": { … } }.'],
        ];

        return [
            Tool::read(
                'landings_list',
                'The landings: name, address, base, the set (codes and slugs, and the ids it is stored by), how many products '
                .'it shows, published, and the mark of attention — a value of its set was deleted, it duplicates another, '
                .'or its set went empty. Read it before making one: a set is a landing once per base.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Only the ones whose name or slug contains this.'],
                    'category' => ['type' => ['integer', 'string'], 'description' => 'A category id — the landings of it and every category below; "root" — the whole catalogue\'s.'],
                    'attention' => ['type' => 'boolean', 'description' => 'Only the ones that need attention (or none of them).'],
                    'published' => ['type' => 'boolean'],
                    'empty' => ['type' => 'boolean', 'description' => 'Only the ones with no products (or with some).'],
                    'trashed' => ['type' => 'boolean', 'description' => 'The ones in the bin instead.'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer', 'description' => 'Up to 200; 50 when left out.'],
                    'locale' => $locale,
                ]],
            ),

            Tool::read(
                'landings_get',
                'One landing with every language of its texts, its set, recommended products, sort and SEO card.',
                fn (array $arguments): array => ['landing' => $this->answer($this->landing($arguments['landing'] ?? null, true), $this->locale($arguments))],
                ['properties' => ['landing' => $landing, 'locale' => $locale], 'required' => ['landing']],
            ),

            Tool::read(
                'landings_facets',
                'What a base offers a set: its facets, each with its code, kind and — for a reference book — the values its '
                .'products have, with slugs and counts; for a range, its ends. The words of a set come from here.',
                fn (array $arguments): array => $this->offered($arguments),
                ['properties' => ['category' => $category, 'locale' => $locale]],
            ),

            Tool::read(
                'landings_count',
                'How many products a set shows on a base, nothing saved; the landing that already holds the set, if one does; '
                .'and the address it suggests, per language.',
                fn (array $arguments): array => $this->count($arguments),
                ['properties' => [
                    'category' => $category,
                    'set' => $set,
                    'except' => ['type' => 'integer', 'description' => 'A landing not to count as the holder — the one being edited.'],
                    'locale' => $locale,
                ], 'required' => ['set']],
            ),

            Tool::mutating(
                'landings_create',
                'Make a landing: a base and a set, with its own address, texts and SEO card. Name and slug are made from the '
                .'base and the set when left out. Refused with who holds it: a slug another page has, a set another landing '
                .'of the base has. Unpublished unless is_published.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->create($arguments)),
                ['properties' => ['category' => $category, 'set' => $set, ...$fields, 'locale' => $locale], 'required' => ['set']],
            ),

            Tool::mutating(
                'landings_update',
                'Change a landing: only the fields sent change. A slug changed in any language leaves the old address '
                .'redirecting to the new one. Saving clears the mark of attention.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->update($arguments)),
                ['properties' => ['landing' => $landing, 'category' => $category, 'set' => $set, ...$fields, 'locale' => $locale], 'required' => ['landing']],
            ),

            Tool::mutating(
                'landings_publish',
                'Publish a landing. Refused when its set is empty or another landing of the base holds it.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->publish($arguments, true)),
                ['properties' => ['landing' => $landing], 'required' => ['landing']],
            ),

            Tool::mutating(
                'landings_unpublish',
                'Take a landing off the site; its address answers 404 until it is published again.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->publish($arguments, false)),
                ['properties' => ['landing' => $landing], 'required' => ['landing']],
            ),

            Tool::mutating(
                'landings_delete',
                'Put a landing in the bin. Its set stays taken until it is gone for good: landings_restore brings it back.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->delete($arguments)),
                ['properties' => ['landing' => $landing], 'required' => ['landing']],
            ),

            Tool::mutating(
                'landings_restore',
                'Take a landing out of the bin.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->restore($arguments)),
                ['properties' => ['landing' => $landing], 'required' => ['landing']],
            ),

            Tool::mutating(
                'landings_generate',
                'Create in bulk: a landing for every base × value of one facet, named and addressed by templates. '
                .'Placeholders: {category} and {value} — in a slug the slugs of the category and the value, elsewhere their '
                .'names. Without `values` — every value with at least min_products products in the base. With dry_run — '
                .'the preview: address, H1, products and the conflict of each row (slug-taken, set-taken, empty, no-slug); '
                .'without it the free rows are made, at once or by the queue (landings_generation tells how far it got).',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->generate($arguments, $user)),
                ['properties' => [
                    'categories' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'The bases, category ids.'],
                    'subtree' => ['type' => 'boolean', 'description' => 'Every category below them too.'],
                    'facet' => ['type' => 'string', 'description' => 'The facet code: a reference book, not a range.'],
                    'values' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Value slugs; left out — all with products.'],
                    'min_products' => ['type' => 'integer', 'description' => 'For «all values»: the least products a value needs in the base; 1 when left out.'],
                    'slug' => ['type' => 'string', 'description' => 'Must hold {value}. Default {category}-{value}.'],
                    'name' => ['type' => 'string', 'description' => 'Default {category} {value}.'],
                    'h1' => ['type' => 'string'],
                    'title' => ['type' => 'string', 'description' => 'The SEO title.'],
                    'description' => ['type' => 'string', 'description' => 'The SEO description.'],
                    'publish' => ['type' => 'boolean', 'description' => 'Publish the landings made.'],
                    'locale' => $locale,
                ], 'required' => ['categories', 'facet']],
            ),

            Tool::read(
                'landings_generation',
                'How far a queued «Create in bulk» has got: status, total, done, skipped, failed and the errors.',
                fn (array $arguments): array => $this->generation($arguments),
                ['properties' => ['run' => ['type' => 'integer', 'description' => 'The id landings_generate answered.']], 'required' => ['run']],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $query = ($arguments['trashed'] ?? false) === true ? Landing::onlyTrashed() : Landing::query();
        $query->with('category')->orderBy('position')->orderBy('id');

        $term = trim((string) ($arguments['search'] ?? ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static fn (Builder $any) => $any
                ->whereTranslationLikeAny('name', $like)
                ->orWhere(static fn (Builder $slug) => $slug->whereTranslationLikeAny('slug', $like)));
        }

        $base = $arguments['category'] ?? null;

        if ($base === 'root') {
            $query->whereNull('category_id');
        } elseif ($base !== null) {
            $query->whereIn('category_id', $this->category($base)->subtree()->withTrashed()->pluck('id')->all());
        }

        if (is_bool($arguments['attention'] ?? null)) {
            $arguments['attention'] ? $query->whereNotNull('attention') : $query->whereNull('attention');
        }

        if (is_bool($arguments['published'] ?? null)) {
            $query->where('is_published', $arguments['published']);
        }

        if (is_bool($arguments['empty'] ?? null)) {
            $arguments['empty']
                ? $query->where('products_count', 0)
                : $query->where(static fn (Builder $counted) => $counted->whereNull('products_count')->orWhere('products_count', '>', 0));
        }

        $page = $query->paginate(min(200, max(1, (int) ($arguments['per_page'] ?? 50))), page: max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'count' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'landings' => array_map(fn (Landing $landing): array => [
                ...$this->resource->list($landing),
                'set' => $this->described($landing->set(), $locale),
            ], $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function offered(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $category = isset($arguments['category']) ? $this->category($arguments['category']) : null;
        $facets = [];

        foreach ($this->baseFacets->of($category, $locale) as ['facet' => $facet, 'min' => $min, 'max' => $max, 'counts' => $counts]) {
            if ($facet->kind() === FacetKind::Range) {
                $facets[] = ['key' => $facet->key(), 'code' => $facet->code($locale), 'label' => $facet->label(), 'kind' => $facet->kind()->value, 'min' => $min, 'max' => $max];

                continue;
            }

            $ids = array_map('strval', array_keys($counts));
            $slugs = $facet->slugs($ids, $locale);
            $labels = $facet->labels($ids, $locale);

            $facets[] = [
                'key' => $facet->key(),
                'code' => $facet->code($locale),
                'label' => $facet->label(),
                'kind' => $facet->kind()->value,
                'values' => array_map(static fn (string $id): array => [
                    'id' => $id,
                    'slug' => $slugs[$id] ?? null,
                    'label' => $labels[$id] ?? null,
                    'count' => (int) $counts[$id],
                ], $ids),
            ];
        }

        return ['category_id' => $category?->id, 'locale' => $locale, 'facets' => $facets];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function count(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $category = isset($arguments['category']) ? $this->category($arguments['category']) : null;
        $set = LandingSet::from($this->stored($arguments['set'] ?? null, $locale));

        if ($set->isEmpty()) {
            throw new ToolFailure((string) __('webx-catalog-landings::errors.set-empty'));
        }

        $holder = $this->form->holder($category?->id, $set, isset($arguments['except']) ? (int) $arguments['except'] : null);

        return [
            'count' => $this->counter->count($category?->id, $set->state($this->facets)),
            'filters' => $set->all(),
            'set' => $this->described($set, $locale),
            'taken' => $holder === null ? null : ['id' => (int) $holder->id, 'name' => $holder->label($locale)],
            'suggested' => $this->words->slugs($category, $set),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $category = isset($arguments['category']) ? $this->category($arguments['category']) : null;
        $filters = $this->stored($arguments['set'] ?? null, $locale);
        $set = LandingSet::from($filters);

        $input = [
            ...$this->fields($arguments, $locale, null),
            'category_id' => $category?->id,
            'filters' => $filters,
        ];
        $input['name'] ??= $this->words->names($category, $set);
        $input['slug'] ??= $this->words->slugs($category, $set);

        return ['landing' => $this->answer($this->form->save(new Landing, $input), $locale)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $landing = $this->landing($arguments['landing'] ?? null);
        $input = $this->fields($arguments, $locale, $landing);

        if (array_key_exists('category', $arguments)) {
            $input['category_id'] = $arguments['category'] === null ? null : $this->category($arguments['category'])->id;
        }

        if (array_key_exists('set', $arguments)) {
            $input['filters'] = $this->stored($arguments['set'], $locale);
        }

        if ($input === []) {
            throw new ToolFailure('Send what changes: set, category, name, slug, h1, the texts, sort, on_category, position, is_published, recommended or seo.');
        }

        return ['landing' => $this->answer($this->form->save($landing, $input), $locale)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function publish(array $arguments, bool $published): array
    {
        $landing = $this->landing($arguments['landing'] ?? null);

        if ($published) {
            $this->form->check($landing);
        }

        $landing->is_published = $published;
        $landing->save();

        return ['landing' => $this->answer($landing->refresh(), $this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $landing = $this->landing($arguments['landing'] ?? null);
        $landing->delete();

        return ['deleted' => (int) $landing->id, 'name' => $landing->label($this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restore(array $arguments): array
    {
        $landing = $this->landing($arguments['landing'] ?? null, true);

        if ($landing->deleted_at === null) {
            throw new ToolFailure("The landing {$landing->id} is not in the bin.");
        }

        $landing->restore();

        return ['landing' => $this->answer($landing->refresh(), $this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function generate(array $arguments, ?Authenticatable $user): array
    {
        $locale = $this->locale($arguments);
        $facet = $this->facet((string) ($arguments['facet'] ?? ''), $locale);

        if ($facet->kind() === FacetKind::Range) {
            throw new ToolFailure((string) __('webx-catalog-landings::errors.generate-facet', ['key' => (string) ($arguments['facet'] ?? '')]));
        }

        $values = isset($arguments['values']) && is_array($arguments['values'])
            ? $this->values($facet, array_map('strval', $arguments['values']), $locale)
            : null;

        $params = $this->generator->validate([
            ...array_intersect_key($arguments, array_flip(['categories', 'subtree', 'min_products', 'slug', 'name', 'h1', 'title', 'description', 'publish'])),
            'facet' => $facet->key(),
            'values' => $values,
        ]);

        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            $rows = $this->generator->plan($params);
            $slugs = $facet->slugs(array_values(array_unique(array_map(static fn (array $row): string => (string) $row['value'], $rows))), $locale);

            return [
                'dry_run' => true,
                'total' => count($rows),
                'free' => count(array_filter($rows, static fn (array $row): bool => $row['conflict'] === null)),
                'rows' => array_map(static fn (array $row): array => [...$row, 'value_slug' => $slugs[(string) $row['value']] ?? null], $rows),
            ];
        }

        return $this->generator->run($params, $user)->toResponse();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function generation(array $arguments): array
    {
        $run = LandingRun::query()->find((int) ($arguments['run'] ?? 0));

        if (! $run instanceof LandingRun) {
            throw new ToolFailure('There is no generation '.$this->printable($arguments['run'] ?? null).'.');
        }

        return $run->toResponse();
    }

    /**
     * A set as an agent writes it — codes and slugs — as the panel stores it: keys and ids.
     *
     * @return array<string, array<string, mixed>>
     */
    private function stored(mixed $set, string $locale): array
    {
        if (! is_array($set) || $set === [] || array_is_list($set)) {
            throw new ToolFailure('`set` must be an object: facet code → a list of value slugs, or { "min", "max" } for a range.');
        }

        $stored = [];

        foreach ($set as $code => $choice) {
            $facet = $this->facet((string) $code, $locale);

            if ($facet->kind() === FacetKind::Range) {
                if (! is_array($choice) || array_is_list($choice)) {
                    throw new ToolFailure('«'.$facet->label().'» is a range: { "min": …, "max": … }, either end may be left out.');
                }

                $stored[$facet->key()] = ['min' => $choice['min'] ?? null, 'max' => $choice['max'] ?? null];

                continue;
            }

            $slugs = is_array($choice) ? array_map('strval', array_values($choice)) : [(string) $choice];
            $stored[$facet->key()] = ['values' => $this->values($facet, $slugs, $locale)];
        }

        return $stored;
    }

    /**
     * Slugs of a facet's values as its ids: in the call's language first, then in any other.
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function values(Facet $facet, array $slugs, string $locale): array
    {
        $ids = [];
        $missing = $slugs;

        foreach ([$locale, ...array_diff($this->locales->codes(), [$locale])] as $language) {
            if ($missing === []) {
                break;
            }

            foreach ($facet->resolveSlugs($missing, $language) as $slug => $id) {
                $ids[(string) $slug] = (string) $id;
            }

            $missing = array_values(array_filter($missing, static fn (string $slug): bool => ! isset($ids[$slug])));
        }

        if ($missing !== []) {
            throw new ToolFailure('«'.$facet->label().'» has no value '.implode(', ', array_map($this->printable(...), $missing)).'. catalog_landings_facets lists the values a base has.');
        }

        return array_values(array_unique(array_map(static fn (string $slug): string => $ids[$slug], $slugs)));
    }

    /** A facet by its code in the call's language, in any other, or by its key. */
    private function facet(string $code, string $locale): Facet
    {
        $facet = $this->facets->byCode($code, $locale);

        foreach ($this->locales->codes() as $language) {
            $facet ??= $this->facets->byCode($code, $language);
        }

        $facet ??= $this->facets->find($code);

        if ($facet === null) {
            throw new ToolFailure('There is no facet '.$this->printable($code).'. catalog_landings_facets lists the ones a base offers.');
        }

        if ($facet->key() === CategoryFacet::KEY) {
            throw new ToolFailure((string) __('webx-catalog-landings::errors.category-facet'));
        }

        return $facet;
    }

    /**
     * A stored set as an agent reads it: each facet with its code and label, each value with its
     * id, slug and label.
     *
     * @return list<array<string, mixed>>
     */
    private function described(LandingSet $set, string $locale): array
    {
        $described = [];

        foreach ($set->all() as $key => $choice) {
            $facet = $this->facets->find($key);

            if ($facet === null) {
                $described[] = ['key' => $key, 'code' => null, 'gone' => true, ...$choice];

                continue;
            }

            $head = ['key' => $key, 'code' => $facet->code($locale), 'label' => $facet->label()];

            if ($facet->kind() === FacetKind::Range) {
                $described[] = [...$head, 'min' => $choice['min'] ?? null, 'max' => $choice['max'] ?? null];

                continue;
            }

            $ids = $choice['values'] ?? [];
            $slugs = $facet->slugs($ids, $locale);
            $labels = $facet->labels($ids, $locale);

            $described[] = [...$head, 'values' => array_map(static fn (string $id): array => [
                'id' => $id,
                'slug' => $slugs[$id] ?? null,
                'label' => $labels[$id] ?? null,
            ], $ids)];
        }

        return $described;
    }

    /**
     * The fields of the form an agent sent, the words merged over what the landing has.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fields(array $arguments, string $locale, ?Landing $landing): array
    {
        $input = [];

        foreach (['name', 'slug', 'h1', 'text_above', 'text_below'] as $field) {
            if (! array_key_exists($field, $arguments)) {
                continue;
            }

            $value = $arguments[$field];
            $had = $landing?->getTranslations($field) ?? [];
            $input[$field] = is_array($value) ? [...$had, ...$value] : [...$had, $locale => $value];
        }

        foreach (['sort', 'on_category', 'position', 'is_published', 'recommended', 'seo'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $input[$field] = $arguments[$field];
            }
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(Landing $landing, string $locale): array
    {
        return [...$this->resource->full($landing), 'set' => $this->described($landing->set(), $locale)];
    }

    private function landing(mixed $key, bool $trashed = false): Landing
    {
        $query = $trashed ? Landing::withTrashed() : Landing::query();
        $found = null;

        if (is_int($key) || (is_string($key) && ctype_digit($key))) {
            $found = $query->find((int) $key);
        } elseif (is_string($key) && trim($key) !== '') {
            $slug = trim($key);
            $found = $query->whereTranslationLikeAny('slug', str_replace(['%', '_'], ['\%', '\_'], $slug))->get()
                ->first(static fn (Landing $landing): bool => in_array($slug, $landing->getTranslations('slug'), true));
        }

        if (! $found instanceof Landing) {
            throw new ToolFailure('There is no landing '.$this->printable($key).'. catalog_landings_list has them all'.($trashed ? '.' : ', and one in the bin is restored first.'));
        }

        return $found;
    }

    private function category(mixed $key): Category
    {
        $category = is_int($key) || (is_string($key) && ctype_digit($key)) ? Category::query()->find((int) $key) : null;

        if (! $category instanceof Category) {
            throw new ToolFailure('There is no category '.$this->printable($key).'. catalog_categories_tree has them all.');
        }

        return $category;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function locale(array $arguments): string
    {
        $locale = $arguments['locale'] ?? null;

        if ($locale === null) {
            return $this->locales->defaultCode();
        }

        if (! is_string($locale) || ! $this->locales->has($locale)) {
            throw new ToolFailure('The site has no language '.$this->printable($locale).': '.implode(', ', $this->locales->codes()).'.');
        }

        return $locale;
    }

    /**
     * A write, or with `dry_run` the same write taken back.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function write(array $arguments, Closure $work): array
    {
        if (! (bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return $this->attempt(static fn (): array => DB::transaction($work));
        }

        return $this->attempt(static function () use ($work): array {
            DB::beginTransaction();

            try {
                return ['dry_run' => true, 'would_be' => $work()];
            } finally {
                DB::rollBack();
            }
        });
    }

    /**
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(Closure $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        }
    }

    private function printable(mixed $key): string
    {
        return is_scalar($key) ? '"'.$key.'"' : '(none given)';
    }
}
