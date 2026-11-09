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
      · `:again="$again"` — `<x-webx-inbox::form again>`, the way back to the fields after the thank-you,
      · `:when`, `:unless`, `:condition` on a field — when it is shown (FORM_STEPS §4.3); a view without
        them shows every field, and the intake still throws away the answers to the hidden ones,
      · `:view`, `:columns` and `$control['cards']` as the choices — a group of radios or ticks drawn
        as cards (FORM_STEPS §5); a view without them draws the same choices as a list, the answer
        is the same,
      · `$steps` — the steps of the form (FORM_STEPS §3), each an <x-webx-form.step> around its controls
        (the same entries as `$controls`), `steps` and `:check` on the form; a view that prints only
        `$controls` draws the whole form on one page, as a page without JavaScript sees it, and the
        audit's `inbox.steps_in_old_view` says so,
      · `type` `summary` among the controls of a step — <x-webx-form.summary> (FORM_STEPS §7): the answers
        so far with «Edit»; `$controls` never has one, a view without it simply shows no summary,
      · `$control['datetime']` on a field of type `datetime` — `:step`, `:from`, `:to`, `:weekdays`, `:timezone`,
        `:timezone-label` (FORM_STEPS §6.4): the grid of the day in the site's zone; a view without them
        shows the widgets' defaults, and the intake still checks the field's own grid,
      · `data-webx-attribution` — the widgets' attribution.js posts the campaign, the referrer and the
        landing page (`webx-inbox.attribution`, FORM_STEPS §8); a view without it sends none of them.

    `$description` — the line under the form's title (§5), HTML with its shortcodes; null without
    one, and in a dialog, which prints it under its own title.

    Classes: webx-inbox-form (+ --<placement>), __description, __fields, __field, __field--half, __summary, __captcha,
    __actions, __honeypot; the rest are the widgets' webx-form-* and webx-field-*.
--}}
<x-webx-form
    :action="$action"
    :csrf="false"
    :id="$formId"
    :rules="$rules"
    :again="$again"
    :steps="($steps ?? []) !== []"
    :check="$check ?? null"
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

@if (($steps ?? []) !== [])
        @foreach ($steps as $step)
            <x-webx-form.step
                :name="$step['name']"
                :title="$step['title']"
                :hint="$step['hint']"
                :label="$step['label']"
                :next-label="$step['next']"
                :when="$step['when']"
                :unless="$step['unless']"
                :condition="$step['condition']"
            >
                <div class="webx-inbox-form__fields">
                    @foreach ($step['controls'] as $control)
                    @if ($control['type'] === 'summary')
                    <x-webx-form.summary
                        :title="$control['label']"
                        :hint="$control['hint']"
                        :fields="$control['fields']"
                        class="webx-inbox-form__summary"
                    />
                    @else
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
                :step="$control['datetime']['step'] ?? null"
                :from="$control['datetime']['from'] ?? null"
                :to="$control['datetime']['to'] ?? null"
                :weekdays="$control['datetime']['weekdays'] ?? null"
                :timezone="$control['datetime']['timezone'] ?? null"
                :timezone-label="$control['datetime']['timezone_label'] ?? null"
                        :step="$control['datetime']['step'] ?? null"
                        :from="$control['datetime']['from'] ?? null"
                        :to="$control['datetime']['to'] ?? null"
                        :weekdays="$control['datetime']['weekdays'] ?? null"
                        :timezone="$control['datetime']['timezone'] ?? null"
                        :timezone-label="$control['datetime']['timezone_label'] ?? null"
                        :class="'webx-inbox-form__field'.($control['half'] ? ' webx-inbox-form__field--half' : '')"
                    >@if ($control['consent'] !== null){!! $control['consent'] !!}@endif</x-webx-field>
                    @endif
                    @endforeach
                </div>
                {{-- On the last step, which the form's own button sends from. --}}
                @if ($loop->last)
                    @includeWhen($captcha !== null, 'webx-inbox::form.captcha', ['captcha' => $captcha])
                @endif
            </x-webx-form.step>
        @endforeach
@else
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
                :step="$control['datetime']['step'] ?? null"
                :from="$control['datetime']['from'] ?? null"
                :to="$control['datetime']['to'] ?? null"
                :weekdays="$control['datetime']['weekdays'] ?? null"
                :timezone="$control['datetime']['timezone'] ?? null"
                :timezone-label="$control['datetime']['timezone_label'] ?? null"
                :class="'webx-inbox-form__field'.($control['half'] ? ' webx-inbox-form__field--half' : '')"
            >@if ($control['consent'] !== null){!! $control['consent'] !!}@endif</x-webx-field>
        @endforeach
    </div>

    @includeWhen($captcha !== null, 'webx-inbox::form.captcha', ['captcha' => $captcha])
@endif

    <div class="webx-inbox-form__actions">
        <x-webx-form.submit>{{ $submitText }}</x-webx-form.submit>
    </div>

    {{-- Only with a captcha: everything else of sending is the widgets' form.js. --}}
    @if ($captcha !== null && ($script = $assets->script()) !== null)
        <script src="{{ $script }}" defer></script>
    @endif
</x-webx-form>
