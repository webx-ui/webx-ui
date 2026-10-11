{{-- Printed raw: the field type cleaned the document on the way in. --}}
@if ($ingredients !== '')
    <section class="wx-recipe__ingredients">
        <h2 class="wx-recipe__section-title">{{ trans('webx-recipes::site.ingredients') }}</h2>
        <div class="wx-recipe__text">{!! $ingredients !!}</div>
    </section>
@endif
