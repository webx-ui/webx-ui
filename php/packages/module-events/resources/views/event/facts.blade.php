{{-- When, where, how much, filed under what. Nothing known — no list. --}}
@if ($when !== '' || $online || $venue !== '' || $address !== '' || $price !== '' || $categories !== [])
    <dl class="wx-event__facts">
        @if ($when !== '')
            <dt class="wx-event__term">{{ trans('webx-events::site.when') }}</dt>
            <dd class="wx-event__value">{{ $when }}</dd>
        @endif
        @if ($online)
            <dt class="wx-event__term">{{ trans('webx-events::site.where') }}</dt>
            <dd class="wx-event__value">{{ trans('webx-events::site.online') }}</dd>
        @elseif ($venue !== '' || $address !== '')
            <dt class="wx-event__term">{{ trans('webx-events::site.where') }}</dt>
            <dd class="wx-event__value">
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
            <dt class="wx-event__term">{{ trans('webx-events::site.price') }}</dt>
            <dd class="wx-event__value">{{ $price }}</dd>
        @endif
        @if ($categories !== [])
            <dt class="wx-event__term">{{ trans('webx-events::site.categories') }}</dt>
            <dd class="wx-event__value">
                @foreach ($categories as $category)
                    <a class="wx-event__category" href="{{ $category['url'] }}">{{ $category['title'] }}</a>@if (! $loop->last), @endif
                @endforeach
            </dd>
        @endif
    </dl>
@endif
