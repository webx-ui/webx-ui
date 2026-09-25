{{-- The first picture large, the rest in a row under it; one picture — no row. --}}
@if ($pictures !== [])
    <figure class="wx-event__gallery">
        @php($first = $pictures[0])
        <img class="wx-event__photo" src="{{ $first['url'] }}" alt="{{ $first['alt'] ?? $title }}"
             @if ($first['width']) width="{{ $first['width'] }}" height="{{ $first['height'] }}" @endif>
        @if (count($pictures) > 1)
            <ul class="wx-event__strip" style="display: flex; gap: 0.5em; list-style: none; padding: 0; overflow-x: auto;">
                @foreach (array_slice($pictures, 1) as $picture)
                    <li><a href="{{ $picture['url'] }}"><img src="{{ $picture['thumb'] ?? $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" width="120" loading="lazy"></a></li>
                @endforeach
            </ul>
        @endif
    </figure>
@endif
