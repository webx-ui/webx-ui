{{-- The trail a reader sees, from the same list the BreadcrumbList in the <head> is printed
     from. Publish and restyle it as you like (`webx-seo-views`), but keep `$crumbs`: a second
     trail worked out in the view is the one that stops matching. --}}
<nav class="webx-breadcrumbs" aria-label="{{ trans('webx-seo::site.breadcrumbs', [], $locale) }}">
    <ol>
        @foreach ($crumbs as $crumb)
            @if ($loop->last)
                <li aria-current="page">{{ $crumb->title }}</li>
            @elseif ($crumb->url !== null)
                <li><a href="{{ $crumb->url }}">{{ $crumb->title }}</a></li>
            @else
                <li>{{ $crumb->title }}</li>
            @endif
        @endforeach
    </ol>
</nav>
