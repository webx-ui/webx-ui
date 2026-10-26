{{--
    «Calculate in the configurator» on the page of a product (§22.3): the point
    `catalog.product.aside` of the catalogue, filled from the configurator's side. A link to the page
    the configurator stands on, with the product's preset in the address — nothing when the product
    names none, or its configurator or its page is not on the site.

    Override: <layer>/views/vendor/webx-configurator/open.blade.php. Classes: `### Classes` in AGENTS.md.
--}}
@php($open = isset($product) ? ($configurators[$product->id] ?? null) : null)
@if ($open)
    <a class="webx-configurator-open" href="{{ $open['url'] }}" data-webx-configurator-open="{{ $open['key'] }}">{{ __('webx-configurator::site.open') }}</a>
@endif
