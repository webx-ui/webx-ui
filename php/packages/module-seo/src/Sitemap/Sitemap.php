<?php

declare(strict_types=1);

namespace WebxUi\Seo\Sitemap;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route as RouteRow;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\HasSeo;
use WebxUi\Seo\Rendering\Alternates;
use WebxUi\Seo\Rendering\Seo;

/**
 * `/sitemap.xml`: an index, and a file per type of the address registry (§17.3).
 *
 * What goes in is decided by nobody here. An address is in the map when three others say so:
 * the registry has a canonical row for it, the entity says it is visible ({@see Visible} — the
 * same answer its handler gives), and the SEO resolver prints neither `noindex` nor a canonical
 * pointing somewhere else for it. The third is the one that matters (decision 2): a rule, a card
 * or a module's own source that closes a page closes it in the map too, and a map with rules of
 * its own would be a second opinion that nobody notices drifting from the first.
 *
 * Built whole on the first request and kept, under a generation that moves on every save that
 * could change the answer — a registry row, a card, a rule, a visible entity, an `seo.*`
 * setting. The time-to-live is for the one change nothing announces: an article dated for
 * tomorrow becomes visible without anything being written.
 *
 * @phpstan-type Entry array{loc: string, lastmod: CarbonInterface|null, locale: string, group: string}
 * @phpstan-type Status array{enabled: bool, url: string, built_at: string|null, files: array<string, int>, total: int, excluded: array{noindex: int, canonical: int}}
 */
final class Sitemap
{
    /** The file of named routes that have no entity; a type of that name would share it. */
    public const ROUTES = 'routes';

    private const CHUNK = 500;

    /**
     * Visible addresses the resolver closed, by why — counted while building, for the panel.
     *
     * @var array{noindex: int, canonical: int}
     */
    private array $excluded = ['noindex' => 0, 'canonical' => 0];

    public function __construct(
        private readonly RouteTypes $types,
        private readonly SitemapRoutes $routes,
        private readonly Seo $seo,
        private readonly SiteUrl $site,
        private readonly Locales $locales,
        private readonly Router $router,
        private readonly Resolver $resolver,
        private readonly Alternates $alternates,
        private readonly Cache $cache,
        private readonly Config $config,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('webx-seo.sitemap.enabled', true);
    }

    /** The absolute address of the index, as `robots.txt` names it. */
    public function url(): string
    {
        return URL::to('sitemap.xml');
    }

    /** The index, built if the current generation has none. */
    public function index(): string
    {
        return $this->cached('index') ?? $this->build()['index'];
    }

    /** One file of the map, or null when there is no such file. */
    public function file(string $name): ?string
    {
        $key = 'file.'.$name;

        if ($this->cached('index') !== null) {
            return $this->cached($key);
        }

        return $this->build()['files'][$name] ?? null;
    }

    /**
     * What the panel's card and `seo_sitemap_status` say: how many addresses in which file, when
     * it was built, and how many visible addresses were left out and why (§17.6). The current
     * build if there is one, a fresh one if there is not — the question is asked about the map a
     * crawler would get.
     *
     * @return Status
     */
    public function status(): array
    {
        $stats = null;

        if ($this->enabled()) {
            $cached = $this->cached('stats');
            $stats = $cached === null ? null : json_decode($cached, true);

            if (! is_array($stats)) {
                $stats = $this->build()['stats'];
            }
        }

        /** @var array{built_at?: string|null, files?: array<string, int>, excluded?: array{noindex: int, canonical: int}} $stats */
        $stats ??= [];
        $files = $stats['files'] ?? [];

        return [
            'enabled' => $this->enabled(),
            'url' => $this->url(),
            'built_at' => $stats['built_at'] ?? null,
            'files' => $files,
            'total' => array_sum($files),
            'excluded' => $stats['excluded'] ?? ['noindex' => 0, 'canonical' => 0],
        ];
    }

