<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title }}</title>

        {{-- The panel reads this before it draws anything. --}}
        <meta name="webx-manifest" content="{{ $manifestUrl }}">

        {{-- The admin application ships its own assets; publish this view to point at them. --}}
        @stack('webx-head')
    </head>
    <body>
        <div id="webx-app"></div>
        @stack('webx-body')
    </body>
</html>
