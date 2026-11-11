{{-- A view of the site written before the steps: it goes through $controls alone (FORM_STEPS §12.5). --}}
<x-webx-form
    :action="$action"
    :csrf="false"
    :id="$formId"
    :rules="$rules"
    :again="$again"
    data-webx-form="{{ $form->slug }}"
    :data-webx-attribution="($attribution ?? false) ? '' : null"
    {{ $attributes->class(['webx-inbox-form', 'webx-inbox-form--'.$placement => $placement !== null]) }}
>
    <input type="hidden" name="webx_form" value="{{ $form->slug }}">
    <input type="hidden" name="webx_page" value="{{ $page }}">
    <input type="hidden" name="webx_locale" value="{{ $locale }}">
    @if ($placement !== null)
        <input type="hidden" name="webx_placement" value="{{ $placement }}">
    @endif
    <input type="hidden" name="{{ $timestampField }}" value="{{ $timestamp }}">

    @includeWhen($honeypot !== null, 'webx-inbox::form.honeypot', ['name' => $honeypot, 'id' => $formId.'-hp'])

    @if ($description !== null)
        <p class="webx-inbox-form__description">{{ $description }}</p>
    @endif

    <div class="webx-inbox-form__fields">
        @foreach ($controls as $control)
            <x-webx-field
                :type="$control['type']"
                :name="$control['name']"
                :id="$control['id']"
                :label="$control['label']"
                :hint="$control['hint']"
                :placeholder="$control['placeholder']"
                :value="$control['value']"
                :choices="$control['cards'] ?? $control['choices']"
                :multiple="$control['multiple']"
                :rows="$control['rows']"
                :view="$control['view'] ?? 'list'"
                :columns="$control['columns'] ?? null"
                :when="$control['when'] ?? null"
                :unless="$control['unless'] ?? null"
                :condition="$control['condition'] ?? null"
                :class="'webx-inbox-form__field'.($control['half'] ? ' webx-inbox-form__field--half' : '')"
            >@if ($control['consent'] !== null){!! $control['consent'] !!}@endif</x-webx-field>
        @endforeach
    </div>

    @includeWhen($captcha !== null, 'webx-inbox::form.captcha', ['captcha' => $captcha])

    <div class="webx-inbox-form__actions">
        <x-webx-form.submit>{{ $submitText }}</x-webx-form.submit>
    </div>

    {{-- Only with a captcha: everything else of sending is the widgets' form.js. --}}
    @if ($captcha !== null && ($script = $assets->script()) !== null)
        <script src="{{ $script }}" defer></script>
    @endif
</x-webx-form>
