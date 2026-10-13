<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Container\Container as Containers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UnexpectedValueException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockLabel;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\ContentEdit;
use WebxUi\Blocks\ContentOps;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Exceptions\RegionRefused;
use WebxUi\Blocks\Models\BlockBundle;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\RegionContext;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\StrayValues;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteTypes;

/**
 * The «Blocks» tab of the panel on the site (§8–§9 of WEBX_UI_SITE_BAR.md): the blocks of the
 * record behind the address and of the layout's regions as maps, their fields and their
 * structure edited into the draft of whatever holds them, and the blocks drawn again for the page
 * to put in place of the old ranges.
 *
 * The same door the agent's `blocks_edit_content` goes through (§18.1 of the blocks spec): the
 * operations of {@see ContentOps}, the revision the editor holds, the checks of
 * {@see ContentValues} — and for a region the gate of its declaration — then `saveDraft()`. Since
 * SB5 that is the whole dictionary — add, duplicate, move, hide, show, remove as well as set — and
 * the answer says what the page has to do to show the result ({@see self::steps()}).
 *
 * A region is addressed as the type `region` with its name for an id, the way the agent's tools
 * address it: it has no address of its own to be found by, it is on every page.
 */
final class SiteBarBlocks
{
    /** The type a region goes by in the tab's addresses — never a type of the address registry. */
    public const REGION = 'region';

    public function __construct(
        private readonly Container $container,
        private readonly SiteBar $bar,
        private readonly EditedRecords $records,
        private readonly BlockTypes $types,
        private readonly StrayValues $strays,
        private readonly Locales $locales,
        private readonly Regions $regions,
    ) {}

    /**
     * Whether the record's half of the tab is there: a record that holds blocks, and an
     * administrator who may edit it — its section's right — and read blocks. Writing goes into
     * the draft the record's own editor writes, so the section's right is the one that counts;
     * `blocks.view` is what the constructor in the panel asks of the same person.
     */
    public static function available(HasPermissions $user, ?Model $entity, ?EntityLink $link): bool
    {
        return $entity instanceof Model
            && $link instanceof EntityLink
            && method_exists($entity, 'blocksTree')
            && $user->hasPermission($link->permission)
            && ($user->hasPermission('blocks.view') || $user->hasPermission('blocks.manage'));
    }

    /**
     * Whether the regions' half is there: the right of the «Site regions» screen, on a site that
     * declares any. Asked of every page, record or not — the header is on all of them — and
     * answered from the config alone, so the decision about a visitor's page still costs no query.
     */
    public static function regionsAvailable(HasPermissions $user, ?Model $entity = null, ?EntityLink $link = null): bool
    {
        return $user->hasPermission('blocks.regions')
            && Containers::getInstance()->make(Regions::class)->declared() !== [];
    }

    /**
     * What the tab edits — 404 for one the bar cannot name, 403 for one this administrator may
     * not edit. A region nobody has saved is found all the same, empty: it prints its fallback.
     */
    public function find(string $type, string $id, mixed $user): Model
    {
        if ($type === self::REGION) {
            return $this->region($id, $user);
        }

        $found = $this->bar->record($type, $id);

        if ($found === null || ! method_exists($found[0], 'blocksTree')) {
            throw new NotFoundHttpException;
        }

        [$entity, $link] = $found;

        if (! $user instanceof HasPermissions || ! self::available($user, $entity, $link)) {
            throw new AccessDeniedHttpException;
        }

        return $entity;
    }

    /**
     * What the tab reads: the tree an edit works on — the draft when there is one, what the site
     * shows otherwise — its map, and the revision to send back.
     *
     * @return array<string, mixed>
     */
    public function read(Model $entity): array
    {
        $tree = $this->editing($entity);
        $labels = $this->container->make(BlockLabel::class);

        return [
            'revision' => $this->revision($entity, $tree),
            'editing' => $this->hasDraftTree($entity) ? 'draft' : 'live',
            'tree' => $tree,
            'outline' => ContentEdit::outline($tree, label: $labels->of(...)),
        ];
    }

