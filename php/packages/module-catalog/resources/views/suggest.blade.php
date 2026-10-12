{{--
    The suggestions of the search (WIDGETS §18, W6.5): the HTML `<x-webx-suggest>` puts under the
    field as it is. `$products` — the first found, `$total` — how many in all, `$corrected` — the
    words the engine searched instead, or null; `$all` — the search page for them; `$priced`.

    The widget needs only `data-webx-option` on what can be chosen (a link is followed) and
    `data-webx-section` with `data-webx-heading` on a group; everything else here is the theme's to
    change. An answer without an option is «nothing found».
--}}
@if ($products->isEmpty())
    <p class="webx-catalog-suggest__nothing">{{ __('webx-catalog::storefront.suggest-nothing', ['q' => $term]) }}</p>
@else
    <div class="webx-catalog-suggest" data-webx-section="products">
        <p class="webx-catalog-suggest__heading" data-webx-heading>{{ __('webx-catalog::storefront.suggest-products', ['count' => $total]) }}</p>
        @if ($corrected !== null)
            <p class="webx-catalog-suggest__corrected">{{ __('webx-catalog::storefront.search-corrected', ['corrected' => $corrected]) }}</p>
        @endif
        @foreach ($products as $product)
            @php($image = $product->mainImage())
            <a class="webx-catalog-suggest__product" href="{{ $product->listedUrl() }}" data-webx-option>
                @if ($image !== null)
                    <img class="webx-catalog-suggest__picture" src="{{ $image->thumbUrl(96, 96) ?? $image->url() }}" alt="" width="48" height="48" loading="lazy">
                @endif
                <span class="webx-catalog-suggest__name">{{ $product->displayName() }}</span>
                @if ($priced)
                    <span class="webx-catalog-suggest__price">
                        @if ($product->price !== null)
                            @if ($product->old_price !== null)
                                <s>{{ $product->old_price }}</s>
                            @endif
                            <strong>{{ $product->price }}</strong>
                        @else
                            {{ __('webx-catalog::storefront.price-on-request') }}
                        @endif
                    </span>
                @endif
            </a>
        @endforeach
    </div>
    <a class="webx-catalog-suggest__all" href="{{ $all }}" data-webx-option>{{ __('webx-catalog::storefront.suggest-all', ['count' => $total]) }}</a>
@endif
