{{--
    «Choose another method» (spec §8.5, groups spec §6.8): one button that opens every method this
    order may take, on the page of payment and the order's page — the list of the checkout's step.
    A choice here leaves for the gateway, so it is not written at once: «Continue» posts the list,
    its `group` and `method` read by §6.4. It works without JavaScript: `details`.

    $others — `PayPage`'s list, an `option` in each; $switch — the signed address the choice is
    posted to; $toggle — the words of a button that stands open, by default it is «Choose another
    method», closed.

    Override: <layer>/views/vendor/webx-payments/pay/others.blade.php.
    Classes: webx-payments-others, __toggle, __list, __continue, and those of the list of methods.
--}}
@if ($others !== [] && $switch)
    @php($toggle ??= null)
    <details class="webx-payments-others" @if ($toggle !== null) open @endif>
        <summary class="webx-payments-others__toggle">{{ $toggle ?? __('webx-payments::buyer.pay.other_method') }}</summary>
        <div class="webx-payments webx-payments-others__list">
            <form class="webx-payments__form" method="post" action="{{ $switch }}">
                @csrf
                <x-webx-payments::methods :groups="app(\WebxUi\Payments\Methods::class)->grouped(array_column($others, 'option'))" :legend="__('webx-payments::buyer.step.methods')" />
                <button class="webx-payments-others__continue" type="submit">{{ __('webx-payments::buyer.pay.continue') }}</button>
            </form>
        </div>
    </details>
@endif
