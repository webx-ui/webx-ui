{{--
    A collection on the site (§5.3): `<x-webx-catalog-collections::collection key="…" />`.

    The card is the catalogue's own, `webx-catalog::card`: a theme that overrides it changes it here
    too, and its points and its button come with it. The carousel is the widgets' slider, which
    loads its script when it renders. The link's <a> is LinkAttributes::of($link): href, the new tab
    and its rel, and `download` with the name a file of the library was uploaded under.

    Override: <layer>/views/vendor/webx-catalog-collections/collection.blade.php — keep
    `data-collection`. Classes: `### Classes` in AGENTS.md.
--}}
<section class="webx-catalog-collection webx-catalog-collection--{{ $view }}" data-collection="{{ $key }}">
    @if ($title !== null || $link !== null)
        <header class="webx-catalog-collection__head">
            @if ($title !== null)
                <h2 class="webx-catalog-collection__title">{{ $title }}</h2>
            @endif
            @if ($link !== null)
                <a class="webx-catalog-collection__all" {{ \WebxUi\Admin\Links\LinkAttributes::of($link) }}>{{ $link['text'] }}</a>
            @endif
        </header>
    @endif
    @if ($view === WebxUi\CatalogCollections\Models\ProductCollection::CAROUSEL)
        <x-webx-slider variant="cards" :label="$title">
            @foreach ($products as $product)
                <x-webx-slide class="webx-catalog-collection__item">@include('webx-catalog::card', ['product' => $product, 'verdict' => $verdicts[$product->id] ?? WebxUi\Catalog\Purchase\Verdict::yes()])</x-webx-slide>
            @endforeach
        </x-webx-slider>
    @else
        <ul class="webx-catalog-grid webx-catalog-collection__grid">
            @foreach ($products as $product)
                <li class="webx-catalog-collection__item">@include('webx-catalog::card', ['product' => $product, 'verdict' => $verdicts[$product->id] ?? WebxUi\Catalog\Purchase\Verdict::yes()])</li>
            @endforeach
        </ul>
    @endif
</section>
