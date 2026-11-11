{{--
    One banner (§4.3 of the layers spec): the frame, the picture, the video, and the layers on a
    stage with the picture's proportions — or, without layers, the banner's words and buttons in a
    corner. Every length of a layer is a percent of the picture's width; layers.css says how.

    The picture is the poster too: `$still` is the poster where a video plays, else the picture,
    and the videos — the widgets' backdrops — fade in over it (the component says which plays at
    which width).

    With `parallax`, the picture and the videos are in a second __picture (__drift) that moves; the
    outer one crops it, and the stage stays put. `medium` is the attribute bare, the others its value.

    A button's <a> is LinkAttributes::of(): href, the new tab and its rel, and `download` with the
    name a file of the library was uploaded under.

    Classes: see resources/css/layers.css.
--}}
@php
    $image = $still;
    $mobile = $stillMobile;
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
            @if ($parallax)
                <div class="webx-banner__picture webx-banner__drift" {!! $parallax === 'medium' ? 'data-webx-parallax' : 'data-webx-parallax="'.e($parallax).'"' !!}>
            @endif
            <picture>
                @if (is_array($mobile) && $phone !== null)
                    <source media="{{ $phone }}" srcset="{{ $mobile['url'] }}" @if ($mobile['width'] ?? null) width="{{ $mobile['width'] }}" height="{{ $mobile['height'] }}" @endif>
                @endif
                <img {{ (new \Illuminate\View\ComponentAttributeBag(array_filter([
                    'class' => 'webx-banner__image',
                    'src' => $image['url'],
                    // A poster with no words of its own stands for the picture: it says the picture's.
                    'alt' => (string) (($image['alt'] ?? '') !== '' ? $image['alt'] : ($banner['image']['alt'] ?? '')),
                    'width' => $image['width'] ?? null,
                    'height' => $image['height'] ?? null,
                    ...$loading,
                ], static fn ($value) => $value !== null))) }}>
            </picture>
            @foreach ($videos as $video)
                <x-webx-video variant="background" :file="$video['file']" :class="trim('webx-banner__video '.$video['class'])" />
            @endforeach
            @if ($parallax)
                </div>
            @endif
        </div>
    @endif

    @if ($layers !== [])
        <div class="webx-banner__stage" data-webx-overlap-fade>
            @foreach ($layers as $layer)
                @switch ($layer['type'])
                    @case('text')
                        <{{ $layer['tag'] }} class="webx-banner__layer webx-banner__layer--text {{ $layer['class'] }}" style="{{ $layer['style'] }}"><span class="webx-banner__inner">{!! nl2br(e($layer['text'])) !!}</span></{{ $layer['tag'] }}>
                        @break
                    @case('image')
                        <div class="webx-banner__layer webx-banner__layer--image {{ $layer['class'] }}" style="{{ $layer['style'] }}"><img class="webx-banner__inner" src="{{ $layer['image']['url'] }}" alt="{{ $layer['image']['alt'] ?? '' }}" @if ($layer['image']['width'] ?? null) width="{{ $layer['image']['width'] }}" height="{{ $layer['image']['height'] }}" @endif></div>
                        @break
                    @case('button')
                        <div class="webx-banner__layer webx-banner__layer--button {{ $layer['class'] }}" style="{{ $layer['style'] }}"><span class="webx-banner__inner webx-banner__buttons"><a class="webx-banner__button webx-banner__button--{{ $layer['button']['variant'] }}" {{ \WebxUi\Admin\Links\LinkAttributes::of($layer['button']) }}>{{ $layer['button']['label'] }}</a></span></div>
                        @break
                    @case('buttons')
                        <div class="webx-banner__layer webx-banner__layer--buttons {{ $layer['class'] }}" style="{{ $layer['style'] }}"><span class="webx-banner__inner webx-banner__buttons">@foreach ($layer['buttons'] as $button)<a class="webx-banner__button webx-banner__button--{{ $button['variant'] }}" {{ \WebxUi\Admin\Links\LinkAttributes::of($button) }}>{{ $button['label'] }}</a>@endforeach</span></div>
                        @break
                @endswitch
            @endforeach
        </div>
    @elseif (($banner['title'] ?? '') !== '' || ($banner['text'] ?? '') !== '' || ($banner['buttons'] ?? []) !== [])
        <div class="webx-banner__content" data-webx-overlap-fade>
            @if (($banner['title'] ?? '') !== '')
                <h2 class="webx-banner__title">{{ $banner['title'] }}</h2>
            @endif
            @if (($banner['text'] ?? '') !== '')
                <p class="webx-banner__text">{!! nl2br(e($banner['text'])) !!}</p>
            @endif
            @if (($banner['buttons'] ?? []) !== [])
                <div class="webx-banner__buttons">
                    @foreach ($banner['buttons'] as $button)
                        <a class="webx-banner__button webx-banner__button--{{ $button['variant'] }}" {{ \WebxUi\Admin\Links\LinkAttributes::of($button) }}>{{ $button['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
