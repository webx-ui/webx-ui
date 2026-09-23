<li @class([
    'wx-menu__item',
    'wx-menu__item--'.$item->variant,
    'wx-menu__item--heading' => $item->isHeading,
    'wx-menu__item--parent' => $item->hasChildren(),
    'is-active' => $item->isActive(),
    'is-current' => $item->isCurrent(),
])>
    @if ($item->isHeading || $item->url === null)
        <span class="wx-menu__label">{{ $item->label }}</span>
    @else
        <a class="wx-menu__link"
           href="{{ $item->url }}"
           @if ($item->newTab) target="_blank" @endif
           @if ($item->rel !== null) rel="{{ $item->rel }}" @endif
           @if ($item->isCurrent()) aria-current="page" @endif>{{ $item->label }}</a>
    @endif

    @if ($item->hasChildren())
        <ul class="wx-menu__list wx-menu__list--{{ $depth + 1 }}">
            @foreach ($item->children as $child)
                @include('webx-menu::item', ['item' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
