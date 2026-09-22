{{-- A site's layout, the way the skeleton writes one: both places for the head, a header and a
     footer around the content. Registered as an anonymous component path by the test, so that
     `<x-layout>` here is the same tag a real site would have. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
<header>The site header</header>
<main>{{ $slot }}</main>
<footer>The site footer</footer>
</body>
</html>
