{{-- Book, and put it in a calendar. Over, or no link — no button; no date — no calendar file. --}}
@if ($booking_url || ($ics_url && ! $past))
    <p class="wx-event__booking">
        @if ($booking_url)
            <a class="wx-event__book" href="{{ $booking_url }}" rel="noopener" target="_blank">{{ trans('webx-events::site.book') }}</a>
        @endif
        @if ($ics_url && ! $past)
            <a class="wx-event__calendar" href="{{ $ics_url }}" rel="nofollow">{{ trans('webx-events::site.add-to-calendar') }}</a>
        @endif
    </p>
@endif
