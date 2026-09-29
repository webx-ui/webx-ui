{{--
    One value of the filter: a link with its count, or grey text when it would leave nothing.
    The children of a tree's value are drawn under it.
--}}
<li class="webx-catalog-filter__option{{ $option->selected ? ' is-selected' : '' }}{{ $option->disabled() ? ' is-disabled' : '' }}">
    @if ($option->disabled())
        <span>{{ $option->label }}</span>
    @else
        <a href="{{ $option->url }}"@if ($option->nofollow) rel="nofollow"@endif @if ($option->selected) aria-current="true"@endif>{{ $option->label }}</a>
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
