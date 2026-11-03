{{--
    The step to a gateway that takes a form (spec §8.2): the gateway's signed fields, posted by the
    buyer's browser at once; without JavaScript — one button. No card field is ever here: the
    `Handoff` refused it before (decision 3). Not themed: the buyer sees it for a moment.

    Override: <layer>/views/vendor/webx-payments/pay/handoff.blade.php.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('webx-payments::buyer.pay.redirecting', [], $locale) }}</title>
</head>
<body>
<form class="webx-payments-handoff" method="post" action="{{ $url }}" id="webx-payments-handoff">
    @foreach ($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <p>{{ __('webx-payments::buyer.pay.redirecting', [], $locale) }}</p>
    <button type="submit">{{ __('webx-payments::buyer.pay.go', [], $locale) }}</button>
</form>
<script>document.getElementById('webx-payments-handoff').submit();</script>
</body>
</html>
