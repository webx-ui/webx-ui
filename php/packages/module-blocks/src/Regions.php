<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Blocks\Events\RegionRendered;
use WebxUi\Blocks\Events\RegionRendering;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Blocks\Rendering\RegionContext;
use WebxUi\Blocks\Rendering\RegionRender;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Cache\CacheGroups;
use WebxUi\Routing\Resolution;

/**
 * The regions of the layout: which are declared, what each holds, and the one place a region is
 * printed from (§5 of the regions spec).
 *
 *     <x-webx-blocks::region name="header" fallback="components.header" />
 *
 * What is cached is the published tree, by name and not by language: one tree serves every
 * language, the translated values inside it are picked at render time. The HTML is never cached —
 * a header is a function of the request (the active menu item, the CSRF token of a form, who is
 * signed in), and a cache keyed by everything it depends on is a cache keyed by every page.
 *
 * A region that cannot be printed as blocks prints the markup from code instead: not declared,
 * never saved, not published, nothing visible in it — and, on the live site, any block in it
 * that threw. A hole where the menu was costs a visitor the whole site; the header from code
 * costs nothing but the change the editor has not yet got right.
 */
final class Regions
{
    /** Where the region preview route leaves its grant on the request: the region, not a page. */
    public const PREVIEW = 'webx.preview.region';

    /** The pattern a region's name follows — it is also a key in `allowed_in`, as `region:<name>`. */
    public const NAME = '/^[a-z][a-z0-9-]*$/';

    /** The prefix of a region in a block type's `allowed_in`. */
    public const ALLOWED_IN = 'region:';

    /** The cache group the published trees live in (WEBX_UI_CACHE.md). */
    public const GROUP = 'regions';

    /** A colon cannot be part of a name, so no region can collide with the hints. */
    private const FALLBACKS = 'webx.blocks.regions:fallbacks';

    /** @var array<string, string> what this process has already written to the hints */
    private array $remembered = [];

    /**
     * The fallbacks the store knows, read once: the header and the footer each asked for them
     * on every page, and the answer is a hint that may lag a worker's lifetime without harm.
     *
     * @var array<string, string>|null
     */
    private ?array $known = null;

    /**
     * The regions being drawn right now, outermost first. A shared block may stand inside
     * another, and one that ends up inside itself — published before publication checked for it —
     * would draw until the stack overflowed.
     *
     * @var list<string>
     */
    private array $drawing = [];

    public function __construct(
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly CacheGroups $groups,
        private readonly Renderer $renderer,
        private readonly Bundles $bundles,
        private readonly Views $views,
        private readonly Container $container,
    ) {}

    /**
     * The regions the site declared, in the order it declared them. A name that is not a name
     * is skipped rather than fatal: the tag will say so in the log.
     *
     * @return array<string, array{title: string, description: string|null, allow: list<string>|null, max: int|null, fragment: bool}>
     */
    public function declared(): array
    {
        $configured = $this->config->get('webx-blocks.regions', []);
        $regions = [];

        foreach (is_array($configured) ? $configured : [] as $name => $region) {
            if (! is_string($name) || preg_match(self::NAME, $name) !== 1) {
                continue;
            }

            $region = is_array($region) ? $region : [];
            $allow = $region['allow'] ?? null;
            $max = $region['max'] ?? null;

            $regions[$name] = [
                'title' => is_string($region['title'] ?? null) && $region['title'] !== '' ? $region['title'] : Str::headline($name),
                'description' => is_string($region['description'] ?? null) && $region['description'] !== '' ? $region['description'] : null,
                'allow' => is_array($allow) ? array_values(array_map('strval', array_filter($allow, 'is_string'))) : null,
                'max' => is_int($max) && $max > 0 ? $max : null,
                // A shared block: printed by whoever places it, not by the layout (§2 of the shared blocks spec).
                'fragment' => ($region['fragment'] ?? false) === true,
            ];
        }

        return $regions;
    }

    public function has(string $name): bool
    {
        return isset($this->declared()[$name]);
    }

    /**
     * The shared blocks: the regions a page places as a block or a template prints with a tag,
     * rather than the layout on every page.
     *
     * @return list<string>
     */
    public function fragments(): array
    {
        return array_keys(array_filter($this->declared(), static fn (array $region): bool => $region['fragment']));
    }

