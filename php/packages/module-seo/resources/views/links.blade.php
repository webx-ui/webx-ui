{{-- The interlinking block of a page: the heading and the links the SEO brief gave it, broken
     ones already left out. Publish and restyle it as you like (`webx-seo-views`); keep the
     heading as the nav's label, so a screen reader announces the block by its name. --}}
<nav class="webx-links" aria-labelledby="{{ $id }}">
    <h2 id="{{ $id }}" class="webx-links__heading">{{ $heading }}</h2>
    <ul class="webx-links__list">
        @foreach ($links as $link)
            <li><a href="{{ $link['href'] }}">{{ $link['anchor'] }}</a></li>
        @endforeach
    </ul>
</nav>
