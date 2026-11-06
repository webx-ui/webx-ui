<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Catalog\Variables\StockStatuses;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Build\FeedCategories;
use WebxUi\CatalogFeeds\Build\FeedExclusions;
use WebxUi\CatalogFeeds\Build\FeedFailure;
use WebxUi\CatalogFeeds\Build\FeedSelection;
use WebxUi\CatalogFeeds\Build\FeedTrial;
use WebxUi\CatalogFeeds\Delivery\FeedKey;
use WebxUi\CatalogFeeds\Http\FeedBuildResource;
use WebxUi\CatalogFeeds\Http\FeedController;
use WebxUi\CatalogFeeds\Http\FeedForm;
use WebxUi\CatalogFeeds\Http\FeedMeta;
use WebxUi\CatalogFeeds\Http\FeedResource;
use WebxUi\CatalogFeeds\Http\FeedSaved;
use WebxUi\CatalogFeeds\Http\TaxonomyController;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogFeeds\Taxonomy\Taxonomies;
use WebxUi\CatalogFeeds\Taxonomy\TaxonomyImport;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Grants\Grants;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Scopes;
use WebxUi\Mcp\Tool;

/**
 * The feeds to an agent (§12 of the feeds spec), served as the catalogue's own tools —
 * `catalog_feeds_*` — so they carry its scopes and permissions.
 *
 * The doors are the panel's: {@see FeedForm} for a write, {@see FeedTrial} for a preview,
 * {@see FeedBuilder} for a build.
 *
 * A `dry_run` of what only touches the database is the real write, rolled back. A rollback brings
 * back neither a file nor a job in the queue, so `delete`, `rebuild`, `reissue_key` and
 * `taxonomy_import` answer from what they read and do nothing.
 *
 * The address holds the key, and a key is read access to the file for whoever has it. Besides
 * `catalog.manage` it asks a connection that may write: someone who connected an agent to look
 * did not hand it the key (§13).
 */
final class FeedTools
{
    private const READ_PERMISSIONS = ['catalog.view', 'catalog.manage'];

    private const WRITE_PERMISSION = 'catalog.manage';

    private const WRITE_SCOPE = 'catalog:write';

