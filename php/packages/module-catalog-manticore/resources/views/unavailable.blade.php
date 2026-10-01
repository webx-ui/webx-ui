{{--
    The catalogue while Manticore is down and the catalogue is too large for the database to stand
    in (decision 12 of the Manticore spec): the site's own layout — the header, the menu, the
    footer — around a few words, answered with 503 and `Retry-After`. A search engine waits a 503
    out; an empty category with 200 it would remember. A site words it in its dictionary or draws
    it its own way by overriding this view.
--}}
<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        <meta name="robots" content="noindex, follow">
        <title>{{ __('webx-catalog-manticore::storefront.unavailable-title') }}</title>
    </x-slot:head>

    <article class="webx-catalog-unavailable">
        <h1>{{ __('webx-catalog-manticore::storefront.unavailable-title') }}</h1>
        <p>{{ __('webx-catalog-manticore::storefront.unavailable-text') }}</p>
    </article>
</x-dynamic-component>