    /**
     * Is this address in the map, and if not, why not — the line `test-url` adds (§17.6).
     *
     * Asked by the same steps the build takes, one address at a time: the registry row, the
     * entity's own answer, the resolver. The query does not count: the map lists addresses, and
     * `?page=2` of a feed is found through the feed.
     *
     * @return array{included: bool, reason: 'disabled'|'unknown'|'alias'|'hidden'|'noindex'|'canonical'|null}
     */
    public function verdict(string $url, ?string $locale = null): array
    {
        if (! $this->enabled()) {
            return ['included' => false, 'reason' => 'disabled'];
        }

        [$path] = explode('?', UrlNormaliser::normalise($url), 2);
        [$subject, $rowLocale, $reason] = $this->find($path, $locale);

        if ($reason !== null) {
            return ['included' => false, 'reason' => $reason];
        }

        $closed = $this->seo->closedBecause($this->seo->for($path, $subject, $rowLocale), $path);

        return $closed === null
            ? ['included' => true, 'reason' => null]
            : ['included' => false, 'reason' => $closed];
    }

    /**
     * Everything, from scratch, into the cache.
     *
     * @return array{index: string, files: array<string, string>, counts: array<string, int>, stats: array{built_at: string, files: array<string, int>, excluded: array{noindex: int, canonical: int}}}
     */
    public function build(): array
    {
        $files = [];
        $counts = [];
        $modified = [];
        $this->excluded = ['noindex' => 0, 'canonical' => 0];

        foreach ($this->entries() as $type => $entries) {
            $alternates = $this->alternatesOf($entries);

            foreach ($this->split($type, $entries) as $name => $part) {
                $files[$name] = $this->urlset($part, $alternates);
                $counts[$name] = count($part);
                $modified[$name] = $this->latest($part);
            }
        }

        $index = $this->sitemapindex($modified);
        $stats = ['built_at' => Carbon::now()->toAtomString(), 'files' => $counts, 'excluded' => $this->excluded];

        if ($this->caching()) {
            $generation = $this->generation();
            $ttl = $this->ttl();

            foreach ($files as $name => $xml) {
                $this->cache->put($this->key($generation, 'file.'.$name), $xml, $ttl);
            }

            $this->cache->put($this->key($generation, 'stats'), (string) json_encode($stats), $ttl);

            // The index last: it is what says a build is in the cache, so a request that sees
            // it can trust that every file it names is there too.
            $this->cache->put($this->key($generation, 'index'), $index, $ttl);
        }

        return ['index' => $index, 'files' => $files, 'counts' => $counts, 'stats' => $stats];
    }

    /**
     * Throw the built map away. Not by deleting — the files are many and their names are not
     * known in advance — but by moving on to a new generation; the old one runs out on its own.
     */
    public function refresh(): void
    {
        if ($this->caching()) {
            $this->cache->forever($this->generationKey(), bin2hex(random_bytes(6)));
        }
    }

    /**
     * Every address of the map, by the file it belongs to.
     *
     * @return array<string, list<Entry>>
     */
    private function entries(): array
    {
        $all = [];

        foreach ($this->types->all() as $type) {
            if (! is_subclass_of($type->model, Visible::class)) {
                continue;
            }

            $entries = $this->typeEntries($type);

            if ($entries !== []) {
                $all[$type->type] = $entries;
            }
        }

        $named = $this->routeEntries();

        if ($named !== []) {
            $all[self::ROUTES] = [...($all[self::ROUTES] ?? []), ...$named];
        }

        return $all;
    }

    /**
     * The canonical rows of one type, a language at a time and five hundred at a time, with
     * the entities behind each batch loaded in one query and their cards in one more.
     *
     * An address is written once even where two languages share it — with the language outside
     * the path, every language of an untranslated slug is the same address.
     *
     * @return list<Entry>
     */
    private function typeEntries(RouteType $type): array
    {
        /** @var Model&Visible $prototype */
        $prototype = new ($type->model);
        $withSeo = in_array(HasSeo::class, class_uses_recursive($prototype), true);
        $entries = [];
        $seen = [];

        foreach ($this->locales->codes() as $locale) {
            RouteRow::query()
                ->where('entity_type', $type->type)
                ->where('kind', RouteRow::CANONICAL)
                ->where('locale', $locale)
                ->chunkById(self::CHUNK, function (Collection $rows) use ($prototype, $withSeo, $locale, $type, &$entries, &$seen): void {
                    $query = $prototype->newQuery();
                    $prototype->scopeVisible($query, $locale);

                    if ($withSeo) {
                        $query->with('seo');
                    }

                    /** @var Collection<int, Model&Visible> $found */
                    $found = $query->whereKey($rows->pluck('entity_id')->unique()->values()->all())->get();
                    $entities = $found->keyBy(static fn (Model $entity): int => (int) $entity->getKey());

                    foreach ($rows as $row) {
                        /** @var RouteRow $row */
                        $entity = $entities->get($row->entity_id);

                        if ($entity === null) {
                            continue;
                        }

                        $path = $this->path($row->path, $locale);

                        if (isset($seen[$path]) || ! $this->indexable($path, $entity, $locale)) {
                            continue;
                        }

                        $seen[$path] = true;
                        $entries[] = [
                            'loc' => URL::to($path),
                            'lastmod' => $entity->visibleUpdatedAt(),
                            'locale' => $locale,
                            'group' => $type->type.':'.$row->entity_id,
                        ];
                    }
                });
        }

        return $entries;
    }

