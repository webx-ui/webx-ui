<?php

declare(strict_types=1);

namespace WebxUi\Pages\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

/**
 * What an agent reads to know where it is (§13).
 *
 * One resource, and it is the map: the pages of the site nested the way they are nested, each
 * with the address it answers at in every language and whether it answers at all. An agent
 * asked to "add a page under the catalogue" needs that before it needs anything else, and
 * reading it is one call rather than a walk down the tree a level at a time.
 */
final class PageResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'pages://sitemap',
                'Sitemap',
                'Every page of the site as a tree: its address in each language, whether it is on the site, '
                .'and what may be done to it. The map to read before changing anything.',
                fn (): array => $this->sitemap(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sitemap(): array
    {
        /** @var Locales $locales */
        $locales = $this->container->make(Locales::class);

        // One query for the whole site, ordered the way the tree reads, and the nesting is put
        // back together in memory: a map built with a query per level is a map nobody asks for
        // twice.
        $pages = Page::query()->with('routes')->orderBy('lft')->get();

        $nodes = [];
        $children = [];
        $roots = [];

        foreach ($pages as $page) {
            $nodes[(int) $page->getKey()] = $this->node($page);

            if ($page->parent_id === null) {
                $roots[] = (int) $page->getKey();

                continue;
            }

            $children[(int) $page->parent_id][] = (int) $page->getKey();
        }

        $build = static function (int $id) use (&$build, &$nodes, &$children): array {
            $node = $nodes[$id];
            $node['children'] = array_map($build, $children[$id] ?? []);

            return $node;
        };

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'count' => $pages->count(),
            'pages' => array_map($build, $roots),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function node(Page $page): array
    {
        $shown = $page->hasDraft() ? $page->withDraft() : $page;
        $urls = [];

        foreach ($page->routes as $route) {
            if ($route->kind === Route::CANONICAL) {
                $urls[$route->locale] = '/'.$route->path;
            }
        }

        return [
            'id' => (int) $page->getKey(),
            'is_home' => $page->isRoot(),
            'title' => $shown->getTranslations('title'),
            // A language missing here is a language the page has no address in, which is a real
            // state: it or something above it was never given a slug there (§8).
            'paths' => $urls,
            'status' => $page->status(),
            'can' => $page->capabilities(),
        ];
    }
}
