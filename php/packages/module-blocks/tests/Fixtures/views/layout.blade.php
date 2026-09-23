<!doctype html>
<html>
<head>
    <link rel="stylesheet" href="/site.css">
    {{ $head ?? '' }}
    @stack('head')
</head>
<body>
<header class="site-header">Header of the site</header>
<main>{{ $slot }}</main>
<footer class="site-footer">Footer of the site</footer>
</body>
</html>
