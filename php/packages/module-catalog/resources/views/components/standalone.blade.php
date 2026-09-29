{{--
    The document a catalogue page is printed in when the site has no layout of its own
    (`webx-catalog.layout`). Two places for the head: the `head` slot, and a stack for whatever a
    partial pushes, which a slot cannot receive.
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
