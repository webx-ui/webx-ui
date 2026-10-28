{{--
    The frame of the sign-in and the registration (spec §11.4): the layout of `users.auth`, a
    narrow card in the middle, its heading, the form in the slot and the links under it in
    `footer`. Nothing personal is printed in a shared place: these pages are `no-store`.

    Override: <layer>/views/vendor/webx-users/components/page.blade.php.
    Classes: webx-account-auth, __card, __title, __links.
--}}
@props(['title'])
<x-dynamic-component :component="\WebxUi\Users\Rendering\Layouts::auth()" handle="users.auth">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>
    <div class="webx-account-auth">
        <div class="webx-account-auth__card">
            <h1 class="webx-account-auth__title">{{ $title }}</h1>
            {{ $slot }}
            @isset($footer)
                <div class="webx-account-auth__links">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</x-dynamic-component>
