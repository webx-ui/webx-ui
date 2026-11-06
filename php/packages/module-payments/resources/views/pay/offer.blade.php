{{--
    «Pay online now» on the page of an order on receipt (spec §8.5): the list of the page of payment,
    standing open. The order awaits nothing, so its status stays the shop's.

    $offer — `WebxUi\Payments\Online\PayPage::offer()`.

    Override: <layer>/views/vendor/webx-payments/pay/offer.blade.php.
    Classes: webx-payments-prompt, and those of `pay.others`.
--}}
<div class="webx-payments-prompt">
    @include('webx-payments::pay.others', [
        'others' => $offer['others'],
        'switch' => $offer['switch'],
        'toggle' => __('webx-payments::buyer.pay.online_now'),
    ])
</div>
