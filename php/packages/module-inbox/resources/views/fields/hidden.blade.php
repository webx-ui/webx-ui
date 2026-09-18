{{-- What the site fills in and the visitor never sees: which page, which product, which
     campaign. The value comes from `<x-webx-form :values="[…]" />`, keyed by the field's
     machine name — which is the whole reason a field has one (§2.6). --}}
<input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ is_scalar($value) ? $value : '' }}">
