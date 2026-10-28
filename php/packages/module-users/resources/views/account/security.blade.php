{{--
    «Sign-in and security» (spec §11.1): the password — a change on the spot, or «Set a password»
    by a letter for an account without one (§9.6) —, «Sign out everywhere», and the deletion of the
    account with what the modules warn about (§14.1): the current password, or the account's own
    address where there is no password. Between them — the social networks (§9.6): linked with
    «Unlink» (off, with the reason, for the last way to sign in) or «Link».

    Override: <layer>/views/vendor/webx-users/account/security.blade.php — keep the names of the fields.
--}}
<x-webx-users::account :title="__('webx-users::account.security.title')">
    @if (is_string(session(\WebxUi\Users\Social\SocialSession::NOTICE)))
        <p class="webx-account__notice" role="status">{{ session(\WebxUi\Users\Social\SocialSession::NOTICE) }}</p>
    @endif
    <section class="webx-account__section">
        <h2 class="webx-account__heading">{{ __('webx-users::account.password.title') }}</h2>
        @if ($hasPassword)
            <x-webx-form :action="$password['action']" :rules="$password['rules']" id="webx-users-password" class="webx-account__form">
                <x-webx-field type="password" name="current_password" :label="__('webx-users::account.password.current')" autocomplete="current-password" />
                <x-webx-field type="password" name="password" :label="__('webx-users::account.password.new')" :hint="__('webx-users::auth.register.password_hint', ['min' => $min])" autocomplete="new-password" />
                <x-webx-field type="password" name="password_confirmation" :label="__('webx-users::account.password.confirmation')" autocomplete="new-password" />
                <x-webx-form.submit>{{ __('webx-users::account.password.submit') }}</x-webx-form.submit>
            </x-webx-form>
        @else
            <p class="webx-account__text">{{ __('webx-users::account.password.none') }}</p>
            <x-webx-form :action="$link" id="webx-users-password-link" class="webx-account__form">
                <x-webx-form.submit>{{ __('webx-users::account.password.set') }}</x-webx-form.submit>
            </x-webx-form>
        @endif
    </section>

    @if ($social !== [])
        <section class="webx-account__section webx-users-identities">
            <h2 class="webx-account__heading">{{ __('webx-users::account.social.title') }}</h2>
            <ul class="webx-users-identities__list">
                @foreach ($social as $row)
                    <li @class(['webx-users-identities__item', 'webx-users-identities__item--'.$row['key'], 'is-linked' => $row['linked']])>
                        @if (view()->exists($row['icon']))
                            <span class="webx-users-identities__icon" aria-hidden="true">@include($row['icon'])</span>
                        @endif
                        <span class="webx-users-identities__text">
                            <span class="webx-users-identities__name">{{ $row['label'] }}</span>
                            @if ($row['linked'])
                                <span class="webx-users-identities__meta">
                                    {{ $row['email'] ?? __('webx-users::account.social.linked_short') }}@if ($row['since'] !== null) · {{ __('webx-users::account.social.since', ['date' => $row['since']->locale(app()->getLocale())->isoFormat('LL')]) }}@endif
                                </span>
                            @endif
                        </span>
                        @if ($row['linked'])
                            <x-webx-form :action="$row['unlink']" id="webx-users-unlink-{{ $row['key'] }}" class="webx-users-identities__form">
                                <x-webx-form.submit :disabled="$row['last']">{{ __('webx-users::account.social.unlink') }}</x-webx-form.submit>
                            </x-webx-form>
                            @if ($row['last'])
                                <p class="webx-users-identities__hint">{{ __('webx-users::account.social.last') }}</p>
                            @endif
                        @else
                            <a href="{{ $row['link'] }}" class="webx-users-identities__link" rel="nofollow">{{ __('webx-users::account.social.link') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="webx-account__section">
        <h2 class="webx-account__heading">{{ __('webx-users::account.sessions.title') }}</h2>
        <p class="webx-account__text">{{ __('webx-users::account.sessions.text') }}</p>
        <x-webx-form :action="$sessions" id="webx-users-sessions" class="webx-account__form">
            <x-webx-form.submit>{{ __('webx-users::account.sessions.submit') }}</x-webx-form.submit>
        </x-webx-form>
    </section>

    <section class="webx-account__section webx-account__section--danger">
        <h2 class="webx-account__heading">{{ __('webx-users::account.delete.title') }}</h2>
        <p class="webx-account__text">{{ __('webx-users::account.delete.text') }}</p>
        @if ($warnings !== [])
            <ul class="webx-account__warnings">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @endif
        <x-webx-form :action="$delete" id="webx-users-delete" class="webx-account__form">
            @if ($hasPassword)
                <x-webx-field type="password" name="password" :label="__('webx-users::account.delete.password')" autocomplete="current-password" />
            @else
                <x-webx-field type="email" name="email" :label="__('webx-users::account.delete.email')" :hint="__('webx-users::account.delete.email_hint')" autocomplete="off" />
            @endif
            <x-webx-form.submit class="webx-account__danger">{{ __('webx-users::account.delete.submit') }}</x-webx-form.submit>
        </x-webx-form>
    </section>
</x-webx-users::account>
