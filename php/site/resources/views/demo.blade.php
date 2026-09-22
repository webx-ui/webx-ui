@php
    /**
     * Proof that the modules are in, for a site that has no page of its own yet.
     *
     * `php artisan webx:setup` routes this at `/` only when the pages module is not installed —
     * with it, the front page is a row in the page tree and a route here would take the address
     * away from it for good. Either way this view is meant to be deleted: it is the first thing
     * the README asks for.
     */
    $modules = app(\WebxUi\Admin\ModuleRegistry::class)->all();
@endphp

<x-layout>
    <x-slot:head>
        <title>{{ config('app.name') }}</title>
        <meta name="robots" content="noindex">
    </x-slot:head>

    <h1>{{ config('app.name') }} is up.</h1>

    <p>
        The panel is at <a href="{{ url(config('webx-admin.path')) }}">/{{ ltrim((string) config('webx-admin.path'), '/') }}</a>.
        This page is a placeholder — delete <code>resources/views/demo.blade.php</code> and the
        route in <code>routes/web.php</code> once the site has a front page of its own.
    </p>

    @if ($modules !== [])
        <h2>Installed modules</h2>

        <ul>
            @foreach ($modules as $module)
                <li>{{ __($module->title()) }} <code>{{ $module->id() }}</code></li>
            @endforeach
        </ul>
    @endif
</x-layout>
