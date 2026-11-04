{{--
    «Choose another method» (spec §8.5): one button that opens every method this order may take, on
    the page of payment and the order's page. It works without JavaScript: `details`.

    $others — list of { key, title }; $switch — the signed address the choice is posted to.

    Override: <layer>/views/vendor/webx-payments/pay/others.blade.php.
    Classes: webx-payments-others, __toggle, __list, __form, __choice.
--}}
@if ($others !== [] && $switch)
    <details class="webx-payments-others">
        <summary class="webx-payments-others__toggle">{{ __('webx-payments::buyer.pay.other_method') }}</summary>
        <div class="webx-payments-others__list">
            @foreach ($others as $option)
                <form class="webx-payments-others__form" method="post" action="{{ $switch }}">
                    @csrf
                    <input type="hidden" name="method" value="{{ $option['key'] }}">
                    <button class="webx-payments-others__choice" type="submit">{{ $option['title'] }}</button>
                </form>
            @endforeach
        </div>
    </details>
@endif
