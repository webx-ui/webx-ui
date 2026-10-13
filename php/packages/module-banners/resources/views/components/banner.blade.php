{{--
    One banner (§4.3 of the layers spec): the frame, the picture, the video, and the layers on a
    stage with the picture's proportions — or, without layers, the banner's words and buttons in a
    corner. Every length of a layer is a percent of the picture's width; layers.css says how.

    Classes: see resources/css/layers.css.
--}}
@php
    $image = $banner['image'] ?? null;
    $mobile = $banner['image_mobile'] ?? null;
    $video = $banner['video'] ?? null;
    $layers = $banner['layers'] ?? [];
    $loading = $eager === null ? [] : ['loading' => $eager ? 'eager' : 'lazy', 'fetchpriority' => $eager ? 'high' : null];
    $phone = $breakpoint > 1 ? '(max-width: '.($breakpoint - 1).'px)' : null;
@endphp
@if ($css !== '')
    <style data-webx-banners>{!! $css !!}</style>
@endif
<div {{ $attributes->class(['webx-banner'])->merge([
    'id' => $banner['anchor'] ?? null,
    'style' => $frameStyle,
    'data-breakpoint' => $breakpoint,
]) }}>
    @if (is_array($image))
        <div class="webx-banner__picture">
            <picture>
                @if (is_array($mobile) && $phone !== null)
                    <source media="{{ $phone }}" srcset="{{ $mobile['url'] }}" @if ($mobile['width'] ?? null) width="{{ $mobile['width'] }}" height="{{ $mobile['height'] }}" @endif>
                @endif
                <img {{ (new \Illuminate\View\ComponentAttributeBag(array_filter([
                    'class' => 'webx-banner__image',
                    'src' => $image['url'],
                    'alt' => (string) ($image['alt'] ?? ''),
                    'width' => $image['width'] ?? null,
                    'height' => $image['height'] ?? null,
                    ...$loading,
                ], static fn ($value) => $value !== null))) }}>
            </picture>
            @if (is_array($video))
                <video class="webx-banner__video" muted playsinline loop preload="none" aria-hidden="true"
                       poster="{{ $image['url'] }}" data-src="{{ $video['url'] }}"
                       data-breakpoint="{{ $breakpoint }}" data-on-mobile="{{ ($options['video_on_mobile'] ?? false) ? 'true' : 'false' }}"></video>
            @endif
        </div>
    @endif

    @if ($layers !== [])
        <div class="webx-banner__stage">
            @foreach ($layers as $layer)
                @switch ($layer['type'])
                    @case('text')
                        <{{ $layer['tag'] }} class="webx-banner__layer webx-banner__layer--text {{ $layer['class'] }}" style="{{ $layer['style'] }}"><span class="webx-banner__inner">{!! nl2br(e($layer['text'])) !!}</span></{{ $layer['tag'] }}>
                        @break
                    @case('image')
                        <div class="webx-banner__layer webx-banner__layer--image {{ $layer['class'] }}" style="{{ $layer['style'] }}"><img class="webx-banner__inner" src="{{ $layer['image']['url'] }}" alt="{{ $layer['image']['alt'] ?? '' }}" @if ($layer['image']['width'] ?? null) width="{{ $layer['image']['width'] }}" height="{{ $layer['image']['height'] }}" @endif></div>
                        @break
                    @case('button')
                        <div class="webx-banner__layer webx-banner__layer--button {{ $layer['class'] }}" style="{{ $layer['style'] }}"><span class="webx-banner__inner webx-banner__buttons"><a class="webx-banner__button webx-banner__button--{{ $layer['button']['variant'] }}" href="{{ $layer['button']['url'] }}" @if ($layer['button']['new_tab']) target="_blank" @endif @if ($layer['button']['rel']) rel="{{ $layer['button']['rel'] }}" @endif>{{ $layer['button']['label'] }}</a></span></div>
                        @break
                    @case('buttons')
                        <div class="webx-banner__layer webx-banner__layer--buttons {{ $layer['class'] }}" style="{{ $layer['style'] }}"><span class="webx-banner__inner webx-banner__buttons">@foreach ($layer['buttons'] as $button)<a class="webx-banner__button webx-banner__button--{{ $button['variant'] }}" href="{{ $button['url'] }}" @if ($button['new_tab']) target="_blank" @endif @if ($button['rel']) rel="{{ $button['rel'] }}" @endif>{{ $button['label'] }}</a>@endforeach</span></div>
                        @break
                @endswitch
            @endforeach
        </div>
    @elseif (($banner['title'] ?? '') !== '' || ($banner['text'] ?? '') !== '' || ($banner['buttons'] ?? []) !== [])
        <div class="webx-banner__content">
            @if (($banner['title'] ?? '') !== '')
                <h2 class="webx-banner__title">{{ $banner['title'] }}</h2>
            @endif
            @if (($banner['text'] ?? '') !== '')
                <p class="webx-banner__text">{!! nl2br(e($banner['text'])) !!}</p>
            @endif
            @if (($banner['buttons'] ?? []) !== [])
                <div class="webx-banner__buttons">
                    @foreach ($banner['buttons'] as $button)
                        <a class="webx-banner__button webx-banner__button--{{ $button['variant'] }}" href="{{ $button['url'] }}" @if ($button['new_tab']) target="_blank" @endif @if ($button['rel']) rel="{{ $button['rel'] }}" @endif>{{ $button['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
@if ($scripts !== '')
    <script>{!! $scripts !!}</script>
@endif
