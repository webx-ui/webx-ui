{{--
    A number between two ends: the price. A form, because numbers cannot be links — but a form
    that needs no script: it sends `?range[price][from]=…&range[price][to]=…` to the page without
    this range, which answers with a redirect to the address that has it as a segment. A slider is
    the site's to add on top.
--}}
@php($range = $group->range)
<form method="get" action="{{ $range['action'] }}" class="webx-catalog-filter__range">
    @foreach (array_filter(['q' => $page->search, 'sort' => $page->context->query['sort'] ?? null]) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <label>
        {{ __('webx-catalog::storefront.from') }}
        <input type="number" name="{{ $range['field'] }}[from]" min="0" step="any"
               value="{{ $range['from'] ?? '' }}" placeholder="{{ $range['min'] !== null ? floor($range['min']) : '' }}">
    </label>
    <label>
        {{ __('webx-catalog::storefront.to') }}
        <input type="number" name="{{ $range['field'] }}[to]" min="0" step="any"
               value="{{ $range['to'] ?? '' }}" placeholder="{{ $range['max'] !== null ? ceil($range['max']) : '' }}">
    </label>
    <button type="submit">{{ __('webx-catalog::storefront.apply') }}</button>
    @if ($group->resetUrl !== null)
        <a href="{{ $group->resetUrl }}" rel="nofollow">{{ __('webx-catalog::storefront.reset') }}</a>
    @endif
</form>
