{{--
    «Confirm your e-mail» (spec §8.3), the node `users.verify-banner` the module puts first into
    the `main` of the layout. A theme moves it or takes it out by that id.

    A page cached for everybody prints it hidden and the same for everybody — no address, no
    name — and `users.js` shows it when the cookie `webx_visitor` says `u`, asking
    `/account/state` for the masked address; a guest has no cookie and asks nothing. A page of the
    module (`no-store`) is the visitor's own: there the server prints it, or nothing.

    Closed with the cross — for the session of the browser; never for good.

    Override: <layer>/views/vendor/webx-users/components/verify-banner.blade.php — keep the data-
    attributes. Classes: webx-users-verify, __text, __email, __form, __close.
--}}
@php
    $private = request()->attributes->get(\WebxUi\Users\Http\KeepPrivate::ATTRIBUTE) === true;
    $urls = app(\WebxUi\Users\Urls::class);
    $visitor = $private ? app(\WebxUi\Admin\Visitors\Visitors::class)->current() : null;
    $show = ! $private || ($visitor !== null && ! $visitor->verified);
@endphp
@if ($show)
    <div class="webx-users-verify" role="status" data-webx-users-verify data-webx-users-state="{{ $private ? '' : $urls->to('state') }}" @if (! $private) hidden @endif>
        <p class="webx-users-verify__text">
            {{ __('webx-users::auth.verify.banner') }}
            <span class="webx-users-verify__email" data-webx-users-email>{{ $visitor !== null ? \WebxUi\Users\Emails\Mask::email($visitor->email) : '' }}</span>
        </p>
        <x-webx-form :action="$urls->to('verify.resend')" class="webx-users-verify__form" data-webx-users-sent="{{ __('webx-users::auth.verify.sent_short') }}">
            <x-webx-form.submit>{{ __('webx-users::auth.verify.resend') }}</x-webx-form.submit>
        </x-webx-form>
        <button type="button" class="webx-users-verify__close" data-webx-users-close aria-label="{{ __('webx-users::auth.verify.close') }}">&times;</button>
    </div>
    @once
        @push('scripts')
            <script type="module" src="{{ route('webx-users.script', ['v' => \WebxUi\Users\Http\ScriptController::version()]) }}"></script>
        @endpush
    @endonce
@endif
