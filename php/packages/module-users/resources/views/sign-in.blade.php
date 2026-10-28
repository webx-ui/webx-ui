{{--
    The sign-in (spec §6.2): e-mail, password, «Remember me», «Forgot your password?», the way to the registration. One
    sentence for every wrong pair; the blocked account learns it only with the right password.

    Override: <layer>/views/vendor/webx-users/sign-in.blade.php — keep the names of the fields.
--}}
<x-webx-users::page :title="__('webx-users::auth.sign_in.title')">
    <x-webx-form :action="$action" :rules="$rules" id="webx-users-sign-in" class="webx-account-auth__form">
        <x-webx-field type="hidden" name="back" :value="$back" />
        <x-webx-field type="email" name="email" :label="__('webx-users::auth.fields.email')" autocomplete="email" enterkeyhint="next" />
        <x-webx-field type="password" name="password" :label="__('webx-users::auth.fields.password')" autocomplete="current-password" enterkeyhint="go" />
        @if ($remember)
            <x-webx-field type="checkbox" name="remember" :label="__('webx-users::auth.sign_in.remember')" />
        @endif
        <x-webx-form.submit>{{ __('webx-users::auth.sign_in.submit') }}</x-webx-form.submit>
    </x-webx-form>
    <p class="webx-account-auth__forgot"><a href="{{ $forgot }}">{{ __('webx-users::auth.forgot.link') }}</a></p>
    @if ($register !== null)
        <x-slot:footer>
            <p>{{ __('webx-users::auth.sign_in.no_account') }} <a href="{{ $register }}">{{ __('webx-users::auth.sign_in.register') }}</a></p>
        </x-slot:footer>
    @endif
</x-webx-users::page>
