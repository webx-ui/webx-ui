{{--
    The document an events page is printed in when the site has no layout of its own.

    A component so that the views can ask the site where to stand instead: `webx-events.layout`
    names the site's layout, and this one answers until it does. Two places for the head — a slot
    and a stack — because what a part or the list pushes cannot reach a slot.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('webx-events::partials.base')
    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
{{ $slot }}
@stack('scripts')
</body>
</html>
