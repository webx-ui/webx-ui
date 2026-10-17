<?php

declare(strict_types=1);

namespace WebxUi\Debug;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\SiteBar\SiteBar;
use WebxUi\Blocks\Models\Block;
use WebxUi\Cache\CacheGroups;
use WebxUi\Debug\Support\CallSite;
use WebxUi\Debug\Support\Masker;
use WebxUi\Routing\Resolution;
use WebxUi\Themes\ThemeChain;

/**
 * The report out of what the {@see Collector} wrote down (§9 and §12 of the debug bar spec):
 * one format for the bar, its API and the MCP tool `debug_page`.
 *
 * Built after collecting stopped, so what it asks — the versions of the cache groups, the theme
 * layer each block type came from — is never in the report it builds.
 */
final class ReportBuilder
{
    /** The format of a report; a reader that knows another one says so rather than guessing. */
    public const VERSION = 1;

    /** Session keys whose values are shown; the rest are named only. */
    private const SESSION_VALUES = ['locale', 'url.intended'];

    public function __construct(
        private readonly Collector $collector,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Masker $masker,
        private readonly CallSite $sites,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $id, Model $user, Request $request, Response $response): array
    {
        $started = microtime(true);
        $c = $this->collector;

        $queries = $this->queries();
        $repeated = $this->repeated($queries);
        $slowMs = (float) $this->config->get('webx-debug.slow_ms', 50);
        $errors = count(array_filter($c->log, static fn (array $line): bool => in_array($line['level'], ['error', 'critical', 'alert', 'emergency'], true)));

        $report = [
            'version' => self::VERSION,
            'id' => $id,
            'user_id' => $user->getKey(),
            'url' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'at' => gmdate('Y-m-d\TH:i:s\Z'),
            'early' => ! $c->late,
            'truncated' => $c->truncated,
            'summary' => [
                'queries' => $c->queryCount,
                'sql_ms' => round($c->queryTime, 2),
                'response_ms' => $c->at($c->stopped),
                'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
                'repeated' => count($repeated),
                'slow' => count(array_filter($queries, static fn (array $query): bool => ($query['kind'] ?? '') === 'query' && $query['time'] >= $slowMs)),
                'errors' => $errors,
                'collector_ms' => round($c->overhead * 1000, 2),
            ],
            'thresholds' => [
                'slow_ms' => $slowMs,
                'repeat' => (int) $this->config->get('webx-debug.repeat', 3),
            ],
            'queries' => $queries,
            'repeated' => $repeated,
            'timeline' => $this->timeline(),
            'blocks' => $this->blocks(),
            'views' => $this->views(),
            'cache' => $this->cache(),
            'request' => $this->request($request, $response),
            'links' => $this->links($user, $request),
            'other' => [
                'http' => array_values(array_map(static function (array $call): array {
                    unset($call['started']);

                    return $call;
                }, $c->http)),
                'jobs' => $c->jobs,
                'mail' => $c->mail,
                'events' => $c->events,
                'log' => $c->log,
            ],
        ];

        $report['summary']['report_ms'] = round((microtime(true) - $started) * 1000, 2);

        return $report;
    }

    /**
     * The statements with their values written in — masks stay masks.
     *
     * @return list<array<string, mixed>>
     */
    private function queries(): array
    {
        $out = [];

        foreach ($this->collector->queries as $query) {
            if (($query['kind'] ?? '') === 'query') {
                $query['inline'] = self::inline((string) $query['sql'], (array) $query['bindings']);
            }

            $out[] = $query;
        }

        return $out;
    }