    /**
     * What the tab says over a region's map: what it is called, whether the site shows it or the
     * markup from code, and the draft nobody has published — counted in blocks rather than in
     * fields: a region has one field, and «1 change» would say nothing.
     *
     * @return array<string, mixed>
     */
    public function regionState(Region $region, mixed $user): array
    {
        $name = $region->name;
        $published = $region->exists && $region->isPublished();
        $changes = $region->exists && $region->hasDraft() ? $this->changedBlocks($region->blocksTree(), $region->editingTree()) : 0;
        // Whose the draft is, the way the bar says it of a record: anybody else's hand in it since
        // the last publication makes it theirs too.
        $hands = $changes > 0 ? SiteBar::hands($region, $user) : ['by' => null, 'at' => null, 'mine' => true];

        return [
            'name' => $name,
            'title' => $this->regions->title($name),
            'published' => $published,
            'fallback' => $this->regions->fallbackOf($name),
            'draft' => ['differs' => $changes > 0, 'changes' => $changes] + $hands,
            // The whole draft on a page, for after a reload — the panel appends `&at=` with this page.
            'preview' => $changes > 0 ? $this->container->make(Preview::class)->regionUrl($name, $this->idOf($user)) : null,
        ];
    }

    /**
     * The tree an edit works on: the draft being prepared, or what the site shows when there is
     * no draft yet. Writing goes the same way round — `saveDraft` starts the draft from it.
     *
     * @return list<array<string, mixed>>
     */
    public function editing(Model $entity): array
    {
        $column = $this->column($entity);
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];

        if (is_array($draft[$column] ?? null)) {
            return array_values($draft[$column]);
        }

        $live = $entity->getAttribute($column);