    public function isFragment(string $name): bool
    {
        return ($this->declared()[$name]['fragment'] ?? false) === true;
    }

    /** The region's name as a person reads it, in the language of the request. */
    public function title(string $name): string
    {
        return self::words($this->declared()[$name]['title'] ?? Str::headline($name)) ?? $name;
    }

    public function description(string $name): ?string
    {
        return self::words($this->declared()[$name]['description'] ?? null);
    }

    /** The row of a region, if it was ever saved. */
    public function find(string $name): ?Region
    {
        return Region::query()->where('name', $name)->first();
    }

    /**
     * The row, or a new one that is not saved yet — what reading a declared region that nobody
     * has saved returns, and what the first write saves.
     */
    public function findOrNew(string $name): Region
    {
        return $this->find($name) ?? new Region(['name' => $name]);
    }

    /**
     * The published tree, as the site prints it. Empty when there is no row or it is not
     * published — both mean "the fallback".
     *
     * @return list<array<string, mixed>>
     */
    public function tree(string $name): array
    {
        if (! (bool) $this->config->get('webx-blocks.cache.enabled', true)) {
            return $this->published($name);
        }

        /** @var list<array<string, mixed>> $tree */
        $tree = $this->groups->remember(
            'blocks.region.'.$name,
            [self::GROUP],
            fn (): array => $this->published($name),
            max(1, (int) $this->config->get('webx-blocks.cache.ttl', 86400)),
            localized: false,
        );

        return $tree;
    }

    /**
     * Throw the published trees away: the `regions` group moves, every region's with it — a
     * version is one number, and the regions are few. A region saved through its model moves
     * the group on its own; this is for a write that went round it.
     */
    public function forget(string $name): void
    {
        $this->groups->bump(self::GROUP);
    }

    /**
     * The view the layout named as the region's fallback, the last time it printed the tag.
     * Known only from a call — the server has no other way to learn it — so it is a hint for
     * the panel and an agent ("now on the site: components.header"), never a contract.
     */
    public function fallbackOf(string $name): ?string
    {
        $known = $this->cache->get(self::FALLBACKS);

        return is_array($known) && is_string($known[$name] ?? null) ? $known[$name] : null;
    }

