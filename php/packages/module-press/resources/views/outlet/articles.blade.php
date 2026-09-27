{{-- In the outlet's own order (decision 6): the order the editor put them in, not the date. --}}
<ul class="wx-press-outlet__articles">
    @foreach ($articles as $article)
        @include('webx-press::partials.article', ['article' => $article])
    @endforeach
</ul>
