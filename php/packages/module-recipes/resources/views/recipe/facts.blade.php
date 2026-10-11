{{-- Time, servings, the categories as links, what it is rich in as chips. Nothing known — no list. --}}
@if ($minutes || $servings || $categories !== [] || $nutrients !== [])
    <ul class="wx-recipe__facts">
        @if ($minutes)
            <li class="wx-recipe__fact wx-recipe__fact--time">{{ trans('webx-recipes::site.time') }}: {{ WebxUi\Recipes\Rendering\Duration::format($minutes) }}</li>
        @endif
        @if ($servings)
            <li class="wx-recipe__fact wx-recipe__fact--servings">{{ trans('webx-recipes::site.servings', ['count' => $servings]) }}</li>
        @endif
        @if ($categories !== [])
            <li class="wx-recipe__fact wx-recipe__fact--categories">
                @foreach ($categories as $category)
                    <a class="wx-recipe__category" href="{{ $category['url'] }}">{{ $category['title'] }}</a>@if (! $loop->last), @endif
                @endforeach
            </li>
        @endif
        @if ($nutrients !== [])
            <li class="wx-recipe__fact wx-recipe__fact--nutrients">{{ trans('webx-recipes::site.nutrients') }}:
                @foreach ($nutrients as $nutrient)
                    <span class="wx-recipe__chip">{{ $nutrient['title'] }}</span>
                @endforeach
            </li>
        @endif
    </ul>
@endif
