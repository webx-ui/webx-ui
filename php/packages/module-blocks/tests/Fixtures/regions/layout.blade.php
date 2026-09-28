<!doctype html>
<html>
<head>
{{ $head ?? '' }}
@webxBlocks('styles')
</head>
<body>
<div class="frame">
<x-webx-blocks::region name="header" fallback="region-site::header" tone="dark" />
</div>
<main>{{ $slot }}</main>
<x-webx-blocks::region name="footer" />
</body>
</html>
