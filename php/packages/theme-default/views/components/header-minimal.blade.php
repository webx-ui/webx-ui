{{--
    The header of `layout.minimal` (COMPOSITIONS §3.7): the brand and nothing to leave by — a
    checkout or a wizard keeps the visitor on its steps. The widget's own "Skip to content" is off,
    as in the full header: the layout prints its own first in <body>.
--}}
<x-webx-header class="site-header" mode="drill" :skip="false">
    <x-slot:brand>
        <a class="site-header__title" href="{{ url('/') }}">{{ config('app.name') }}</a>
    </x-slot:brand>
</x-webx-header>
