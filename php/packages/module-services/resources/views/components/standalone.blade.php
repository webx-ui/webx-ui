{{--
    The document a services page is printed in when the site has no layout of its own.

    Kept as a component so that the three views can ask the site where to stand instead:
    `webx-services.layout` names the site's layout and this one is what answers until it does. A
    fresh installation therefore still serves a whole document rather than a fragment nobody
    wrapped.

    The deal is the same for every layout, the site's included: a `head` slot, and the default
    slot for the content. Both places for the head, because a slot is one place and a stack is
    as many as needed — what a block type or a partial pushes cannot reach a slot.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('webx-services::partials.base')
    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
{{ $slot }}
@stack('scripts')
</body>
</html>
