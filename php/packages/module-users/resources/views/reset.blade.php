{{--
    The new password (spec §7 item 3): the password and its repetition — the one page of the
    module that asks twice (decision 3).

    Override: <layer>/views/vendor/webx-users/reset.blade.php — keep the names of the fields.
--}}
<x-webx-users::page :title="__('webx-users::auth.reset.title')">
    <x-webx-form :action="$action" :rules="$rules" id="webx-users-reset" class="webx-account-auth__form">
        <x-webx-field type="password" name="password" :label="__('webx-users::auth.reset.password')" :hint="__('webx-users::auth.register.password_hint', ['min' => $min])" autocomplete="new-password" enterkeyhint="next" />
        <x-webx-field type="password" name="password_confirmation" :label="__('webx-users::auth.reset.confirmation')" autocomplete="new-password" enterkeyhint="go" />
        <x-webx-form.submit>{{ __('webx-users::auth.reset.submit') }}</x-webx-form.submit>
    </x-webx-form>
</x-webx-users::page>
