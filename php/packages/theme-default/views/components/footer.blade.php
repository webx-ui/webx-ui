@php
    /**
     * The footer region's fallback. `menu('footer')` when the menus module is there and the
     * menu has entries; otherwise just the line every footer has.
     */
    $items = function_exists('menu')
        ? menu('footer')->map(fn ($item) => ['label' => $item->label, 'attrs' => $item->attrs()])
        : collect();
@endphp

<footer class="site-footer">
    <div class="site-container site-footer__inner">
        @if ($items->isNotEmpty())
            <nav class="site-footer__nav" aria-label="{{ __('Footer') }}">
                @foreach ($items as $item)
                    <a class="site-footer__link" @foreach ($item['attrs'] as $name => $value) {{ $name }}="{{ $value }}" @endforeach>{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <p class="site-footer__copy">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </div>
</footer>
