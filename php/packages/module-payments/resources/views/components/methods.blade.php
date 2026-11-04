{{--
    The list of methods, group → method (groups spec §6), one component wherever a buyer chooses.
    Radios `group` and `method`: a group opens by the stylesheet, so without JavaScript the same
    list works and the server reads the pair by §6.4. With `methods.js` a group chooses its first
    visible method, and the list says `webx:payments:method` to the page around it. A row of a method
    that is Apple Pay alone carries `data-apple-pay-only`: the script takes it away where Apple Pay
    cannot work (§4), and without the script it stays.

    A group of one method is one row that speaks for the method (decision 9), its radio carrying
    `data-method`. One group on the site is a flat list without heads (decision 10), and one method
    is one line, chosen, its radio out of sight.

    $groups — list of `WebxUi\Payments\MethodGroupView`, from `Methods::grouped()`; $chosen — the key
    of the method chosen, or null; $legend — what the list is called.

    Override: <layer>/views/vendor/webx-payments/components/methods.blade.php.
    Classes: see AGENTS.md; on the list — is-flat, is-single; on a group and a method — is-chosen.
--}}
@props(['groups', 'chosen' => null, 'legend'])
@php
    $flat = count($groups) === 1;
    $only = $flat && count($groups[0]->options) === 1 ? $groups[0]->options[0] : null;
    $script = app(\WebxUi\Payments\Rendering\Assets::class)->methods();
@endphp
<fieldset @class(['webx-payments__groups', 'is-flat' => $flat, 'is-single' => $only !== null]) role="radiogroup" data-webx-payments-methods>
    <legend class="webx-payments__methods-title">{{ $legend }}</legend>

    @if ($flat)
        @foreach ($groups[0]->options as $option)
            @php($isChosen = $chosen === $option->key() || $only !== null)
            <label @class(['webx-payments__option', 'is-chosen' => $isChosen]) @if ($option->applePayOnly) data-apple-pay-only @endif>
                <input class="webx-payments__radio" type="radio" name="method" value="{{ $option->key() }}" @checked($isChosen) @if ($only !== null && $chosen === null) data-autosave @endif>
                <x-webx-payments::row :title="$option->title" :note="$option->note" :icon="$option->icon" :marks="$option->marks" />
            </label>
        @endforeach
    @else
        @foreach ($groups as $view)
            @php($one = count($view->options) === 1 ? $view->options[0] : null)
            @php($isChosen = in_array($chosen, array_map(static fn ($option) => $option->key(), $view->options), true))
            <div @class(['webx-payments__group', 'is-chosen' => $isChosen]) data-group="{{ $view->key() }}">
                <label class="webx-payments__group-head" @if ($one?->applePayOnly) data-apple-pay-only @endif>
                    <input class="webx-payments__radio" type="radio" name="group" value="{{ $view->key() }}" @checked($isChosen) @if ($one !== null) data-method="{{ $one->key() }}" @endif>
                    @if ($one !== null)
                        <x-webx-payments::row :title="$one->title" :note="$one->note" :icon="$one->icon" :pictogram="$view->group->pictogram" :marks="$one->marks" />
                    @else
                        <x-webx-payments::row :title="$view->group->title()" :note="$view->group->description()" :pictogram="$view->group->pictogram" :marks="$view->marks" />
                    @endif
                </label>

                @if ($one === null)
                    <fieldset class="webx-payments__group-methods" role="radiogroup">
                        <legend class="webx-payments__group-legend">{{ $view->group->title() }}</legend>
                        @foreach ($view->options as $option)
                            <label @class(['webx-payments__option', 'is-chosen' => $chosen === $option->key()]) @if ($option->applePayOnly) data-apple-pay-only @endif>
                                <input class="webx-payments__radio" type="radio" name="method" value="{{ $option->key() }}" @checked($chosen === $option->key())>
                                <x-webx-payments::row :title="$option->title" :note="$option->note" :icon="$option->icon" :marks="$option->marks" />
                            </label>
                        @endforeach
                    </fieldset>
                @endif
            </div>
        @endforeach
    @endif
</fieldset>
@if ($script !== null)
    <script type="module" src="{{ $script }}"></script>
@endif
