{{--
    Beside a product: the landings whose list holds it.
--}}
@include('webx-catalog-landings::parts.links', ['links' => $collections, 'title' => __('webx-catalog-landings::storefront.product'), 'modifier' => 'product'])
