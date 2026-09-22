<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * The addresses the site has that no entity owns: an account area, a checkout, a search page.
 *
 * A link to one of those is stored as a literal path, and that is on the editor (§2, decision 6):
 * a route *name* would survive the path changing, but it is a second way of writing a target for
 * a case that is rare. What makes the literal path bearable is this — the panel offers the list
 * instead of asking somebody to remember it.
 *
 * Only `GET` routes with a name and no parameters. A route with a parameter has no one address to
 * offer, and one without a name is usually a fallback or a callback rather than a page. The
 * panel's own addresses are left out: a link into the CMS is not a link on the site.
 *
 * And then the machinery, which is the part worth reading twice. A site of this size registers
 * dozens of named GET routes that are nobody's page — the OAuth dance, `.well-known`, a runtime
 * script — and on the demo site they were the first eight entries in a list meant to save an
 * editor from remembering `/account`. What comes out is named by `webx-admin.links.exclude`, as
 * path masks, so a site can add its own without waiting for us.
 */
final readonly class SiteRoutes
{
    /**
     * The machinery every installation has: an authorisation dance, discovery documents, and
     * anything that is a file rather than a page.
     *
     * @var list<string>
     */
    public const EXCLUDE = ['.well-known/*', 'oauth/*', '*.js', '*.css', '*.json', '*.xml', '*.txt'];

    public function __construct(
        private Router $router,
        private Config $config,
    ) {}

    /**
     * @return list<array{name: string, path: string}>
     */
    public function all(): array
    {
        $found = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! $this->isPlainPage($route)) {
                continue;
            }

            $path = '/'.trim((string) $route->uri(), '/');

            // Two names for one address — a localised copy of the same page — are one entry:
            // what an editor picks is the address, and the name is only there to recognise it.
            $found[$path] ??= ['name' => $name, 'path' => $path];
        }

        ksort($found);

        return array_values($found);
    }

    private function isPlainPage(Route $route): bool
    {
        if (! in_array('GET', $route->methods(), true)) {
            return false;
        }

        // Optional parameters count: `{path?}` is the panel's catch-all, and a route that takes
        // one has no single address for a menu item to point at.
        if (str_contains((string) $route->uri(), '{')) {
            return false;
        }

        $uri = trim((string) $route->uri(), '/');

        return ! $this->insidePanel($uri) && ! $this->machinery($uri);
    }

    private function insidePanel(string $uri): bool
    {
        foreach (['webx-admin.path', 'webx-admin.api_path'] as $key) {
            $prefix = trim((string) $this->config->get($key), '/');

            if ($prefix !== '' && ($uri === $prefix || str_starts_with($uri, $prefix.'/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read with `??` rather than through the merge: a site that published `webx-admin.php` before
     * this key existed has no copy of it, and `mergeConfigFrom` only merges the top level
     * (CLAUDE.md §4). Without the fallback that site would be offered the whole OAuth dance.
     */
    private function machinery(string $uri): bool
    {
        /** @var list<string> $masks */
        $masks = (array) ($this->config->get('webx-admin.links.exclude') ?? self::EXCLUDE);

        return Str::is($masks, $uri);
    }
}
