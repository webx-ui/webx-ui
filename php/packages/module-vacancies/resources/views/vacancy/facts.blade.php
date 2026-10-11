{{-- Where, how, for how much, until when, under what. Nothing known — no list. --}}
@php($place = $workplace === 'remote' ? trans('webx-vacancies::vacancy.workplace.remote') : implode(', ', array_filter([$city, $address])))
@if ($place !== '' || $workplace === 'hybrid' || $employment !== [] || $salary !== '' || $valid_through !== '' || $categories !== [])
    <dl class="wx-vacancy__facts">
        @if ($place !== '' || $workplace === 'hybrid')
            <dt class="wx-vacancy__term">{{ trans('webx-vacancies::vacancy.where') }}</dt>
            <dd class="wx-vacancy__value">
                @if ($place !== '')
                    <span class="wx-vacancy__place">{{ $place }}</span>
                @endif
                @if ($workplace === 'hybrid')
                    <span class="wx-vacancy__remote">{{ trans('webx-vacancies::vacancy.remote-possible') }}</span>
                @endif
            </dd>
        @endif
        @if ($employment !== [])
            <dt class="wx-vacancy__term">{{ trans('webx-vacancies::vacancy.employment-label') }}</dt>
            <dd class="wx-vacancy__value">{{ implode(', ', $employment) }}</dd>
        @endif
        @if ($salary !== '')
            <dt class="wx-vacancy__term">{{ trans('webx-vacancies::vacancy.salary') }}</dt>
            <dd class="wx-vacancy__value">{{ $salary }}</dd>
        @endif
        @if ($valid_through !== '')
            <dt class="wx-vacancy__term">{{ trans('webx-vacancies::vacancy.valid-through') }}</dt>
            <dd class="wx-vacancy__value">{{ $valid_through }}</dd>
        @endif
        @if ($categories !== [])
            <dt class="wx-vacancy__term">{{ trans('webx-vacancies::vacancy.categories') }}</dt>
            <dd class="wx-vacancy__value">{{ implode(', ', $categories) }}</dd>
        @endif
    </dl>
@endif
