{{-- The line above the bar: the hours and the first e-mail of the Contacts tab. --}}@if ($hours || $email !== null)
    <div class="site-header__topbar">
        @if ($hours)
            <x-webx-hours />
        @endif
        @if ($email !== null)
            <a class="site-header__email" href="{{ $email->href() }}">{{ $email->address }}</a>
        @endif
    </div>
@endif
