{{-- A payment mark (groups spec §3.2): the file as an image, or a plate with its words. Never a button, it stands inside the row's label. --}}
@props(['name'])
@php($mark = app(\WebxUi\Payments\PaymentMarks::class)->get($name))
@if ($mark->isPlate())
<span {{ $attributes->class(['webx-payments__mark', 'is-text']) }}>{{ $mark->alt }}</span>
@else
<img {{ $attributes->class(['webx-payments__mark']) }} src="{{ $mark->url() }}" width="{{ $mark->width }}" height="{{ \WebxUi\Payments\PaymentMark::HEIGHT }}" alt="{{ $mark->alt }}">
@endif
