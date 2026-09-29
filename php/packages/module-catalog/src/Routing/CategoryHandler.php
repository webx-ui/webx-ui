<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Routing;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Models\Category;
use WebxUi\Routing\RouteHandler;

/**
 * What answers a category's address.
 *
 * Hidden — unpublished, or under an unpublished ancestor — is a 404 (§6.3). The tail is the
 * filter's (§7.7), and reading it is the engine's work; until the filter exists any tail is a 404
 * rather than a page that pretends to have filtered something.
 *
 * The page itself is the bare minimum — the name and the description — so that a redirect to a
 * category lands somewhere. The listing, the filter and the rest of the storefront come with the
 * engine.
 */
final class CategoryHandler implements RouteHandler
{
    public function __construct(private readonly ViewFactory $views) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Category || $tail !== '' || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        return response($this->views->make('webx-catalog::category', ['category' => $entity])->render());
    }
}
