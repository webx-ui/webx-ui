{{--
    The extra fields of one place (WEBX_UI_ORDER_FIELDS.md §4.5), from the view of a step:
    `@include('webx-orders::checkout.parts.fields', ['place' => '<key>'])`. `$groups` comes from
    `CheckoutFields::view()` (a composer): a field shown always, or a checkbox with the fields behind
    it — those are hidden by `checkout.css` while it is not ticked, and the server ignores them then.
    No fields in the place — nothing at all.

    Classes: webx-orders-checkout__fields, __fields--<place>, __field, __field--<key>, __field-group,
    __field-when.
--}}
@if ($groups !== [])
    <div class="webx-orders-checkout__fields webx-orders-checkout__fields--{{ $place }}">
        @foreach ($groups as $group)
            @if ($group['when'] === [])
                @include('webx-orders::checkout.parts.field', ['field' => $group['field']])
            @else
                <div class="webx-orders-checkout__field-group">
                    @include('webx-orders::checkout.parts.field', ['field' => $group['field']])
                    <div class="webx-orders-checkout__field-when">
                        @foreach ($group['when'] as $field)
                            @include('webx-orders::checkout.parts.field', ['field' => $field])
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endif
