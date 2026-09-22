{{--
    The document every public page of this site is printed in.

    The deal with the panel's modules is two lines long: a `head` slot and the default slot for
    the content. A module's view — a page, an article, a rubric — asks for this component by
    name (`config('webx-pages.layout')` and friends, which `php artisan webx:panel --sync`
    points here) and stands inside it. Nothing else is agreed, and nothing else should be.

    Both `{{ $head }}` and `@stack('head')` are here, and both are needed: a slot is one place,
    and what a block type or a partial pushes cannot reach it. A layout without the stack loses
    metatags silently — the page still looks finished, and you find out from a search engine.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{--
        Eighty lines of stylesheet, and they are meant to be deleted.

        "Unstyled" and "broken" are not the same thing, so this is the least that keeps a page
        readable on a phone — and it is inline rather than built so that there is nothing to
        inherit from by accident. The day you start on the real design, delete this block and
        uncomment the @vite line below; `resources/css/app.css` is where it goes.
    --}}
    <style>
        :root {
            --ink: #1f2328;
            --muted: #5b6570;
            --line: #e3e6ea;
            --accent: #1c64d8;
            --page: 68rem;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font: 16px/1.6 system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--ink);
            background: #fff;
        }

        img, video { max-width: 100%; height: auto; display: block; }

        a { color: var(--accent); }

        h1, h2, h3 { line-height: 1.25; text-wrap: balance; }

        h1 { font-size: clamp(1.75rem, 1.2rem + 2.2vw, 2.75rem); }

        table { border-collapse: collapse; width: 100%; }

        th, td { border: 1px solid var(--line); padding: .5rem .75rem; text-align: left; }

        blockquote {
            margin: 1.5rem 0;
            padding-left: 1rem;
            border-left: 3px solid var(--line);
            color: var(--muted);
        }

        .wrap { width: 100%; max-width: var(--page); margin-inline: auto; padding-inline: 1rem; }

        .site-header, .site-footer { border-block: 1px solid var(--line); }

        .site-header { border-top: 0; }

        .site-header .wrap, .site-footer .wrap {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem 1.5rem;
            padding-block: 1rem;
        }

        .site-header a { color: inherit; text-decoration: none; }

        .site-header nav { display: flex; flex-wrap: wrap; gap: 1rem; }

        .site-header nav a:hover { color: var(--accent); }

        .site-title { font-weight: 600; margin-right: auto; }

        .site-footer { margin-top: 4rem; color: var(--muted); font-size: .875rem; }

        main { padding-block: 2rem; }

        main > * { width: 100%; max-width: var(--page); margin-inline: auto; padding-inline: 1rem; }
    </style>

    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}

    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
    <x-header />

    <main>{{ $slot }}</main>

    <x-footer />

    @stack('scripts')
</body>
</html>
