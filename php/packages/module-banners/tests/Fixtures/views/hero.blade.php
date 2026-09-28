@php($banners = banners($place)->get())
@php($layout = banners_layout($place))
<main>
@if ($banners !== [])
  <section class="hero hero--{{ $layout['layout'] }}" style="--ratio: {{ $layout['ratio'] }}" data-banners='@json($layout)'>
    @foreach ($banners as $banner)
      <article id="{{ $banner['anchor'] }}">
        <img src="{{ $banner['image']['url'] }}" alt="{{ $banner['image']['alt'] ?? '' }}" width="{{ $banner['image']['width'] }}" height="{{ $banner['image']['height'] }}">
        <h2>{{ $banner['title'] }}</h2>
        <p>{!! nl2br(e($banner['text'])) !!}</p>
        @foreach ($banner['buttons'] as $button)
          <a class="button button--{{ $button['variant'] }}" href="{{ $button['url'] }}">{{ $button['label'] }}</a>
        @endforeach
      </article>
    @endforeach
  </section>
@endif
</main>
