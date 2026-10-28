{{--
    The registration (spec §6.2): first and last name, phone, e-mail, password — in that order.
    No second password: the field has «Show». The visitor is signed in the moment it is sent.

    Override: <layer>/views/vendor/webx-users/register.blade.php — keep the names of the fields.
--}}
<x-webx-users::page :title="__('webx-users::auth.register.title')">
    <x-webx-form :action="$action" :rules="$rules" id="webx-users-register" class="webx-account-auth__form">
        <x-webx-field type="hidden" name="back" :value="$back" />
        <x-webx-field name="first_name" :label="__('webx-users::auth.fields.first_name')" autocomplete="given-name" optional />
        <x-webx-field name="last_name" :label="__('webx-users::auth.fields.last_name')" autocomplete="family-name" optional />
        <x-webx-field type="tel" name="phone" :label="__('webx-users::auth.fields.phone')" autocomplete="tel" optional />
        <x-webx-field type="email" name="email" :label="__('webx-users::auth.fields.email')" autocomplete="email" />
        <x-webx-field type="password" name="password" :label="__('webx-users::auth.fields.password')" :hint="__('webx-users::auth.register.password_hint', ['min' => $min])" autocomplete="new-password" />
        <x-webx-form.submit>{{ __('webx-users::auth.register.submit') }}</x-webx-form.submit>
    </x-webx-form>
    <x-slot:footer>
        <p>{{ __('webx-users::auth.register.have_account') }} <a href="{{ $signIn }}">{{ __('webx-users::auth.register.sign_in') }}</a></p>
    </x-slot:footer>
</x-webx-users::page>
