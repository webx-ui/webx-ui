<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use SplFileInfo;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Doctor\Paths;

/**
 * Whether the panel will still open after the deploy has cached everything.
 *
 * Caching is where a site that works stops working, and in two ways that both look like
 * something else. A configuration cache written before `webx:panel` added the entry serves a
 * panel with no front end. And a `throttle:<name>` whose limiter is never declared is read by
 * Laravel as a *number* of attempts, which is zero, so the address answers 429 to everything —
 * and that one is only ever seen after `route:cache`, because the declaration and the
 * registration usually live in the same branch (CLAUDE.md §4).
 */
final class PanelOpens implements Check
{
    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
        private readonly Filesystem $files,
        private readonly Router $router,
        private readonly RateLimiter $limiter,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        return [...$this->shell(), ...$this->caches(), ...$this->limiters()];
    }

    /**
     * That there is an address to open at all.
     *
     * @return list<Diagnosis>
     */
    private function shell(): array
    {
        $path = '/'.ltrim((string) $this->config->get('webx-admin.path'), '/');

        return $this->router->getRoutes()->getByName('webx.shell') === null
            ? [Diagnosis::fail(
                'Panel',
                "no route answers at {$path}: the panel's routes are not registered — check that WebxUi\\Admin\\AdminServiceProvider is discovered, and clear the route cache.",
            )]
            : [Diagnosis::ok('Panel', "answers at {$path}.")];
    }

    /**
     * A cache older than what it was made from.
     *
     * @return list<Diagnosis>
     */
    private function caches(): array
    {
        $found = [];

        foreach ([
            'config:cache' => [$this->app->getCachedConfigPath(), [$this->app->configPath(), $this->app->basePath('.env')]],
            'route:cache' => [$this->app->getCachedRoutesPath(), [$this->app->basePath('routes')]],
        ] as $command => [$cache, $sources]) {
            if (! $this->files->exists($cache)) {
                continue;
            }

            $written = (int) $this->files->lastModified($cache);
            $newer = $this->newerThan($written, $sources);

            $found[] = $newer === null
                ? Diagnosis::ok(explode(':', $command)[0].' cache', 'newer than everything it was built from.')
                : Diagnosis::fail(
                    explode(':', $command)[0].' cache',
                    "{$newer} has changed since the cache was written, and the cache is what the site reads — run `php artisan {$command}`.",
                );
        }

        return $found === []
            ? [Diagnosis::ok('Caches', 'nothing is cached, so the site reads the files themselves.')]
            : $found;
    }

    /**
     * The first file under any of these paths newer than the given moment.
     *
     * @param  list<string>  $sources
     */
    private function newerThan(int $written, array $sources): ?string
    {
        foreach ($sources as $source) {
            $files = $this->files->isDirectory($source)
                ? $this->files->allFiles($source)
                : ($this->files->exists($source) ? [new SplFileInfo($source)] : []);

            foreach ($files as $file) {
                if ((int) $file->getMTime() > $written) {
                    return Paths::short($file->getPathname(), $this->app->basePath());
                }
            }
        }

        return null;
    }

    /**
     * Every named limiter a registered route asks for, and whether anybody declared it.
     *
     * @return list<Diagnosis>
     */
    private function limiters(): array
    {
        $wanted = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'throttle:')) {
                    continue;
                }

                $name = substr($middleware, strlen('throttle:'));

                // `throttle:60,1` is the plain one and needs nothing declared.
                if (preg_match('/^\d+(,\d+)*$/', $name) !== 1) {
                    $wanted[$name][] = $route->uri();
                }
            }
        }

        $found = [];

        foreach ($wanted as $name => $uris) {
            if ($this->limiter->limiter((string) $name) !== null) {
                continue;
            }

            $found[] = Diagnosis::fail(
                'Rate limits',
                "nothing declares the limiter [{$name}], so Laravel reads the name as a number of attempts — /"
                .$uris[0].' answers 429 to everybody. Declare it with `RateLimiter::for()` outside the branch that registers the routes.',
            );
        }

        return $found === []
            ? [Diagnosis::ok('Rate limits', $wanted === [] ? 'no named limiter is asked for.' : count($wanted).' named limiters, all declared.')]
            : $found;
    }
}
