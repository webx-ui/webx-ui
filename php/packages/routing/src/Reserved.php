<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouterRoute;
use Illuminate\Routing\Router;

/**
 * Addresses the registry must not hand out.
 *
 * Checked when an entity is saved, never when a request is resolved (§10). By the time a request
 * arrives it is too late to be useful: the project's route has already won, and the editor is
 * left with a page that exists in the panel, has an address in the registry, and answers with
 * somebody else's screen. Refusing the save says so at the only moment anybody can act on it.
 *
 * The router is asked first, so a project that adds a screen keeps the list true without editing
 * it. The configured list is for what no route describes — directories the web server serves
 * straight off disk, names kept for later — and the panel's own prefix is added here rather than
 * in the config file so that changing it in one place is enough.
 */
class Reserved
{
    public function __construct(
        private readonly Router $router,
        private readonly Config $config,
    ) {}

    /** @param  string  $path  A registry key: no leading slash, lower case, `''` for the root. */
    public function taken(string $path): bool
    {
        return $this->prefixed($path) || $this->routed($path);
    }

    /** @return list<string> Every reserved prefix that is not a route, for `webx:routes:check`. */
    public function prefixes(): array
    {
        /** @var list<string> $configured */
        $configured = (array) $this->config->get('webx-routing.reserved', []);

        $prefixes = array_map(UrlNormaliser::key(...), $configured);

        // The panel's two prefixes and the preview's: routes with parameters, which the router
        // cannot be asked about one address at a time, so their prefixes are closed here.
        foreach (['webx-admin.path', 'webx-admin.api_path', 'webx-blocks.preview.path'] as $key) {
            $panel = UrlNormaliser::key((string) $this->config->get($key, ''));

            if ($panel !== '') {
                $prefixes[] = $panel;
            }
        }

        return array_values(array_unique(array_filter($prefixes, static fn (string $prefix): bool => $prefix !== '')));
    }

    /** A prefix reserves the branch under it: `storage` closes `storage/exports` too. */
    private function prefixed(string $path): bool
    {
        foreach ($this->prefixes() as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the project already answer this address with a route of its own?
     *
     * GET only: those are the addresses a page competes for. The fallback is skipped for the
     * obvious reason — it is us, and it matches everything.
     */
    private function routed(string $path): bool
    {
        $request = Request::create('/'.$path, 'GET');

        foreach ($this->router->getRoutes()->get('GET') as $route) {
            if ($route instanceof RouterRoute && ! $route->isFallback && $route->matches($request)) {
                return true;
            }
        }

        return false;
    }
}
