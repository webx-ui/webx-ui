{{--
    The form of the sign-in, on its page and in the dialog of a checkout (spec §6.2, §12.1).
    `$id` — the form's id on the page; `$dialog` — sent from the dialog (SignInDialog::INPUT).

    Override: <layer>/views/vendor/webx-users/forms/sign-in.blade.php — keep the names of the fields.
--}}
<x-webx-form :action="$action" :rules="$rules" :id="$id" class="webx-account-auth__form">
    <x-webx-field type="hidden" name="back" :value="$back" />
    @if ($dialog ?? false)
        <x-webx-field type="hidden" :name="\WebxUi\Users\SignInDialog::INPUT" value="1" />
    @endif
    <x-webx-field type="email" name="email" :label="__('webx-users::auth.fields.email')" autocomplete="email" enterkeyhint="next" />
    <x-webx-field type="password" name="password" :label="__('webx-users::auth.fields.password')" autocomplete="current-password" enterkeyhint="go" />
    @if ($remember)
        <x-webx-field type="checkbox" name="remember" :label="__('webx-users::auth.sign_in.remember')" />
    @endif
    <x-webx-form.submit>{{ __('webx-users::auth.sign_in.submit') }}</x-webx-form.submit>
</x-webx-form>
