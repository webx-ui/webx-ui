{{--
    What the buyer may do on the page of an order that awaits an online payment (spec §8.4): «Pay»
    and «Choose another method», or, while a payment is being checked, the way to its page — never
    a second «Pay»: that is how a payment gets made twice.

    $prompt — `WebxUi\Payments\Online\PayPage::prompt()`.

    Override: <layer>/views/vendor/webx-payments/pay/prompt.blade.php.
    Classes: webx-payments-prompt, __message, __form, __button, __link.
--}}
<div class="webx-payments-prompt">
    @if ($prompt['checking'])
        <p class="webx-payments-prompt__message" role="status">{{ __('webx-payments::buyer.pay.checking') }}</p>
        <p><a class="webx-payments-prompt__link" href="{{ $prompt['page'] }}">{{ __('webx-payments::buyer.pay.check_again') }}</a></p>
    @else
        @if ($prompt['attempts'])
            <form class="webx-payments-prompt__form" method="post" action="{{ $prompt['attempts'] }}">
                @csrf
                <button class="webx-payments-prompt__button" type="submit">{{ __('webx-payments::buyer.status.pay') }}</button>
            </form>
        @endif
        @include('webx-payments::pay.others', ['others' => $prompt['others'], 'switch' => $prompt['switch']])
    @endif
</div>
