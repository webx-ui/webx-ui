{{--
    The document every public page is printed in, for as long as no layer above overrides it.

    Modules ask for this component by name (`config('webx-pages.layout')` and friends hold
    'layout') and stand inside it: a `head` slot and the default slot. Their views already
    put `@webxSeo` and `@webxBlocks` into that slot, and neither prints its tags only once —
    a second call is a second <title> and a second bundle. So the layout prints its own pair
    only when nobody handed it a head: a page of the site's own that wrote `<x-layout>` and
    nothing else still gets its metatags and the styles of the blocks it rendered.

    `@stack('head')` stays beside the slot: what a block type or a partial pushes cannot
    reach a slot, and a layout without the stack loses metatags without looking broken.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- The site tokens as one inline :root, then the stylesheet of every layer, bottom first. --}}
    @webxTheme

    @if (isset($head) && trim((string) $head) !== '')
        {{ $head }}
    @else
        @webxSeo
        @webxBlocks
    @endif

    @stack('head')
</head>
<body class="site">
    <a class="site-skip" href="#content">{{ __('Skip to content') }}</a>

    {{--
        Regions: trees of blocks an editor arranges in the panel, with the view in `fallback`
        printed while a region is empty or unpublished. `components.header` is looked up through
        the chain like any view, so a local theme replaces the fallback by having the file.
    --}}
    <x-webx-blocks::region name="header" fallback="components.header" />

    <main id="content" class="site-main">{{ $slot }}</main>

    <x-webx-blocks::region name="footer" fallback="components.footer" />

    {{-- Quick contact (WIDGETS §12.4): the chats, the number, the e-mail of the Contacts tab; nothing while it is empty. --}}
    <x-webx-contact-button />

    @stack('scripts')
</body>
</html>
