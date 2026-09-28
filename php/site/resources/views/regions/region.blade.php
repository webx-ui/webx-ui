{{--
    `<x-webx-blocks::region>` on a site without webx-ui/module-blocks.

    The layout prints its header and footer through that tag, and Blade resolves a tag when it
    compiles the view — so without the module the layout would not compile at all, whatever
    `@if` stood around it. `AppServiceProvider` answers the tag with this file instead, and
    this file prints what the tag would print for an empty region: its fallback, with the
    tag's other attributes as the fallback's variables.

    Installing the module later takes over by itself — its class wins over this file — once
    the compiled layout is gone: `php artisan view:clear`.
--}}
@props(['name', 'fallback' => null])

@if ($fallback)
    @include($fallback, $attributes->getAttributes())
@endif
