<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Regions;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteHandler;
use WebxUi\Routing\RouteTypes;

/**
 * The draft of a region, drawn on a real page of the site (§6 of the regions spec).
 *
 * A header cannot be judged apart from the page under it, so the answer is the page a visitor
 * would get at `at` — the same handler, the same `Resolution`, the same language and active menu
 * item — with one thing swapped: the region named in the token prints its draft. The page itself
 * is the published one, and its own blocks carry no markers; a region's preview must not show
 * anybody's drafts but its own, and in this frame only the region is there to be picked.
 *
 * An address the registry does not know — a route of the site with a controller of its own, a
 * typo — is not handed to that controller: under a preview token it might write, not only read.
 * The region is drawn on the stage instead, the layout with nothing in it, under a notice.
 */
final class RegionPreviewController
{
    /** Where the stage leaves room for a region its layout does not print. */
    private const PLACE = '<!--wx-region-stage-->';

    public function __construct(
        private readonly Preview $preview,
        private readonly Regions $regions,
        private readonly Resolver $resolver,
        private readonly RouteTypes $types,
        private readonly Locales $locales,
        private readonly Container $container,
        private readonly Views $views,
        private readonly Config $config,
    ) {}

    public function __invoke(Request $request, string $name): Response
    {
        $grant = $this->preview->grant($request, Preview::REGION, $name) ?? throw new AccessDeniedHttpException('The preview link is invalid or has expired.');

        if (! $this->regions->has($name)) {
            throw new NotFoundHttpException;
        }

        [$path, $query] = $this->at($request);

        // The page's own request: its path is what the menu highlights and what `$region->path()`
        // says, and a handler reading `?page=2` reads the page's query, not the token.
        $server = $request->server->all();
        $queryString = http_build_query($query);
        $server['REQUEST_URI'] = $path.($queryString === '' ? '' : '?'.$queryString);
        $server['QUERY_STRING'] = $queryString;

        $page = $request->duplicate($query, [], null, null, null, $server);
        $page->attributes->set(Regions::PREVIEW, $grant);

        $previous = $this->container->make('request');
        $this->container->instance('request', $page);

        try {
            $response = $this->page($page, $path) ?? $this->stage($name, $path);
        } finally {
            $this->container->instance('request', $previous);
        }

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * The page at the address, answered by its handler — or null when the registry has nothing
     * there to answer with. An alias is followed quietly: a redirect would take the frame off
     * the preview address, and the page behind it is the one the editor meant.
     */
    private function page(Request $request, string $path): ?Response
    {
        $found = $this->resolver->lookup($path);

        if ($found === null) {
            return null;
        }

        $route = $found->route->isAlias() ? $found->route->target : $found->route;

        if (! $route instanceof Route) {
            return null;
        }

        $type = $this->types->find($route->entity_type);

        if ($type === null || $type->handler === null) {
            return null;
        }

        /** @var Model $model */
        $model = $this->container->make($type->model);
        $entity = $model->newQuery()->find($route->entity_id);

        if (! $entity instanceof Model) {
            return null;
        }

        // The page's language, which on a site with prefixes the preview's own address never
        // carried: `/de/about` is German whatever the frame's URL says.
        $this->locales->use($route->locale);

        $request->attributes->set(Resolution::ATTRIBUTE, new Resolution($route, $found->tail, $type, $entity));

        /** @var RouteHandler $handler */
        $handler = $this->container->make($type->handler);

        return $handler->handle($request, $entity, $found->tail);
    }

    /**
     * The layout with nothing in it and a notice where the content goes. A layout that does not
     * print this region at all gets it drawn in that place too, so the editor sees their draft
     * rather than an empty page.
     */
    private function stage(string $name, string $path): Response
    {
        $layout = $this->config->get('webx-blocks.layout');

        $html = $this->views->make('webx-blocks::region-stage', [
            'layout' => is_string($layout) && $layout !== '' ? $layout : 'webx-blocks::standalone',
            'notice' => (string) __('webx-blocks::regions.not-in-registry', ['path' => $path]),
            'place' => self::PLACE,
        ])->render();

        $region = str_contains($html, "<!--wx-region:{$name}-->") ? '' : (string) $this->regions->render($name);

        return response(str_replace(self::PLACE, $region, $html));
    }

    /**
     * The page to draw on: a path of this site with its query, whatever was sent. A whole URL
     * counts for its path only — the preview never leaves the site it was asked on.
     *
     * @return array{string, array<string, mixed>}
     */
    private function at(Request $request): array
    {
        $at = $request->query('at');
        $parts = is_string($at) && $at !== '' ? parse_url($at) : [];
        $parts = is_array($parts) ? $parts : [];

        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');
        $query = [];

        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        /** @var array<string, mixed> $query */
        return [$path, $query];
    }
}
