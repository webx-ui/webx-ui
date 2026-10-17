{{-- <img src="commented.png"> in a comment is not a finding. --}}
@php $html = '<img src="in-php.png">'; @endphp
<figure class="gallery">
    <img src="{{ $picture->url() }}" alt="">
    <img src="{{ $picture['url'] }}" alt="" width="{{ $picture['width'] }}">
    <img src="{{ $picture['url'] }}" alt="" @if (! empty($picture['width'])) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif>
    <img src="{{ $picture['url'] }}" alt="" data-width="1200" data-height="800">
    <img {{ $attributes->merge(['class' => 'gallery__picture']) }} alt="">
    <img
        src="{{ $picture['url'] }}"
        alt=""
        width="1200" height="800"
        loading="lazy"
    >
</figure>