    /**
     * Shapes that ran more than once, most repeated first, with where they came from. `likely`
     * marks what the threshold calls an N+1.
     *
     * @param  list<array<string, mixed>>  $queries
     * @return list<array<string, mixed>>
     */
    private function repeated(array $queries): array
    {
        $threshold = max(2, (int) $this->config->get('webx-debug.repeat', 3));
        $shapes = [];

        foreach ($queries as $query) {
            if (($query['kind'] ?? '') !== 'query') {
                continue;
            }

            $shape = (string) $query['shape'];
            $shapes[$shape] ??= ['shape' => $shape, 'count' => 0, 'time' => 0.0, 'sites' => [], 'in' => [], 'queries' => []];
            $shapes[$shape]['count']++;
            $shapes[$shape]['time'] += (float) $query['time'];
            $shapes[$shape]['queries'][] = $query['index'];

            if (is_string($query['site'] ?? null)) {
                $shapes[$shape]['sites'][$query['site']] = ($shapes[$shape]['sites'][$query['site']] ?? 0) + 1;
            }

            if (is_string($query['in'] ?? null)) {
                $shapes[$shape]['in'][$query['in']] = true;
            }
        }

        $repeated = [];

        foreach ($shapes as $entry) {
            if ($entry['count'] < 2) {
                continue;
            }

            arsort($entry['sites']);
            $entry['time'] = round($entry['time'], 2);
            $entry['likely'] = $entry['count'] >= $threshold;
            $entry['in'] = array_keys($entry['in']);
            $repeated[] = $entry;
        }

        usort($repeated, static fn (array $a, array $b): int => [$b['count'], $b['time']] <=> [$a['count'], $a['time']]);

        return $repeated;
    }

    /** @return array<string, mixed> */
    private function timeline(): array
    {
        $c = $this->collector;
        $spans = [];

        foreach ($c->spans as $index => $span) {
            $end = $span['end'] ?? $c->stopped;

            $spans[] = [
                'id' => $index,
                'kind' => $span['kind'],
                'label' => $span['label'],
                'parent' => $span['parent'],
                'start' => $c->at($span['start']),
                'ms' => round(($end - $span['start']) * 1000, 2),
                'queries' => ($span['q1'] ?? $c->queryCount) - $span['q0'],
            ];
        }

        return [
            'spans' => $spans,
            'total_ms' => $c->at($c->stopped),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'collector_ms' => round($c->overhead * 1000, 2),
        ];
    }

    /**
     * The regions and blocks as a tree, each with its own time and statements and those of what
     * is inside it, and the theme layer its type came from.
     *
     * @return list<array<string, mixed>>
     */
    private function blocks(): array
    {
        $c = $this->collector;
        $layers = $this->typeLayers();
        $nodes = [];

        foreach ($c->nodes as $index => $node) {
            $end = $node['end'] ?? $c->stopped;
            $entry = [
                'kind' => $node['kind'],
                'ms' => round(($end - $node['start']) * 1000, 2),
                'queries' => ($node['q1'] ?? $c->queryCount) - $node['q0'],
                'children' => [],
            ];

            if ($node['kind'] === 'region') {
                $entry['name'] = $node['name'];
                $entry['source'] = $node['source'] ?? null;
            } else {
                $entry += [
                    'key' => $node['key'],
                    'type' => $node['type'],
                    'version' => $node['version'],
                    'layer' => array_key_exists($node['type'], $layers) ? ($layers[$node['type']] ?? 'site') : null,
                    'error' => $node['error'] ?? null,
                ];
            }

            $nodes[$index] = ['parent' => $node['parent'], 'entry' => $entry];
        }

        // Children are written after their parent, so walking backwards folds every subtree
        // into its parent before the parent itself is folded.
        $roots = [];

        for ($i = count($c->nodes) - 1; $i >= 0; $i--) {
            if (! isset($nodes[$i])) {
                continue;
            }

            $entry = $nodes[$i]['entry'];
            $childMs = array_sum(array_map(static fn (array $child): float => (float) $child['ms'], $entry['children']));
            $childQueries = array_sum(array_map(static fn (array $child): int => (int) $child['queries'], $entry['children']));
            $entry['self_ms'] = round(max(0.0, $entry['ms'] - $childMs), 2);
            $entry['self_queries'] = max(0, $entry['queries'] - $childQueries);
            $entry['children'] = array_reverse($entry['children']);

            $parent = $nodes[$i]['parent'];

            if ($parent !== null && isset($nodes[$parent])) {
                $nodes[$parent]['entry']['children'][] = $entry;
            } else {
                $roots[] = $entry;
            }
        }

        return array_reverse($roots);
    }