        return is_array($live) ? array_values($live) : [];
    }

    /**
     * The revision the record's editor holds — a page's, which covers its other fields too —
     * or the hash of the tree for a record no editor registered and for a region, whose editor
     * holds exactly that. One revision a record, the same the agent's tools answer: whoever read
     * it can write with it anywhere.
     *
     * @param  list<array<string, mixed>>  $tree
     */
    public function revision(Model $entity, array $tree): string
    {
        if ($entity instanceof Region) {
            return Content::revision($tree);
        }

        return $this->records->revisionOf($entity) ?? Content::revision($tree);
    }

    /**
     * Apply the operations and keep the result as the draft, from the panel.
     *
     * The operations are the agent's ({@see ContentOps}) — `set`, `add`, `duplicate`, `move`,
     * `hide`, `show`, `remove` — so a structure the bar may build is one the agent may build, and
     * the other way round. What a block may stand next to, inside or how many times is checked on
     * the whole tree afterwards, by the same {@see ContentValues} every save goes through.
     *
     * @param  list<mixed>  $ops
     * @return string|null the key of the block the operations made — an added block, a copy — for the tab to open
     *
     * @throws ValidationException under `ops.N` for an operation that cannot be applied, under
     *                             `blocks.<key>.<field>` for a value its field refuses or a block
     *                             where it may not stand, under `blocks` for a region its
     *                             declaration would not take
     */
    public function edit(Model $entity, array $ops, ?string $locale, ?int $authorId): ?string
    {
        $tree = $this->editing($entity);
        $made = null;

        foreach ($ops as $index => $op) {
            $tree = $this->apply($tree, $op, $locale, $index, $made);
        }

        $this->refuseDepth($tree);

        // The gate a region's own editor and the agent go through: what its declaration and the
        // types' `allowed_in` let stand at its top.
        if ($entity instanceof Region) {
            try {
                $this->container->make(RegionWriter::class)->admissible($entity->name, $tree);
            } catch (RegionRefused $refused) {
                throw ValidationException::withMessages(['blocks' => $refused->errors !== [] ? $refused->errors : [$refused->getMessage()]]);
            }
        }

        $column = $this->column($entity);

        // Checked and cast by the field types, as every other save of content is — at the root
        // the record names, which for a region is its own (`region:<name>`). A container's
        // `allow` and `max` and a type's `max_per_entity` are checked here, on the whole tree.
        $stored = method_exists($entity, 'storeBlocks') ? $entity->storeBlocks($tree) : $tree;
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];

        if (! method_exists($entity, 'saveDraft')) {
            throw new NotFoundHttpException;
        }

        $entity->saveDraft(array_merge($draft, [$column => $stored]), $authorId, EntityVersion::SOURCE_PANEL);

        return $made;
    }

    /**
     * What the page has to do to show the tree as it is now, given what it was: the steps the
     * loader takes over the ranges between the markers (§9 of the bar's spec, SB5).
     *
     * - `place` — the block drawn anew: in place of its range when the page has one, otherwise
     *   after the first of `after` the page has, before the first of `before`, or — at the top
     *   level (`top`) — at the end of the tree's markers;
     * - `move` — the same block, unchanged, to the place `after`/`before` names;
     * - `remove` — its range taken out: removed, or hidden, which the site does not draw.
     *
     * Inside a container a change of what it holds draws the container again rather than one
     * block beside another: where a container prints its list is its template's business, and a
     * container that held nothing has no neighbour to stand by. The keys are only ever the
     * visible blocks' — a hidden one is not on the page.
     *
     * A list that holds a block without a key cannot be followed — its blocks go by their place in
     * it — and the answer is then one step, `reload`.
     *
     * Which of two neighbours moved is not in the trees — swapping them is either one moving —
     * so the keys an operation moved are named: those are the blocks that move on the page too.
     *
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @param  list<string>  $moved  The keys `move` operations named.
     * @return list<array{do: string, key: string, after?: list<string>, before?: list<string>, top?: bool}>
     */
    public static function steps(array $before, array $after, array $moved = []): array
    {
        try {
            return self::diff($before, $after, $moved);
        } catch (UnexpectedValueException) {
            return [['do' => 'reload', 'key' => '']];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @param  list<string>  $moved
     * @return list<array{do: string, key: string, after?: list<string>, before?: list<string>, top?: bool}>
     */
    private static function diff(array $before, array $after, array $moved): array
    {
        $old = self::visible($before);
        $new = self::visible($after);
        $oldKeys = array_keys($old);
        $newKeys = array_keys($new);
        $steps = [];

        foreach (array_diff($oldKeys, $newKeys) as $key) {
            $steps[] = ['do' => 'remove', 'key' => (string) $key];
        }

        $common = array_values(array_filter($newKeys, static fn (string|int $key): bool => isset($old[$key]) && ! in_array((string) $key, $moved, true)));
        $stay = self::longestInOrder($common, array_flip($oldKeys));

        foreach ($newKeys as $at => $key) {
            $key = (string) $key;
            $around = [
                'after' => array_map('strval', array_reverse(array_slice($newKeys, 0, $at))),
                'before' => array_map('strval', array_slice($newKeys, $at + 1)),
                'top' => true,
            ];

            if (! isset($old[$key])) {
                $steps[] = ['do' => 'place', 'key' => $key] + $around;

                continue;
            }

            $same = $old[$key] === $new[$key];

            if (! in_array($key, $stay, true)) {
                // Moved: the range goes with it, unless what is in it changed too.
                $steps[] = $same
                    ? ['do' => 'move', 'key' => $key] + $around
                    : ['do' => 'remove', 'key' => $key];

                if (! $same) {
                    $steps[] = ['do' => 'place', 'key' => $key] + $around;
                }

                continue;
            }

            if (! $same) {
                $steps = [...$steps, ...self::nodeSteps($old[$key], $new[$key], $newKeys, $at, true)];
            }
        }

        return $steps;
    }

    /**
     * What the page needs of one block that stays where it was: nothing, the block drawn anew,
     * or — when only blocks inside it changed and not which — those blocks, one by one.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  list<string|int>  $siblings  The visible keys of the list it is in, in order.
     * @param  bool  $top  Whether that list is the top level of the tree.
     * @return list<array{do: string, key: string, after?: list<string>, before?: list<string>, top?: bool}>
     */
    private static function nodeSteps(array $old, array $new, array $siblings, int $at, bool $top): array
    {
        $key = (string) $new['key'];
        $whole = [[
            'do' => 'place',
            'key' => $key,
            'after' => array_map('strval', array_reverse(array_slice($siblings, 0, $at))),
            'before' => array_map('strval', array_slice($siblings, $at + 1)),
            'top' => $top,
        ]];

        if ($old['type'] !== $new['type'] || ($old['hidden'] ?? false) !== ($new['hidden'] ?? false)) {
            return $whole;
        }

        $oldValues = is_array($old['values'] ?? null) ? $old['values'] : [];
        $newValues = is_array($new['values'] ?? null) ? $new['values'] : [];
        $steps = [];

        foreach (array_unique([...array_keys($oldValues), ...array_keys($newValues)]) as $field) {
            $was = $oldValues[$field] ?? null;
            $is = $newValues[$field] ?? null;

            if ($was === $is) {
                continue;
            }

            if (! Content::isNodeList($was) && ! Content::isNodeList($is)) {
                return $whole;
            }

            $inside = Content::isNodeList($was) ? array_values($was) : [];
            $now = Content::isNodeList($is) ? array_values($is) : [];
            $oldList = self::visible($inside);
            $newList = self::visible($now);

            // Which blocks the container shows, and in what order, changed: it is drawn again.
            if (array_keys($oldList) !== array_keys($newList)) {
                return $whole;
            }

            $keys = array_keys($newList);

            foreach ($keys as $index => $child) {
                if ($oldList[$child] !== $newList[$child]) {
                    $steps = [...$steps, ...self::nodeSteps($oldList[$child], $newList[$child], $keys, $index, false)];
                }
            }
        }

        return $steps;
    }

    /**
     * The blocks of one list a visitor's page draws, by key. A block without a key gets a
     * temporary one from its place in the list, which moves with every block put before it — so
     * a list holding one cannot be followed, and the page is drawn again whole.
     *
     * @param  list<mixed>  $list
     * @return array<string, array<string, mixed>>
     */
    private static function visible(array $list): array
    {
        $visible = [];

        foreach ($list as $node) {
            if (! Content::isNode($node) || Content::isHidden($node)) {
                continue;
            }

            if (! is_string($node['key'] ?? null) || $node['key'] === '') {
                throw new UnexpectedValueException('A block without a key.');
            }

            $visible[$node['key']] = $node;
        }

        return $visible;
    }

    /**
     * The longest run of keys that kept their order — the ones that stay put while the rest
     * move around them. Quadratic, which on the length of one page's list is nothing.
     *
     * @param  list<string|int>  $keys  In the new order.
     * @param  array<string|int, int>  $was  Key → its old position.
     * @return list<string>
     */
    private static function longestInOrder(array $keys, array $was): array
    {
        $count = count($keys);
        $length = array_fill(0, $count, 1);
        $previous = array_fill(0, $count, -1);
        $best = -1;

        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $i; $j++) {
                if ($was[$keys[$j]] < $was[$keys[$i]] && $length[$i] < $length[$j] + 1) {
                    $length[$i] = $length[$j] + 1;
                    $previous[$i] = $j;
                }
            }

            if ($best === -1 || $length[$i] > $length[$best]) {
                $best = $i;
            }
        }

        $run = [];

        for ($i = $best; $i !== -1; $i = $previous[$i]) {
            $run[] = (string) $keys[$i];
        }

        return array_reverse($run);
    }

    /**
     * The styles and the script of the types the blocks just drawn use and the page does not
     * have yet: a type new to the page brings none of its own, since the page's set was glued
     * when it was printed (§9 of the blocks spec). The page names the sets it has by their
     * hashes; what they hold is read back from `block_bundles`, where every printed set is.
     * Null when nothing is missing.
     *
     * @param  array<string, BlockType>  $used
     * @param  list<string>|null  $have  The hashes of the sets the page has; null when it did not say, and then every type counts as missing.
     * @return array{hash: string, css: string|null, js: string|null}|null
     */
    public function assets(array $used, ?array $have): ?array
    {
        $present = [];

        foreach ($have ?? [] as $hash) {
            $bundle = preg_match('/^[a-f0-9]{16}$/', $hash) === 1 ? BlockBundle::query()->find($hash) : null;

            if ($bundle instanceof BlockBundle) {
                $present += $bundle->versions();
            }
        }

        $missing = array_filter($used, static fn (BlockType $type): bool => ! isset($present[$type->slug]));
        $bundles = $this->container->make(Bundles::class);
        $bundle = $missing === [] ? null : $bundles->build($missing);

        if (! $bundle instanceof BlockBundle) {
            return null;
        }

        $url = $this->container->make(UrlGenerator::class);

        return [
            'hash' => $bundle->hash,
            'css' => $bundle->css !== '' ? $url->route('webx.blocks.css', ['hash' => $bundle->hash]) : null,
            'js' => $bundle->js !== null ? $url->route('webx.blocks.js', ['hash' => $bundle->hash]) : null,
        ];
    }

    /**
     * Blocks nest as deep as the site lets them and no deeper — what the agent's write refuses
     * too: the renderer leaves out whatever is below the limit, and an editor would see a block
     * they just added vanish.
     *
     * @param  list<array<string, mixed>>  $tree
     */
    private function refuseDepth(array $tree): void
    {
        $limit = (int) $this->container->make(Config::class)->get('webx-blocks.max_depth', 5);

        foreach (ContentEdit::outline($tree) as $line) {
            if ((int) $line['depth'] >= $limit) {
                throw ValidationException::withMessages(['blocks' => (string) __('webx-blocks::site-bar.too-deep', ['max' => $limit])]);
            }
        }
    }

    /**
     * One block of the draft, drawn the way the page draws it for the bar: published types, the
     * values of the draft, the markers around it. In the page's language rather than the panel's —
     * a block that reads records, the next events, finds them in the page's.
     *
     * A region's block is drawn as if on the page it stands on — `$url`: the page's record as
     * `$entity`, its path for the menu's active item and `$region->path()`. What the layout's tag
     * passed as attributes is not known out here, so `$region->data()` is empty until the page
     * loads again.
     */
    public function html(Model $entity, string $key, ?string $locale, ?string $url = null): string
    {
        $tree = $this->editing($entity);
        $node = ContentEdit::find($tree, $key);

        if ($node === null) {
            return '';
        }

        $depth = 0;

        foreach (ContentEdit::outline($tree) as $line) {
            if (($line['key'] ?? null) === $key) {
                $depth = (int) $line['depth'];

                break;
            }
        }

        $renderer = $this->container->make(Renderer::class);

        if ($entity instanceof Region) {
            return $this->onPage($url ?? '/', $locale, fn (?Model $page, string $path): string => $renderer->block(
                $node,
                $page,
                $depth,
                new RegionContext($entity->name, [], $path),
            ));
        }

        $draft = method_exists($entity, 'withDraft') ? $entity->withDraft() : $entity;

        return $this->inLanguage($this->locales->content($locale), static fn (): string => $renderer->block($node, $draft, $depth));
    }

    /**
     * How many blocks the draft changes, adds or takes away. A container counts for itself and
     * not for what it holds: the blocks inside are counted on their own.
     *
     * @param  list<array<string, mixed>>  $live
     * @param  list<array<string, mixed>>  $draft
     */
    public function changedBlocks(array $live, array $draft): int
    {
        $before = self::flat($live);
        $after = self::flat($draft);
        $count = 0;

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Each block of a tree by its key, as a string to compare, with the blocks it holds reduced
     * to their keys.
     *
     * @param  iterable<array-key, mixed>  $tree
     * @return array<string, string>
     */
    private static function flat(iterable $tree): array
    {
        $flat = [];
        $index = 0;

        Content::walk($tree, static function (array $node, int $depth) use (&$flat, &$index): void {
            $values = is_array($node['values'] ?? null) ? $node['values'] : [];

            foreach ($values as $field => $value) {
                if (Content::isNodeList($value)) {
                    $values[$field] = array_map(static fn (array $child): mixed => $child['key'] ?? null, $value);
                }
            }

            $key = is_string($node['key'] ?? null) ? $node['key'] : "~{$depth}-".$index++;
            $flat[$key] = (string) json_encode([$node['type'], $node['hidden'] ?? false, $values]);
        });

        return $flat;
    }

    /**
     * A region's block runs inside the request of the page it stands on, as the region preview
     * draws it: `request()` there is the page's — its path, its record — not the panel's call.
     *
     * @param  callable(?Model, string): string  $draw
     */
    private function onPage(string $url, ?string $locale, callable $draw): string
    {
        $path = '/'.ltrim((string) (parse_url($url, PHP_URL_PATH) ?: '/'), '/');
        $previous = $this->container->make('request');
        $page = null;

        if ($previous instanceof Request) {
            $server = $previous->server->all();
            $server['REQUEST_URI'] = $path;
            $server['QUERY_STRING'] = '';
            $page = $previous->duplicate([], [], null, null, null, $server);
        }

        [$entity, $language] = $this->resolve($path, $locale, $page);

        if ($page !== null) {
            $this->container->instance('request', $page);
        }

        try {
            return $this->inLanguage($language, static fn (): string => $draw($entity, $page === null ? '' : trim(rawurldecode($page->path()), '/')));
        } finally {
            if ($page !== null) {
                $this->container->instance('request', $previous);
            }
        }
    }

    /**
     * The record behind a page and the language it is in, the way the resolver answers the page
     * itself; the resolution is left on the stand-in request for whatever reads it there.
     *
     * @return array{0: Model|null, 1: string}
     */
    private function resolve(string $path, ?string $locale, ?Request $page): array
    {
        $language = $this->locales->content($locale);

        if (! class_exists(Resolver::class) || ! $this->container->bound(Resolver::class)) {
            return [null, $language];
        }

        $found = $this->container->make(Resolver::class)->lookup($path, $locale);
        $route = $found?->route;

        if ($route instanceof Route && $route->isAlias()) {
            $route = $route->target;
        }

        if ($found === null || ! $route instanceof Route) {
            return [null, $language];
        }

        $type = $this->container->make(RouteTypes::class)->find($route->entity_type);
        $entity = null;

        if ($type !== null) {
            /** @var Model $model */
            $model = $this->container->make($type->model);
            $entity = $model->newQuery()->find($route->entity_id);
            $entity = $entity instanceof Model ? $entity : null;
        }

        if ($page !== null && $type !== null && $entity !== null) {
            $page->attributes->set(Resolution::ATTRIBUTE, new Resolution($route, $found->tail, $type, $entity));
        }

        return [$entity, $route->locale];
    }

    /**
     * @param  callable(): string  $draw
     */
    private function inLanguage(string $locale, callable $draw): string
    {
        /** @var Application $app */
        $app = $this->container->make(Application::class);
        $was = $app->getLocale();
        $app->setLocale($locale);

        try {
            return $draw();
        } finally {
            $app->setLocale($was);
        }
    }

    /** A declared region, for an administrator who may edit the regions. */
    private function region(string $name, mixed $user): Region
    {
        if (! $this->regions->has($name)) {
            throw new NotFoundHttpException;
        }

        if (! $user instanceof HasPermissions || ! self::regionsAvailable($user)) {
            throw new AccessDeniedHttpException;
        }

        return $this->regions->findOrNew($name);
    }

    /**
     * One operation of the agent's dictionary, with what the bar adds to it: the page's language
     * for a `set`, and a new block only of a type the page can draw.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private function apply(array $tree, mixed $op, ?string $locale, int $index, ?string &$made): array
    {
        $field = 'ops.'.$index;
        $name = is_array($op) ? ($op['op'] ?? null) : null;

        if (! is_array($op) || ! in_array($name, ContentOps::NAMES, true)) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
        }

        $key = is_string($op['key'] ?? null) ? $op['key'] : null;

        // A block that is not in the tree any more — somebody took it out since the tab read it.
        if ($name !== 'add' && ($key === null || ContentEdit::find($tree, $key) === null)) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.gone')]);
        }

        $ops = $this->container->make(ContentOps::class);

        try {
            if ($name === 'set') {
                return $this->set($tree, $op, $locale, $field, $ops);
            }

            if ($name === 'add') {
                $this->refuseNew($op, $field);
            }

            $tree = $ops->apply($tree, $op, $one);
            $made = $one ?? $made;

            return $tree;
        } catch (BlocksException $failed) {
            throw ValidationException::withMessages([$field => $failed->getMessage()]);
        }
    }

    /**
     * A new block only of a type the site draws — published, offered to editors, not a component
     * that only templates call — and empty of blocks: what goes inside it is added after it.
     *
     * @param  array<string, mixed>  $op
     */
    private function refuseNew(array $op, string $field): void
    {
        $type = is_string($op['type'] ?? null) ? $this->types->find($op['type']) : null;

        if ($type === null || ! $type->enabled || $type->isComponent()) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-type')]);
        }

        foreach (is_array($op['values'] ?? null) ? $op['values'] : [] as $value) {
            if (Content::isNodeList($value)) {
                throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
            }
        }
    }

    /**
     * The fields of one block, the way the form sends them.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $op
     * @return list<array<string, mixed>>
     */
    private function set(array $tree, array $op, ?string $locale, string $field, ContentOps $ops): array
    {
        $node = ContentEdit::find($tree, (string) $op['key']) ?? [];
        $values = $op['values'] ?? null;

        if (! is_array($values)) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
        }

        $type = (string) ($node['type'] ?? '');

        // A field the type does not have is refused at the door, as the agent's is.
        // So is a list of blocks inside it: what stands in a container is changed by the
        // operations that add, move and take out blocks, and a whole list sent with a field
        // would replace blocks the form never showed.
        $nested = ($this->types->find($type) ?? $this->types->draft($type))?->nestedFields() ?? [];

        if ($this->strays->unknown($type, $values, is_array($node['values'] ?? null) ? $node['values'] : []) !== []
            || array_intersect(array_map('strval', array_keys($values)), $nested) !== []) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
        }

        if (is_string($op['locale'] ?? null) && $op['locale'] !== '') {
            return $ops->apply($tree, $op);
        }

        // The page's language goes to the fields that hold a language map and were sent one
        // value: that value is the page's, and the other languages stay as they are. A map
        // is written whole, as the constructor writes it; a plain field takes its value.
        [$own, $whole] = [[], []];

        foreach ($values as $name => $value) {
            $one = $locale !== null && $ops->localized($type, (string) $name) === true && ! $this->isMap($value);
            $one ? $own[$name] = $value : $whole[$name] = $value;
        }

        $base = ['op' => 'set', 'key' => $op['key']];
        $tree = $whole === [] ? $tree : $ops->apply($tree, $base + ['values' => $whole]);

        return $own === [] ? $tree : $ops->apply($tree, $base + ['values' => $own, 'locale' => $locale]);
    }

    private function isMap(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }

    private function idOf(mixed $user): ?int
    {
        $id = $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function hasDraftTree(Model $entity): bool
    {
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];

        return is_array($draft[$this->column($entity)] ?? null);
    }

    private function column(Model $entity): string
    {
        return method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
    }
}
