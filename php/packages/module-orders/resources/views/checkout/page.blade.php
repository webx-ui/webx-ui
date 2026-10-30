{{--
    The checkout page (spec §20, §27): the steps one under another, each its own form, and the
    column of the totals with the confirmation beside them. Without JavaScript every form is a POST
    that comes back here; nobody but the visitor reads it: `noindex`, `no-store`.

    The layout is `layout.minimal` of the theme (a header of the brand, no footer, §27.1) with the
    handle `checkout.page`, or what `webx-orders.checkout.layout` names (`CheckoutLayout`).

    Override: <layer>/views/vendor/webx-orders/checkout/page.blade.php.
--}}
@php($title = __('webx-orders::checkout.page.title'))
<x-dynamic-component :component="\WebxUi\Orders\Checkout\CheckoutLayout::name()" handle="checkout.page">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>

    {{-- The page itself is the composition checkout.page (§27.1): a theme moves the columns, fills
         checkout.trust and picks `data-steps` in compositions/checkout.page/default.json instead
         of copying this file. The leaves get $steps, $warnings, $summary, $summaryUrl and $parts
         from here — its `vars`. --}}
    @include(composition_view('checkout.page'))

    @push('scripts')
        <script type="module" src="{{ \WebxUi\Orders\Checkout\Http\ScriptController::url() }}"></script>
    @endpush
</x-dynamic-component>
