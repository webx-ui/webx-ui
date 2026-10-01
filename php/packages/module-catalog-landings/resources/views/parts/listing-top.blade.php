{{--
    Over a list: on a plain landing, the strip of recommended products in the cards of the grid;
    on a category, its collections. A site that wants its own publishes this view
    (`webx-catalog-landings-views`); the data comes with it.
--}}
@if ($recommended->isNotEmpty())
    <section class="webx-catalog-landings-recommended">
        <h2 class="webx-catalog-landings-recommended__title">{{ __('webx-catalog-landings::storefront.recommended') }}</h2>
        <ul class="webx-catalog-grid">
            @foreach ($recommended as $product)
                <li>@include('webx-catalog::card', ['product' => $product, 'verdict' => $verdicts[$product->id] ?? WebxUi\Catalog\Purchase\Verdict::yes()])</li>
            @endforeach
        </ul>
    </section>
@endif
@include('webx-catalog-landings::parts.links', ['links' => $collections, 'title' => __('webx-catalog-landings::storefront.collections'), 'modifier' => 'collections'])
