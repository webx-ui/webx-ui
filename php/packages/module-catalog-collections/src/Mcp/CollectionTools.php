<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Mcp;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Sorts\Sort;
use WebxUi\Catalog\Sorts\Sorts;
use WebxUi\CatalogCollections\Catalog\CollectionPreset;
use WebxUi\CatalogCollections\Catalog\CollectionPresets;
use WebxUi\CatalogCollections\Catalog\CollectionPreview;
use WebxUi\CatalogCollections\Http\CollectionForm;
use WebxUi\CatalogCollections\Http\CollectionResource;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Panel\CollectionUsage;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * The collections to an agent (§7 of the collections spec), served as the catalogue's own tools,
 * `catalog_collections_*`, so they carry its scopes and permissions.
 *
 * The doors are the panel's: {@see CollectionForm} for a write, {@see CollectionPreview} for what a
 * collection would show, {@see CollectionUsage} for where it stands. A `dry_run` does the write
 * inside a transaction and takes it back, so the answer, a refusal included, is what would really
 * have happened: nothing here touches a file or the queue, and the cache moves only on a commit.
 *
 * What the panel cannot be asked to say, an agent is told: a condition sent to a hand-picked
 * collection and a list sent to a filter are refused, because neither would be read and the agent
 * would believe it had changed what the site shows.
 */
final class CollectionTools
{
    /** How many keys a refusal lists: enough to pick from, not a page of them. */
    private const LISTED = 20;

    /** The words of a collection, per language. */
    private const WORDS = ['title', 'link_text'];

    /** What `values` passes to the form as it is. */
    private const PLAIN = ['key', 'source', 'on_sale', 'sort', 'only_purchasable', 'limit', 'view', 'link', 'is_enabled'];

    /** What a preview has no use for: nothing here changes which products are shown. */
    private const NOT_PREVIEWED = ['key', 'title', 'link', 'link_text', 'is_enabled'];

