{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run
     after the blocks themselves — and a slot is worked out before the layout around it, so this
     line has to stay at the top of the file rather than move inside the tag. --}}
@php($content = $page->renderBlocks())

<x-dynamic-component :component="config('webx-pages.layout') ?: 'webx-pages::standalone'">
    <x-slot:head>
        {{-- Everything the page says about itself; the title is in there too. --}}
        @webxSeo($page)
        {{-- The styles and scripts of exactly the block types this page used. --}}
        @webxBlocks
    </x-slot:head>

    {!! $content !!}
</x-dynamic-component>
