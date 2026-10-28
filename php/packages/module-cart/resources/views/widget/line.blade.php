{{--
    One line (§6.2): the picture, the name — a link while the record exists, the snapshot of its
    name when it is gone —, the options, the price with the old one struck out when it fell, the
    stepper and the remove button, the notices and warnings about it. The dropdown is the mini cart: the quantity as a number, no
    stepper. A generated line (a gift of a rule) has no stepper; it is declined, not removed, and
    only when it says it may be. The children of a bundle go under it.
--}}
@php
    $stored = $line['source'] === null;
    $removable = $stored || $line['declinable'];
    $stepper = $controls && $stored && ! $child && ! $line['gone'];
    $max = min((int) ($line['max_qty'] ?? config('webx-cart.qty_max', 999)), (int) config('webx-cart.qty_max', 999));
@endphp
<li @class(['webx-cart__line', 'is-unavailable' => ! $line['available']]) data-webx-cart-line="{{ $line['id'] }}">
    @if ($line['image'] !== null)
        <img class="webx-cart__line-image" src="{{ $line['image'] }}" alt="" loading="lazy">
    @endif

    <div class="webx-cart__line-body">
        @if ($line['url'] !== null)
            <a class="webx-cart__line-name" href="{{ $line['url'] }}">{{ $line['name'] }}</a>
        @else
            <span class="webx-cart__line-name">{{ $line['name'] }}</span>
        @endif

        @if ($line['label'] !== null || $line['options_label'] !== null)
            <p class="webx-cart__line-options">{{ collect([$line['label'], $line['options_label']])->filter()->implode(' · ') }}</p>
        @endif

        @if ($line['gone'])
            <p class="webx-cart__warning">{{ __('webx-cart::widget.gone') }}</p>
        @elseif (! $line['available'])
            <p class="webx-cart__warning">{{ __('webx-cart::widget.unavailable') }}</p>
        @endif

        @foreach ($warnings[(string) $line['id']] ?? [] as $warning)
            <p @class(['webx-cart__notice' => $warning['notice'], 'webx-cart__warning' => ! $warning['notice']])>{{ $warning['message'] }}</p>
        @endforeach

        @if ($stepper)
            <div class="webx-cart__stepper" role="group" aria-label="{{ __('webx-cart::widget.quantity') }}: {{ $line['name'] }}">
                <button type="button" class="webx-cart__stepper-button" data-webx-cart-step="-1" aria-label="{{ __('webx-cart::widget.decrease') }}">&minus;</button>
                <input class="webx-cart__stepper-input" type="number" inputmode="numeric" min="1" max="{{ $max }}" value="{{ $line['qty'] }}" data-webx-cart-qty-input aria-label="{{ __('webx-cart::widget.quantity') }}">
                <button type="button" class="webx-cart__stepper-button" data-webx-cart-step="1" aria-label="{{ __('webx-cart::widget.increase') }}">+</button>
            </div>
        @elseif ($line['effective_qty'] > 1)
            <p class="webx-cart__line-options">&times;&nbsp;{{ $line['effective_qty'] }}</p>
        @endif
    </div>

    <p class="webx-cart__line-price">
        @if ($line['price_change'] === 'down' && $line['price_at_add_formatted'] !== null)
            <s class="webx-cart__line-old">{{ $line['price_at_add_formatted'] }}</s>
        @endif
        <span>{{ $line['available'] ? $line['line_total_formatted'] : '' }}</span>
    </p>

    @if ($removable && $controls)
        <button type="button" class="webx-cart__remove" data-webx-cart-remove="{{ $line['id'] }}" aria-label="{{ $stored ? __('webx-cart::widget.remove') : __('webx-cart::widget.decline') }}: {{ $line['name'] }}">&times;</button>
    @endif

    {!! \WebxUi\Cart\View\Points::render('cart.line.after', ['line' => $line, 'mode' => $mode]) !!}

    @if ($line['children'] !== [])
        @include('webx-cart::widget.lines', ['lines' => $line['children'], 'children' => true])
    @endif
</li>
