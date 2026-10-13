<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Container\Container as Containers;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Editing\EditedRecords;
use WebxUi\Admin\SiteBar\EntityLink;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockLabel;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\ContentEdit;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Exceptions\RegionRefused;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;
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
 * record behind the address and of the layout's regions as maps, one block's fields edited into
 * the draft of whatever holds it, and the block drawn again for the page to put in place of the
 * old one.
 *
 * The same door the agent's `blocks_edit_content` goes through (§18.1 of the blocks spec): the
 * operations of {@see ContentEdit}, the revision the editor holds, the checks of
 * {@see ContentValues} — and for a region the gate of its declaration — then `saveDraft()`. Only
 * `set` here — adding, moving and removing blocks are the constructor's, in the panel (§1 of the
 * bar's spec).
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
     * @param  list<mixed>  $ops
     *
     * @throws ValidationException under `ops` for an operation that cannot be applied, under
     *                             `blocks.<key>.<field>` for a value its field refuses, under
     *                             `blocks` for a region its declaration would not take
     */
    public function edit(Model $entity, array $ops, ?string $locale, ?int $authorId): void
    {
        $tree = $this->editing($entity);
        $primary = $this->locales->defaultCode();

        foreach ($ops as $index => $op) {
            $tree = $this->apply($tree, $op, $locale, $primary, $index);
        }

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
        // the record names, which for a region is its own (`region:<name>`).
        $stored = method_exists($entity, 'storeBlocks') ? $entity->storeBlocks($tree) : $tree;
        $draft = method_exists($entity, 'draftValues') ? $entity->draftValues() : [];

        if (! method_exists($entity, 'saveDraft')) {
            throw new NotFoundHttpException;
        }

        $entity->saveDraft(array_merge($draft, [$column => $stored]), $authorId, EntityVersion::SOURCE_PANEL);
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
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private function apply(array $tree, mixed $op, ?string $locale, string $primary, int $index): array
    {
        $field = 'ops.'.$index;

        if (! is_array($op) || ($op['op'] ?? null) !== 'set' || ! is_string($op['key'] ?? null) || ! is_array($op['values'] ?? null)) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
        }

        $node = ContentEdit::find($tree, $op['key']);

        if ($node === null) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.gone')]);
        }

        $type = (string) $node['type'];

        // A field the type does not have is refused at the door, as the agent's is.
        // So is a list of blocks inside it: what stands in a container is the constructor's to
        // change, and a whole list sent with a field would replace blocks the form never showed.
        $nested = ($this->types->find($type) ?? $this->types->draft($type))?->nestedFields() ?? [];

        if ($this->strays->unknown($type, $op['values'], is_array($node['values'] ?? null) ? $node['values'] : []) !== []
            || array_intersect(array_map('strval', array_keys($op['values'])), $nested) !== []) {
            throw ValidationException::withMessages([$field => (string) __('webx-blocks::site-bar.bad-op')]);
        }

        try {
            if (is_string($op['locale'] ?? null) && $op['locale'] !== '') {
                return ContentEdit::set($tree, $op['key'], $op['values'], $op['locale'], $this->localized(...), $primary);
            }

            // The page's language goes to the fields that hold a language map and were sent one
            // value: that value is the page's, and the other languages stay as they are. A map
            // is written whole, as the constructor writes it; a plain field takes its value.
            [$own, $whole] = [[], []];

            foreach ($op['values'] as $name => $value) {
                $one = $locale !== null && $this->localized($type, (string) $name) === true && ! $this->isMap($value);
                $one ? $own[$name] = $value : $whole[$name] = $value;
            }

            $tree = $whole === [] ? $tree : ContentEdit::set($tree, $op['key'], $whole, null, $this->localized(...), $primary);

            return $own === [] ? $tree : ContentEdit::set($tree, $op['key'], $own, $locale, $this->localized(...), $primary);
        } catch (BlocksException $failed) {
            throw ValidationException::withMessages([$field => $failed->getMessage()]);
        }
    }

    private function isMap(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }

    /**
     * Whether a field of a block type holds a language map; null when nobody can say. With it, a
     * language is written into its map and the other languages stay; without it, a word in one
     * language would take the place of all of them.
     */
    private function localized(string $type, string $field): ?bool
    {
        $block = $this->types->draft($type) ?? $this->types->find($type);

        if ($block === null || ! in_array($field, $block->fields(), true)) {
            return null;
        }

        return in_array($field, $block->localizedFields(), true);
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
