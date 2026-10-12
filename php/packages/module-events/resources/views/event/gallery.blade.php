{{-- The first picture large, the rest in a row under it; one picture — no row. --}}
@if ($pictures !== [])
    {{-- The row's look without a theme; under one it is resources/theme/events.css (see partials/list). --}}
    @unless (function_exists('theme_active') && theme_active())
        @once
            @push('head')
                <style>
                    .wx-event__strip { display: flex; gap: 0.5em; list-style: none; padding: 0; overflow-x: auto; }
                </style>
            @endpush
        @endonce
    @endunless
    <figure class="wx-event__gallery">
        @php($first = $pictures[0])
        <img class="wx-event__photo" src="{{ $first['url'] }}" alt="{{ $first['alt'] ?? $title }}"
             @if ($first['width']) width="{{ $first['width'] }}" height="{{ $first['height'] }}" @endif>
        @if (count($pictures) > 1)
            <ul class="wx-event__strip">
                @foreach (array_slice($pictures, 1) as $picture)
                    <li class="wx-event__strip-item"><a class="wx-event__strip-link" href="{{ $picture['url'] }}"><img class="wx-event__thumb" src="{{ $picture['thumb'] ?? $picture['url'] }}" alt="{{ $picture['alt'] ?? '' }}" width="120" loading="lazy"></a></li>
                @endforeach
            </ul>
        @endif
    </figure>
@endif
