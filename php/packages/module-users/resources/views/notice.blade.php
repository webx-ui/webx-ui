{{--
    A page of one sentence and the way on (spec §8.1, §8.5): «E-mail confirmed», «The link is out
    of date», «Confirm your e-mail». `resend` — the address of the letter once more, for a visitor
    signed in whose address is not confirmed; `link` and `button` — where to go next.

    Override: <layer>/views/vendor/webx-users/notice.blade.php.
--}}
<x-webx-users::page :title="$title">
    <p class="webx-account-auth__note">{{ $text }}</p>
    @if ($resend !== null)
        <x-webx-form :action="$resend" id="webx-users-resend" class="webx-account-auth__form">
            <x-webx-form.submit>{{ __('webx-users::auth.verify.resend') }}</x-webx-form.submit>
        </x-webx-form>
    @endif
    <x-slot:footer>
        <p><a href="{{ $link }}">{{ $button }}</a></p>
    </x-slot:footer>
</x-webx-users::page>
