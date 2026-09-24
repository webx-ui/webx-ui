{{-- Printed raw: the field type cleaned the document on the way in. --}}
@if ($method !== '')
    <section class="wx-recipe__method">
        <h2>{{ trans('webx-recipes::site.method') }}</h2>
        {!! $method !!}
    </section>
@endif
