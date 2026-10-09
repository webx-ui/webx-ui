@php
    /**
     * The header region's fallback: what shows until somebody arranges the region in the panel.
     *
     * With the menus module it is `menu('header')` — the entries an editor put there, in their
     * order; `$item->attrs()` carries `href`, `target` and `rel` together. Until that menu is
     * filled in, the published pages one level below the home page: a header that is empty on
     * the day a site is created reads as broken rather than as waiting. Both halves are guarded,
     * since the theme requires neither module.
     */
    $items = function_exists('menu')
        ? menu('header')->map(fn ($item) => ['label' => $item->label, 'attrs' => $item->attrs()])
        : collect();

    if ($items->isEmpty() && class_exists(\WebxUi\Pages\Models\Page::class)) {
        $items = (\WebxUi\Pages\Models\Page::query()->roots()->first()
                ?->children()->whereNotNull('published_at')->ordered()->get() ?? collect())
            ->map(fn ($page) => ['label' => $page->title, 'attrs' => ['href' => $page->url()]]);
    }
@endphp

<header class="site-header">
    <div class="site-container site-header__inner">
        <a class="site-header__title" href="{{ url('/') }}">{{ config('app.name') }}</a>

        @if ($items->isNotEmpty())
            <nav class="site-header__nav" aria-label="{{ __('Main') }}">
                @foreach ($items as $item)
                    <a class="site-header__link" @foreach ($item['attrs'] as $name => $value) {{ $name }}="{{ $value }}" @endforeach>{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</header>
