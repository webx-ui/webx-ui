<header class="wx-press-outlet__heading">
    {{-- The logo is the name: printed as text only when there is no logo to print. --}}
    @if ($logo)
        <h1 class="wx-press-outlet__logo">
            <img class="wx-press-outlet__logo-image" src="{{ $logo['url'] }}" alt="{{ $title }}" @if ($logo['width'] ?? null) width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" @endif>
        </h1>
    @else
        <h1 class="wx-press-outlet__title">{{ $title }}</h1>
    @endif
    @if ($summary !== '')
        <p class="wx-press-outlet__summary">{!! nl2br(e($summary)) !!}</p>
    @endif
</header>
