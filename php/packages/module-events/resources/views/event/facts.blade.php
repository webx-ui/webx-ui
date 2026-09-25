{{-- When, where, how much, filed under what. Nothing known — no list. --}}
@if ($when !== '' || $online || $venue !== '' || $address !== '' || $price !== '' || $categories !== [])
    <dl class="wx-event__facts">
        @if ($when !== '')
            <dt>{{ trans('webx-events::site.when') }}</dt>
            <dd>{{ $when }}</dd>
        @endif
        @if ($online)
            <dt>{{ trans('webx-events::site.where') }}</dt>
            <dd>{{ trans('webx-events::site.online') }}</dd>
        @elseif ($venue !== '' || $address !== '')
            <dt>{{ trans('webx-events::site.where') }}</dt>
            <dd>
                @if ($venue !== '')
                    <span class="wx-event__venue">{{ $venue }}</span>
                @endif
                @if ($address !== '')
                    <span class="wx-event__address">{{ $address }}</span>
                @endif
                @if ($map_url)
                    <a class="wx-event__map" href="{{ $map_url }}" rel="noopener" target="_blank">{{ trans('webx-events::site.map') }}</a>
                @endif
            </dd>
        @endif
        @if ($price !== '')
            <dt>{{ trans('webx-events::site.price') }}</dt>
            <dd>{{ $price }}</dd>
        @endif
        @if ($categories !== [])
            <dt>{{ trans('webx-events::site.categories') }}</dt>
            <dd>
                @foreach ($categories as $category)
                    <a href="{{ $category['url'] }}">{{ $category['title'] }}</a>@if (! $loop->last), @endif
                @endforeach
            </dd>
        @endif
    </dl>
@endif
