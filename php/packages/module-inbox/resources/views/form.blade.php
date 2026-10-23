{{--
    A form of the panel on the site (§10), drawn by the widgets' form engine (WIDGETS §17): the
    shell is <x-webx-form>, every field an <x-webx-field>, the answer FormResponse. What the shell
    does — sending in place, the errors of a 422 under their fields, the checks of the intake's own
    rules in the browser, in the validator's words, the thank-you, a form in a dialog — it does for
    this form the way it does for any other. What stays the inbox's is printed here, inside it.

    `:csrf="false"`: the intake guards itself — the origin, the honeypot, the timestamp, the limit
    per address (decision 8: a page cached whole carries a stale token).

    What must survive a rewrite of this view (`vendor:publish --tag=webx-inbox-views`, or the
    theme's views/vendor/webx-inbox/form.blade.php — keep <x-webx-form> in it):

      · `webx_form` — which form of the panel this is, and `webx_locale`, `webx_page`,
        `webx_placement` — the intake stands outside the site's own middleware,
      · the honeypot and the timestamp — the antispam that costs the visitor nothing,
      · the captcha block — `data-webx-field="captcha"` is where the intake's word about it goes,
      · `data-webx-form` on the form — the hook for a site's own script (`webx:form:success`),
      · `:again="$again"` — `<x-webx-inbox::form again>`, the way back to the fields after the thank-you.

    `$description` — the line under the form's title (§5), HTML with its shortcodes; null without
    one, and in a dialog, which prints it under its own title.

    Classes: webx-inbox-form (+ --<placement>), __description, __fields, __field, __field--half, __captcha,
    __actions, __honeypot; the rest are the widgets' webx-form-* and webx-field-*.
--}}
<x-webx-form
    :action="$action"
    :csrf="false"
    :id="$formId"
    :rules="$rules"
    :again="$again"
    data-webx-form="{{ $form->slug }}"
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
                :choices="$control['choices']"
                :multiple="$control['multiple']"
                :rows="$control['rows']"
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
