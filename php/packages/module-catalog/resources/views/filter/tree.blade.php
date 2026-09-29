{{--
    Values with ancestors: on a category page, its children — each a move to its own page; on the
    root or in the search, the tree of categories to filter by (§8.3 of the architecture).
--}}
<ul>
    @foreach ($group->options as $option)
        @include('webx-catalog::filter.option', ['option' => $option])
    @endforeach
</ul>
