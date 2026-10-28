{{-- The buy button (§9.3): a form around it, so it puts the line into the cart without JavaScript
     too and comes back to the page; with the widget's script the click is sent in place by its
     data attributes. `is-in-cart` is known on the server, without a request from the page. --}}
@props([
    'type',
    'ref',
    'qty' => 1,
    'options' => null,
])

@php
    $inCart = cart()->has((string) $type, (int) $ref);
    $options = is_array($options) && $options !== [] ? $options : null;
@endphp

<form method="post" action="{{ route('webx-cart.items.store') }}" class="webx-cart-add-form">
    @csrf
    <input type="hidden" name="items[0][type]" value="{{ $type }}">
    <input type="hidden" name="items[0][ref]" value="{{ (int) $ref }}">
    <input type="hidden" name="items[0][qty]" value="{{ (int) $qty }}">
    @if ($options !== null)
        @foreach (\Illuminate\Support\Arr::dot($options) as $key => $value)
            <input type="hidden" name="items[0][options]{{ collect(explode('.', (string) $key))->map(fn ($part) => '['.$part.']')->implode('') }}" value="{{ $value }}">
        @endforeach
    @endif
    <button
        type="submit"
        {{ $attributes->class(['webx-cart-add', 'is-in-cart' => $inCart]) }}
        data-webx-cart-add="{{ $type }}:{{ (int) $ref }}"
        data-webx-cart-qty="{{ (int) $qty }}"
        @if ($options !== null) data-webx-cart-options="{{ json_encode($options) }}" @endif
    >{{ $slot->isEmpty() ? __('webx-cart::widget.add') : $slot }}</button>
</form>
