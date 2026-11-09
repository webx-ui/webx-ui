<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as Products;
use Illuminate\View\Component;
use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Purchase\Purchasability;
use WebxUi\Catalog\Storefront\StorefrontParts;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Storefront\CollectionProducts;
use WebxUi\CatalogCollections\Storefront\LiveCollections;
use WebxUi\Localization\Locales;

/**
 * `<x-webx-catalog-collections::collection key="popular" />`: a collection on the site (§4.1), in a
 * file of the theme, a view of a module or the template of a block.
 *
 * `view` is `grid` or `carousel` over the collection's own; anything else is dropped. `limit` can
 * only lower the collection's limit. `title` is the heading: empty is the collection's title, `-`
 * is none.
 *
 * A key with nothing to show prints nothing, and neither does a collection left empty: a page with
 * one section missing is better than one that says «not found» to a customer (§4.4), and the panel
 * is where somebody finds out.
 */
final class Collection extends Component
{
    public function __construct(
        private readonly LiveCollections $live,
        private readonly CollectionProducts $contents,
        private readonly StorefrontParts $parts,
        private readonly Purchasability $purchasability,
        private readonly LinkUrls $links,
        private readonly Locales $locales,
        private readonly ?string $key = null,
        private readonly ?string $view = null,
        private readonly int|string|null $limit = null,
        private readonly ?string $title = null,
    ) {}

    public function render(): View|string
    {
        $collection = $this->collection();

        if ($collection === null) {
            return '';
        }

        // The ids are cached, so who may be seen is asked again here (§5.1): a product taken off
        // the site between two versions of the group never reaches a card.
        $ids = $this->contents->ids($collection, $this->lowered());
        $products = $ids === [] ? new Products : products()->only($ids)->models();

        if ($products->isEmpty()) {
            return '';
        }

        // Told to the card's points beside what a listing on the same page prepared.
        $this->parts->extend($products);

        return view('webx-catalog-collections::collection', [
            'key' => $collection->key,
            'view' => in_array($this->view, ProductCollection::VIEWS, true) ? $this->view : $collection->view,
            'title' => $this->heading($collection),
            'link' => $this->link($collection),
            'products' => $products,
            'verdicts' => $this->purchasability->forMany($products),
        ]);
    }

    /** The argument is an editor's input (§8): a key of any other shape asks nothing. */
    private function collection(): ?ProductCollection
    {
        $key = (string) $this->key;

        return preg_match(ProductCollection::KEY, $key) === 1 ? $this->live->find($key) : null;
    }

    /** Null is no limit of the place's own: not given, a block's empty field, or not a number. */
    private function lowered(): ?int
    {
        return is_numeric($this->limit) ? max(1, (int) $this->limit) : null;
    }

    private function heading(ProductCollection $collection): ?string
    {
        $title = trim((string) $this->title);

        if ($title === '-') {
            return null;
        }

        $title = $title !== '' ? $title : trim((string) $collection->getTranslation('title'));

        return $title === '' ? null : $title;
    }

    /**
     * «All products», only while it leads somewhere: a link to a draft or to something gone is not
     * printed (§5.3). The entity is looked up once here: `LinkType::resolve()` asks for it twice,
     * and on every page that prints the collection.
     *
     * `download` is the name a file of the library is saved under, for a file link that asks to be
     * downloaded — the key it is kept under is a hash; else null.
     *
     * @return array{url: string, text: string, new_tab: bool, rel: string|null, download: string|null}|null
     */
    private function link(ProductCollection $collection): ?array
    {
        if ($collection->link === null) {
            return null;
        }

        $locale = $this->locales->current();
        $link = Link::fromArray($collection->link);
        $candidate = $this->links->candidate($link, $locale);
        $url = $this->links->hrefWith($link, $candidate, $locale);

        if ($url === null || ($link->target === LinkTarget::Entity && $candidate?->available !== true)) {
            return null;
        }

        $text = trim((string) $collection->getTranslation('link_text'));

        return [
            'url' => $url,
            'text' => $text !== '' ? $text : (string) __('webx-catalog-collections::storefront.all'),
            'new_tab' => $link->newTab,
            'rel' => $link->relAttribute(),
            'download' => $link->download ? ($this->links->file($link)['name'] ?? null) : null,
        ];
    }
}
