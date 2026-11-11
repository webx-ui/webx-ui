{{-- One extra field (WEBX_UI_ORDER_FIELDS.md §4.5): the widgets' own field, under a class with its key. --}}
<div class="webx-orders-checkout__field webx-orders-checkout__field--{{ $field['key'] }}">
    @if ($field['type'] === 'checkbox')
        <x-webx-field type="checkbox" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :checked="$field['checked']" :required="$field['required']" />
    @elseif ($field['type'] === 'select')
        <x-webx-field type="select" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :value="$field['value']" :choices="$field['choices']" :required="$field['required']" :optional="$field['optional']" />
    @elseif ($field['type'] === 'textarea')
        <x-webx-field type="textarea" :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :value="$field['value']" :maxlength="$field['maxlength']" :rows="3" :required="$field['required']" :optional="$field['optional']" />
    @else
        <x-webx-field :name="$field['name']" :label="$field['label']" :hint="$field['hint']" :value="$field['value']" :maxlength="$field['maxlength']" :required="$field['required']" :optional="$field['optional']" />
    @endif
</div>
