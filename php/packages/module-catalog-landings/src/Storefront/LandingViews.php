<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Storefront;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Storefront\CatalogPage;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogLandings\Models\Landing;

/**
 * The data of the landings' views, from what the point gives them.
 *
 * Over the list: on a plain landing's first page, the recommended products — visible ones only,
 * so one taken off the site leaves the strip and comes back with it; on a category's page with
 * nothing chosen, its collections. Under the list of a landing with nothing chosen over its set:
 * the neighbours and the same set elsewhere. Beside a product: the landings that hold it.
 */
final class LandingViews
{
    public function __construct(
        private readonly LandingLinks $links,
        private readonly Purchasability $purchasability,
        private readonly StorefrontParts $parts,
        private readonly Config $config,
    ) {}

    public function top(View $view): void
    {
        $page = $view->getData()['page'] ?? null;
        $recommended = new Collection;
        $collections = [];

        if ($page instanceof CatalogPage) {
            $owner = $page->subject();

            if ($owner instanceof Landing && $page->isPlain()) {
                $recommended = $this->recommended($owner, $page);
            } elseif ($owner === null && $page->category() !== null && ! $page->isFiltered()) {
                $collections = $this->links->forCategory($page->category(), $page->context->locale);
            }
        }

        $view->with([
            'recommended' => $recommended,
            'verdicts' => $recommended->isEmpty() ? [] : $this->purchasability->forMany($recommended),
            'collections' => $collections,
        ]);
    }

    public function bottom(View $view): void
    {
        $page = $view->getData()['page'] ?? null;
        $owner = $page instanceof CatalogPage ? $page->subject() : null;
        $plain = $owner instanceof Landing && ! $page->isFiltered();

        $view->with([
            'siblings' => $plain ? $this->links->siblings($owner, $page->context->locale) : [],
            'elsewhere' => $plain ? $this->links->elsewhere($owner, $page->context->locale) : [],
        ]);
    }

    public function product(View $view): void
    {
        $product = $view->getData()['product'] ?? null;

        $view->with([
            'collections' => $product instanceof Product ? $this->links->forProduct($product, app()->getLocale()) : [],
        ]);
    }

    /**
     * @return Collection<int, Product>
     */
    private function recommended(Landing $landing, CatalogPage $page): Collection
    {
        $limit = max(0, (int) $this->config->get('webx-catalog-landings.recommended', 8));

        if ($limit === 0) {
            return new Collection;
        }

        /** @var Collection<int, Product> $products */
        $products = $landing->recommended()->visible($page->context->locale)->limit($limit)->get();

        if ($products->isNotEmpty()) {
            // The cards' own points — badges, the brand — are told about these products too.
            $this->parts->prepare(new Collection([...$page->products->items(), ...$products->all()]));
        }

        return $products;
    }
}
