{{--
    «Almost done» after a social network (spec §9.5): what the provider did not give — the
    address, a field the settings require — and the account is made when it is sent. An address
    typed here is not confirmed: the letter of confirmation follows. An address that has an
    account already sends the visitor to sign in with its password, which links the network (§9.7).

    Override: <layer>/views/vendor/webx-users/complete.blade.php — keep the names of the fields.
--}}
<x-webx-users::page :title="__('webx-users::auth.social.complete.title')">
    <p class="webx-account-auth__note">{{ __('webx-users::auth.social.complete.text', ['provider' => $provider]) }}</p>
    <x-webx-form :action="$action" :rules="$rules" id="webx-users-complete" class="webx-account-auth__form">
        @foreach (['first_name' => ['text', 'given-name'], 'last_name' => ['text', 'family-name'], 'phone' => ['tel', 'tel']] as $name => [$type, $autocomplete])
            @if (in_array($name, $asked, true))
                <x-webx-field :type="$type" :name="$name" :label="__('webx-users::auth.fields.'.$name)" :autocomplete="$autocomplete" />
            @endif
        @endforeach
        @if (in_array('email', $asked, true))
            <x-webx-field type="email" name="email" :label="__('webx-users::auth.fields.email')" :hint="__('webx-users::auth.social.complete.email_hint')" autocomplete="email" />
        @endif
        @foreach ($fields as $field)
            <x-webx-field :type="$field['type']" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :choices="$field['choices']" />
        @endforeach
        <x-webx-form.submit>{{ __('webx-users::auth.social.complete.submit') }}</x-webx-form.submit>
    </x-webx-form>
</x-webx-users::page>
