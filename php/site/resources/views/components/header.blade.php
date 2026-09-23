@php
    /**
     * The menu of a site, and the example it falls back to on its first day.
     *
     * With the menus module installed this is `menu('header')`: the entries somebody put there,
     * in their order, pointing at pages, at addresses of their own, or at nothing — which is
     * what a real menu is and what the page tree never was. `$item->attrs()` carries `href`,
     * `target` and `rel` together, so the template does not repeat the three conditionals.
     *
     * Under it, the published pages one level below the home page, which is what shows until
     * that menu has been filled in: a header that is empty on the day a site is created reads
     * as broken rather than as waiting. Both halves are guarded — a site is free to install
     * neither module — and both are written to be rewritten.
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
    <div class="wrap">
        <a class="site-title" href="{{ url('/') }}">{{ config('app.name') }}</a>

        @if ($items->isNotEmpty())
            <nav>
                @foreach ($items as $item)
                    <a @foreach ($item['attrs'] as $name => $value) {{ $name }}="{{ $value }}" @endforeach>{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</header>