    /**
     * The theme layer each block type drawn on the page came from — null for one a person made
     * in the panel. One query, after collecting stopped.
     *
     * @return array<string, string|null>
     */
    private function typeLayers(): array
    {
        $types = array_values(array_unique(array_filter(array_map(
            static fn (array $node): ?string => $node['kind'] === 'block' ? (string) $node['type'] : null,
            $this->collector->nodes,
        ))));

        if ($types === [] || ! class_exists(Block::class)) {
            return [];
        }

        try {
            /** @var array<string, string|null> $layers */
            $layers = Block::query()->whereIn('slug', $types)->pluck('theme_source', 'slug')->all();

            return $layers;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The Blade views made, each named by the layer of the theme chain — or the module, or the
     * site — that the file the view factory found belongs to.
     *
     * @return list<array<string, mixed>>
     */
    private function views(): array
    {
        $out = [];

        foreach ($this->collector->views as $view) {
            $out[] = [
                'name' => $view['name'],
                'count' => $view['count'],
                'file' => $view['path'] !== '' ? $this->sites->name($view['path']) : null,
                'owner' => $view['path'] !== '' ? $this->owner($view['path']) : null,
            ];
        }

        return $out;
    }

    /** Who the file of a view belongs to: a layer of the theme chain, the site, or a package. */
    private function owner(string $path): string
    {
        $real = str_replace('\\', '/', (string) (realpath($path) ?: $path));

        if (class_exists(ThemeChain::class) && $this->container->bound(ThemeChain::class)) {
            try {
                /** @var ThemeChain $chain */
                $chain = $this->container->make(ThemeChain::class);

                foreach ($chain->layers as $layer) {
                    $root = str_replace('\\', '/', (string) (realpath($layer->path) ?: $layer->path));

                    if ($root !== '' && str_starts_with($real, rtrim($root, '/').'/')) {
                        return 'theme '.$layer->name;
                    }
                }
            } catch (Throwable) {
                // No chain on this site: the path alone says enough.
            }
        }

        $name = $this->sites->name($path);

        return str_contains($name, ' · ') ? explode(' · ', $name, 2)[0] : 'site';
    }

    /** @return array<string, mixed> */
    private function cache(): array
    {
        $c = $this->collector;
        $versions = [];

        if ($this->container->bound(CacheGroups::class)) {
            try {
                /** @var CacheGroups $groups */
                $groups = $this->container->make(CacheGroups::class);
                $versions = $groups->versions(...$groups->ids());
            } catch (Throwable) {
                $versions = [];
            }
        }

        $read = [];

        foreach ($c->groups as $name => $entry) {
            foreach ($entry['depends'] as $group) {
                $read[$group] ??= ['group' => $group, 'version' => $versions[$group] ?? null, 'reads' => 0, 'memo' => 0, 'store' => 0, 'miss' => 0, 'entries' => []];
                $read[$group]['reads'] += $entry['memo'] + $entry['store'] + $entry['miss'];
                $read[$group]['memo'] += $entry['memo'];
                $read[$group]['store'] += $entry['store'];
                $read[$group]['miss'] += $entry['miss'];
                $read[$group]['entries'][] = $name;
            }
        }

        ksort($read);

        $keys = [];

        foreach ($c->cache as $event) {
            if (str_starts_with($event['key'], 'webx:')) {
                continue;
            }

            $keys[] = $event;
        }

        return [
            'groups' => array_values($read),
            'versions' => $versions,
            'entries' => array_map(static fn (string $name, array $entry): array => ['name' => $name] + $entry, array_keys($c->groups), array_values($c->groups)),
            'keys' => $keys,
            'counts' => [
                'hit' => count(array_filter($c->cache, static fn (array $event): bool => $event['type'] === 'hit')),
                'miss' => count(array_filter($c->cache, static fn (array $event): bool => $event['type'] === 'miss')),
                'write' => count(array_filter($c->cache, static fn (array $event): bool => $event['type'] === 'write')),
                'forget' => count(array_filter($c->cache, static fn (array $event): bool => $event['type'] === 'forget')),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function request(Request $request, Response $response): array
    {
        $route = $request->route();
        $resolution = class_exists(Resolution::class) ? Resolution::of($request) : null;

        $session = [];

        if ($request->hasSession()) {
            foreach ($request->session()->all() as $key => $value) {
                $key = (string) $key;
                $shown = in_array($key, self::SESSION_VALUES, true) || str_contains($key, 'consent');
                $session[$key] = $shown && ! is_array($value) ? $this->masker->value($value) : Masker::MASK;
            }

            ksort($session);
        }

        $theme = null;

        if (class_exists(ThemeChain::class) && $this->container->bound(ThemeChain::class)) {
            try {
                /** @var ThemeChain $chain */
                $chain = $this->container->make(ThemeChain::class);
                $theme = array_map(static fn ($layer): string => $layer->name, $chain->layers);
            } catch (Throwable) {
                $theme = null;
            }
        }

        return [
            'route' => $route instanceof Route ? [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'action' => $route->getActionName(),
                'middleware' => array_values(array_map('strval', $route->gatherMiddleware())),
                'fallback' => $route->isFallback,
            ] : null,
            'resolution' => $resolution === null ? null : [
                'type' => $resolution->type?->type,
                'handler' => $resolution->type?->handler,
                'id' => $resolution->entity?->getKey(),
                'route_id' => $resolution->route->getKey(),
                'path' => $resolution->route->getAttribute('path'),
                'locale' => $resolution->locale,
                'tail' => $resolution->tail,
            ],
            'view' => $this->collector->page,
            'theme' => $theme,
            'status' => $response->getStatusCode(),
            'locale' => $this->container->make('translator')->getLocale(),
            'request_headers' => $this->masker->headers($request->headers->all()),
            'response_headers' => $this->masker->headers($response->headers->all()),
            'cookies' => array_values(array_map('strval', array_keys($request->cookies->all()))),
            'session' => $session,
            'connection' => DB::getDefaultConnection(),
        ];
    }

    /**
     * Where the bar may send this administrator in the panel: the record of the page, by the same
     * registry and the same permission as the panel on the site, and the screen of a region. None
     * where the administrator could not edit what is there — the bar only offers what works.
     *
     * @return array{page: string|null, region: string|null}
     */
    private function links(Model $user, Request $request): array
    {
        $panel = '/'.trim((string) $this->config->get('webx-admin.path', 'cms'), '/');
        $can = static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
        $page = null;

        $resolution = class_exists(Resolution::class) ? Resolution::of($request) : null;
        $type = $resolution?->type?->type;

        if ($resolution?->entity instanceof Model && is_string($type) && $this->container->bound(SiteBar::class)) {
            try {
                /** @var SiteBar $bar */
                $bar = $this->container->make(SiteBar::class);
                $link = $bar->link($type);

                if ($link !== null && $can($link->permission)) {
                    $edit = ($link->edit)($resolution->entity);
                    $page = is_string($edit) && $edit !== '' ? $panel.'/'.ltrim($edit, '/') : null;
                }
            } catch (Throwable) {
                $page = null;
            }
        }

        return [
            'page' => $page,
            // The bar adds the region's name: one prefix, not one address per region drawn.
            'region' => class_exists(Block::class) && $can('blocks.regions') ? $panel.'/regions/' : null,
        ];
    }

    /**
     * The statement with its values written in: good enough to read and to paste into a
     * console, never run by anything here.
     *
     * @param  array<int, mixed>  $bindings
     */
    public static function inline(string $sql, array $bindings): string
    {
        $values = array_values($bindings);
        $index = 0;

        return preg_replace_callback('/\?/', static function () use ($values, &$index): string {
            if (! array_key_exists($index, $values)) {
                return '?';
            }

            $value = $values[$index++];

            return match (true) {
                $value === null => 'null',
                is_bool($value) => $value ? '1' : '0',
                is_int($value), is_float($value) => (string) $value,
                $value === Masker::MASK => "'".Masker::MASK."'",
                default => "'".str_replace("'", "''", (string) $value)."'",
            };
        }, $sql) ?? $sql;
    }
}
