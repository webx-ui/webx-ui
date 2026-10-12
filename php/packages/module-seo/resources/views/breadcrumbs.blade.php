{{-- The trail a reader sees, from the same list the BreadcrumbList in the <head> is printed
     from. Publish and restyle it as you like (`webx-seo-views`), but keep `$crumbs`: a second
     trail worked out in the view is the one that stops matching. The classes are the module's
     contract with a theme (AGENTS.md, «Classes»): restyling by them needs no published view. --}}
<nav class="webx-breadcrumbs" aria-label="{{ trans('webx-seo::site.breadcrumbs', [], $locale) }}">
    <ol class="webx-breadcrumbs__list">
        @foreach ($crumbs as $crumb)
            @if ($loop->last)
                <li class="webx-breadcrumbs__item webx-breadcrumbs__item--current" aria-current="page">{{ $crumb->title }}</li>
            @elseif ($crumb->url !== null)
                <li class="webx-breadcrumbs__item"><a class="webx-breadcrumbs__link" href="{{ $crumb->url }}">{{ $crumb->title }}</a></li>
            @else
                <li class="webx-breadcrumbs__item">{{ $crumb->title }}</li>
            @endif
        @endforeach
    </ol>
</nav>
