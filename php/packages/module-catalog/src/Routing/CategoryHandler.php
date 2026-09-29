<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Routing;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Filter\SegmentSerializer;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Storefront\Storefront;
use WebxUi\Routing\RouteHandler;

/**
 * What answers a category's address, with the filter's tail behind it (§4, §10).
 *
 * Hidden — unpublished, or under an unpublished ancestor — is a 404 (§6.3), tail or no tail. A
 * tail is the filter's: segments with `_`; anything else is not a page of this category and is a
 * 404 as well ({@see SegmentSerializer}).
 */
final class CategoryHandler implements RouteHandler
{
    public function __construct(private readonly Storefront $storefront) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Category || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        return $this->storefront->category($request, $entity, $tail);
    }
}
