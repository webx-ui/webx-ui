@if ($related->isNotEmpty())
<aside class="webx-blog-related">
    <h2 class="webx-blog-related__title">{{ trans('webx-blog::blog.related') }}</h2>
    @include('webx-blog::partials.cards', ['articles' => $related])
</aside>
@endif
