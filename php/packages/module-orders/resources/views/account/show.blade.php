{{--
    One order in «My orders» (spec §13.2): what the signed page shows, inside the account's frame,
    with the way back to the list.

    Override: <layer>/views/vendor/webx-orders/account/show.blade.php.
    Classes: webx-account-orders__back.
--}}
<x-webx-users::account :title="__('webx-orders::site.order.title', ['number' => $order['number']])">
    <p class="webx-account-orders__back"><a href="{{ $back }}">{{ __('webx-orders::site.account.back') }}</a></p>
    @include('webx-orders::order.body')
</x-webx-users::account>
