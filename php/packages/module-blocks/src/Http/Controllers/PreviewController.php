<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Controllers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use WebxUi\Blocks\Preview\Preview;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\RouteHandler;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;

/**
 * The preview route's whole body: the resolver's last step, done for an id instead of an
 * address, under a token instead of publication.
 */
class PreviewController
{
    public function __construct(
        private readonly Preview $preview,
        private readonly RouteTypes $types,
        private readonly Renderer $renderer,
        private readonly Locales $locales,
        private readonly Container $container,
    ) {}

    public function __invoke(Request $request, string $type, string $id): Response
    {
        // 403 rather than 404 for a bad token: the address is right, the permission is not,
        // and an editor whose link has expired should be told that rather than "no such page".
        $grant = $this->preview->grant($request, $type, $id) ?? throw new AccessDeniedHttpException('The preview link is invalid or has expired.');

        $routeType = $this->types->find($type);

        if ($routeType === null || $routeType->handler === null) {
            throw new NotFoundHttpException;
        }

        $entity = $this->entity($routeType, $id) ?? throw new NotFoundHttpException;

        // The draft laid over the columns — a copy, so nothing here can publish by accident.
        if (method_exists($entity, 'withDraft')) {
            $entity = $entity->withDraft();
        }

        $request->attributes->set(PreviewGrant::ATTRIBUTE, $grant);
        $request->attributes->set(Resolution::ATTRIBUTE, new Resolution($this->route($routeType, $entity), '', $routeType, $entity));

        // Drafts of the block types, notices instead of log lines, a marker pair around every
        // block: everything the panel needs and the site never sees.
        $this->renderer->preview(true);

        /** @var RouteHandler $handler */
        $handler = $this->container->make($routeType->handler);

        $response = $handler->handle($request, $entity, '');

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function entity(RouteType $type, string $id): ?Model
    {
        /** @var Model $model */
        $model = $this->container->make($type->model);

        return $model->newQuery()->find($id);
    }

    /**
     * The row the resolver would have found for the entity's real address, so that breadcrumbs,
     * the active menu item and the canonical URL are the page's own although the path is not.
     * A record that has no address yet gets an unsaved row with the path the formatter would
     * give it — or none, when there is not enough of the record for a path.
     */
    private function route(RouteType $type, Model $entity): Route
    {
        if (method_exists($entity, 'routeCanonical')) {
            $canonical = $entity->routeCanonical();

            if ($canonical instanceof Route) {
                return $canonical;
            }
        }

        $path = '';

        if (method_exists($entity, 'routePath')) {
            try {
                $path = (string) $entity->routePath();
            } catch (Throwable) {
                // A formatter that cannot say yet: an empty slug, a parent not chosen.
            }
        }

        return new Route([
            'locale' => $this->locales->current(),
            'path' => $path,
            'kind' => Route::CANONICAL,
            'entity_type' => $type->type,
            'entity_id' => $entity->getKey(),
        ]);
    }
}
