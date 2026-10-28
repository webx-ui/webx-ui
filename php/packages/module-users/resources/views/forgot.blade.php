{{--
    «Forgot your password?» (spec §7 item 1): one field (forms/forgot), and the same answer for
    every address. Also the page of a dead link, with «The link is out of date» over the form
    (§7 item 3).

    Override: <layer>/views/vendor/webx-users/forgot.blade.php.
--}}
<x-webx-users::page :title="__('webx-users::auth.forgot.title')">
    @if ($expired)
        <p class="webx-account-auth__note" role="alert">{{ __('webx-users::auth.reset.expired') }}</p>
    @else
        <p class="webx-account-auth__note">{{ __('webx-users::auth.forgot.intro') }}</p>
    @endif
    @include('webx-users::forms.forgot', ['id' => 'webx-users-forgot'])
    <x-slot:footer>
        <p><a href="{{ $signIn }}">{{ __('webx-users::auth.forgot.back') }}</a></p>
    </x-slot:footer>
</x-webx-users::page>
