{{-- Everything here goes through Blade's own escaping: a title with a quote in it or a
     description with a `<` must not be able to end an attribute or open a tag. JSON-LD is not
     an attribute, so it is encoded with JSON_HEX_TAG instead — `</script>` inside a string
     would close the block otherwise. --}}
@if (($print['title'] ?? true) && $seo->title !== null)
<title>{{ $seo->title }}</title>
@endif
@if (($print['description'] ?? true) && $seo->description !== null)
<meta name="description" content="{{ $seo->description }}">
@endif
@if (($print['keywords'] ?? true) && $seo->keywords !== null)
<meta name="keywords" content="{{ $seo->keywords }}">
@endif
@if (($print['robots'] ?? true) && $seo->robots !== null)
<meta name="robots" content="{{ $seo->robots }}">
@endif
@if (($print['canonical'] ?? true) && $seo->canonical !== null)
<link rel="canonical" href="{{ $seo->canonical }}">
@endif
@foreach ($alternates ?? [] as $hreflang => $href)
<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
@endforeach
@if ($print['og'] ?? true)
@foreach ($seo->og as $property => $content)
<meta property="og:{{ $property }}" content="{{ $content }}">
@endforeach
@endif
{{-- The one Twitter line Open Graph cannot say for it; the rest it reads from og:*. --}}
@if (($print['twitter'] ?? true) && isset($twitter))
<meta name="twitter:card" content="{{ $twitter }}">
@endif
@foreach ($blocks ?? [] as $block)
<script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
