{{--
    What stands in the point `catalog.card.meta` while no site has customised it as a component:
    every satellite's part registered for it, in the order the modules booted (StorefrontParts).
--}}
{!! app(WebxUi\Catalog\Storefront\StorefrontParts::class)->render('catalog.card.meta', array_filter(['product' => $product ?? null, 'verdict' => $verdict ?? null], static fn ($value): bool => $value !== null)) !!}
