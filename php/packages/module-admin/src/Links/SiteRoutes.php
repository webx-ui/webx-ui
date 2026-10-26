<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use WebxUi\Admin\SiteBar\PageResponse;

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
 * editor from remembering `/account`. A package says so where it registers such a route — the
 * action attribute {@see self::SERVICE}, set on its group — so a preview, a form's token or a sitemap
 * stylesheet stays out wherever the package moves it. What nobody of ours registers (Passport's
 * OAuth dance, discovery documents, files) is named by `webx-admin.links.exclude`, as path masks, so
 * a site can add its own without waiting for us.
 *
 * The same line is what makes a page "service" anywhere else: the panel on the site and the debug
 * bar stay off it ({@see PageResponse}), and an SEO rule cannot be made
 * for it — an OAuth consent screen is not a page of the site, whoever is looking at it.
 */
final readonly class SiteRoutes
{
    /**
     * The machinery every installation has: an authorisation dance, discovery documents, and
     * anything that is a file rather than a page.
     *
     * @var list<string>
     */
    public const EXCLUDE = ['.well-known/*', 'oauth/*', '*.js', '*.css', '*.json', '*.xml', '*.xsl', '*.txt'];

    /**
     * The route action attribute that marks a route as machinery: `Route::group([SiteRoutes::SERVICE
     * => true], …)`. A plain string, so that a package which knows nothing of the panel — the forms
     * of `webx-ui/widgets` — can set it all the same.
     */
    public const SERVICE = 'webx_service';

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

        return ! $this->isService($route);
    }

    /**
     * Whether a route is machinery rather than a page: marked so by its package, or at an address
     * that is ({@see self::isServicePath()}).
     */
    public function isService(Route $route): bool
    {
        // Nested groups merge the attribute into a list of trues, hence "not empty" rather than "true".
        if (! empty($route->getAction(self::SERVICE))) {
            return true;
        }

        return $this->isServicePath((string) $route->uri());
    }

    /**
     * Whether an address is machinery by where it is: the panel and its JSON, the previews, the
     * OAuth dance and the masks of `webx-admin.links.exclude`.
     */
    public function isServicePath(string $path): bool
    {
        $path = trim($path, '/');

        return $this->insidePanel($path) || $this->machinery($path);
    }

    /**
     * Whether an address of the site is machinery, the route that answers it included — for a
     * question asked about a path rather than a request, like an SEO rule for an address.
     */
    public function isServiceAddress(string $path): bool
    {
        $path = '/'.trim((string) parse_url($path, PHP_URL_PATH), '/');

        if ($this->isServicePath($path)) {
            return true;
        }

        try {
            $route = $this->router->getRoutes()->match(Request::create($path));
        } catch (\Throwable) {
            return false;
        }

        return ! empty($route->getAction(self::SERVICE));
    }

    private function insidePanel(string $uri): bool
    {
        // The preview's own default: a site without blocks has no config of theirs to read.
        foreach (['webx-admin.path' => '', 'webx-admin.api_path' => '', 'webx-blocks.preview.path' => '_preview'] as $key => $default) {
            $prefix = trim((string) $this->config->get($key, $default), '/');

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

        // The OAuth dance where the MCP server put it, when that is not the default `oauth`.
        $oauth = $this->config->get('webx-mcp.oauth.prefix');

        if (is_string($oauth) && trim($oauth, '/') !== '') {
            $masks[] = trim($oauth, '/');
            $masks[] = trim($oauth, '/').'/*';
        }

        return Str::is($masks, $uri);
    }
}
