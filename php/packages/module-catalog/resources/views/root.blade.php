{{--
    The root of the catalogue (decision 25 of the architecture): the top categories and a filter
    over the whole catalogue, where the category is a filter like any other. Only there when
    `webx-catalog.root.enabled` is on.
--}}
@php($seo = app(WebxUi\Seo\Rendering\Seo::class))
@php($meta = $seo->for($seo->currentUrl(), $page))

<x-dynamic-component :component="config('webx-catalog.layout') ?: 'webx-catalog::standalone'">
    <x-slot:head>
        @webxSeo($page)
        @if ($meta->title === null)
            <title>{{ $page->heading }}</title>
        @endif
    </x-slot:head>

    <article class="webx-catalog-root">
        <header>
            @include('webx-catalog::breadcrumbs', ['for' => $page])
            <h1>{{ $meta->h1 ?? $page->heading }}</h1>
        </header>

        @if ($page->isPlain() && $categories->isNotEmpty())
            <nav class="webx-catalog-root__categories">
                <ul>
                    @foreach ($categories as $top)
                        <li><a href="{{ $top->url() }}">{{ $top->displayName() }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="webx-catalog-listing">
            @include('webx-catalog::filter', ['page' => $page])

            <section>
                @include('webx-catalog::sort', ['page' => $page])
                @include('webx-catalog::grid', ['page' => $page])
                @include('webx-catalog::pagination', ['products' => $page->products])
            </section>
        </div>
    </article>
</x-dynamic-component>