    /**
     * What the tag prints.
     *
     * @param  array<string, mixed>  $data  The tag's attributes, less its own two.
     */
    public function render(string $name, array $data = [], ?string $fallback = null): HtmlString
    {
        $events = $this->container->bound('events') ? $this->container->make('events') : null;

        // Told only to a listener — the debug bar's timeline — and otherwise one check of an array.
        if (! $events instanceof Dispatcher || ! $events->hasListeners(RegionRendering::class)) {
            return $this->printTag($name, $data, $fallback);
        }

        $events->dispatch(new RegionRendering($name));
        $source = RegionRendered::EMPTY;

        try {
            return $this->printTag($name, $data, $fallback, $source);
        } finally {
            $events->dispatch(new RegionRendered($name, $source));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string  $source  what was printed: the tree, the fallback or nothing ({@see RegionRendered})
     */
    private function printTag(string $name, array $data = [], ?string $fallback = null, string &$source = RegionRendered::EMPTY): HtmlString
    {
        if ($fallback !== null) {
            $this->remember($name, $fallback);
        }

        if (! $this->has($name)) {
            // A typo in the name must not quietly become a second header nobody can edit.
            $this->log()->warning("The layout prints the region [{$name}], which webx-blocks.regions does not declare; its fallback is printed instead.", ['region' => $name]);

            $source = $fallback === null ? RegionRendered::EMPTY : RegionRendered::FALLBACK;

            return $this->fallback($fallback, $data);
        }

        if (in_array($name, $this->drawing, true)) {
            $this->log()->warning('The region ['.$name.'] stands inside itself ('.implode(' → ', [...$this->drawing, $name]).'); it is left empty there.', ['region' => $name]);

            return new HtmlString('');
        }

        $preview = $this->previewing($name);

        try {
            $tree = $preview ? $this->findOrNew($name)->editingTree() : $this->tree($name);
        } catch (Throwable $failure) {
            // No table yet, a broken row: the region is down, the site is not.
            $this->container->make(ExceptionHandler::class)->report($failure);

            $source = $fallback === null ? RegionRendered::EMPTY : RegionRendered::FALLBACK;

            return $this->mark($name, $this->fallback($fallback, $data), $preview);
        }

        if (self::visible($tree) === 0) {
            $source = $fallback === null ? RegionRendered::EMPTY : RegionRendered::FALLBACK;

            return $this->mark($name, $this->fallback($fallback, $data), $preview);
        }

        $render = $this->draw($name, $tree, $data, $preview);

        if ($render->failed() && ! $preview) {
            $this->log()->warning("A block of the region [{$name}] failed; the whole region prints its fallback.", [
                'region' => $name,
                'blocks' => array_map(static fn (array $failure): string => $failure['type'].' ('.$failure['key'].')', $render->failures),
            ]);

            $source = $fallback === null ? RegionRendered::EMPTY : RegionRendered::FALLBACK;

            return $this->fallback($fallback, $data);
        }

        $body = $render->html;
        $source = RegionRendered::TREE;

        if ($preview && $render->failed()) {
            $body = $this->banner((string) __('webx-blocks::regions.preview-failed')).$body;
        }

        return new HtmlString(
            $this->bundles->tagsFor($render->types, 'styles')
            .$this->mark($name, new HtmlString($body), $preview)
            .$this->bundles->tagsFor($render->types, 'scripts'),
        );
    }

    /**
     * A tree rendered as the region would print it — for the tag, and for publishing, which has
     * to know whether anything in it throws before the site finds out.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     * @param  array<string, mixed>  $data
     */
    public function draw(string $name, ?iterable $tree, array $data = [], bool $preview = false, bool $report = true): RegionRender
    {
        $this->drawing[] = $name;

        try {
            return $this->renderer->region($tree, $this->entity(), new RegionContext($name, $data, $this->path()), $preview, $report);
        } finally {
            array_pop($this->drawing);
        }
    }

    /**
     * Whether the current request previews this region's draft: the region preview route left a
     * grant for exactly this name. A token for the header does not open the footer.
     */
    public function previewing(string $name): bool
    {
        $request = $this->request();
        $grant = $request?->attributes->get(self::PREVIEW);

        return $grant instanceof PreviewGrant && $grant->id === $name;
    }

    /**
     * Why a tree cannot stand in a region: types the region does not take, types that do not go
     * there, more blocks than it holds — only the top level, what is nested inside a block is
     * that block's business, as on a page — and a shared block that would end up inside itself,
     * at any depth. Empty when it can.
     *
     * @param  iterable<array-key, mixed>  $tree
     * @return list<string>
     */
    public function refusals(string $name, iterable $tree): array
    {
        $region = $this->declared()[$name] ?? null;

        if ($region === null) {
            return [];
        }

        $slugs = [];
        $count = 0;

        foreach ($tree as $node) {
            if (Content::isNode($node)) {
                $slugs[] = (string) $node['type'];
                $count++;
            }
        }

        $refusals = [];

        if ($region['max'] !== null && $count > $region['max']) {
            $refusals[] = (string) __('webx-blocks::regions.too-many', ['max' => $region['max']]);
        }

        /** @var array<string, list<string>|null> $allowedIn */
        $allowedIn = Block::query()->whereIn('slug', array_unique($slugs))->pluck('allowed_in', 'slug')->all();

        foreach (array_unique($slugs) as $slug) {
            $notAllowed = $region['allow'] !== null && ! in_array($slug, $region['allow'], true);
            $goesElsewhere = is_array($allowedIn[$slug] ?? null) && ! in_array(self::ALLOWED_IN.$name, $allowedIn[$slug], true);

            if ($notAllowed || $goesElsewhere) {
                $refusals[] = (string) __('webx-blocks::regions.not-allowed', ['type' => $slug]);
            }
        }

        $cycle = $this->cycle($name, $tree);

        if ($cycle !== null) {
            $refusals[] = (string) __('webx-blocks::regions.cycle', ['path' => implode(' → ', $cycle)]);
        }

        return $refusals;
    }

    /**
     * How many blocks the region shows at the top level: the switched-off ones do not count.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     */
    public static function visible(?iterable $tree): int
    {
        $count = 0;

        foreach ($tree ?? [] as $node) {
            if (Content::isNode($node) && ! Content::isHidden($node)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * A declared title or description in the language of the request: `trans::` is a key of a
     * dictionary, anything else is the words themselves.
     */
    public static function words(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return str_starts_with($text, 'trans::') ? (string) __(substr($text, 7)) : $text;
    }

    /**
     * The way back to the region from its own tree, through the shared blocks it places and the
     * ones those place in turn — `request → promo → request` — or null when there is none. The
     * others are read as published: that is what the site would draw inside this one.
     *
     * @param  iterable<array-key, mixed>  $tree
     * @param  list<string>  $path
     * @return list<string>|null
     */
    private function cycle(string $name, iterable $tree, array $path = []): ?array
    {
        $path = $path === [] ? [$name] : $path;

        foreach ($this->container->make(Fragments::class)->placed($tree) as $inner) {
            if ($inner === $name) {
                return [...$path, $inner];
            }

            // A loop that does not pass through this region is not this publication's doing; the
            // site leaves it empty where it closes.
            if (in_array($inner, $path, true)) {
                continue;
            }

            $found = $this->cycle($name, $this->published($inner), [...$path, $inner]);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The published tree as the row holds it, past the cache — what a copy is made of, and what
     * a check reads right after another region was published.
     *
     * @return list<array<string, mixed>>
     */
    public function published(string $name): array
    {
        $region = $this->find($name);

        return $region instanceof Region && $region->isPublished() ? $region->blocksTree() : [];
    }

    /**
     * The markup from code, with the tag's attributes as variables and as `$attributes` — the way
     * the same view would receive them had the layout called it as a component.
     *
     * @param  array<string, mixed>  $data
     */
    private function fallback(?string $view, array $data): HtmlString
    {
        if ($view === null || $view === '') {
            return new HtmlString('');
        }

        return new HtmlString($this->views->make($view, [...$data, 'attributes' => new ComponentAttributeBag($data)])->render());
    }

    /**
     * The pair of comments the panel finds the region by, in its preview and on a page that gets
     * the panel on the site. Blocks inside carry their own pair already; the region's is around
     * all of them, fallback included, so that the panel knows where the first block of an empty
     * region goes — and the bar, that a block under the pointer is the header's, not the page's.
     */
    private function mark(string $name, HtmlString $html, bool $preview): HtmlString
    {
        if (! $preview && ! $this->renderer->isMarkingForSiteBar()) {
            return $html;
        }

        return new HtmlString("<!--wx-region:{$name}-->{$html->toHtml()}<!--/wx-region:{$name}-->");
    }

    /** The strip over a region whose draft has a failing block, in the preview. */
    /** @return array<string, string> */
    private function known(): array
    {
        if ($this->known === null) {
            $known = $this->cache->get(self::FALLBACKS);
            /** @var array<string, string> $known */
            $known = is_array($known) ? $known : [];
            $this->known = $known;
        }

        return $this->known;
    }

    private function banner(string $text): string
    {
        return '<div class="wx-region-error" data-wx-region-error style="padding:8px 12px;background:#fff4e5;color:#7a4b00;font:14px/1.4 system-ui,sans-serif;border:1px solid #f0c36d">'.e($text).'</div>';
    }

    private function remember(string $name, string $fallback): void
    {
        if (($this->remembered[$name] ?? null) === $fallback) {
            return;
        }

        $this->remembered[$name] = $fallback;

        try {
            $known = $this->known();

            if (($known[$name] ?? null) !== $fallback) {
                $known[$name] = $fallback;
                $this->known = $known;
                $this->cache->forever(self::FALLBACKS, $known);
            }
        } catch (Throwable) {
            // A hint, not a contract: a cache that cannot be written costs the panel a sentence.
        }
    }

    /** The entity of the page the region stands on, when `routing` answered the address. */
    private function entity(): ?object
    {
        $request = $this->request();

        return $request === null ? null : Resolution::of($request)?->entity;
    }

    /** Where the visitor stands, spelled the way the menu compares it. */
    private function path(): string
    {
        $request = $this->request();

        return $request === null ? '' : trim(rawurldecode($request->path()), '/');
    }

    private function request(): ?Request
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        $request = $this->container->make('request');

        return $request instanceof Request ? $request : null;
    }

    private function log(): LoggerInterface
    {
        return $this->container->make(LoggerInterface::class);
    }
}
