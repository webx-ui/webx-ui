{{--
    A value's colour or picture before its label (SwatchedFacet): the picture wins when there are
    both. Decorative — the label beside it says the same in words.
--}}
@if (is_array($swatch ?? null))
    @if (! empty($swatch['image']))
        <img class="webx-catalog-filter__swatch" src="{{ $swatch['image'] }}" alt="" width="12" height="12" loading="lazy">
    @elseif (! empty($swatch['color']))
        <span class="webx-catalog-filter__swatch" style="background-color: {{ $swatch['color'] }}" aria-hidden="true"></span>
    @endif
@endif
