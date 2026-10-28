{{--
    The frame of a page of the account (spec §11.4): the layout with the handle `users.account`,
    the heading, the page in the slot. In `layout.account` the menu and the grid are the
    composition's; in any other layout — the site's own, the bare document — this prints the same
    grid and menu itself, so the account is whole without a theme. A section of a module stands in
    the same frame: <x-webx-users::account :title="…">…</x-webx-users::account>.

    Override: <layer>/views/vendor/webx-users/components/account.blade.php.
    Classes: webx-account, __nav, __main, __title.
--}}
@props(['title'])
@php
    $layout = \WebxUi\Users\Rendering\Layouts::account();
    $own = $layout !== 'layout.account';
@endphp
<x-dynamic-component :component="$layout" handle="users.account">
    <x-slot:head>
        <title>{{ $title }}@if (is_string(config('app.name')) && config('app.name') !== '') — {{ config('app.name') }}@endif</title>
        <meta name="robots" content="noindex">
    </x-slot:head>
    @if ($own)
        <div class="webx-account">
            <nav class="webx-account__nav" aria-label="{{ __('webx-users::account.menu.label') }}">
                <x-webx-users::account-menu />
            </nav>
            <div class="webx-account__main">
                <h1 class="webx-account__title">{{ $title }}</h1>
                {{ $slot }}
            </div>
        </div>
    @else
        <h1 class="webx-account__title">{{ $title }}</h1>
        {{ $slot }}
    @endif
</x-dynamic-component>
