{{--
    The consent screen: a person has come here from somebody else's application, so there is
    no panel around it — the site's logo, the question, the answer, nothing to navigate.

    It still is the panel: the frame of `webx-admin::page` — the panel's tokens, its typeface,
    and its card, alert, checkbox, buttons and link by their own classes, styled by rules taken
    from the components. Loading the panel's own bundle for that would start the panel.

    The client's name and logo are its own choice, made at registration; the address the
    answer goes to is the part it cannot choose, so that is shown twice.
--}}
@php
    /** @var \Laravel\Passport\Client $client */
    /** @var \WebxUi\Auth\Models\CmsUser $user */
    /** @var \WebxUi\Admin\Manifest\Branding $brand */
    /** @var list<array{module: string, level: 'view'|'edit'}>|null $rights */
@endphp
@extends('webx-admin::page')

@section('title', __('webx-auth::consent.page-title', ['client' => $client->name]))

@push('webx-page-styles')
    <style>
        .return-to {
            display: inline-block; margin-top: var(--wx-space-8); padding: var(--wx-space-2) var(--wx-space-8);
            border-radius: var(--wx-radius-xs); background: var(--wx-bg-muted); color: var(--wx-text-default);
            font-family: var(--wx-font-family-mono); font-size: var(--wx-font-size-xs); overflow-wrap: anywhere;
        }
        .asks { margin-top: var(--wx-space-6) !important; }
        .who {
            display: flex; flex-wrap: wrap; align-items: center; gap: var(--wx-space-4) var(--wx-space-12);
            padding: var(--wx-space-12) var(--wx-space-14); border-radius: var(--wx-radius-sm); background: var(--wx-bg-subtle);
        }
        .who__person { display: flex; flex-direction: column; min-width: 0; }
        .who__name { color: var(--wx-text-strong); font-weight: var(--wx-font-weight-medium); }
        .who__email { color: var(--wx-text-muted); overflow-wrap: anywhere; }
        .who form { margin-left: auto; }
        /* A link that posts: the component draws an anchor, and a button has to drop its own box. */
        .who button.wx-link { padding: 0; border: 0; background: none; font: inherit; font-weight: var(--wx-font-weight-medium); cursor: pointer; }
        .rights ul { margin: var(--wx-space-6) 0 0; padding-left: var(--wx-space-18); }
        .rights li { margin: var(--wx-space-2) 0; }
    </style>
@endpush

@section('content')
    <div class="wx-page__brand">
        @if ($brand->logo !== null)
            <img src="{{ $brand->logo->url }}" alt="{{ $brand->title }}">
        @else
            <span>{{ $brand->title }}</span>
        @endif
    </div>

    <section class="wx-card wx-card--shadow-always wx-card--padding-lg">
        <div class="wx-card__body wx-page__stack">
            <div>
                <h1 class="wx-page__title">{{ $client->name }}</h1>
                <p class="asks wx-page__muted">{{ __('webx-auth::consent.asks', ['site' => $site]) }}</p>
                <span class="return-to">{{ $returnTo }}</span>
            </div>

            <div class="who">
                <div class="who__person">
                    <span class="who__name">{{ __('webx-auth::consent.signed-in-as', ['name' => $user->name]) }}</span>
                    <span class="who__email">{{ $user->email }}</span>
                </div>
                <form method="post" action="{{ route('webx.auth.consent.switch') }}">
                    @csrf
                    <input type="hidden" name="next" value="{{ $here }}">
                    <button type="submit" class="wx-link wx-link--primary wx-link--underline-hover">{{ __('webx-auth::consent.switch') }}</button>
                </form>
            </div>

            <div class="rights">
                @if ($rights === null)
                    <p>{{ __('webx-auth::consent.everything') }}</p>
                @elseif ($rights === [])
                    <p>{{ __('webx-auth::consent.nothing') }}</p>
                @else
                    <p>{{ __('webx-auth::consent.can-do') }}</p>
                    <ul>
                        @foreach ($rights as $right)
                            <li>{{ __('webx-auth::consent.'.$right['level'], ['module' => $right['module']]) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="wx-alert wx-alert--warning wx-alert--soft wx-alert--titled" role="note">
                <span class="wx-alert__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.3 4.9a2 2 0 0 1 3.4 0l7 12.1a2 2 0 0 1-1.7 3H5a2 2 0 0 1-1.7-3z"/><path d="M12 9.5v4"/><circle cx="12" cy="16.6" r="1.05" fill="currentColor" stroke="none"/></svg></span>
                <div class="wx-alert__content">
                    <p class="wx-alert__title">{{ __('webx-auth::consent.warning') }}</p>
                    <div class="wx-alert__body">
                        <p>{{ __('webx-auth::consent.liability') }}</p>
                    </div>
                </div>
            </div>

            <form method="post" action="{{ route('webx.auth.consent.approve') }}" id="consent-approve">
                @csrf
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <label class="wx-checkbox wx-checkbox--md">
                    <input type="checkbox" name="read_only" value="1" class="wx-checkbox__native">
                    <span class="wx-checkbox__box" aria-hidden="true"><svg class="wx-checkbox__mark" viewBox="0 0 16 16" fill="none"><path d="M3.5 8.5l3 3 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <span class="wx-checkbox__label">{{ __('webx-auth::consent.read-only') }}</span>
                </label>
            </form>

            <div class="wx-page__buttons">
                <button type="submit" form="consent-approve" class="wx-button wx-button--primary wx-button--solid wx-button--md"><span class="wx-button__label">{{ __('webx-auth::consent.allow') }}</span></button>

                <form method="post" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="wx-button wx-button--default wx-button--outline wx-button--md"><span class="wx-button__label">{{ __('webx-auth::consent.cancel') }}</span></button>
                </form>
            </div>
        </div>
    </section>

    <div class="wx-page__foot">
        <p>{{ __('webx-auth::consent.disconnect') }}</p>
        <p>{{ __('webx-auth::consent.returns-to', ['host' => $returnTo]) }}</p>
    </div>
@endsection
