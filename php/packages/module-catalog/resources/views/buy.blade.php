{{--
    What can be done with a product, on its page and on a card (§5.3 of the collections' spec).
    `$verdict` decides: the cart's button, a plain one for the site to wire, «Ask the price» for a
    price on request, or the reason in place of a button — the status's own name for `stock`.

    `$block` is the BEM block the classes belong to, so a card and a page are styled apart.
    `$options` is the chosen variant a site's view composer gives; none in the core.

    The cart's button is a form that works without JavaScript, and a dynamic component, so a site
    without the cart compiles this view too. It sits outside the card's link: a form inside <a> is
    invalid markup.
--}}
@php($block = $block ?? 'webx-catalog-product')
@php($options = isset($options) && is_array($options) ? $options : [])
@if ($verdict->purchasable && WebxUi\Catalog\Cart\CartButton::shown())
    <x-dynamic-component component="webx-cart::add" type="catalog.product" :ref="$product->id" :options="$options" :class="$block.'__action'">{{ __('webx-catalog::storefront.buy') }}</x-dynamic-component>
@elseif ($verdict->purchasable)
    <button type="button" class="{{ $block }}__action" data-product="{{ $product->id }}">{{ __('webx-catalog::storefront.buy') }}</button>
@elseif ($verdict->code === WebxUi\Catalog\Purchase\CoreRules::PRICE_ON_REQUEST)
    <button type="button" class="{{ $block }}__action" data-product="{{ $product->id }}">{{ __('webx-catalog::storefront.ask-price') }}</button>
@else
    <p class="{{ $block }}__refusal">{{ $verdict->label }}</p>
@endif
