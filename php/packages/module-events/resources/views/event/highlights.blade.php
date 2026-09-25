{{-- "What to expect": the cards written in this language; none — no section. --}}
@if ($highlights !== [])
    <section class="wx-event__highlights">
        <h2>{{ trans('webx-events::site.highlights') }}</h2>
        <ul>
            @foreach ($highlights as $highlight)
                <li>
                    @if ($highlight['title'] !== '')
                        <h3>{{ $highlight['title'] }}</h3>
                    @endif
                    @if ($highlight['text'] !== '')
                        <p>{{ $highlight['text'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
