{{--
    The list of events — the index and a category page both print this, so a site that publishes
    it changes both at once. `$listing` is a Rendering\Listing: the cards of this page and the
    pages as ready links. Only the events to come are ever here: the past ones are printed where a
    site asks for them, `events()->past()`.

    Each card goes through `@webxPart('events.card')`, so a theme's `parts/events/card.blade.php`
    replaces the card without replacing the list.

    Links, not buttons: without a script the pages still work, and a search engine reads them the
    way a reader clicks through.
--}}
{{-- Under a theme the base look is resources/theme/events.css in the modules' stylesheet, which
     the theme restyles by coming later; this inline copy would come later still and win. --}}
@unless (function_exists('theme_active') && theme_active())
    @once
        @push('head')
            <style>
                .wx-events { container-type: inline-size; }
                .wx-events__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5em; margin: 0 0 1.5em; padding: 0; list-style: none; }
                @container (max-width: 40rem) { .wx-events__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
                @container (max-width: 24rem) { .wx-events__grid { grid-template-columns: minmax(0, 1fr); } }
                .wx-events__card { display: flex; flex-direction: column; gap: 0.4em; }
                .wx-events__link { display: flex; flex-direction: column; gap: 0.6em; color: inherit; text-decoration: none; }
                .wx-events__cover { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; border-radius: 0.5em; }
                .wx-events__name { font-weight: 600; }
                .wx-events__meta { display: flex; flex-wrap: wrap; gap: 0.5em; opacity: 0.7; font-size: 0.9em; }
                .wx-events__meta > * + *::before { content: "·"; margin-inline-end: 0.5em; }
                .wx-events__meta a { color: inherit; }
                .wx-events__pages { display: flex; flex-wrap: wrap; gap: 0.5em; margin: 0 0 1.25em; padding: 0; list-style: none; }
                .wx-events__pages a[aria-current] { font-weight: 600; text-decoration: none; }
            </style>
        @endpush
    @endonce
@endunless

<div class="wx-events">
    @if ($listing->items === [])
        <p class="wx-events__empty">{{ trans('webx-events::site.empty') }}</p>
    @else
        <ul class="wx-events__grid">
            @foreach ($listing->items as $card)
                @webxPart('events.card', ['card' => $card], 'webx-events::partials.card')
            @endforeach
        </ul>
    @endif

    @if (count($listing->pages) > 1)
        <nav class="wx-events__pager" aria-label="{{ trans('webx-events::site.pages') }}">
            <ul class="wx-events__pages">
                @if ($listing->previous)
                    <li class="wx-events__page"><a class="wx-events__page-link" href="{{ $listing->previous }}" rel="prev">{{ trans('webx-events::site.previous') }}</a></li>
                @endif
                @foreach ($listing->pages as $link)
                    <li class="wx-events__page"><a class="wx-events__page-link" href="{{ $link['url'] }}" @if ($link['current']) aria-current="page" @endif>{{ $link['page'] }}</a></li>
                @endforeach
                @if ($listing->next)
                    <li class="wx-events__page"><a class="wx-events__page-link" href="{{ $listing->next }}" rel="next">{{ trans('webx-events::site.next') }}</a></li>
                @endif
            </ul>
        </nav>
    @endif
</div>
