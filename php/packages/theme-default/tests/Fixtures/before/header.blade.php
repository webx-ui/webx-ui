@php
    /**
     * The header region's fallback: what shows until somebody arranges the region in the panel.
     * The behaviour — dropdowns, folding into the mobile menu, sticking — is the widget's
     * (`<x-webx-header>`, WIDGETS §6.2); what is here is which items and which brand.
     *
     * With the menus module it is `menu('header')` — the entries an editor put there, nested, in
     * their order. An entry of the `button` look is a call to action and stands among the
     * actions rather than in the navigation. Until that menu is filled in, the published pages
     * one level below the home page: a header that is empty on the day a site is created reads
     * as broken rather than as waiting. Both halves are guarded, since the theme requires
     * neither module.
     *
     * The layout prints its own "Skip to content" first in <body> — it must be there when a
     * region from the panel replaces this header — so the widget's is off.
     */
    $items = function_exists('menu') ? collect(menu('header')) : collect();

    if ($items->isEmpty() && class_exists(\WebxUi\Pages\Models\Page::class)) {
        $items = (\WebxUi\Pages\Models\Page::query()->roots()->first()
                ?->children()->whereNotNull('published_at')->ordered()->get() ?? collect())
            ->map(fn ($page) => ['label' => $page->title, 'url' => $page->url()]);
    }

    $variant = fn ($item) => is_array($item) ? ($item['variant'] ?? 'link') : ($item->variant ?? 'link');
    [$calls, $nav] = $items->partition(fn ($item) => $variant($item) === 'button');

    // The Contacts tab of the settings (WIDGETS §12): the hours and the e-mail above the bar,
    // the numbers among the actions — and so at the bottom of the mobile menu. Asked for here,
    // rather than left to the widgets, because a slot with nothing in it is still a slot.
    $contacts = function_exists('contacts') ? contacts() : null;
    $hours = $contacts !== null && ! $contacts->hours()->isEmpty();
    $email = $contacts?->emails()[0] ?? null;
    $phones = $contacts !== null && $contacts->phones() !== [];

    // The same page in the site's other languages (WIDGETS §13), last among the actions — and so
    // at the bottom of the mobile menu. Only on a site with more than one.
    $languages = \WebxUi\Widgets\Languages\LanguageLinks::current();
@endphp

<x-webx-header class="site-header" sticky="sticky" mode="drill" :skip="false">
    @if ($hours || $email !== null)
        <x-slot:topbar>
            <div class="site-header__topbar">
                @if ($hours)
                    <x-webx-hours />
                @endif
                @if ($email !== null)
                    <a class="site-header__email" href="{{ $email->href() }}">{{ $email->address }}</a>
                @endif
            </div>
        </x-slot:topbar>
    @endif

    <x-slot:brand>
        <a class="site-header__title" href="{{ url('/') }}">{{ config('app.name') }}</a>
    </x-slot:brand>

    @if ($nav->isNotEmpty())
        <x-webx-header.nav :items="$nav->values()" />
    @endif

    @if ($calls->isNotEmpty() || $phones || count($languages) > 1)
        <x-slot:actions>
            @if ($phones)
                <x-webx-phones class="site-header__phones" />
            @endif
            @foreach ($calls as $call)
                <a class="site-header__cta" @foreach ($call->attrs() as $name => $value) {{ $name }}="{{ $value }}" @endforeach>{{ $call->label }}</a>
            @endforeach
            @if (count($languages) > 1)
                <x-webx-language-switcher class="site-header__languages" :languages="$languages" />
            @endif
        </x-slot:actions>
    @endif
</x-webx-header>
