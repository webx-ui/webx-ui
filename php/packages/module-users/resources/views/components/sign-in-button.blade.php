{{--
    `<x-webx-users::sign-in-button />` — «Sign in» of a checkout (spec §12.1, §12.2): opens the
    dialog of the sign-in on the page — printed before </body> because this is on it. Without
    JavaScript it is the link to the page of the sign-in with the way back to this page.

    `view` — `sign-in` (the default) or `register`: the view the dialog opens on. The slot — the
    words, «Sign in» without one. On a page cached for everybody `users.js` hides it once the
    cookie says the visitor is in (`data-webx-users-guest`); a page of its own — a checkout —
    prints it for a guest only.

    Override: <layer>/views/vendor/webx-users/components/sign-in-button.blade.php — keep
    `data-webx-sign-in`. Classes: webx-users-sign-in-button.
--}}
@props(['view' => 'sign-in'])
@php
    $urls = app(\WebxUi\Users\Urls::class);
    $page = $view === 'register' && \WebxUi\Users\SignIn::registrationOpen() ? 'register' : 'sign-in';
@endphp
<a {{ $attributes->class(['webx-users-sign-in-button'])->merge(['href' => $urls->to($page, request()->getRequestUri()), 'rel' => 'nofollow']) }} data-webx-sign-in="{{ $page }}" data-webx-users-guest>{{ $slot->isEmpty() ? __('webx-users::auth.sign_in.title') : $slot }}</a>
