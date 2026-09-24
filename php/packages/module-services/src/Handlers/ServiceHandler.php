<?php

declare(strict_types=1);

namespace WebxUi\Services\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Blocks\Preview\PreviewGrant;
use WebxUi\Routing\RouteHandler;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Rendering\Views;

/**
 * What answers once the registry has decided that this address is a service.
 *
 * `isVisible()` is the whole of the decision, and the sitemap asks the same thing: a draft is a
 * 404 to everybody, and the same service under a preview token is shown as it will be, because
 * the preview route of `module-blocks` has already laid the draft over the columns by then.
 *
 * A service in a hidden category answers all the same (§4.4): it is not the category's property.
 */
class ServiceHandler implements RouteHandler
{
    public function __construct(private readonly Views $views) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Service) {
            throw new NotFoundHttpException;
        }

        if (! $entity->isVisible() && PreviewGrant::of($request) === null) {
            throw new NotFoundHttpException;
        }

        $entity->loadMissing(['cover', 'categories']);

        return response($this->views->make('service', [
            'service' => $entity,
            'category' => $entity->mainServiceCategory(),
        ])->render());
    }
}
