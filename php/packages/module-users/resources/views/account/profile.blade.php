{{--
    The profile (spec §11.1): first and last name and phone as the settings ask for them (§10.5),
    the fields of the site with the place `profile` (§10.4) — one the visitor may not change is
    shown, not editable —, then the e-mail: the one the visitor signs in with, whether it is
    confirmed, and its change, which waits for the link sent to the new address (§8.5).

    Override: <layer>/views/vendor/webx-users/account/profile.blade.php — keep the names of the fields.
--}}
<x-webx-users::account :title="__('webx-users::account.profile.title')">
    <section class="webx-account__section">
        <h2 class="webx-account__heading">{{ __('webx-users::account.profile.personal') }}</h2>
        <x-webx-form :action="$action" :rules="$rules" id="webx-users-profile" class="webx-account__form">
            @foreach (['first_name' => ['text', 'given-name'], 'last_name' => ['text', 'family-name'], 'phone' => ['tel', 'tel']] as $name => [$type, $autocomplete])
                @if ($asked[$name] !== \WebxUi\Users\Fields\Requirement::Hidden)
                    <x-webx-field :type="$type" :name="$name" :label="__('webx-users::auth.fields.'.$name)" :value="$user->{$name}" :autocomplete="$autocomplete" :optional="$asked[$name] !== \WebxUi\Users\Fields\Requirement::Required" />
                @endif
            @endforeach
            @foreach ($fields as $field)
                @if ($field['readonly'])
                    <x-webx-field type="text" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :value="is_scalar($field['value']) ? (string) $field['value'] : ''" readonly />
                @elseif ($field['type'] === 'checkbox')
                    <x-webx-field type="checkbox" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :checked="(bool) $field['value']" />
                @else
                    <x-webx-field :type="$field['type']" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :choices="$field['choices']" :value="$field['value']" :optional="! $field['required']" />
                @endif
            @endforeach
            <x-webx-form.submit>{{ __('webx-users::account.profile.save') }}</x-webx-form.submit>
        </x-webx-form>
    </section>

    <section class="webx-account__section">
        <h2 class="webx-account__heading">{{ __('webx-users::account.email.title') }}</h2>
        <p class="webx-account__text">
            <span class="webx-account__value">{{ $user->email }}</span>
            @if ($user->hasVerifiedEmail())
                <span class="webx-account__status webx-account__status--ok">{{ __('webx-users::account.email.confirmed') }}</span>
            @else
                <span class="webx-account__status webx-account__status--warn">{{ __('webx-users::account.email.unconfirmed') }}</span>
            @endif
        </p>
        @if ($email['pending'] !== null)
            <p class="webx-account__note">{{ __('webx-users::account.email.pending', ['email' => $email['pending']]) }}</p>
        @endif
        <x-webx-form :action="$email['action']" id="webx-users-email" class="webx-account__form">
            <x-webx-field type="email" name="email" :label="__('webx-users::auth.email.new')" autocomplete="email" />
            @if ($email['password'])
                <x-webx-field type="password" name="password" :label="__('webx-users::auth.email.password')" autocomplete="current-password" />
            @endif
            <x-webx-form.submit>{{ __('webx-users::account.email.submit') }}</x-webx-form.submit>
        </x-webx-form>
    </section>
</x-webx-users::account>
