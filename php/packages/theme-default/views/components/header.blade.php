@php
    /**
     * The header region's fallback: what shows until somebody arranges the region in the panel —
     * and the dispatcher of the composition `header` (COMPOSITIONS §10.3). Its tree is
     * compositions/header/default.json: the brand, the navigation, the contacts above the bar and
     * the actions are nodes a theme patches by id. The behaviour — dropdowns, folding into the
     * mobile menu, sticking — is the widget's (`<x-webx-header>`, WIDGETS §6.2).
     *
     * What is worked out here is what the leaves get (`vars` of the base), once for all of them:
     * the navigation and the calls to action come from one menu.
     *
     * With the menus module it is `menu('header')` — the entries an editor put there, nested, in
     * their order. An entry of the `button` look is a call to action and stands among the
     * actions rather than in the navigation. Until that menu is filled in, the published pages
     * one level below the home page: a header that is empty on the day a site is created reads
     * as broken rather than as waiting. Both halves are guarded, since the theme requires
     * neither module.
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
    // the numbers among the actions — and so at the bottom of the mobile menu.
    $contacts = function_exists('contacts') ? contacts() : null;
    $hours = $contacts !== null && ! $contacts->hours()->isEmpty();
    $email = $contacts?->emails()[0] ?? null;
    $phones = $contacts !== null && $contacts->phones() !== [];

    // The same page in the site's other languages (WIDGETS §13), last among the actions — and so
    // at the bottom of the mobile menu. Only on a site with more than one.
    $languages = \WebxUi\Widgets\Languages\LanguageLinks::current();
@endphp
@include(composition_view('header'))
