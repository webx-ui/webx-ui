<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Routing;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Popularity\ViewCounter;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Seo\UnavailableSource;
use WebxUi\Catalog\Storefront\CategoryIndex;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\Routing\RouteCache;
use WebxUi\Routing\RouteHandler;

/**
 * What answers once the registry has decided that this address is a product (§5).
 *
 * On sale — the full page, a view counted towards popularity (§9), and the verdict of
 * {@see Purchasability} for the template to choose its button by. Unpublished, or published in no
 * visible category — the trimmed one: 200 rather than 404, because the address is somebody's
 * bookmark or an old order's link, and a page that says "no longer sold" (and, with
 * `module-catalog-links`, what to take instead) is the useful answer. `noindex` comes from
 * {@see UnavailableSource} and from the response header, so a page without the `<head>` of
 * `module-seo` is closed as well.
 *
 * A deleted product never gets here: it has no row in the registry, and its address is answered
 * by {@see CatalogMisses}.
 */
final class ProductHandler implements RouteHandler
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly StorefrontParts $parts,
        private readonly Purchasability $purchasability,
        private readonly ViewCounter $counter,
        private readonly CategoryIndex $categories,
        private readonly RouteCache $routes,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Product || $tail !== '') {
            throw new NotFoundHttpException;
        }

        // The main category and the ancestors of its trail out of one pool (§8.4) — one query on
        // a cold page, and a shelf of the same category asks nothing again. Visibility comes from
        // the shape of the tree, so the handler, the purchase rule and the SEO source do not ask.
        $entity->loadMissing(['categories', 'images']);
        $this->categories->adopt($entity->categories);
        $this->categories->attach([$entity], withAncestors: true);
        Product::rememberVisible((int) $entity->getKey(), $this->categories->productVisible($entity));

        // The addresses of the product, its categories and the trail are noted, not read: the
        // parts prepared next note theirs (the brand), and the first `url()` reads them all in
        // one query — none on a warm page, where the cache of the registry has them.
        $category = $entity->category;
        $this->routes->preload([
            $entity,
            ...$entity->categories,
            ...($category instanceof Category ? [$category, ...array_values($this->categories->find($this->categories->ancestorIds((int) $category->getKey())))] : []),
        ]);
        $this->parts->prepare(new Collection([$entity]));

        if (! $entity->isVisible()) {
            return response($this->views->make('webx-catalog::product-unavailable', ['product' => $entity])->render())
                ->header('X-Robots-Tag', 'noindex, follow');
        }

        $this->counter->record($entity, $request);

        return response($this->views->make('webx-catalog::product', [
            'product' => $entity,
            'verdict' => $this->purchasability->for($entity),
        ])->render());
    }
}
