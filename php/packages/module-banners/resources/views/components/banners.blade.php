{{--
    A place whole, in its layout (§4.3 of the layers spec). The width is the parent's; nothing at
    all when the place is empty (the component does not render).

    Classes: webx-banners, webx-banners--<layout>, webx-banners--overflow, __strip, __item.
--}}
<div {{ $attributes->class([
    'webx-banners',
    'webx-banners--'.$options['layout'],
    'webx-banners--overflow' => $overflow,
])->merge(['data-place' => $place]) }}>
    @if ($slider)
        @include('webx-banners::components.banners-slider')
    @elseif ($options['layout'] === 'slider' && count($banners) > 1)
        {{-- No theme to style the widgets' slider: a strip that scrolls sideways and snaps to a
             banner; the script plays it and adds the arrows and the dots. --}}
        <div class="webx-banners__strip" role="region" aria-roledescription="carousel" aria-label="{{ $label ?? $place }}" tabindex="0"
             data-interval="{{ $options['autoplay'] ? (int) $options['interval'] : 0 }}" data-loop="{{ $options['loop'] ? 'true' : 'false' }}"
             data-pause="{{ ($options['pause_on_hover'] ?? true) ? 'true' : 'false' }}">
            @foreach ($banners as $banner)
                <x-webx-banner class="webx-banners__item" role="group" aria-roledescription="slide" :aria-label="($loop->iteration).' / '.count($banners)" :banner="$banner" :layout="$options" :eager="$loop->first" />
            @endforeach
        </div>
        <div class="webx-banners__controls" hidden>
            @if ($options['arrows'])
                <button type="button" class="webx-banners__arrow webx-banners__prev" aria-label="{{ __('webx-banners::banner.previous') }}">‹</button>
            @endif
            @if ($options['dots'])
                <div class="webx-banners__dots"></div>
            @endif
            @if ($options['arrows'])
                <button type="button" class="webx-banners__arrow webx-banners__next" aria-label="{{ __('webx-banners::banner.next') }}">›</button>
            @endif
        </div>
        <script>{!! app(\WebxUi\Banners\Rendering\Stylesheet::class)->scripts() !!}</script>
    @else
        @foreach ($banners as $banner)
            <x-webx-banner class="webx-banners__item" :banner="$banner" :layout="$options" :eager="$loop->first" :hidden="! $loop->first" />
        @endforeach
        @if ($options['layout'] === 'random' && count($banners) > 1)
            <script>{!! app(\WebxUi\Banners\Rendering\Stylesheet::class)->scripts() !!}</script>
        @endif
    @endif
</div>