    public function __construct(
        private readonly CollectionForm $form,
        private readonly CollectionResource $resource,
        private readonly CollectionPreview $preview,
        private readonly CollectionPresets $presets,
        private readonly CollectionUsage $usage,
        private readonly CollectionSet $set,
        private readonly Sorts $sorts,
        private readonly Locales $locales,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $collection = ['type' => ['string', 'integer'], 'description' => 'The collection: its key, or its id.'];
        $locale = ['type' => 'string', 'description' => 'The language of the codes, slugs and one-language words; the site\'s default when left out.'];
        $category = ['type' => ['integer', 'null'], 'description' => 'The base: a category id (catalog_categories_tree), its whole branch; null or left out, the whole catalogue.'];
        $words = ['type' => ['string', 'object'], 'description' => 'One language (the call\'s) as a string, or several as { "en": "…" }; a language left out keeps what it had.'];
        $values = ['type' => 'object', 'description' => 'The fields: title, key (on create only), source (manual, filter), category, set, on_sale, sort, '
            .'only_purchasable, limit, view (grid, carousel), link, link_text, is_enabled. set, category, on_sale and sort are read by a filter only. '
            .'set: facet code → a list of value slugs, or { "min": …, "max": … } for a range with at least one end, from '
            .'catalog_collections_facets; a facet named with nothing in it is refused. '
            .'title and link_text: a string in the call\'s language, or { "en": "…" }. '
            .'link, the "All products" link: { "target": "url", "url": "/sale" }, an entity { "target": "entity", "entity_type": "page", "entity_id": 3 }, '
            .'or a file of the media library { "target": "file", "path": "media/ab/cd/name.pdf", "download": true } — the key from media_get_file; '
            .'also new_tab and rel; null takes it away.', 'properties' => [
                'title' => $words,
                'link_text' => $words,
                'category' => $category,
            ]];
        $products = ['type' => 'array', 'items' => ['type' => 'integer']];

        return [
            Tool::read(
                'collections_list',
                'The collections: key, title, how they are filled, the preset the fields still match, enabled, the mark of attention, '
                .'and places: how many blocks and regions hold each (null without the blocks module; texts and tags are not counted).',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'search' => ['type' => 'string', 'description' => 'Only the ones whose title or key contains this.'],
                    'attention' => ['type' => 'boolean', 'description' => 'Only the ones that need attention (or none of them).'],
                    'enabled' => ['type' => 'boolean', 'description' => 'Only the ones switched on (or off).'],
                    'trashed' => ['type' => 'boolean', 'description' => 'The ones in the bin instead.'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer', 'description' => 'Up to 200; 50 when left out.'],
                ]],
            ),

            Tool::read(
                'collections_get',
                'One collection, in the bin too: every language of its words, the set by codes and ids, the hand-picked list.',
                fn (array $arguments): array => ['collection' => $this->answer($this->collection($arguments['collection'] ?? null, true), $this->locale($arguments))],
                ['properties' => ['collection' => $collection, 'locale' => $locale], 'required' => ['collection']],
            ),

            Tool::read(
                'collections_facets',
                'What a base offers: its facets, each with its code, kind and, for a reference book, the values its products have, '
                .'with slugs and counts; for a range, its ends. And the presets and the sorts. The words of a set come from here.',
                fn (array $arguments): array => $this->offered($arguments),
                ['properties' => ['category' => $category, 'locale' => $locale]],
            ),

            Tool::read(
                'collections_preview',
                'What a collection would show, nothing saved: the products in order, how many fit, and of a hand-picked list what '
                .'stays out and why (over-limit, gone, in-bin, unpublished, hidden, no-language, status). Name a collection, send '
                .'`values`, or both: `values` then go over it. `values.products` is a hand-picked list to try.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->previewed($arguments)),
                ['properties' => [
                    'collection' => $collection,
                    'values' => ['type' => 'object', 'description' => $values['description'].' And products: a list of product ids.'],
                    'locale' => $locale,
                ]],
            ),

            Tool::read(
                'collections_usage',
                'Where a collection stands as a block, in pages and regions, in what the site shows and in drafts. A shortcode in a '
                .'text and a tag in a template are not counted, so no places does not mean it is printed nowhere.',
                fn (array $arguments): array => $this->places($arguments),
                ['properties' => ['collection' => $collection], 'required' => ['collection']],
            ),

            Tool::mutating(
                'collections_create',
                'Make a collection. A key is made from the title when left out, and fixed from then on; a second collection with '
                .'the same key is refused, one in the bin included. Preset: new, on-sale, label (catalog_collections_facets lists '
                .'the site\'s); it fills ordinary fields and `values` go over it, label takes its values from values.set. Hand-picked '
                .'products go in with catalog_collections_products afterwards.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->create($arguments)),
                ['properties' => [
                    'preset' => ['type' => 'string', 'description' => 'What fills the collection; left out, `values` say it.'],
                    'values' => $values,
                    'locale' => $locale,
                ], 'required' => ['values']],
            ),

            Tool::mutating(
                'collections_update',
                'Change a collection: only the fields sent change. The key cannot change: templates and texts name the collection by '
                .'it. Saving clears the mark of attention.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->update($arguments)),
                ['properties' => ['collection' => $collection, 'values' => $values, 'locale' => $locale], 'required' => ['collection', 'values']],
            ),

            Tool::mutating(
                'collections_products',
                'The hand-picked list of a manual collection: `set` for the whole list, or `remove`, `add` and `move`, done in that '
                .'order. Added products go last. An unknown product and one in the bin are refused with their ids; one already on '
                .'the list keeps its place when it goes to the bin, and comes back with it. Refused on a filter: it reads no list.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->products($arguments)),
                ['properties' => [
                    'collection' => $collection,
                    'set' => [...$products, 'description' => 'The whole list, in order. Alone: not with add, remove or move.'],
                    'add' => [...$products, 'description' => 'Product ids to put at the end.'],
                    'remove' => [...$products, 'description' => 'Product ids to take off the list.'],
                    'move' => ['type' => 'array', 'description' => 'Each { "product": id, "position": n }, counted from 1, one after the other.', 'items' => [
                        'type' => 'object',
                        'properties' => ['product' => ['type' => 'integer'], 'position' => ['type' => 'integer']],
                        'required' => ['product', 'position'],
                    ]],
                    'locale' => $locale,
                ], 'required' => ['collection']],
            ),

            Tool::mutating(
                'collections_delete',
                'Put a collection in the bin, wherever it stands: every place prints nothing until it is restored. Its key stays taken '
                .'until catalog_collections_purge.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->delete($arguments)),
                ['properties' => ['collection' => $collection, 'locale' => $locale], 'required' => ['collection']],
            ),

            Tool::mutating(
                'collections_restore',
                'Take a collection out of the bin: every place that names it prints it again.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->restore($arguments)),
                ['properties' => ['collection' => $collection, 'locale' => $locale], 'required' => ['collection']],
            ),

            Tool::mutating(
                'collections_purge',
                'Delete a collection in the bin for good, with its hand-picked list. Cannot be undone. Only a collection in the bin: '
                .'catalog_collections_delete puts it there first. Not refused for being in use: every place that names the key '
                .'prints nothing, and its key is free, so a new collection with that key prints there. dry_run names the blocks '
                .'and regions that hold it; a shortcode in a text and a tag in a template are not counted.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->purge($arguments)),
                ['properties' => ['collection' => $collection, 'locale' => $locale], 'required' => ['collection']],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $query = ($arguments['trashed'] ?? false) === true ? ProductCollection::onlyTrashed() : ProductCollection::query();
        $query->withCount('products')->orderBy('id');

        $term = trim(is_string($arguments['search'] ?? null) ? $arguments['search'] : '');

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(static fn (Builder $any) => $any->whereTranslationLikeAny('title', $like)->orWhere('key', 'like', $like));
        }

        if (is_bool($arguments['attention'] ?? null)) {
            $arguments['attention'] ? $query->whereNotNull('attention') : $query->whereNull('attention');
        }

        if (is_bool($arguments['enabled'] ?? null)) {
            $query->where('is_enabled', $arguments['enabled']);
        }

        $page = $query->paginate(min(200, max(1, (int) ($arguments['per_page'] ?? 50))), page: max(1, (int) ($arguments['page'] ?? 1)));
        $places = $this->usage->counts();

        return [
            'count' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'collections' => array_map(fn (ProductCollection $collection): array => [
                ...$this->resource->list($collection),
                // Null without blocks: nothing is counted, which is not the same as none.
                'places' => $places === null ? null : ($places[$collection->key] ?? 0),
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

        return [
            'category_id' => $category?->id,
            'locale' => $locale,
            'facets' => $this->set->offered($category, $locale),
            'presets' => array_map(static fn (CollectionPreset $preset): array => [
                'key' => $preset->key,
                'sort' => $preset->sort,
                'on_sale' => $preset->onSale,
                'facet' => $preset->facet,
            ], $this->presets->all()),
            'sorts' => array_map(static fn (Sort $sort): array => ['key' => $sort->key(), 'label' => $sort->label()], $this->sorts->all()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function previewed(array $arguments): array
    {
        if (! isset($arguments['collection']) && ! isset($arguments['values'])) {
            throw new ToolFailure('Name a collection, send `values`, or both.');
        }

        $locale = $this->locale($arguments);
        $named = isset($arguments['collection']) ? $this->collection($arguments['collection'], true) : null;
        $values = $this->values($arguments);
        $picked = array_key_exists('products', $values) ? $this->ids($values['products'], 'products') : ($named === null ? [] : $this->held($named));
        $max = ProductCollection::maxLimit();

        if (count($picked) > $max) {
            throw new ToolFailure((string) __('webx-catalog-collections::errors.products-too-many', ['max' => $max]));
        }

        unset($values['products']);

        if ($named !== null && $values === []) {
            return $this->preview->of($named, $picked, $locale);
        }

        $input = array_diff_key($this->input($values, $locale, $named), array_flip(self::NOT_PREVIEWED));
        $source = $input['source'] ?? $named->source ?? ProductCollection::MANUAL;

        if (! in_array($source, ProductCollection::SOURCES, true)) {
            throw new ToolFailure((string) __('webx-catalog-collections::errors.source-unknown', ['source' => is_scalar($source) ? (string) $source : '']));
        }

        $this->refuseUnread($input, $named);

        $draft = $this->form->draft($named === null ? $input : [...$this->saved($named), ...$input]);
        // A preview of values no write would keep would promise the agent what it cannot have.
        ProductCollection::refuseValues($input);

        return $this->preview->of($draft, $picked, $locale);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function places(array $arguments): array
    {
        $collection = $this->collection($arguments['collection'] ?? null, true);

        return [
            'key' => $collection->key,
            'places' => $this->usage->available() ? $this->usage->of($collection->key) : null,
            'counted' => $this->usage->available()
                ? 'Blocks in pages and regions, in what the site shows and in drafts.'
                : 'Nothing: the site has no blocks module.',
            'not_counted' => 'A shortcode in a text and a tag in a template: no places does not mean it is printed nowhere.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $values = $this->values($arguments);
        $this->refuseList($values);

        $input = $this->input($values, $locale, null);

        if (isset($arguments['preset'])) {
            $input = [...$this->preset($arguments['preset'], $input), ...$input];
        }

        $this->refuseUnread($input, null);

        return ['collection' => $this->answer($this->form->save(new ProductCollection, $input), $locale)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments): array
    {
        $locale = $this->locale($arguments);
        $collection = $this->collection($arguments['collection'] ?? null);
        $values = $this->values($arguments);
        $this->refuseList($values);

        if ($values === []) {
            throw new ToolFailure('Send what changes in `values`: title, source, category, set, on_sale, sort, only_purchasable, limit, view, link, link_text or is_enabled.');
        }

        $input = $this->input($values, $locale, $collection);
        $this->refuseUnread($input, $collection);

        return ['collection' => $this->answer($this->form->save($collection, $input), $locale)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function products(array $arguments): array
    {
        $collection = $this->collection($arguments['collection'] ?? null);

        if ($collection->source !== ProductCollection::MANUAL) {
            throw new ToolFailure("«{$collection->key}» is filled by a filter and reads no hand-picked list, so the site would not change. "
                .'Change its conditions with catalog_collections_update, or make its source manual first.');
        }

        $held = $this->held($collection);
        $ids = $this->listed($arguments, $held);
        $this->refuseMissing(array_values(array_diff($ids, $held)));

        return ['collection' => $this->answer($this->form->save($collection, ['products' => $ids]), $this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $collection = $this->collection($arguments['collection'] ?? null);
        $collection->delete();

        return ['deleted' => (int) $collection->id, 'key' => $collection->key, 'title' => $collection->label($this->locale($arguments))];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function purge(array $arguments): array
    {
        $collection = $this->collection($arguments['collection'] ?? null, true);

        if ($collection->deleted_at === null) {
            throw new ToolFailure("The collection «{$collection->key}» is not in the bin: catalog_collections_delete puts it there first.");
        }

        // Read before it goes: the words are the collection's, and the places outlive it.
        $answer = [
            'purged' => (int) $collection->id,
            'key' => $collection->key,
            'title' => $collection->label($this->locale($arguments)),
            'places' => $this->usage->available() ? $this->usage->of($collection->key) : null,
        ];

        $collection->forceDelete();

        return $answer;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restore(array $arguments): array
    {
        $collection = $this->collection($arguments['collection'] ?? null, true);

        if ($collection->deleted_at === null) {
            throw new ToolFailure("The collection «{$collection->key}» is not in the bin.");
        }

        $collection->restore();

        return ['collection' => $this->answer($collection->refresh(), $this->locale($arguments))];
    }

    /**
     * The list the call asks for: the whole of it, or the one held with the changes made in the
     * order the tool promises.
     *
     * @param  array<string, mixed>  $arguments
     * @param  list<int>  $held
     * @return list<int>
     */
    private function listed(array $arguments, array $held): array
    {
        $changes = array_intersect_key($arguments, array_flip(['add', 'remove', 'move']));

        if (array_key_exists('set', $arguments)) {
            if ($changes !== []) {
                throw new ToolFailure('`set` is the whole list: send it alone, or send add, remove and move.');
            }

            return $this->ids($arguments['set'], 'set');
        }

        if ($changes === []) {
            throw new ToolFailure('Send the whole list as `set`, or the changes as add, remove and move.');
        }

        $remove = $this->ids($arguments['remove'] ?? [], 'remove');
        $absent = array_diff($remove, $held);

        if ($absent !== []) {
            throw new ToolFailure('Not on the list: #'.implode(', #', $absent).'.');
        }

        $list = array_values(array_diff($held, $remove));

        foreach ($this->ids($arguments['add'] ?? [], 'add') as $id) {
            if (! in_array($id, $list, true)) {
                $list[] = $id;
            }
        }

        return $this->moved($list, $arguments['move'] ?? []);
    }

    /**
     * @param  list<int>  $list
     * @return list<int>
     */
    private function moved(array $list, mixed $moves): array
    {
        if (! is_array($moves) || ! array_is_list($moves)) {
            throw new ToolFailure('`move` is a list of { "product": id, "position": n }.');
        }

        foreach ($moves as $move) {
            $id = is_array($move) ? ($move['product'] ?? null) : null;
            $position = is_array($move) ? ($move['position'] ?? null) : null;

            if (! is_int($id) || ! is_int($position)) {
                throw new ToolFailure('`move` is a list of { "product": id, "position": n }.');
            }

            $at = array_search($id, $list, true);

            if ($at === false) {
                throw new ToolFailure("Not on the list: #{$id}.");
            }

            if ($position < 1 || $position > count($list)) {
                throw new ToolFailure('A position is from 1 to '.count($list).", not {$position}.");
            }

            array_splice($list, $at, 1);
            array_splice($list, $position - 1, 0, [$id]);
        }

        return $list;
    }

    /**
     * What a preset writes. Its values come from the set the call sent: a preset of a facet with
     * none picked would be a collection of the whole catalogue.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function preset(mixed $key, array $input): array
    {
        $preset = is_string($key) ? $this->presets->find($key) : null;

        if (! $preset instanceof CollectionPreset) {
            $known = array_map(static fn (CollectionPreset $one): string => $one->key, $this->presets->all());

            throw new ToolFailure('There is no preset '.$this->printable($key).': '.implode(', ', $known).'.');
        }

        $picked = $preset->facet === null ? [] : ($input['filters'][$preset->facet]['values'] ?? []);

        if ($preset->facet !== null && $picked === []) {
            throw new ToolFailure("The preset «{$preset->key}» needs its values in values.set: { \"{$preset->facet}\": […] }. catalog_collections_facets lists them.");
        }

        return $this->presets->fields($preset, $picked);
    }

    /**
     * The form's fields from what an agent sent, the words merged over what the collection has.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function input(array $values, string $locale, ?ProductCollection $collection): array
    {
        $input = [];

        foreach ($values as $field => $value) {
            match (true) {
                in_array($field, self::PLAIN, true) => $input[$field] = $value,
                in_array($field, self::WORDS, true) => $input[$field] = $this->words($field, $value, $locale, $collection),
                $field === 'category' => $input['category_id'] = $value === null ? null : $this->category($value)->id,
                $field === 'set' => $input['filters'] = $this->set->stored($value, $locale),
                default => throw new ToolFailure("There is no field \"{$field}\": ".implode(', ', [...self::WORDS, ...self::PLAIN, 'category', 'set']).'.'),
            };
        }

        return $input;
    }

    /**
     * @return array<string, string>|null
     */
    private function words(string $field, mixed $value, string $locale, ?ProductCollection $collection): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = [$locale => $value];
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new ToolFailure("`{$field}` is a string in the call's language, or { \"en\": \"…\" }.");
        }

        foreach ($value as $language => $text) {
            $this->locale(['locale' => $language]);

            if ($text !== null && ! is_string($text)) {
                throw new ToolFailure("`{$field}` in \"{$language}\" is a string, or null to clear it.");
            }
        }

        return [...($collection?->getTranslations($field) ?? []), ...$value];
    }

    /**
     * Conditions on a hand-picked collection would be saved and never read.
     *
     * @param  array<string, mixed>  $input
     */
    private function refuseUnread(array $input, ?ProductCollection $collection): void
    {
        if (($input['source'] ?? $collection->source ?? ProductCollection::MANUAL) !== ProductCollection::MANUAL) {
            return;
        }

        $unread = array_keys(array_filter([
            'set' => $input['filters'] ?? null,
            'category' => $input['category_id'] ?? null,
            'on_sale' => $input['on_sale'] ?? null,
            'sort' => $input['sort'] ?? null,
        ]));

        if ($unread !== []) {
            throw new ToolFailure(implode(', ', $unread).': a hand-picked collection does not read them. Send source: filter, or a preset.');
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function refuseList(array $values): void
    {
        if (array_key_exists('products', $values)) {
            throw new ToolFailure('The hand-picked list is written by catalog_collections_products.');
        }
    }

    /**
     * What was not on the list has to be a product the site has: one in the bin would be chosen
     * and never shown, while a row already held simply waits for its product to come back (§2.3).
     *
     * @param  list<int>  $ids
     */
    private function refuseMissing(array $ids): void
    {
        $live = Product::query()->whereKey($ids)->pluck('id')->map(intval(...))->all();
        $missing = array_diff($ids, $live);

        if ($missing !== []) {
            throw new ToolFailure(implode(' ', array_map(
                static fn (int $id): string => (string) __('webx-catalog-collections::errors.unknown-product', ['id' => $id]),
                $missing,
            )).' A product in the bin is restored first.');
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function values(array $arguments): array
    {
        $values = $arguments['values'] ?? [];

        if (! is_array($values) || ($values !== [] && array_is_list($values))) {
            throw new ToolFailure('`values` is an object of the collection\'s fields.');
        }

        return $values;
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $ids, string $field): array
    {
        if (! is_array($ids) || ! array_is_list($ids)) {
            throw new ToolFailure("`{$field}` is a list of product ids.");
        }

        $listed = [];

        foreach ($ids as $id) {
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw new ToolFailure("`{$field}` is a list of product ids, and ".$this->printable($id).' is not one.');
            }

            $listed[] = (int) $id;
        }

        return array_values(array_unique($listed));
    }

    /**
     * The hand-picked list as it stands, products in the bin too.
     *
     * @return list<int>
     */
    private function held(ProductCollection $collection): array
    {
        return $collection->products()->withTrashed()->get()->map(static fn (Product $product): int => (int) $product->id)->values()->all();
    }

    /**
     * What decides the products of a saved collection, for `values` to go over.
     *
     * @return array<string, mixed>
     */
    private function saved(ProductCollection $collection): array
    {
        return [
            'source' => $collection->source,
            'category_id' => $collection->category_id,
            'filters' => $collection->set()->all(),
            'on_sale' => $collection->on_sale,
            'sort' => $collection->sort,
            'only_purchasable' => $collection->only_purchasable,
            'limit' => $collection->limit,
            'view' => $collection->view,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(ProductCollection $collection, string $locale): array
    {
        return [...$this->resource->full($collection), 'set' => $this->set->described($collection->set(), $locale)];
    }

    /**
     * By its key first: a key may be all digits, and it is what templates and texts call it.
     */
    private function collection(mixed $key, bool $trashed = false): ProductCollection
    {
        $query = static fn (): Builder => $trashed ? ProductCollection::withTrashed() : ProductCollection::query();
        $found = is_string($key) ? $query()->where('key', trim($key))->first() : null;

        if (! $found instanceof ProductCollection && (is_int($key) || (is_string($key) && ctype_digit($key)))) {
            $found = $query()->find((int) $key);
        }

        if ($found instanceof ProductCollection) {
            return $found;
        }

        $keys = $query()->orderBy('id')->limit(self::LISTED)->pluck('key')->all();
        $name = is_scalar($key) ? (string) $key : '';

        throw new ToolFailure(($keys === []
            ? (string) __('webx-catalog-collections::errors.key-unknown-none', ['key' => $name])
            : (string) __('webx-catalog-collections::errors.key-unknown', ['key' => $name, 'keys' => implode(', ', $keys)]))
            .($trashed ? '' : ' One in the bin is restored first.'));
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

            throw new ToolFailure('Not accepted: '.implode('; ', $lines));
        }
    }

    private function printable(mixed $key): string
    {
        return is_scalar($key) ? '"'.$key.'"' : '(none given)';
    }
}
