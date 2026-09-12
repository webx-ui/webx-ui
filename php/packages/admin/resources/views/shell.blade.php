<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title }}</title>

        {{-- The panel reads this before it draws anything. --}}
        <meta name="webx-manifest" content="{{ $manifestUrl }}">

        @foreach ($styles as $stylesheet)
            <link rel="stylesheet" href="{{ $stylesheet }}">
        @endforeach

        @foreach ($scripts as $script)
            <script type="module" src="{{ $script }}" defer></script>
        @endforeach

        {{-- For an application that builds the panel with Laravel's own Vite. --}}
        @if ($viteEntrypoints !== [])
            @vite($viteEntrypoints)
        @endif

        @stack('webx-head')
    </head>
    <body>
        <div id="webx-app"></div>
        @stack('webx-body')
    </body>
</html>
