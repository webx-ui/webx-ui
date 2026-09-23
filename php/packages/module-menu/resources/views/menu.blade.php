{{--
    The markup a new site starts with, and the markup it replaces.

    Classes and nothing else: no styling ships with this package, because a menu belongs to the
    design of the site rather than to the panel that edits it.
--}}
<ul {{ $attributes->merge(['class' => 'wx-menu']) }}>
    @foreach ($items as $item)
        @include('webx-menu::item', ['item' => $item, 'depth' => 0])
    @endforeach
</ul>
