<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Localization\Locales;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Rendering\Seo;

/**
 * The three pages of the storefront's list (§10): a category, the root of the catalogue, and the
 * search — one path through the filter, the engine and the template, each with its own context.
 *
 * Every page answers at exactly one spelling. The tail is read, the page is built, and the address
 * the page would give itself is compared with the one it was asked for: another order of values,
 * a subcategory chosen as a filter, a set a landing has taken over — 301 to the one spelling
 * (§8.1 of the architecture, decision 26). The comparison is made after the page is built because
 * the page's own address is built in the same pass as the links of its filter — the rewriters are
 * prepared once (§7.7).
 */
final class Storefront
{
    /** The query the range form without a script sends: `?range[price][from]=100&range[price][to]=500`. */
    public const RANGE = 'range';

    public function __construct(
        private readonly Listing $listing,
        private readonly FilterUrls $urls,
        private readonly CategoryFacets $categoryFacets,
        private readonly Facets $facets,
        private readonly Locales $locales,
        private readonly ViewFactory $views,
        private readonly Seo $seo,
        private readonly Config $config,
    ) {}

    public function category(Request $request, Category $category, string $tail): Response
    {
        $locale = $this->locales->current();
        $context = new FilterContext(
            FilterContext::CATEGORY,
            $category->routeCanonical($locale)->path ?? $category->routePath($locale),
            $locale,
            $this->categoryFacets->visible($category),
            $category,
        );

        return $this->listing($request, $context, $tail, 'webx-catalog::category', [
            CategoryFacet::KEY => FacetValue::of([(string) $category->id]),
        ], (int) $category->id);
    }

    public function root(Request $request, string $tail): Response
    {
        $context = new FilterContext(FilterContext::ROOT, $this->rootPath(), $this->locales->current(), $this->facets->all());

        return $this->listing($request, $context, $tail, 'webx-catalog::root', [], null, [
            'categories' => Category::query()->visible()->whereNull('parent_id')->orderBy('lft')->get(),
        ]);
    }

    public function search(Request $request, string $tail): Response
    {
        $term = trim((string) $request->query('q', ''));
        $typed = $request->query('typed') === '1';
        $context = new FilterContext(
            FilterContext::SEARCH,
            UrlNormaliser::join($this->rootPath(), 'search'),
            $this->locales->current(),
            $this->facets->all(),
        );

        $response = $term === ''
            ? response($this->views->make('webx-catalog::search', ['page' => null, 'term' => ''])->render())
            : $this->listing($request, $context, $tail, 'webx-catalog::search', [], null, ['term' => $term], $term, $typed);

        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        return $response;
    }

    /** The root's path, as the registry would spell it: `catalog`. */
    public function rootPath(): string
    {
        return UrlNormaliser::key((string) $this->config->get('webx-catalog.root.prefix', 'catalog'));
    }

    /**
     * A page of the list at one spelling: the tail read, the page built, and a 301 when the address
     * asked for is not the one the page gives itself. Public for a satellite whose entity has a page
     * of the catalogue narrowed to it — a brand — which brings its own context, scope and view.
     *
     * A landing brings a base too (§10.1 of the landings spec): its set, which the page starts from
     * and the tail adds to. The address the page was asked at is then the base's own — the landing
     * the rewriters name for it — and the tail after it.
     *
     * @param  string  $view  the full name: `webx-catalog::category`, `webx-catalog-brands::brand`
     * @param  array<string, FacetValue>  $scope
     * @param  array<string, mixed>  $data
     * @param  bool  $asTyped  a search as typed, without the engine's correction
     * @param  FilterState|null  $base  the state before the tail; null is nothing
     */
    public function listing(
        Request $request,
        FilterContext $context,
        string $tail,
        string $view,
        array $scope,
        ?int $contextId,
        array $data = [],
        ?string $search = null,
        bool $asTyped = false,
        ?FilterState $base = null,
    ): Response {
        $base ??= FilterState::empty();
        $chosen = $this->urls->parse($context, $tail) ?? throw new NotFoundHttpException;
        $state = $base->merge($chosen);

        // The form a range is typed into works without a script: it sends numbers in the query,
        // and they become a segment of the address.
        $ranged = $this->ranges($request, $context, $state);

        if ($ranged !== null) {
            return new RedirectResponse($this->urls->url($context->withQuery($this->kept($request)), $ranged), 302);
        }

        $page = $this->listing->page($context, $state, $request, $scope, $search, $contextId, $asTyped, $base);
        $card = $search === null ? null : $this->cardOf($page, $request);

        if ($card !== null) {
            return new RedirectResponse($card, 302);
        }

        $asked = $base->isEmpty() ? $context->path : $this->urls->path($context, $base);

        if ($page->path !== trim($asked.'/'.trim($tail, '/'), '/')) {
            $query = (string) $request->getQueryString();

            return new RedirectResponse($this->urls->absolute($context->withQuery([]), $page->path).($query === '' ? '' : '?'.$query), 301);
        }

        $this->pushItemList($page);

        return response($this->views->make($view, [...$data, 'page' => $page, 'category' => $page->category()])->render());
    }

    /**
     * The card a search goes straight to (decision 21 of the Manticore spec): the search is the
     * code of one product on the site, and nothing is chosen around it. Null when it is not, or
     * when two products share the code. 302, not 301: tomorrow the code may be another's.
     */
    private function cardOf(CatalogPage $page, Request $request): ?string
    {
        if (count($page->result->exact) !== 1 || $page->state->size() !== 0 || $request->query('page') !== null) {
            return null;
        }

        $product = Product::query()->find($page->result->exact[0]);

        return $product instanceof Product ? $product->url($page->context->locale) : null;
    }

    /**
     * The state with the typed ranges in it, or null when the query has none.
     */
    private function ranges(Request $request, FilterContext $context, FilterState $state): ?FilterState
    {
        $typed = $request->query(self::RANGE);

        if (! is_array($typed)) {
            return null;
        }

        foreach ($typed as $code => $ends) {
            $facet = is_string($code) ? $context->facetByCode($code) : null;

            if ($facet === null || $facet->kind() !== FacetKind::Range || ! is_array($ends)) {
                continue;
            }

            $from = is_numeric($ends['from'] ?? null) ? max(0.0, (float) $ends['from']) : null;
            $to = is_numeric($ends['to'] ?? null) ? max(0.0, (float) $ends['to']) : null;

            $state = $from === null && $to === null
                ? $state->without($facet->key())
                : $state->with($facet->key(), FacetValue::range($from, $to));
        }

        return $state;
    }

    /**
     * What a redirect keeps of the query: the sort and the search, not the form's numbers.
     *
     * @return array<string, string>
     */
    private function kept(Request $request): array
    {
        return array_filter([
            'q' => is_string($request->query('q')) ? (string) $request->query('q') : '',
            'typed' => $request->query('typed') === '1' ? '1' : '',
            'sort' => is_string($request->query('sort')) ? (string) $request->query('sort') : '',
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * `ItemList` of the page's products, positions running on from the pages before (§10.3) —
     * what is on this page rather than what the category is, so it is the handler's to say.
     */
    private function pushItemList(CatalogPage $page): void
    {
        if ($page->products->isEmpty()) {
            return;
        }

        $first = (int) $page->products->firstItem();

        $this->seo->push([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => collect($page->products->items())->values()->map(static fn (Product $product, int $i): array => [
                '@type' => 'ListItem',
                'position' => $first + $i,
                'url' => $product->listedUrl(),
            ])->all(),
        ]);
    }
}
