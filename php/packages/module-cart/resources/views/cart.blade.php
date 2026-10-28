{{--
    The cart's widget (§9.1, decision of 28.10.2026): the trigger with the counter, the main view —
    a drawer or a dialog of the widgets (`<x-webx-dialog>`) — and, with `preview="dropdown"`, the
    mini cart under the icon (`<x-webx-dropdown>`), whose «Edit cart» opens the main view. The
    panels are empty on the page and the script fills them from the API when one opens (§9.2); a
    page that came back from a buy button without JavaScript prints the main view open and full
    (§9.5).

    Override: <layer>/views/vendor/webx-cart/cart.blade.php — keep `data-webx-cart` and its
    `data-webx-cart-*` on the root, `data-webx-cart-body` on the main view's panel and
    `data-webx-cart-body="preview"` on the mini cart's, `data-webx-cart-count` on the counter.
    Classes: `### Classes` in AGENTS.md.
--}}
@php
    $empty = '<noscript><p class="webx-cart__empty">'.e(__('webx-cart::widget.no_script')).'</p></noscript>';
    $body = $content ?? $empty;
@endphp
<div {{ $attributes->class([
    'webx-cart',
    'webx-cart--drawer' => $mode === 'drawer',
    'webx-cart--dialog' => $mode === 'dialog',
    'webx-cart--preview' => $preview === 'dropdown',
])->merge([
    'data-webx-cart' => $mode,
    'data-webx-cart-preview' => $preview === 'dropdown' ? $preview : null,
    'data-webx-cart-panel' => $id,
    'data-webx-cart-api' => $api,
    'data-webx-cart-csrf' => $csrf,
    'data-webx-cart-added' => __('webx-cart::widget.added'),
    'data-webx-cart-error' => __('webx-cart::widget.error'),
]) }}>
    @if ($preview === 'dropdown')
        <x-webx-dropdown placement="bottom-end">
            <x-slot:trigger class="webx-cart__trigger">@include('webx-cart::trigger')</x-slot:trigger>
            <div class="webx-cart__panel webx-cart__panel--preview" data-webx-cart-body="preview">{!! $empty !!}</div>
        </x-webx-dropdown>
    @else
        <a class="webx-cart__trigger" href="#{{ $id }}" data-webx-dialog="{{ $id }}" aria-haspopup="dialog">@include('webx-cart::trigger')</a>
    @endif
    <x-webx-dialog class="webx-cart__dialog" :id="$id" :title="$heading" :close="__('webx-cart::widget.close')" :drawer="$mode === 'drawer'" sheet>
        <div class="webx-cart__panel" data-webx-cart-body @if ($returned) data-webx-returned @endif>{!! $body !!}</div>
    </x-webx-dialog>
    @if ($script)
        <script type="module" src="{{ $script }}"></script>
    @endif
</div>
