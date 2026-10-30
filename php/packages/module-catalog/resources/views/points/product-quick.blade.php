{{--
    What stands in the point `catalog.product.quick`: the orders' «Buy in one click» (§29 of the
    orders' spec) for this product, while `orders` is a module of the site. A dynamic component — a
    site without the orders compiles this view too; whether it shows (the checkout and
    `orders.checkout.quick.enabled` on) is the component's own answer. A theme takes the point
    over with `views/parts/catalog/product/quick.blade.php`: empty to remove the button, or this
    partial included elsewhere with `product` and `options`.
--}}
@if (WebxUi\Catalog\Orders\QuickButton::shown())
    <x-dynamic-component component="webx-orders::checkout.quick-button" type="catalog.product" :ref="$product->id" :options="$options ?? []" class="webx-catalog-product__action webx-catalog-product__quick" />
@endif
