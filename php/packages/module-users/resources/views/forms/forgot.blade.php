{{--
    The form of «Forgot your password?», on its page and in the dialog of a checkout (spec §7,
    §12.1): one field; `back` goes into the link of the letter.

    Override: <layer>/views/vendor/webx-users/forms/forgot.blade.php — keep the name of the field.
--}}
<x-webx-form :action="$action" :rules="$rules" :id="$id" class="webx-account-auth__form">
    <x-webx-field type="hidden" name="back" :value="$back" />
    <x-webx-field type="email" name="email" :label="__('webx-users::auth.fields.email')" autocomplete="email" enterkeyhint="send" />
    <x-webx-form.submit>{{ __('webx-users::auth.forgot.submit') }}</x-webx-form.submit>
</x-webx-form>