    public function __construct(
        private readonly FeedForm $form,
        private readonly FeedResource $resource,
        private readonly FeedBuildResource $builds,
        private readonly FeedMeta $meta,
        private readonly FeedTrial $trial,
        private readonly FeedBuilder $builder,
        private readonly FeedSelection $selection,
        private readonly FeedExclusions $exclusions,
        private readonly FeedCategories $categories,
        private readonly FeedKey $key,
        private readonly Taxonomies $taxonomies,
        private readonly TaxonomyImport $import,
        private readonly Locales $locales,
        private readonly AccessPolicy $access,
        private readonly Grants $grants,
        private readonly Container $container,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $feed = ['type' => 'integer', 'description' => 'The feed\'s id, from catalog_feeds_list.'];
        $values = ['type' => 'object', 'description' => 'The fields of a feed: name, format, locale, currency, head, item, foot, separator, filter, stock_map, '
            .'taxonomy, custom_labels, sources, options, schedule, schedule_at, rebuild_on_change, is_active. catalog://feed-rules says what each holds.'];
        $page = ['type' => 'integer'];
        $taxonomy = ['type' => 'string', 'enum' => Taxonomies::KEYS, 'description' => 'The marketplace\'s list.'];
        $locale = ['type' => 'string', 'description' => 'The language of the list, en or en-US; the site\'s default when left out.'];
        $products = ['type' => 'array', 'items' => ['type' => 'integer']];

        return [
            Tool::read(
                'feeds_list',
                'The feeds: name, format, language, schedule, whether it is on, how its last build went. The address only to who may write.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->list($arguments, $user),
                ['properties' => ['page' => $page, 'per_page' => ['type' => 'integer', 'description' => 'Up to 200; 50 when left out.']]],
                permission: self::READ_PERMISSIONS,
            ),

            Tool::read(
                'feeds_get',
                'One feed whole: its templates, filter, mappings of stock, labels and schedule. The address only to who may write.',
                fn (array $arguments, ?Authenticatable $user = null): array => ['feed' => $this->shown($this->feed($arguments), $user)],
                ['properties' => ['feed' => $feed], 'required' => ['feed']],
                permission: self::READ_PERMISSIONS,
            ),

            Tool::read(
                'feeds_presets',
                'What a feed starts from: each preset with everything it copies, the formats, the sources of labels, the site\'s '
                .'currency and the stock statuses. Read it before catalog_feeds_create.',
                fn (): array => $this->meta->all(),
                permission: self::READ_PERMISSIONS,
            ),

            Tool::mutating(
                'feeds_create',
                'Make a feed from a preset (catalog_feeds_presets), which `values` override, or from `values` alone. It is tried '
                .'on real products first, and a feed that does not make a valid file is refused with the line and the part. '
                .'`currency` may be left out: a feed is in the site\'s currency, and another one is refused.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->create($arguments)),
                ['properties' => ['preset' => ['type' => 'string', 'description' => 'A preset\'s key.'], 'values' => $values]],
                permission: self::WRITE_PERMISSION,
            ),

            Tool::mutating(
                'feeds_update',
                'Change a feed: only the fields sent change. What changes the file is tried on real products first.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->update($arguments)),
                ['properties' => ['feed' => $feed, 'values' => $values], 'required' => ['feed', 'values']],
                permission: self::WRITE_PERMISSION,
            ),

            Tool::mutating(
                'feeds_delete',
                'Delete a feed for good, with its files and journal. Its address stops working, and there is no bin.',
                fn (array $arguments): array => $this->delete($arguments),
                ['properties' => ['feed' => $feed], 'required' => ['feed']],
                permission: self::WRITE_PERMISSION,
            ),

            Tool::read(
                'feeds_preview',
                'Try a feed with nothing kept: the text of a sample, the first mistake (`ok: false`, with its line and part), '
                .'warnings, how many the filter passes and what the sample left out and why. Name a feed, send `values`, or both: '
                .'`values` then go over the feed. `values.preset` starts a new one from a preset.',
                fn (array $arguments): array => $this->preview($arguments),
                ['properties' => [
                    'feed' => $feed,
                    'values' => $values,
                    'product_ids' => [...$products, 'description' => 'Up to '.FeedController::NAMED.' products to try and say the fate of.'],
                ]],
                scope: self::WRITE_SCOPE,
                permission: self::WRITE_PERMISSION,
            ),

            Tool::mutating(
                'feeds_rebuild',
                'Queue a build of a feed; one under way is the answer while it lives. Follow it with catalog_feeds_build_get. '
                .'With dry_run: how many products the filter passes and how many are excluded by hand, counted by a query.',
                fn (array $arguments): array => $this->rebuild($arguments),
                ['properties' => ['feed' => $feed], 'required' => ['feed']],
                permission: self::WRITE_PERMISSION,
            ),

            Tool::mutating(
                'feeds_reissue_key',
                'Replace a key that leaked: the old address answers 404 at once, and the answer is the only place the new one '
                .'is shown. The marketplace has to be given it by hand. With dry_run: the address that would stop working.',
                fn (array $arguments): array => $this->reissue($arguments),
                ['properties' => ['feed' => $feed], 'required' => ['feed']],
                permission: self::WRITE_PERMISSION,
            ),

            Tool::read(
                'feeds_builds',
                'The journal of a feed, latest first: status, counters, reasons, warnings, and when its key was reissued.',
                fn (array $arguments): array => $this->journal($arguments),
                ['properties' => ['feed' => $feed, 'page' => $page, 'per_page' => ['type' => 'integer', 'description' => 'Up to 100; 20 when left out.']], 'required' => ['feed']],
                permission: self::READ_PERMISSIONS,
            ),

            Tool::read(
                'feeds_build_get',
                'One build: counters, reasons, warnings and the first 50 products it left out.',
                fn (array $arguments): array => $this->build($arguments),
                ['properties' => ['feed' => $feed, 'build' => ['type' => 'integer', 'description' => 'The build\'s id, from catalog_feeds_builds.']], 'required' => ['feed', 'build']],
                permission: self::READ_PERMISSIONS,
            ),

            Tool::read(
                'feeds_exclusions',
                'The products taken out of one feed by hand. They stay in the others.',
                fn (array $arguments): array => $this->excluded($arguments),
                ['properties' => [
                    'feed' => $feed,
                    'q' => ['type' => 'string', 'description' => 'Only the ones matching this.'],
                    'page' => $page,
                    'per_page' => ['type' => 'integer', 'description' => 'Up to 100; 25 when left out.'],
                ], 'required' => ['feed']],
                permission: self::READ_PERMISSIONS,
            ),

            Tool::mutating(
                'feeds_exclusions_set',
                'Take products out of a feed or put them back, up to '.FeedExclusions::AT_ONCE.' each way. The file changes with the next build.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->exclude($arguments)),
                ['properties' => [
                    'feed' => $feed,
                    'add' => [...$products, 'description' => 'Product ids to take out.'],
                    'remove' => [...$products, 'description' => 'Product ids to put back.'],
                ], 'required' => ['feed']],
                permission: self::WRITE_PERMISSION,
            ),

            Tool::read(
                'feeds_mappings',
                'What a feed maps: each stock status with the value it goes out as, each category with what it holds or takes '
                .'after, and what has no mapping. A category with none goes out without one; a status falls back to its default.',
                fn (array $arguments): array => $this->mappings($this->feed($arguments)),
                ['properties' => ['feed' => $feed], 'required' => ['feed']],
                permission: self::READ_PERMISSIONS,
            ),

            Tool::mutating(
                'feeds_mappings_set',
                'Change what a feed maps; only what is named changes. A category takes after its parent when it holds nothing.',
                fn (array $arguments): array => $this->write($arguments, fn (): array => $this->map($arguments)),
                ['properties' => [
                    'feed' => $feed,
                    'stock' => ['type' => 'object', 'description' => 'Status code → the marketplace\'s value: { "on-order": "backorder" }; null goes back to the default.'],
                    'categories' => ['type' => 'array', 'description' => 'Rows of { category_id, taxonomy_item_id } (catalog_feeds_taxonomy_search gives `id`), or '
                        .'{ category_id, value } for a value typed by hand. A row with neither takes after the parent again.', 'items' => ['type' => 'object']],
                ], 'required' => ['feed']],
                permission: self::WRITE_PERMISSION,
            ),

            Tool::read(
                'feeds_taxonomy_search',
                'Search a marketplace\'s list of categories by words of the path or by id. Also says how its last import went, '
                .'with `q` left out too: that is how a queued import is followed.',
                fn (array $arguments): array => $this->search($arguments),
                ['properties' => [
                    'key' => $taxonomy,
                    'locale' => $locale,
                    'q' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'description' => 'Up to 50; 20 when left out.'],
                ], 'required' => ['key']],
                permission: self::READ_PERMISSIONS,
            ),

            Tool::mutating(
                'feeds_taxonomy_import',
                'Queue the import of a marketplace\'s list, from its own file or from an https address. An agent has no file to upload. '
                .'Mappings hold: the rows are updated in place.',
                fn (array $arguments): array => $this->importList($arguments),
                ['properties' => [
                    'key' => $taxonomy,
                    'locale' => $locale,
                    'url' => ['type' => 'string', 'description' => 'An https address; the marketplace\'s own file of this language when left out.'],
                ], 'required' => ['key']],
                permission: self::WRITE_PERMISSION,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments, ?Authenticatable $user): array
    {
        $page = Feed::query()->with(['lastBuild', 'currentBuild:id,path'])->orderBy('id')
            ->paginate(min(200, max(1, (int) ($arguments['per_page'] ?? 50))), page: max(1, (int) ($arguments['page'] ?? 1)));

        $sees = $this->seesAddress($user);

        return [
            'count' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'feeds' => array_map(fn (Feed $feed): array => [
                ...$this->resource->list($feed, $user),
                'address' => $sees ? $feed->address() : null,
            ], $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments): array
    {
        $input = $this->values($arguments);

        if (isset($arguments['preset'])) {
            $input['preset'] = $arguments['preset'];
        }

        return $this->saved($this->form->save(new Feed, $input), ! (bool) ($arguments[Tool::DRY_RUN] ?? false));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments): array
    {
        $feed = $this->feed($arguments);
        $input = $this->values($arguments);

        if ($input === []) {
            throw new ToolFailure('Send what changes under `values`: '.implode(', ', self::fields()).'.');
        }

        return $this->saved($this->form->save($feed, $input));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function delete(array $arguments): array
    {
        $feed = $this->feed($arguments);

        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return ['dry_run' => true, 'would_delete' => [
                'id' => (int) $feed->id,
                'name' => $feed->name,
                'builds' => $feed->builds()->count(),
                'address' => $feed->address(),
            ]];
        }

        $feed->delete();

        return ['deleted' => (int) $feed->id, 'name' => $feed->name];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function preview(array $arguments): array
    {
        if (! isset($arguments['feed']) && ! isset($arguments['values'])) {
            throw new ToolFailure('Name a feed, send `values`, or both.');
        }

        $validated = Validator::make($arguments, [
            'product_ids' => ['sometimes', 'array', 'max:'.FeedController::NAMED],
            'product_ids.*' => ['integer'],
        ])->validate();

        $values = $this->values($arguments);
        $feed = isset($arguments['feed']) ? $this->feed($arguments) : new Feed;

        // A sample has no use for a name, and only a form has to ask for one.
        $this->form->fill($feed, $feed->exists ? $values : ['name' => 'Preview', ...$values]);

        return $this->trial->preview($feed, array_map(intval(...), $validated['product_ids'] ?? []))->toArray();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function rebuild(array $arguments): array
    {
        $feed = $this->feed($arguments);

        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return ['dry_run' => true, 'would_be' => $this->counted($feed)];
        }

        $build = $this->builder->start($feed, FeedBuild::MCP);

        if (! $build instanceof FeedBuild) {
            throw new ToolFailure((string) __('webx-catalog-feeds::errors.inactive'));
        }

        return ['build' => $this->builds->row($build->refresh(), $feed->refresh())];
    }

    /**
     * The third layer needs the products themselves, so it is left to the build (§5.3).
     *
     * @return array<string, mixed>
     */
    private function counted(Feed $feed): array
    {
        if (! $feed->is_active) {
            throw new ToolFailure((string) __('webx-catalog-feeds::errors.inactive'));
        }

        try {
            $selected = $this->selection->query($feed)->count();
            $excluded = $this->selection->excluded($feed);
        } catch (FeedFailure $failure) {
            throw new ToolFailure($failure->getMessage());
        }

        $going = FeedBuild::query()
            ->where('feed_id', $feed->id)
            ->whereIn('status', [FeedBuild::QUEUED, FeedBuild::RUNNING])
            ->orderByDesc('id')
            ->value('id');

        return [
            'selected' => $selected,
            'excluded' => $excluded,
            'already_going' => $going === null ? null : (int) $going,
            'note' => 'Products without what the file needs drop out during the build; catalog_feeds_preview shows it on a sample.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reissue(array $arguments): array
    {
        $feed = $this->feed($arguments);

        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return ['dry_run' => true, 'stops_working' => $feed->address()];
        }

        $stale = $feed->address();
        $fresh = $this->key->reissue($feed);

        return [
            'feed' => (int) $fresh->id,
            'address' => $fresh->address(),
            'stopped_working' => $stale,
            'key_issued_at' => $fresh->key_issued_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function journal(array $arguments): array
    {
        $feed = $this->feed($arguments);
        $page = $feed->builds()->orderByDesc('id')
            ->paginate(min(100, max(1, (int) ($arguments['per_page'] ?? 20))), page: max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'count' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'builds' => array_map(fn (FeedBuild $build): array => $this->builds->row($build, $feed), $page->items()),
            'reissues' => $this->builds->reissues($feed),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function build(array $arguments): array
    {
        $feed = $this->feed($arguments);
        $build = $feed->builds()->find((int) ($arguments['build'] ?? 0));

        if (! $build instanceof FeedBuild) {
            throw new ToolFailure('The feed '.$feed->id.' has no build '.$this->printable($arguments['build'] ?? null).'. catalog_feeds_builds has them all.');
        }

        return ['build' => [...$this->builds->row($build, $feed), 'rejects' => $this->builds->rejects($build, $feed)]];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function excluded(array $arguments): array
    {
        $feed = $this->feed($arguments);
        $page = $this->exclusions->page(
            $feed,
            trim((string) ($arguments['q'] ?? '')),
            min(100, max(1, (int) ($arguments['per_page'] ?? 25))),
            max(1, (int) ($arguments['page'] ?? 1)),
        );

        return [
            'count' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'products' => $this->exclusions->rows($feed, $page->items()),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function exclude(array $arguments): array
    {
        $feed = $this->feed($arguments);

        $validated = Validator::make($arguments, [
            'add' => ['sometimes', 'array', 'max:'.FeedExclusions::AT_ONCE],
            'add.*' => ['integer', 'distinct', 'exists:catalog_products,id'],
            'remove' => ['sometimes', 'array', 'max:'.FeedExclusions::AT_ONCE],
            'remove.*' => ['integer', 'distinct'],
        ])->validate();

        $add = array_map(intval(...), $validated['add'] ?? []);
        $remove = array_map(intval(...), $validated['remove'] ?? []);

        if ($add === [] && $remove === []) {
            throw new ToolFailure('Send product ids under `add`, `remove` or both.');
        }

        if (array_intersect($add, $remove) !== []) {
            throw new ToolFailure('A product cannot be both taken out and put back: '.implode(', ', array_intersect($add, $remove)).'.');
        }

        $held = $this->exclusions->among($feed, [...$add, ...$remove]);
        $this->exclusions->add($feed, $add);
        $this->exclusions->remove($feed, $remove);

        return [
            'added' => array_values(array_diff($add, $held)),
            'removed' => array_values(array_intersect($remove, $held)),
            'excluded' => $this->selection->excluded($feed),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mappings(Feed $feed): array
    {
        $tree = $this->categories->tree($feed);
        $stock = $this->stock($feed);

        return [
            'stock' => $stock,
            'categories' => $tree,
            'unmapped' => [
                'stock' => array_values(array_map(
                    static fn (array $status): string => $status['code'],
                    array_filter($stock, static fn (array $status): bool => $status['value'] === null),
                )),
                'categories' => array_map(static fn (array $row): int => $row['id'], FeedCategories::unmapped($tree)),
            ],
            'taxonomy' => $feed->taxonomy === null ? null : $this->taxonomies->describe($feed->taxonomy, $feed->locale),
        ];
    }

    /**
     * @return list<array{code: string, title: string, is_purchasable: bool, value: string|null}>
     */
    private function stock(Feed $feed): array
    {
        $map = $feed->stock_map;

        return array_map(static fn (array $status): array => [
            ...$status,
            'value' => is_string($map[$status['code']] ?? null) ? $map[$status['code']] : null,
        ], $this->statuses($feed->locale));
    }

    /**
     * @return list<array{code: string, title: string, is_purchasable: bool}>
     */
    private function statuses(string $locale): array
    {
        return $this->container->bound(StockStatuses::class) ? $this->container->make(StockStatuses::class)->all($locale) : [];
    }

    /**
     * Categories first: the trial of a new `stock_map` then looks at the feed as it will be.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function map(array $arguments): array
    {
        $feed = $this->feed($arguments);

        if (! isset($arguments['stock']) && ! isset($arguments['categories'])) {
            throw new ToolFailure('Send `stock`, `categories` or both.');
        }

        if (isset($arguments['categories'])) {
            $validated = Validator::make(['categories' => $arguments['categories']], FeedCategories::RULES)->validate();
            $this->categories->set($feed, $validated['categories']);
        }

        $warnings = [];

        if (isset($arguments['stock'])) {
            $warnings = $this->form->save($feed, ['stock_map' => $this->stockMap($feed, $arguments['stock'])])->warnings;
        }

        return [...$this->mappings($feed), 'warnings' => $warnings];
    }

    /**
     * Over what the feed holds, which is what «only what is named changes» means for a map. A code
     * the site lacks would be kept and do nothing, so it is refused here.
     *
     * @return array<string, mixed>
     */
    private function stockMap(Feed $feed, mixed $stock): array
    {
        if (! is_array($stock) || array_is_list($stock) && $stock !== []) {
            throw new ToolFailure('`stock` must be an object: status code → the marketplace\'s value.');
        }

        $codes = array_column($this->statuses($feed->locale), 'code');

        if ($codes === []) {
            throw new ToolFailure('The site has no stock statuses: every product goes out as options.default_availability of the feed.');
        }

        $unknown = array_diff(array_map(strval(...), array_keys($stock)), $codes);

        if ($unknown !== []) {
            throw new ToolFailure('The site has no status '.implode(', ', $unknown).': '.implode(', ', $codes).'.');
        }

        return array_filter(
            [...$feed->stock_map, ...$stock],
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function search(array $arguments): array
    {
        $key = $this->taxonomy($arguments);
        $validated = Validator::make($arguments, [
            'locale' => ['nullable', 'string', TaxonomyController::LOCALE],
            'q' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();

        $locale = (string) ($validated['locale'] ?? $this->locales->defaultCode());

        return [
            'items' => $this->taxonomies->search($key, $locale, (string) ($validated['q'] ?? ''), (int) ($validated['limit'] ?? TaxonomyController::LIMIT)),
            'taxonomy' => $this->taxonomies->describe($key, $locale),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function importList(array $arguments): array
    {
        $key = $this->taxonomy($arguments);
        $validated = Validator::make($arguments, [
            'locale' => ['nullable', 'string', TaxonomyController::LOCALE],
            'url' => TaxonomyController::ADDRESS,
        ])->validate();

        $locale = (string) ($validated['locale'] ?? $this->locales->defaultCode());
        $url = $validated['url'] ?? null;

        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            $taxonomy = $this->taxonomies->describe($key, $locale);

            return ['dry_run' => true, 'would_fetch' => $url ?? $taxonomy['address'], 'taxonomy' => $taxonomy];
        }

        $this->import->queue($key, $locale, $url);

        return ['taxonomy' => $this->taxonomies->describe($key, $locale)];
    }

    /**
     * What is written to the database alone: a dry run is the real write, taken back.
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function write(array $arguments, Closure $work): array
    {
        if ((bool) ($arguments[Tool::DRY_RUN] ?? false)) {
            return ['dry_run' => true, 'would_be' => Rehearsal::run($work)];
        }

        return DB::transaction($work);
    }

    /**
     * What a write answers. It went through every check of the door, so the address is shown; only a
     * feed made in a dry run has none, its key going back with it.
     *
     * @return array<string, mixed>
     */
    private function saved(FeedSaved $saved, bool $kept = true): array
    {
        return [
            'feed' => [...$this->resource->full($saved->feed, null), 'address' => $kept ? $saved->feed->address() : null],
            'warnings' => $saved->warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shown(Feed $feed, ?Authenticatable $user): array
    {
        return [...$this->resource->full($feed, $user), 'address' => $this->seesAddress($user) ? $feed->address() : null];
    }

    /** With nobody on the line there is nobody to refuse: the local stdio server, as everywhere. */
    private function seesAddress(?Authenticatable $user): bool
    {
        if ($user === null) {
            return true;
        }

        return $this->access->allows($user, self::WRITE_PERMISSION)
            && Scopes::allows($user, self::WRITE_SCOPE)
            && $this->grants->forUser($user)?->read_only !== true;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function values(array $arguments): array
    {
        $values = $arguments['values'] ?? [];

        if (! is_array($values)) {
            throw new ToolFailure('`values` must be an object of a feed\'s fields.');
        }

        $unknown = array_diff(array_map(strval(...), array_keys($values)), self::fields());

        // Dropped in silence, a field would read as done: `access_key`, or a word misspelt.
        if ($unknown !== []) {
            throw new ToolFailure('A feed has no field '.implode(', ', $unknown).': '.implode(', ', self::fields()).'.');
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function fields(): array
    {
        return [...FeedForm::FIELDS, 'currency'];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function feed(array $arguments): Feed
    {
        $key = $arguments['feed'] ?? null;
        $feed = is_int($key) || (is_string($key) && ctype_digit($key)) ? Feed::query()->find((int) $key) : null;

        if (! $feed instanceof Feed) {
            throw new ToolFailure('There is no feed '.$this->printable($key).'. catalog_feeds_list has them all.');
        }

        return $feed;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function taxonomy(array $arguments): string
    {
        $key = $arguments['key'] ?? null;

        if (! is_string($key) || ! in_array($key, Taxonomies::KEYS, true)) {
            throw new ToolFailure('There is no list '.$this->printable($key).': '.implode(', ', Taxonomies::KEYS).'.');
        }

        return $key;
    }

    private function printable(mixed $key): string
    {
        return is_scalar($key) ? '"'.$key.'"' : '(none given)';
    }
}
