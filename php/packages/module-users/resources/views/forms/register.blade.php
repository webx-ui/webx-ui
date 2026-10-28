{{--
    The form of the registration, on its page and in the dialog of a checkout (spec §6.2, §12.1):
    first and last name, phone, e-mail, password — in that order — then the fields of the site
    (§10.4). The settings hide or require the first three (§10.5). No second password: the field
    has «Show». `$dialog` — sent from the dialog (SignInDialog::INPUT).

    Override: <layer>/views/vendor/webx-users/forms/register.blade.php — keep the names of the fields.
--}}
<x-webx-form :action="$action" :rules="$rules" :id="$id" class="webx-account-auth__form">
    <x-webx-field type="hidden" name="back" :value="$back" />
    @if ($dialog ?? false)
        <x-webx-field type="hidden" :name="\WebxUi\Users\SignInDialog::INPUT" value="1" />
    @endif
    @foreach (['first_name' => ['text', 'given-name'], 'last_name' => ['text', 'family-name'], 'phone' => ['tel', 'tel']] as $name => [$type, $autocomplete])
        @if ($asked[$name] !== \WebxUi\Users\Fields\Requirement::Hidden)
            <x-webx-field :type="$type" :name="$name" :label="__('webx-users::auth.fields.'.$name)" :autocomplete="$autocomplete" :optional="$asked[$name] !== \WebxUi\Users\Fields\Requirement::Required" />
        @endif
    @endforeach
    <x-webx-field type="email" name="email" :label="__('webx-users::auth.fields.email')" autocomplete="email" />
    <x-webx-field type="password" name="password" :label="__('webx-users::auth.fields.password')" :hint="__('webx-users::auth.register.password_hint', ['min' => $min])" autocomplete="new-password" />
    @foreach ($fields as $field)
        <x-webx-field :type="$field['type']" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :choices="$field['choices']" :optional="! $field['required']" />
    @endforeach
    <x-webx-form.submit>{{ __('webx-users::auth.register.submit') }}</x-webx-form.submit>
</x-webx-form>
