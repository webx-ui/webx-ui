{{--
    The markup a new site starts with, and the markup it replaces.

    Classes and nothing inline: a menu belongs to the design of the site rather than to the panel
    that edits it. Under a theme the classes get a plain base look (`resources/theme/menu.css`,
    THEMES §10.2) that the theme restyles by the same classes.
--}}
<ul {{ $attributes->merge(['class' => 'wx-menu']) }}>
    @foreach ($items as $item)
        @include('webx-menu::item', ['item' => $item, 'depth' => 0])
    @endforeach
</ul>
