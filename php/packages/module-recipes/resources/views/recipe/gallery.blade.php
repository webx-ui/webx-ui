{{-- The first picture large, the rest in a row under it; one picture — no row. --}}
@if ($pictures !== [])
    {{-- The row's look without a theme is in partials/look, with the catalogue's. --}}
    @include('webx-recipes::partials.look')
    <figure class="wx-recipe__gallery">
        @php($first = $pictures[0])
        <img class="wx-recipe__photo" src="{{ $first['url'] }}" alt="{{ $first['alt'] ?? $title }}"
             @if ($first['width']) width="{{ $first['width'] }}" height="{{ $first['height'] }}" @endif>
        @if (count($pictures) > 1)
            <ul class="wx-recipe__strip">
                @foreach (array_slice($pictures, 1) as $picture)
                    <li class="wx-recipe__strip-item"><a class="wx-recipe__strip-link" href="{{ $picture['url'] }}"><img class="wx-recipe__thumb" src="{{ $picture['thumb'] ?? $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" width="120" loading="lazy"></a></li>
                @endforeach
            </ul>
        @endif
    </figure>
@endif
