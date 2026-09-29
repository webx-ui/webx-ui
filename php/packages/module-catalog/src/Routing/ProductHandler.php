<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Routing;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Seo\UnavailableSource;
use WebxUi\Routing\RouteHandler;

/**
 * What answers once the registry has decided that this address is a product (§5).
 *
 * On sale — the full page. Unpublished, or published in no visible category — the trimmed one:
 * 200 rather than 404, because the address is somebody's bookmark or an old order's link, and a
 * page that says "no longer sold" (and, with `module-catalog-links`, what to take instead) is
 * the useful answer. `noindex` comes from {@see UnavailableSource} and from
 * the response header, so a page without the `<head>` of `module-seo` is closed as well.
 *
 * A deleted product never gets here: it has no row in the registry, and its address is answered
 * by {@see CatalogMisses}.
 */
final class ProductHandler implements RouteHandler
{
    public function __construct(private readonly ViewFactory $views) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Product || $tail !== '') {
            throw new NotFoundHttpException;
        }

        $entity->loadMissing(['category', 'images']);

        if ($entity->isVisible()) {
            return response($this->views->make('webx-catalog::product', ['product' => $entity])->render());
        }

        return response($this->views->make('webx-catalog::product-unavailable', ['product' => $entity])->render())
            ->header('X-Robots-Tag', 'noindex, follow');
    }
}
