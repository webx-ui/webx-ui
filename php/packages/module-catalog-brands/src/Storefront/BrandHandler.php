<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Storefront;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Storefront\Storefront;
use WebxUi\CatalogBrands\Catalog\BrandFacet;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\Localization\Locales;
use WebxUi\Routing\RouteHandler;

/**
 * What answers a brand's address, with the filter's tail behind it (§2.3 of the dictionaries
 * spec): the catalogue's list narrowed to the brand — `scope: brand` — drawn by the core's
 * storefront, one path for a category's page and this one.
 *
 * The brand's own facet is not on its page (every product there is of it), so the first level of
 * the filter is the rest — the categories first: `/brands/apple/category_laptops/`. Hidden is a
 * 404, tail or no tail; a tail that is not the filter's is a 404 as well.
 */
final class BrandHandler implements RouteHandler
{
    /** The context the engine and a facet read: the core knows no brands, only the word. */
    public const CONTEXT = 'brand';

    public function __construct(
        private readonly Storefront $storefront,
        private readonly Facets $facets,
        private readonly Locales $locales,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Brand || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        $locale = $this->locales->current();
        $context = new FilterContext(
            context: self::CONTEXT,
            path: $entity->routeCanonical($locale)->path ?? $entity->routePath($locale),
            locale: $locale,
            facets: array_values(array_filter($this->facets->all(), static fn (Facet $facet): bool => $facet->key() !== BrandFacet::KEY)),
            subject: $entity,
        );

        return $this->storefront->listing($request, $context, $tail, 'webx-catalog-brands::brand', [
            BrandFacet::KEY => FacetValue::of([(string) $entity->id]),
        ], (int) $entity->id, ['brand' => $entity]);
    }
}
