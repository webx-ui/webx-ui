{{--
    On or off, with the number of products it leaves: "in stock".
--}}
<ul>
    @foreach ($group->options as $option)
        @include('webx-catalog::filter.option', ['option' => $option])
    @endforeach
</ul>
