<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use WebxUi\Localization\Locales;
use WebxUi\Orders\Checkout\FieldPlace;
use WebxUi\Orders\Checkout\FieldPlaces;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\FieldPlan;
use WebxUi\Orders\FieldRefused;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\OrderFields;

/**
 * The extra fields of the checkout as the panel shows them (WEBX_UI_ORDER_FIELDS.md §7.1, §7.3):
 * the places of the registry in the order a buyer meets them, every definition with its words in
 * all the languages, and what a change does — said in the panel's words before it is made.
 *
 * A field whose place the registry no longer knows is shown all the same, marked: the buyer is
 * not asked it until it gets a place, and the panel is where that is done.
 */
final readonly class FieldBoard
{
    public function __construct(
        private OrderFields $fields,
        private FieldPlaces $places,
        private CheckoutSteps $steps,
        private Locales $locales,
    ) {}

    /**
     * @return array{places: list<array{key: string, step: string, label: string}>, fields: list<array<string, mixed>>, limits: array<string, int>}
     */
    public function describe(string $locale): array
    {
        $all = $this->fields->all();
        $keys = $all->pluck('key', 'id')->all();

        return [
            'places' => array_map(static fn (FieldPlace $place): array => [
                'key' => $place->key,
                'step' => $place->step,
                'label' => $place->name(),
            ], $this->places->all($this->steps->positions())),
            'fields' => $all->map(fn (OrderField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'type' => $field->type,
                'place' => $field->place,
                'place_known' => $this->places->has($field->place),
                'name' => $this->name($field, $locale),
                'label' => $field->getTranslations('label'),
                'hint' => $field->getTranslations('hint'),
                'options' => $field->options ?? [],
                'required' => $field->required,
                'active' => $field->active,
                'parent' => $field->parent_id !== null ? ($keys[$field->parent_id] ?? null) : null,
                'position' => $field->position,
            ])->values()->all(),
            'limits' => [
                'fields' => OrderField::MAX_FIELDS,
                'options' => OrderField::MAX_OPTIONS,
                'label' => OrderField::MAX_LABEL,
                'hint' => OrderField::MAX_HINT,
            ],
        ];
    }

    /**
     * What the change does for the buyer, line by line — or, refused, why. `$place` is where the
     * change takes the field: on a dry run the definition still stands where it was.
     *
     * @return array{refusal: string|null, message: string|null, field: string|null, effects: list<string>, dependents: list<string>, lines: list<string>}
     */
    public function consequences(FieldPlan $plan, string $locale, ?string $place = null): array
    {
        $names = $this->fields->all()->mapWithKeys(fn (OrderField $field): array => [$field->key => $this->name($field, $locale)]);
        $named = static fn (array $keys): string => implode(', ', array_map(static fn (string $key): string => '«'.($names[$key] ?? $key).'»', $keys));
        $field = $plan->key !== null ? OrderField::query()->where('key', $plan->key)->first() : null;
        $lines = [];

        foreach ($plan->effects as $effect) {
            $lines[] = match ($effect) {
                FieldPlan::MOVED => (string) __('webx-orders::panel.checkout-fields.effects.moved', ['place' => $this->placeName($place ?? $field?->place)]),
                FieldPlan::OPTIONS_REMOVED => (string) __('webx-orders::panel.checkout-fields.effects.options_removed', ['options' => $this->optionNames($field, $plan->removedOptions, $locale)]),
                default => (string) __('webx-orders::panel.checkout-fields.effects.'.$effect),
            };

            if ($plan->dependents !== [] && in_array($effect, [FieldPlan::HIDDEN, FieldPlan::MOVED], true)) {
                $lines[] = (string) __('webx-orders::panel.checkout-fields.effects.'.$effect.'-dependents', ['fields' => $named($plan->dependents)]);
            }
        }

        if ($plan->refusal === null && array_intersect($plan->effects, [FieldPlan::DELETED, FieldPlan::OPTIONS_REMOVED, FieldPlan::HIDDEN]) !== []) {
            $lines[] = (string) __('webx-orders::panel.checkout-fields.effects.orders');
        }

        return [
            'refusal' => $plan->refusal,
            'message' => $plan->refusal !== null ? $this->refusal($plan, $named($plan->dependents)) : null,
            'field' => $plan->field,
            'effects' => $plan->effects,
            'dependents' => $plan->dependents,
            'lines' => $plan->refusal === null ? $lines : [],
        ];
    }

    /** The refusal of the service, as the panel says it. */
    public function refused(FieldRefused $refused, string $locale): string
    {
        return (string) $this->consequences($refused->plan, $locale)['message'];
    }

    private function refusal(FieldPlan $plan, string $dependents): string
    {
        return (string) __('webx-orders::panel.checkout-fields.refusals.'.$plan->refusal, [
            'fields' => $plan->refusal === FieldRefused::TOO_MANY ? (string) OrderField::MAX_FIELDS : $dependents,
            'options' => (string) OrderField::MAX_OPTIONS,
        ]);
    }

    /** The label in the panel's language, else the site's main one, else the key. */
    private function name(OrderField $field, string $locale): string
    {
        $label = $field->getTranslations('label');
        $name = $label[$locale] ?? $label[$this->locales->fallback()] ?? null;

        return is_string($name) && $name !== '' ? $name : $field->key;
    }

    private function placeName(?string $key): string
    {
        $place = $key !== null ? $this->places->find($key) : null;

        return $place?->name() ?? (string) $key;
    }

    /** @param  list<string>  $values */
    private function optionNames(?OrderField $field, array $values, string $locale): string
    {
        // The options are gone from the definition once it is written: a dry run still has them.
        $labels = [];

        foreach ($field->options ?? [] as $option) {
            $labels[$option['value']] = $option['label'][$locale] ?? $option['label'][$this->locales->fallback()] ?? $option['value'];
        }

        return implode(', ', array_map(static fn (string $value): string => '«'.($labels[$value] ?? $value).'»', $values));
    }
}
