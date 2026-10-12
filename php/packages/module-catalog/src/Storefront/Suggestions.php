<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Localization\Locales;
use WebxUi\Routing\UrlNormaliser;

/**
 * The suggestions of the catalogue's search (WIDGETS §18, W6.5): `GET /{catalog.root}/suggest?q=`
 * answers the HTML of `webx-catalog::suggest` — the products found, how many there are in all and
 * a link to the search page — for `<x-webx-suggest>` to put under the field as it is. A theme
 * overrides the view; the widget needs only `data-webx-option` on what can be chosen.
 *
 * The same engine and the same rules as the search page: what is not on the site is not found,
 * a correction the engine made is said, and «see all» is the page those words lead to. Never
 * indexed, never cached: the answer is for one visitor's keystrokes.
 */
final class Suggestions
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Listing $listing,
        private readonly FilterUrls $urls,
        private readonly Locales $locales,
        private readonly ViewFactory $views,
        private readonly Config $config,
    ) {}

    public function answer(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));
        $headers = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, private'];

        if ($term === '') {
            return response('', 204, $headers);
        }

        $limit = max(1, min(20, (int) $this->config->get('webx-catalog.suggest.limit', 6)));
        $root = UrlNormaliser::key((string) $this->config->get('webx-catalog.root.prefix', 'catalog'));
        $context = new FilterContext(FilterContext::SEARCH, UrlNormaliser::join($root, 'search'), $this->locales->current(), []);

        $result = $this->catalog->engine()->search(new CatalogQuery(
            locale: $context->locale,
            context: FilterContext::SEARCH,
            search: $term,
            perPage: $limit,
            filter: $context,
        ));

        $html = $this->views->make('webx-catalog::suggest', [
            'term' => $term,
            'products' => $this->listing->products($result->ids),
            'total' => $result->total,
            'corrected' => $result->corrected,
            'all' => $this->urls->absolute($context->withQuery(['q' => $result->corrected ?? $term]), $context->path),
            'priced' => (bool) $this->config->get('webx-catalog.price.enabled', true),
        ])->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
    }
}
