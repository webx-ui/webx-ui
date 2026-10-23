{{--
    The search (§4): `/{catalog.root}/search?q=`, always `noindex`, with the filter over what was
    found. `$page` is null until something is asked.
--}}

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'" handle="catalog.search">
    <x-slot:head>
        @if ($page !== null)
            {{-- The page says `noindex` and its title itself (ListingSource). --}}
            @webxSeo($page)
        @else
            <meta name="robots" content="noindex, follow">
            <title>{{ __('webx-catalog::storefront.search') }}</title>
        @endif
    </x-slot:head>

    <article class="webx-catalog-search">
        <header>
            <h1>{{ $page?->heading ?? __('webx-catalog::storefront.search') }}</h1>
            {{-- Without JavaScript a GET search to this page; with it, suggestions while typing (WIDGETS W6.5). --}}
            <x-webx-suggest :action="$action ?? route('webx.catalog.search')" :source="$suggest ?? route('webx.catalog.suggest')" :value="$term"
                            class="webx-catalog-search__box" :label="__('webx-catalog::storefront.search')"
                            :placeholder="__('webx-catalog::storefront.search-placeholder')" :submit-label="__('webx-catalog::storefront.search-submit')" />
        </header>

        @if ($page?->result->corrected !== null)
            @include('webx-catalog::search-corrected', ['page' => $page])
        @endif

        @if ($page !== null)
            <div class="webx-catalog-listing">
                @include('webx-catalog::filter', ['page' => $page])

                <section>
                    @include('webx-catalog::sort', ['page' => $page])
                    @include('webx-catalog::grid', ['page' => $page])
                    @include('webx-catalog::pagination', ['products' => $page->products])
                </section>
            </div>
        @endif
    </article>
</x-dynamic-component>
