{{--
    The checkout page (spec §20): the steps one under another, each its own form, and the column of
    the totals with the confirmation beside them. Without JavaScript every form is a POST that
    comes back here; nobody but the visitor reads it: `noindex`, `no-store`.

    `data-steps`: `open` — every form shows; `collapse` — a step done shows its summary and hides
    its form until «Change» (§20.1). The module marks the states, the theme picks the view.

    Override: <layer>/views/vendor/webx-orders/checkout/page.blade.php.
    Classes: webx-orders-checkout, __main, __aside.
--}}
@php($title = __('webx-orders::checkout.page.title'))
<x-dynamic-component :component="config('webx-orders.checkout.layout') ?: (config('webx-orders.layout') ?: 'webx-orders::standalone')" handle="checkout.page">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>
    <div class="webx-orders-checkout" data-steps="open">
        @include('webx-orders::checkout.parts.title')
        @include('webx-orders::checkout.parts.warnings')
        <div class="webx-orders-checkout__main">
            @include('webx-orders::checkout.parts.steps')
        </div>
        <aside class="webx-orders-checkout__aside">
            @include('webx-orders::checkout.summary', $summary)
        </aside>
    </div>
</x-dynamic-component>
