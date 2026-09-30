{{--
    One value of the filter: a link with its count, or grey text when it would leave nothing.
    The children of a tree's value are drawn under it. A value with a colour or a picture
    (a property's swatch) gets a dot before its label; what the dot looks like is the site's.
--}}
@php($swatch = $option->swatch)
<li class="webx-catalog-filter__option{{ $option->selected ? ' is-selected' : '' }}{{ $option->disabled() ? ' is-disabled' : '' }}">
    @if ($option->disabled())
        <span>@include('webx-catalog::filter.swatch', ['swatch' => $swatch]){{ $option->label }}</span>
    @else
        <a href="{{ $option->url }}"@if ($option->nofollow) rel="nofollow"@endif @if ($option->selected) aria-current="true"@endif>@include('webx-catalog::filter.swatch', ['swatch' => $swatch]){{ $option->label }}</a>
    @endif
    <small>{{ $option->count }}</small>
    @if ($option->children !== [])
        <ul>
            @foreach ($option->children as $child)
                @include('webx-catalog::filter.option', ['option' => $child])
            @endforeach
        </ul>
    @endif
</li>
