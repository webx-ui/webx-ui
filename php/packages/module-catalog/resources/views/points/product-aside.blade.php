{{--
    What stands in the point `catalog.product.aside` while no site has customised it as a component:
    every satellite's part registered for it, in the order the modules booted (StorefrontParts).
--}}
{!! app(WebxUi\Catalog\Storefront\StorefrontParts::class)->render('catalog.product.aside', array_filter(['product' => $product ?? null, 'verdict' => $verdict ?? null], static fn ($value): bool => $value !== null)) !!}
