{{-- Time, servings, the categories as links, what it is rich in as chips. Nothing known — no list. --}}
@if ($minutes || $servings || $categories !== [] || $nutrients !== [])
    <ul class="wx-recipe__facts">
        @if ($minutes)
            <li>{{ trans('webx-recipes::site.time') }}: {{ WebxUi\Recipes\Rendering\Duration::format($minutes) }}</li>
        @endif
        @if ($servings)
            <li>{{ trans('webx-recipes::site.servings', ['count' => $servings]) }}</li>
        @endif
        @if ($categories !== [])
            <li>
                @foreach ($categories as $category)
                    <a href="{{ $category['url'] }}">{{ $category['title'] }}</a>@if (! $loop->last), @endif
                @endforeach
            </li>
        @endif
        @if ($nutrients !== [])
            <li>{{ trans('webx-recipes::site.nutrients') }}:
                @foreach ($nutrients as $nutrient)
                    <span class="wx-recipe__chip">{{ $nutrient['title'] }}</span>
                @endforeach
            </li>
        @endif
    </ul>
@endif
