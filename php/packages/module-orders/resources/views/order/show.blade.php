{{--
    The buyer's page of an order behind its signed link (spec §13.1), in the site's layout with the
    handle `orders.order`, or in the package's own document while the site has none
    (`webx-orders.layout`). Nobody but the holder of the link reads it: `noindex`, `no-store`.

    Override: <layer>/views/vendor/webx-orders/order/show.blade.php.
    Classes: webx-order-page, __title.
--}}
@php($title = __('webx-orders::site.order.title', ['number' => $order['number']]))
<x-dynamic-component :component="config('webx-orders.layout') ?: 'webx-orders::standalone'" handle="orders.order">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>
    <div class="webx-order-page">
        <h1 class="webx-order-page__title">{{ $title }}</h1>
        @include('webx-orders::order.body')
    </div>
</x-dynamic-component>
