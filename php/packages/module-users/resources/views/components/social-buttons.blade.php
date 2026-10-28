{{--
    The buttons of the social networks (spec §9.2): one per provider that is shown (§9.3), in the
    order of the registry (`webx-users.social.order`). None shown — nothing printed, the word «or»
    neither. Under the sign-in, the registration and the dialog of the checkout.

    `back` — where the visitor returns after signing in, a path of this site.
    Override: <layer>/views/vendor/webx-users/components/social-buttons.blade.php; an icon —
    views/vendor/webx-users/social/icons/<key>.blade.php.
    Classes: webx-users-social, __or, __list, __button (--<key>), __icon, __label.
--}}
@props(['back' => null])
@php
    $providers = app(\WebxUi\Users\Social\ProviderKeys::class)->all();
    $urls = app(\WebxUi\Users\Urls::class);
@endphp
@if ($providers !== [])
    <div class="webx-users-social">
        <p class="webx-users-social__or"><span>{{ __('webx-users::auth.social.or') }}</span></p>
        <ul class="webx-users-social__list">
            @foreach ($providers as $provider)
                <li>
                    <a href="{{ $urls->to('social', $back, ['provider' => $provider->key()]) }}" class="webx-users-social__button webx-users-social__button--{{ $provider->key() }}" rel="nofollow">
                        @if (view()->exists($provider->icon()))
                            <span class="webx-users-social__icon" aria-hidden="true">@include($provider->icon())</span>
                        @endif
                        <span class="webx-users-social__label">{{ __('webx-users::auth.social.continue', ['provider' => __($provider->label())]) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
