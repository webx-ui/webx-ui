{{-- Printed raw: the field type cleaned the document on the way in. --}}
@if ($ingredients !== '')
    <section class="wx-recipe__ingredients">
        <h2>{{ trans('webx-recipes::site.ingredients') }}</h2>
        {!! $ingredients !!}
    </section>
@endif
