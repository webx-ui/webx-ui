{{-- "What to expect": the cards written in this language; none — no section. --}}
@if ($highlights !== [])
    <section class="wx-event__highlights">
        <h2 class="wx-event__section-title">{{ trans('webx-events::site.highlights') }}</h2>
        <ul class="wx-event__highlight-list">
            @foreach ($highlights as $highlight)
                <li class="wx-event__highlight">
                    @if ($highlight['title'] !== '')
                        <h3 class="wx-event__highlight-title">{{ $highlight['title'] }}</h3>
                    @endif
                    @if ($highlight['text'] !== '')
                        <p class="wx-event__highlight-text">{{ $highlight['text'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
