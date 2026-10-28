{{--
    The sign-in (spec §6.2): the form (forms/sign-in), «Forgot your password?», the social networks
    (§9.2), the way to the registration. One sentence for every wrong pair; the blocked account
    learns it only with the right password.

    Override: <layer>/views/vendor/webx-users/sign-in.blade.php.
--}}
<x-webx-users::page :title="__('webx-users::auth.sign_in.title')">
    @include('webx-users::forms.sign-in', ['id' => 'webx-users-sign-in'])
    <p class="webx-account-auth__forgot"><a href="{{ $forgot }}">{{ __('webx-users::auth.forgot.link') }}</a></p>
    <x-webx-users::social-buttons :back="$back" />
    @if ($register !== null)
        <x-slot:footer>
            <p>{{ __('webx-users::auth.sign_in.no_account') }} <a href="{{ $register }}">{{ __('webx-users::auth.sign_in.register') }}</a></p>
        </x-slot:footer>
    @endif
</x-webx-users::page>
