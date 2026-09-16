{{-- The content first: `@webxBlocks` prints the bundle of what was rendered, so it has to run after the blocks themselves. --}}
@php($content = $page->renderBlocks())
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Everything the page says about itself; the title is in there too. --}}
    @webxSeo($page)
    {{-- The styles and scripts of exactly the block types this page used. --}}
    @webxBlocks
</head>
<body>
{!! $content !!}
</body>
</html>
