{{--
    The cart's widget (§9.1): the trigger with the counter and the panel of its mode — a drawer or a
    dialog of the widgets (`<x-webx-dialog>`), or a dropdown under the icon. The panel is empty on
    the page and the script fills it from the API when it opens (§9.2); a page that came back from
    a buy button without JavaScript prints it open and full (§9.5).

    Override: <layer>/views/vendor/webx-cart/cart.blade.php — keep `data-webx-cart` and its
    `data-webx-cart-*` on the root, `data-webx-cart-body` on the panel, `data-webx-cart-count` on
    the counter. Classes: `### Classes` in AGENTS.md.
--}}
@php
    $body = $content ?? '<noscript><p class="webx-cart__empty">'.e(__('webx-cart::widget.no_script')).'</p></noscript>';
@endphp
<div {{ $attributes->class([
    'webx-cart',
    'webx-cart--drawer' => $mode === 'drawer',
    'webx-cart--dialog' => $mode === 'dialog',
    'webx-cart--dropdown' => $mode === 'dropdown',
])->merge([
    'data-webx-cart' => $mode,
    'data-webx-cart-panel' => $mode === 'dropdown' ? null : $id,
    'data-webx-cart-api' => $api,
    'data-webx-cart-csrf' => $csrf,
    'data-webx-cart-added' => __('webx-cart::widget.added'),
    'data-webx-cart-error' => __('webx-cart::widget.error'),
]) }}>
    @if ($mode === 'dropdown')
        <x-webx-dropdown placement="bottom-end" :open="$returned">
            <x-slot:trigger class="webx-cart__trigger">@include('webx-cart::trigger')</x-slot:trigger>
            <div class="webx-cart__panel" data-webx-cart-body @if ($returned) data-webx-returned @endif>{!! $body !!}</div>
        </x-webx-dropdown>
    @else
        <a class="webx-cart__trigger" href="#{{ $id }}" data-webx-dialog="{{ $id }}" aria-haspopup="dialog">@include('webx-cart::trigger')</a>
        <x-webx-dialog class="webx-cart__dialog" :id="$id" :title="$heading" :close="__('webx-cart::widget.close')" :drawer="$mode === 'drawer'" sheet>
            <div class="webx-cart__panel" data-webx-cart-body @if ($returned) data-webx-returned @endif>{!! $body !!}</div>
        </x-webx-dialog>
    @endif
    @if ($script)
        <script type="module" src="{{ $script }}"></script>
    @endif
</div>
