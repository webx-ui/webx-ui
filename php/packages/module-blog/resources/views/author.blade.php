<x-dynamic-component :component="config('webx-blog.layout') ?: 'webx-blog::standalone'">
    <x-slot:head>
        {{-- The SEO card, then the profile's own name, bio and photo where the card is empty; out of
             the index while nothing of theirs is published (§19.1, decision 7). --}}
        @webxSeo($author)
        <link rel="alternate" type="application/rss+xml" title="{{ trans('webx-blog::blog.rss') }}" href="{{ route('webx.blog.rss') }}">
    </x-slot:head>

    {{-- From the same list as the BreadcrumbList in the <head>. --}}
    @if (config('webx-blog.breadcrumbs', true))
        <x-webx-seo::breadcrumbs :for="$author" />
    @endif

    <header class="webx-blog-header webx-blog-author">
        @if ($photo = $author->photoUrl())
            <img class="webx-blog-author__photo" src="{{ $photo }}" alt="{{ $author->name() }}">
        @endif
        <h1 class="webx-blog-header__title">{{ $author->name() }}</h1>
        @if ($role = $author->getTranslation('role', app()->getLocale()))
            <p class="webx-blog-author__role">{{ $role }}</p>
        @endif
        {{-- Printed raw: what is stored has already been through the allowlist of its field type. --}}
        @if (($bio = $author->bioHtml()) !== '')
            <div class="webx-blog-header__lead">{!! $bio !!}</div>
        @endif
        {{-- Their networks (§19.11): the widgets' icons where the site registered them, plain links where
             not. The tag goes through Blade::render in the PHP block: a tag nobody registered would not
             compile, and a template compiles whole. --}}
        @php
            $socials = $author->socialChannels();
            $icons = $socials !== [] && array_key_exists('webx-socials', \Illuminate\Support\Facades\Blade::getClassComponentAliases())
                ? \Illuminate\Support\Facades\Blade::render('<x-webx-socials class="webx-blog-author__socials" :socials="$socials" />', ['socials' => $socials])
                : null;
        @endphp
        @if ($icons !== null)
            {!! $icons !!}
        @elseif ($socials !== [])
            <ul class="webx-blog-author__socials">
                @foreach ($socials as $social)
                    <li><a href="{{ $social->url }}" rel="me noopener" target="_blank">{{ ucfirst($social->kind) }}</a></li>
                @endforeach
            </ul>
        @endif
    </header>

    @if ($articles->isEmpty())
        <p class="webx-blog-empty">{{ trans('webx-blog::blog.empty') }}</p>
    @else
        @include('webx-blog::partials.cards')
        @include('webx-blog::partials.pages')
    @endif
</x-dynamic-component>
