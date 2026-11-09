{{--
    The slider of a place, on <x-webx-slider variant="hero"> of webx-ui/widgets — included only
    when the widgets are installed. A place whose layers may step out of the frame always fades:
    sliding would show the neighbours (decision 13 of the layers spec).
--}}
<x-webx-slider
    variant="hero"
    :label="$label"
    :arrows="(bool) $options['arrows']"
    :pagination="(bool) $options['dots']"
    :loop="(bool) $options['loop']"
    :autoplay="$options['autoplay'] ? (int) $options['interval'] : false"
    :effect="$overflow ? 'fade' : null"
>
    @foreach ($banners as $banner)
        <x-webx-slide>
            {{-- The first picture is the page's largest at once: eager, and first in the queue. The
                 slider makes the others lazy itself. --}}
            <x-webx-banner :banner="$banner" :layout="$options" :eager="$loop->first ? true : null" />
        </x-webx-slide>
    @endforeach
</x-webx-slider>
