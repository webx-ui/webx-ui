{{--
    The search (§4): `/{catalog.root}/search?q=`, always `noindex`, with the filter over what was
    found. `$page` is null until something is asked.
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
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
            <form method="get" action="{{ route('webx.catalog.search') }}" role="search">
                <input type="search" name="q" value="{{ $term }}" aria-label="{{ __('webx-catalog::storefront.search') }}" placeholder="{{ __('webx-catalog::storefront.search-placeholder') }}">
                <button type="submit">{{ __('webx-catalog::storefront.search-submit') }}</button>
            </form>
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
