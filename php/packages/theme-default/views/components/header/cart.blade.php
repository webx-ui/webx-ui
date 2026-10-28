{{--
    The cart's trigger and panel (module-cart §9.1) among the actions, when the cart is installed:
    the mode is the cart's setting (`webx-cart.widget.mode`). A dynamic component, because a tag
    is looked up when the view compiles, and this view compiles on sites without the cart too.
--}}
@if (app()->bound(\WebxUi\Cart\CartHandle::class))
    <x-dynamic-component component="webx-cart" />
@endif
