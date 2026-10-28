{{--
    The registration (spec §6.2): the form (forms/register), the social networks under it (§9.2),
    the way to the sign-in. The visitor is signed in the moment it is sent.

    Override: <layer>/views/vendor/webx-users/register.blade.php.
--}}
<x-webx-users::page :title="__('webx-users::auth.register.title')">
    @include('webx-users::forms.register', ['id' => 'webx-users-register'])
    <x-webx-users::social-buttons :back="$back" />
    <x-slot:footer>
        <p>{{ __('webx-users::auth.register.have_account') }} <a href="{{ $signIn }}">{{ __('webx-users::auth.register.sign_in') }}</a></p>
    </x-slot:footer>
</x-webx-users::page>
