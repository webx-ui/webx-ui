{{--
    Values with a count each: brands, colours. A long list folds into `<details>` — the rest is
    one click away without a script.
--}}
@php($shown = 10)
<ul>
    @foreach (array_slice($group->options, 0, $shown) as $option)
        @include('webx-catalog::filter.option', ['option' => $option])
    @endforeach
</ul>
@if (count($group->options) > $shown)
    <details>
        <summary>{{ __('webx-catalog::storefront.more') }}</summary>
        <ul>
            @foreach (array_slice($group->options, $shown) as $option)
                @include('webx-catalog::filter.option', ['option' => $option])
            @endforeach
        </ul>
    </details>
@endif
