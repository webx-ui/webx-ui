{{--
    The document a page of the module is printed in when the site has no layout of its own
    (`webx-users.layout` empty): a whole page rather than a fragment nobody wrapped. The deal is
    every layout's — a `head` slot, the default slot for the content. `@webxWidgets` puts the
    script of the forms on it: without a theme nothing else does.
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
@webxWidgets
</body>
</html>