    /**
     * The named routes modules asked for, in every language the site is published in.
     *
     * With the language outside the path there is one address for all of them, and it is
     * written once.
     *
     * @return list<Entry>
     */
    private function routeEntries(): array
    {
        $entries = [];
        $seen = [];

        foreach ($this->routes->all() as $name) {
            $route = $this->router->getRoutes()->getByName($name);

            if ($route === null || $route->parameterNames() !== []) {
                continue;
            }

            foreach ($this->locales->codes() as $locale) {
                $path = $this->path($route->uri(), $locale);

                if (isset($seen[$path]) || ! $this->indexable($path, null, $locale)) {
                    continue;
                }

                $seen[$path] = true;
                $entries[] = ['loc' => URL::to($path), 'lastmod' => null, 'locale' => $locale, 'group' => 'route:'.$name];
            }
        }

        return $entries;
    }

    /**
     * The entity behind an address and the language of its row — or why the map has nothing
     * to say about it.
     *
     * @return array{0: object|null, 1: string|null, 2: 'unknown'|'alias'|'hidden'|null}
     */
    private function find(string $path, ?string $locale): array
    {
        $resolution = $this->resolver->lookup($path, $locale);

        if ($resolution !== null && $resolution->tail === '') {
            $row = $resolution->route;

            if ($row->isAlias()) {
                return [null, null, 'alias'];
            }

            $type = $this->types->find($row->entity_type);

            if ($type === null || ! is_subclass_of($type->model, Visible::class)) {
                return [null, null, 'unknown'];
            }

            /** @var (Model&Visible)|null $entity */
            $entity = $type->model::query()->find($row->entity_id);

            if ($entity === null || ! $entity->isVisible($row->locale)) {
                return [null, null, 'hidden'];
            }

            return [$entity, $row->locale, null];
        }

        $key = mb_strtolower(UrlNormaliser::normalise($path), 'UTF-8');

        foreach ($this->routes->all() as $name) {
            $route = $this->router->getRoutes()->getByName($name);

            if ($route === null || $route->parameterNames() !== []) {
                continue;
            }

            foreach ($this->locales->codes() as $code) {
                if (mb_strtolower($this->path($route->uri(), $code), 'UTF-8') === $key) {
                    return [null, $code, null];
                }
            }
        }

        return [null, null, 'unknown'];
    }

    /**
     * What the `<head>` of that page would say, asked of the same resolver that prints it.
     * A closed address is counted by why, for the line on the panel's card.
     */
    private function indexable(string $path, ?object $subject, string $locale): bool
    {
        $closed = $this->seo->closedBecause($this->seo->for($path, $subject, $locale), $path);

        if ($closed !== null) {
            $this->excluded[$closed]++;
        }

        return $closed === null;
    }

    /**
     * Every language of one entity, from the entries already in the map — so an alternate is
     * never an address the map itself left out, and the `<head>` asks the same three questions
     * to get the same set (§17.1, decision 8).
     *
     * @param  list<Entry>  $entries
     * @return array<string, array<string, string>>
     */
    private function alternatesOf(array $entries): array
    {
        if (! $this->alternates->apply()) {
            return [];
        }

        $groups = [];

        foreach ($entries as $entry) {
            $groups[$entry['group']][Alternates::hreflang($entry['locale'])] = $entry['loc'];
        }

        $default = Alternates::hreflang($this->locales->defaultCode());
        $alternates = [];

        foreach ($groups as $group => $links) {
            if (count($links) < 2) {
                continue;
            }

            if (isset($links[$default])) {
                $links['x-default'] = $links[$default];
            }

            $alternates[$group] = $links;
        }

        return $alternates;
    }

