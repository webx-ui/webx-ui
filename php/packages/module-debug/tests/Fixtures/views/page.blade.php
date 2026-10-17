<html>
<body>
@php($count = \Illuminate\Support\Facades\DB::table('demo_articles')->count())
<p>{{ $count }} articles</p>
</body>
</html>
