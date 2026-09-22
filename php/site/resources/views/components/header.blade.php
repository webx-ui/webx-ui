@php
    /**
     * The menu of a site on its first day: the published pages one level under the home page,
     * with their addresses read from the registry rather than built here.
     *
     * Written to be rewritten. A real menu is almost never the page tree — it has its own
     * order, external links, and entries that are not pages at all — so this is an example
     * rather than an API, and there is deliberately nothing in the packages to inherit from.
     *
     * Guarded, because a site is free not to install the pages module at all.
     */
    $home = class_exists(\WebxUi\Pages\Models\Page::class)
        ? \WebxUi\Pages\Models\Page::query()->roots()->first()
        : null;

    $menu = $home === null
        ? collect()
        : $home->children()->whereNotNull('published_at')->ordered()->get();
@endphp

<header class="site-header">
    <div class="wrap">
        <a class="site-title" href="{{ url('/') }}">{{ config('app.name') }}</a>

        @if ($menu->isNotEmpty())
            <nav>
                @foreach ($menu as $page)
                    <a href="{{ $page->url() }}">{{ $page->title }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</header>
