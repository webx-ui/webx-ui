<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title }}</title>

        {{-- The panel's own, under its prefix: the site's `/favicon.ico` is the site's. --}}
        <link rel="icon" type="image/png" sizes="96x96" href="{{ $iconBase }}favicon-96x96.png">
        <link rel="shortcut icon" href="{{ $iconBase }}favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ $iconBase }}apple-touch-icon.png">
        <link rel="manifest" href="{{ $iconBase }}site.webmanifest">

        {{-- The panel reads this before it draws anything. --}}
        <meta name="webx-manifest" content="{{ $manifestUrl }}">

        {{--
            The theme before the stylesheet, not after the bundle: the panel sets `data-theme`
            as it starts up, but that is one network round trip later, and a dark panel that
            begins white flashes on every full page load. Whatever this browser last used is
            good enough to paint with — the administrator's own record arrives with the session
            and corrects it if they have since changed their mind on another machine.
        --}}
        <script>
            try {
                var webxTheme = localStorage.getItem('webx.theme');

                if (webxTheme === 'light' || webxTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', webxTheme);
                }
            } catch (error) {
                /* Private windows, blocked site data: the panel picks it up either way. */
            }
        </script>

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
