<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        {{-- No entity: the feed is a route, not a record (§2.11). A rule for its address and the
             site's defaults speak for it; where neither names a title, the feed's own word does,
             through the title template like any other. --}}
        @webxSeo(fallback: ['title' => trans('webx-blog::blog.title')])
        <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
    </x-slot:head>

    <header class="webx-blog-header">
        <h1 class="webx-blog-header__title">{{ trans('webx-blog::blog.title') }}</h1>
        @if ($rubrics->isNotEmpty())
            <nav class="webx-blog-rubrics">
                <ul class="webx-blog-rubrics__list">
                    @foreach ($rubrics as $rubric)
                        <li class="webx-blog-rubrics__item"><a class="webx-blog-rubrics__link" href="{{ $rubric->url() }}">{{ $rubric->title }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </header>

    @if ($articles->isEmpty())
        <p class="webx-blog-empty">{{ trans('webx-blog::blog.empty') }}</p>
    @else
        @include('webx-blog::partials.cards')
        @include('webx-blog::partials.pages')
    @endif
</x-dynamic-component>
