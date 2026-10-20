{{--
    The plain consent page.

    Passport ships no view of its own, so without one the whole flow ends in "not
    instantiable" at the last step — the one the person is looking at. This is that page at
    its smallest: who is asking, where they will be sent back to, and two buttons. A panel
    that wants its own replaces it with `Passport::authorizationView()`.

    The address is shown, not only the name: a client chooses its own name, and "Site panel"
    costs nothing to type. In the frame of `webx-admin::page`: the panel's tokens and its
    card and buttons by their own classes.
--}}
@php
    $redirect = $request->query('redirect_uri') ?: ($client->redirect_uris[0] ?? '');
    $host = parse_url((string) $redirect, PHP_URL_HOST) ?: (string) $redirect;
@endphp
@extends('webx-admin::page')

@section('title', $client->name)

@push('webx-page-styles')
    <style>
        dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: var(--wx-space-8) var(--wx-space-16); }
        dt { color: var(--wx-text-muted); }
        dd { margin: 0; overflow-wrap: anywhere; }
        ul { margin: 0; padding-left: var(--wx-space-18); color: var(--wx-text-muted); }
    </style>
@endpush

@section('content')
    <section class="wx-card wx-card--shadow-always wx-card--padding-lg">
        <div class="wx-card__body wx-page__stack">
            <h1 class="wx-page__title">{{ $client->name }} is asking for access</h1>

            <dl>
                <dt>Signed in as</dt>
                <dd>{{ $user->name }}</dd>
                <dt>Sends you back to</dt>
                <dd>{{ $host }}</dd>
            </dl>

            @if (count($scopes) > 0)
                <ul>
                    @foreach ($scopes as $scope)
                        <li>{{ $scope->description }}</li>
                    @endforeach
                </ul>
            @endif

            <p>It will act as you, and can do whatever your account can do. Allow it only if you
               recognise both the application and the address above.</p>

            <div class="wx-page__buttons">
                <form method="post" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="wx-button wx-button--primary wx-button--solid wx-button--md"><span class="wx-button__label">Allow</span></button>
                </form>

                <form method="post" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="wx-button wx-button--default wx-button--outline wx-button--md"><span class="wx-button__label">Cancel</span></button>
                </form>
            </div>
        </div>
    </section>
@endsection
