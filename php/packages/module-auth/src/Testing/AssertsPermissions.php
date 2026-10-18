<?php

declare(strict_types=1);

namespace WebxUi\Auth\Testing;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Throwable;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Grants\Grants;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The test every module carries (WEBX_UI_PERMISSIONS.md §15): nothing it serves is open to an
 * administrator who holds no permission, and everything it checks is a permission somebody
 * declared — so a role can list it and the matrix of roles can show it.
 *
 * For a Testbench test case with the module's providers, the auth module's and, for the tools,
 * the MCP server's; the database migrated.
 *
 *     $this->assertPanelRoutesNeedPermission('WebxUi\\Faq\\');
 *     $this->assertToolsNeedPermission('faq', 'faq-categories');
 *
 * A route open to every administrator on purpose — their own profile, a list of names — is
 * named in `$open` with the reason, the way §5.5 of the spec lists them.
 */
trait AssertsPermissions
{
    /** Values for a parameter in an address, the first one its constraint accepts: an id, a slug, a name with a dot, a UUID, a ULID. */
    private const MADE_UP = ['999999', 'missing', 'missing-one', 'missing.one', '00000000-0000-4000-8000-000000000000', '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'a'];

    /**
     * Every route of the panel served by these namespaces answers 401 to nobody and 403 to an
     * administrator with no permissions — before it looks up the record in its address, so that
     * a made-up id is enough to ask with — and asks only for declared permissions.
     *
     * @param  string|list<string>  $namespaces  controller namespaces, `WebxUi\Faq\`, or address prefixes,
     *                                           `api/cms/faq/` — a section whose routes are served by the
     *                                           frame's shared controllers (categories) is found by its address
     * @param  array<string, string>  $open  `METHOD uri` → why every administrator may call it
     */
    protected function assertPanelRoutesNeedPermission(string|array $namespaces, array $open = []): void
    {
        $routes = $this->panelRoutes((array) $namespaces);

        $this->assertNotEmpty($routes, 'No panel route is served from '.implode(', ', (array) $namespaces).'.');

        $nobody = $this->administratorWithoutPermissions();
        $policy = $this->app->make(AccessPolicy::class);
        $problems = [];

        $guarded = [];

        foreach ($routes as $route) {
            $key = $this->methodOf($route).' '.$route->uri();

            foreach ($this->permissionsOf($route) as $permission) {
                if (! $policy->isDeclared($permission)) {
                    $problems[] = "{$key}: [{$permission}] is checked and no installed module declares it.";
                }
            }

            if (! array_key_exists($key, $open)) {
                $guarded[$key] = $route;
            }
        }

        // Nobody first: a test case that signs somebody in stays signed in for every request after.
        foreach ($guarded as $key => $route) {
            $status = $this->json($this->methodOf($route), '/'.$this->fill($route))->status();

            if ($status !== 401) {
                $problems[] = "{$key}: answers {$status} to nobody signed in, not 401.";
            }
        }

        foreach ($guarded as $key => $route) {
            // Again for every request: a route that signs out (or a refusal that does) would
            // otherwise leave the rest of the list asking as nobody.
            $this->actingAs($nobody, (string) config('webx-auth.guard'));

            $status = $this->json($this->methodOf($route), '/'.$this->fill($route))->status();

            if ($status !== 403) {
                $problems[] = "{$key}: answers {$status} to an administrator without permissions, not 403."
                    .($this->permissionsOf($route) === [] ? ' It has no cms.can: add one, or name it as open with the reason.' : '');
            }
        }
        foreach (array_keys($open) as $key) {
            if (! in_array($key, array_map(fn (Route $route): string => $this->methodOf($route).' '.$route->uri(), $routes), true)) {
                $problems[] = "{$key}: named as open, and there is no such route.";
            }
        }

        $this->assertSame([], $problems, "Panel routes open to whoever is signed in:\n".implode("\n", $problems));
    }

    /**
     * Every MCP tool of these modules asks for declared permissions, is left out of the list for
     * an administrator who holds none of them, and is refused if called anyway.
     */
    protected function assertToolsNeedPermission(string ...$modules): void
    {
        $tools = array_values(array_filter(
            $this->app->make(ToolRegistry::class)->tools(),
            static fn (BoundTool $tool): bool => in_array($tool->moduleId, $modules, true),
        ));

        $this->assertNotEmpty($tools, 'No MCP tool comes from '.implode(', ', $modules).'.');

        $nobody = $this->administratorWithoutPermissions();
        $policy = $this->app->make(AccessPolicy::class);
        $grants = $this->app->make(Grants::class);
        $problems = [];

        $request = HttpRequest::create('/');
        $request->setUserResolver(static fn (): CmsUser => $nobody);

        foreach ($tools as $bound) {
            $name = $bound->fullName();

            $declared = array_filter($bound->permissions(), static fn (string $permission): bool => $policy->isDeclared($permission));

            if ($declared === []) {
                $problems[] = "{$name}: asks for [".implode('] or [', $bound->permissions()).'] and no installed module declares any.';
            }

            $tool = new RegistryTool($bound);

            if ($tool->shouldRegister($request, $grants)) {
                $problems[] = "{$name}: listed to an administrator without permissions.";
            }

            $arguments = $bound->tool->mutating ? ['dry_run' => true] : [];

            try {
                WebxServer::actingAs($nobody)->tool($tool, $arguments)->assertHasErrors();
            } catch (Throwable) {
                $problems[] = "{$name}: answers an administrator without permissions.";
            }
        }

        $this->assertSame([], $problems, "MCP tools open to whoever is connected:\n".implode("\n", $problems));
    }

    /** Signed in, switched on, holding nothing. */
    protected function administratorWithoutPermissions(): CmsUser
    {
        return CmsUser::query()->firstOrCreate(
            ['email' => 'nobody-in-particular@example.test'],
            ['name' => 'Nobody', 'password' => 'correct-horse-battery', 'is_super' => false, 'is_active' => true],
        );
    }

    /**
     * @param  list<string>  $namespaces
     * @return list<Route>
     */
    private function panelRoutes(array $namespaces): array
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $found = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $middleware = array_filter($route->gatherMiddleware(), 'is_string');

            if (! in_array('cms.auth', $middleware, true) && ! in_array('webx.panel', $middleware, true)) {
                continue;
            }

            $controller = ltrim($route->getActionName(), '\\');

            foreach ($namespaces as $namespace) {
                $matches = str_contains($namespace, '/')
                    ? str_starts_with($route->uri(), $namespace)
                    : str_starts_with($controller, ltrim($namespace, '\\'));

                if ($matches) {
                    $found[] = $route;

                    break;
                }
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function permissionsOf(Route $route): array
    {
        $permissions = [];

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'cms.can:')) {
                array_push($permissions, ...explode(',', substr($middleware, strlen('cms.can:'))));
            }
        }

        return $permissions;
    }

    private function methodOf(Route $route): string
    {
        return array_values(array_diff($route->methods(), ['HEAD']))[0] ?? 'GET';
    }

    /** The route's address with every parameter made up — a value its constraint accepts. */
    private function fill(Route $route): string
    {
        return (string) preg_replace_callback('/\{([^}?]+)\??\}/', static function (array $match) use ($route): string {
            $pattern = $route->wheres[$match[1]] ?? null;

            foreach (self::MADE_UP as $candidate) {
                if ($pattern === null || preg_match('#^(?:'.$pattern.')$#', $candidate) === 1) {
                    return $candidate;
                }
            }

            return '999999';
        }, $route->uri());
    }
}
