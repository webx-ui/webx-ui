{{--
    A page the panel serves without its Vue app — the consent screen an agent sends a person to,
    the gate of a closed site. Plain Blade, and still the panel: its tokens inlined, the
    stylesheets this site built for the panel (its typeface), and the panel's components by their
    own classes — `wx-card`, `wx-alert`, `wx-checkbox`, `wx-button`, `wx-link` — styled by rules
    generated from the components themselves. A page writes the markup a component renders and
    adds only what is its own, under `@push('webx-page-styles')`.

    @extends('webx-admin::page'), with `@section('title')` and `@section('content')`.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title')</title>
    {{-- The theme the panel last used in this browser: same origin, same key as the shell. --}}
    <script>
        try {
            var webxTheme = localStorage.getItem('webx.theme');

            if (webxTheme === 'light' || webxTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', webxTheme);
            }
        } catch (error) {
            /* Private windows, blocked site data: the system's preference decides. */
        }
    </script>
    <style>{!! \WebxUi\Admin\Support\Tokens::css() !!}</style>
    @foreach (app(\WebxUi\Admin\Support\PanelStyles::class)->urls() as $stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endforeach
    <style>{!! \WebxUi\Admin\Support\Tokens::components() !!}</style>
    <style>
        :root { color-scheme: light dark; }
        :root[data-theme='light'] { color-scheme: light; }
        :root[data-theme='dark'] { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: start center;
            padding: var(--wx-space-24) var(--wx-space-16) var(--wx-space-48);
            background: var(--wx-bg-body); color: var(--wx-text-default);
            font-family: var(--wx-font-family-sans); font-size: var(--wx-font-size-sm);
            line-height: var(--wx-font-line-height-normal);
            -webkit-font-smoothing: antialiased;
        }
        .wx-page { width: 100%; max-width: 30rem; }
        .wx-page p { margin: 0; }
        .wx-page__brand { display: flex; justify-content: center; margin: var(--wx-space-16) 0 var(--wx-space-24); }
        .wx-page__brand img { max-width: 200px; max-height: 56px; }
        .wx-page__brand span { color: var(--wx-text-strong); font-size: var(--wx-font-size-xl); font-weight: var(--wx-font-weight-semibold); }
        .wx-page__stack { display: flex; flex-direction: column; gap: var(--wx-space-16); }
        .wx-page__title {
            margin: 0; color: var(--wx-text-strong); font-size: var(--wx-font-size-xl);
            font-weight: var(--wx-font-weight-semibold); line-height: var(--wx-font-line-height-tight);
            overflow-wrap: anywhere;
        }
        .wx-page__muted { color: var(--wx-text-muted); }
        .wx-page__buttons { display: flex; flex-wrap: wrap; gap: var(--wx-space-8); }
        .wx-page__buttons form { display: contents; }
        .wx-page__foot { margin-top: var(--wx-space-16); color: var(--wx-text-muted); font-size: var(--wx-font-size-xs); text-align: center; }
        .wx-page__foot p { margin: var(--wx-space-4) 0; }
        /* The component marks a ticked box with a class from its script; a form without one has :checked. */
        .wx-checkbox__native:checked + .wx-checkbox__box { border-color: var(--wx-color-primary); color: var(--wx-color-primary); }
        .wx-alert__icon svg { width: 1.15em; height: 1.15em; }
    </style>
    @stack('webx-page-styles')
</head>
<body>
    <main class="wx-page">
        @yield('content')
    </main>
</body>
</html>
