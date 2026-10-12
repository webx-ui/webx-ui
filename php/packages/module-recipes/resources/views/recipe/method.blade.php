{{-- Printed raw: the field type cleaned the document on the way in. --}}
@if ($method !== '')
    <section class="wx-recipe__method">
        <h2 class="wx-recipe__section-title">{{ trans('webx-recipes::site.method') }}</h2>
        <div class="wx-recipe__text">{!! $method !!}</div>
    </section>
@endif
