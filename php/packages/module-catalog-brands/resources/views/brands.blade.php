{{--
    The list of brands, `/brands/`: every published brand with a page in this language, in the
    order of the list, each with its logo. `$brands` is a collection of Brand models.

    Overridden by the site in `resources/views/vendor/webx-catalog-brands/brands.blade.php`.
--}}
@php($heading = __('webx-catalog-brands::module.title'))
{{-- Asked here only for the <h1>: a rule for the address may name a heading of its own. --}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl()))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        {{-- No entity: the list is a route, not a record. Where no rule names a title, its own
             word does, through the title template like any other. --}}
        @webxSeo(fallback: ['title' => $heading])
    </x-slot:head>

    <article class="webx-catalog-brands">
        <header>
            <h1>{{ $meta->h1 ?? $heading }}</h1>
        </header>

        @if ($brands->isEmpty())
            <p>{{ __('webx-catalog-brands::storefront.empty') }}</p>
        @else
            <ul class="webx-catalog-brands__list">
                @foreach ($brands as $brand)
                    <li class="webx-catalog-brands__item">
                        <a href="{{ $brand->pageUrl($locale) }}">
                            @if ($logo = $brand->logoUrl())
                                <img src="{{ $logo }}" alt="" loading="lazy">
                            @endif
                            <span>{{ $brand->displayName($locale) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </article>
</x-dynamic-component>
