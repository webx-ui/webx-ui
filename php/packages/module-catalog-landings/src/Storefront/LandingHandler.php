<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Storefront;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Storefront\Storefront;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\Localization\Locales;
use WebxUi\Routing\RouteHandler;

/**
 * What answers a landing's address, with the filter's tail behind it (§6.1 of the landings spec):
 * the page of its base — the category's, or the root's — started from its set, drawn by the
 * core's storefront with the category's template. The landing is the page's subject, so the
 * heading, the trail, the card, the texts and the order are its own.
 *
 * Hidden — unpublished, in the bin, on a hidden category — is a 404, tail or no tail.
 */
final class LandingHandler implements RouteHandler
{
    public function __construct(
        private readonly Storefront $storefront,
        private readonly CategoryFacets $categoryFacets,
        private readonly Facets $facets,
        private readonly Locales $locales,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Landing || ! $entity->isVisible()) {
            throw new NotFoundHttpException;
        }

        $locale = $this->locales->current();
        $category = $entity->category_id === null ? null : $entity->category;

        if ($category instanceof Category) {
            $context = new FilterContext(
                context: FilterContext::CATEGORY,
                path: $category->routeCanonical($locale)->path ?? $category->routePath($locale),
                locale: $locale,
                facets: $this->categoryFacets->visible($category),
                category: $category,
                subject: $entity,
            );

            return $this->storefront->listing($request, $context, $tail, 'webx-catalog::category', [
                CategoryFacet::KEY => FacetValue::of([(string) $category->id]),
            ], (int) $category->id, base: $entity->state());
        }

        // The whole catalogue's: the root's context, even where the root's own page is off.
        $context = new FilterContext(
            context: FilterContext::ROOT,
            path: $this->storefront->rootPath(),
            locale: $locale,
            facets: $this->facets->all(),
            subject: $entity,
        );

        return $this->storefront->listing($request, $context, $tail, 'webx-catalog::category', [], null, base: $entity->state());
    }
}
