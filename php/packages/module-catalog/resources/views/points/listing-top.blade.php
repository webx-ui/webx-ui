{{--
    What stands in the point `catalog.listing.top` while no site has customised it as a component:
    every satellite's part registered for it, in the order the modules booted (StorefrontParts).
--}}
{!! app(WebxUi\Catalog\Storefront\StorefrontParts::class)->render('catalog.listing.top', ['page' => $page]) !!}
