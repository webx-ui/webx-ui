{{--
    The document the editor's stage is printed in when the site names no layout of its own —
    `webx-blocks.layout` names it, and this answers until it does. The same two slots as every
    layout: `head`, and the default one for the content.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
{{ $slot }}
@stack('scripts')
</body>
</html>
