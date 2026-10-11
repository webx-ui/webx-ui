{{-- Duties, requirements, what we offer: the lines written in this language; none — no section. --}}
@foreach (['duties' => $duties, 'requirements' => $requirements, 'benefits' => $benefits] as $list => $lines)
    @if ($lines !== [])
        <section class="wx-vacancy__section wx-vacancy__{{ $list }}">
            <h2 class="wx-vacancy__section-title">{{ trans('webx-vacancies::vacancy.'.$list) }}</h2>
            <ul>
                @foreach ($lines as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endforeach
