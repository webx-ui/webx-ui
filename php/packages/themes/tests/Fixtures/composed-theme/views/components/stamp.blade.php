@php($tags = $article->tags->map(fn ($tag) => $tag->title))
@foreach ($tags as $index => $title)
<span>{{ $loop->iteration }}. {{ $title }} {{ $year }}</span>
@endforeach
