{{--
    Under a landing's list: the other landings of its base, and the same set on other categories.
--}}
@include('webx-catalog-landings::parts.links', ['links' => $siblings, 'title' => __('webx-catalog-landings::storefront.siblings'), 'modifier' => 'siblings'])
@include('webx-catalog-landings::parts.links', ['links' => $elsewhere, 'title' => __('webx-catalog-landings::storefront.elsewhere'), 'modifier' => 'elsewhere'])