    /** The path a reader would type: the language prefix, then the registry's path. */
    private function path(string $path, string $locale): string
    {
        return '/'.UrlNormaliser::join($this->site->prefix($locale), $path);
    }

    /**
     * A type's addresses, cut into files the protocol accepts. One file keeps the plain name;
     * more are numbered from one, so a type that grows past the limit moves its addresses, not
     * the index's idea of what exists.
     *
     * @param  list<Entry>  $entries
     * @return array<string, list<Entry>>
     */
    private function split(string $type, array $entries): array
    {
        $size = max(1, (int) $this->config->get('webx-seo.sitemap.per_file', 45000));

        if (count($entries) <= $size) {
            return [$type => $entries];
        }

        $parts = [];

        foreach (array_chunk($entries, $size) as $i => $part) {
            $parts[$type.'-'.($i + 1)] = $part;
        }

        return $parts;
    }

    /**
     * @param  list<Entry>  $entries
     */
    private function latest(array $entries): ?CarbonInterface
    {
        $latest = null;

        foreach ($entries as $entry) {
            if ($entry['lastmod'] !== null && ($latest === null || $entry['lastmod']->greaterThan($latest))) {
                $latest = $entry['lastmod'];
            }
        }

        return $latest;
    }

    /**
     * Written by hand rather than through a view: the document opens with `<?xml`, which Blade
     * compiles as PHP (CLAUDE.md §4), and there is nothing here a template would make clearer.
     *
     * @param  list<Entry>  $entries
     * @param  array<string, array<string, string>>  $alternates
     */
    private function urlset(array $entries, array $alternates): string
    {
        $xml = $this->prologue().'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .($alternates === [] ? '' : ' xmlns:xhtml="http://www.w3.org/1999/xhtml"')
            .'>'."\n";

        foreach ($entries as $entry) {
            $xml .= '<url><loc>'.$this->escape($entry['loc']).'</loc>'
                .($entry['lastmod'] === null ? '' : '<lastmod>'.$entry['lastmod']->toAtomString().'</lastmod>');

            foreach ($alternates[$entry['group']] ?? [] as $hreflang => $href) {
                $xml .= '<xhtml:link rel="alternate" hreflang="'.$this->escape($hreflang).'" href="'.$this->escape($href).'"/>';
            }

            $xml .= "</url>\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /**
     * @param  array<string, CarbonInterface|null>  $files
     */
    private function sitemapindex(array $files): string
    {
        $xml = $this->prologue().'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($files as $name => $modified) {
            $xml .= '<sitemap><loc>'.$this->escape(URL::to('sitemap-'.$name.'.xml')).'</loc>'
                .($modified === null ? '' : '<lastmod>'.$modified->toAtomString().'</lastmod>')
                ."</sitemap>\n";
        }

        return $xml.'</sitemapindex>'."\n";
    }

    private function prologue(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function cached(string $what): ?string
    {
        if (! $this->caching()) {
            return null;
        }

        $value = $this->cache->get($this->key($this->generation(), $what));

        return is_string($value) ? $value : null;
    }

    private function generation(): string
    {
        $generation = $this->cache->get($this->generationKey());

        if (is_string($generation) && $generation !== '') {
            return $generation;
        }

        $this->refresh();

        return (string) $this->cache->get($this->generationKey(), '0');
    }

    private function key(string $generation, string $what): string
    {
        return $this->prefix().'.'.$generation.'.'.$what;
    }

    private function generationKey(): string
    {
        return $this->prefix().'.generation';
    }

    private function prefix(): string
    {
        return (string) $this->config->get('webx-seo.sitemap.cache.key', 'webx.seo.sitemap');
    }

    private function caching(): bool
    {
        return (bool) $this->config->get('webx-seo.sitemap.cache.enabled', true);
    }

    private function ttl(): int
    {
        return (int) $this->config->get('webx-seo.sitemap.cache.ttl', 86400);
    }
}
