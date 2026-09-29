<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Routing;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Routing\MissHandler;

/**
 * The catalogue's addresses the registry holds no row for (§4, §5, §6.3).
 *
 * - `/{anything}-{id}` of a live product is that product under a spelling that is not its own —
 *   an old slug, a typo, a link cut short: 301 to the canonical one. So a rename needs no alias to
 *   keep old links alive, and the registry does not grow a row per rename of every product.
 * - The same of a deleted product: 301 to its main category if that is on the site, else to the
 *   first visible additional one; nowhere to go — 410. A deleted product is gone on purpose, and
 *   "gone" is the answer a search engine drops it on fastest.
 * - The slug of a deleted category, with or without a filter behind it: 410.
 *
 * Only ever asked about addresses nothing else claimed, so a page whose slug happens to end in
 * `-12` is its own page and never reaches here.
 */
final class CatalogMisses implements MissHandler
{
    public function miss(Request $request, string $locale, string $path): ?Response
    {
        if ($path === '') {
            return null;
        }

        [$first] = explode('/', $path, 2);

        if ($first === $path && preg_match('/^(.+)-(\d+)$/u', $path, $matches) === 1) {
            $answer = $this->product((int) $matches[2], $locale, $path, $request);

            if ($answer !== null) {
                return $answer;
            }
        }

        $deleted = Category::onlyTrashed()->whereTranslation('slug', $first, $locale)->exists();

        if ($deleted) {
            throw new GoneHttpException;
        }

        return null;
    }

    private function product(int $id, string $locale, string $path, Request $request): ?Response
    {
        $product = Product::withTrashed()->with('category')->find($id);

        if (! $product instanceof Product) {
            return null;
        }

        if ($product->trashed()) {
            $category = $this->landing($product, $locale);

            if ($category === null) {
                throw new GoneHttpException;
            }

            return $this->redirect($request, $category->url($locale));
        }

        // A live product without an address in this language has no spelling to send anybody
        // to: that is a 404, and it is the registry's to give.
        if (! $product->hasUrlIn($locale)) {
            return null;
        }

        // Its own spelling with no row behind it is a registry out of step, not a misspelling:
        // redirecting it to itself would be a loop. `webx:routes:check` is where that shows.
        $canonical = $product->routeCanonical($locale);

        if ($canonical === null || $canonical->path === $path) {
            return null;
        }

        return $this->redirect($request, $product->url($locale));
    }

    /** Where a deleted product's visitors go: the main category, or any other that is on the site. */
    private function landing(Product $product, string $locale): ?Category
    {
        $main = $product->category;

        if ($main instanceof Category && $main->isVisible($locale) && $main->hasUrlIn($locale)) {
            return $main;
        }

        foreach ($product->categories()->visible()->orderBy('lft')->get() as $category) {
            if ($category->hasUrlIn($locale)) {
                return $category;
            }
        }

        return null;
    }

    /** 301, and the query survives it: it is somebody's `?utm_…`, not decoration. */
    private function redirect(Request $request, string $url): RedirectResponse
    {
        $query = (string) $request->getQueryString();

        return new RedirectResponse($url.($query === '' ? '' : '?'.$query), 301);
    }
}
