{{--
    The document the buyer's page of an order is printed in when the site has no layout of its own
    (`webx-orders.layout` empty): a whole page rather than a fragment nobody wrapped. The deal is
    every layout's — a `head` slot and the default slot for the content.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: #1f2328; background: #fff; }
    </style>
    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
<main id="content">
{{ $slot }}
</main>
@stack('scripts')
</body>
</html>
