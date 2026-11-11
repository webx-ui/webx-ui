<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\Steps\Step;
use WebxUi\Orders\Checkout\Steps\StepError;
use WebxUi\Orders\FieldAnswers;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\OrderFields;

/**
 * The extra fields on the way to an order (WEBX_UI_ORDER_FIELDS.md §3–§4): the checkout asks them
 * in the places of each step, and saving, checking and the snapshot are done here around the step's
 * own — so a step with a place, the checkout's or a project's, needs only the line
 * `@include('webx-orders::checkout.parts.fields', ['place' => …])` in its view.
 *
 * The answers live in the draft under `steps.fields`, one map for all the places. Saving a step
 * writes the fields of its places alone, so the contacts never wipe the block «Additional» and
 * the other way round. A field behind a checkbox counts only while the checkbox is ticked: hidden,
 * it is neither checked nor kept, whatever came (§3.3). The server decides; the page only hides.
 */
final readonly class CheckoutFields
{
    /** The key of the draft's `steps` the answers go under (§4.3): no step may take it. */
    public const string DRAFT = 'fields';

    /** The name of a field in a form and in an error: `fields.<key>` (§3.4). */
    private const string NAME = 'fields.';

    public function __construct(
        private FieldPlaces $places,
        private OrderFields $fields,
        private FieldAnswers $answers,
    ) {}

    /** Whether any field may be asked in the places of this step — the block «Additional» stands on it (§4.2). */
    public function any(string $step): bool
    {
        $all = $this->fields->all();

        foreach ($this->places->of($step) as $place) {
            if ($this->groups($place->key, $all) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * The step's form kept in the draft with the answers of its places: the errors of both under
     * their fields at once, nothing of either kept while one of them has an error.
     *
     * @param  array<string, mixed>  $input  the request's fields
     *
     * @throws ValidationException
     */
    public function save(Step $step, CheckoutContext $context, array $input, CheckoutDraft $draft): void
    {
        $errors = [];
        $all = $this->fields->all();
        $answers = $this->values($context);
        $sent = is_array($input[self::DRAFT] ?? null) ? $input[self::DRAFT] : [];

        foreach ($this->places->of($step->key()) as $place) {
            // Every definition of the place loses its answer first: hidden, switched off or left
            // empty is not kept (§3.3). The answers of other places stay as they were.
            foreach ($all as $field) {
                if ($field->place === $place->key) {
                    unset($answers[$field->key]);
                }
            }

            foreach ($this->shown($place->key, $all, $sent) as $field) {
                [$value, $problem] = $this->answers->answer($field, $sent[$field->key] ?? null);
                $problem ??= $value === null && $field->required ? 'required' : null;

                if ($problem !== null) {
                    $errors[self::NAME.$field->key] = [$this->answers->message($field, $problem, $context->locale)];
                } elseif ($value !== null) {
                    $answers[$field->key] = $value;
                }
            }
        }

        try {
            $step->save($context, $input, $draft);
        } catch (ValidationException $e) {
            if ($errors === []) {
                throw $e;
            }

            $errors = [...$e->errors(), ...$errors];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $steps = is_array($draft->steps) ? $draft->steps : [];
        $steps[self::DRAFT] = $answers;

        if ($answers === []) {
            unset($steps[self::DRAFT]);
        }

        $draft->steps = $steps === [] ? null : $steps;
    }

    /**
     * The step's errors and its fields' — against the definitions as they are now: a field made
     * required, or an option taken away, while the buyer thought, stops the order (§3.4).
     *
     * @return list<StepError>
     */
    public function check(Step $step, CheckoutContext $context, Purpose $purpose): array
    {
        $errors = $step->check($context, $purpose);
        $all = $this->fields->all();
        $answers = $this->values($context);

        foreach ($this->places->of($step->key()) as $place) {
            foreach ($this->shown($place->key, $all, $answers) as $field) {
                [$value, $problem] = $this->answers->answer($field, $answers[$field->key] ?? null);
                $problem ??= $value === null && $field->required ? 'required' : null;

                if ($problem !== null) {
                    $errors[] = new StepError($step->key(), self::NAME.$field->key, $this->answers->message($field, $problem, $context->locale), match ($problem) {
                        'required' => 'fields.required',
                        'option-gone' => 'fields.option_gone',
                        default => 'fields.invalid',
                    });
                }
            }
        }

        return $errors;
    }

    /**
     * What the step owns put into the order, then the rows of its places, in the order's language
     * (§4.4): a filled field shown as the definitions are now; the rest is not in the snapshot.
     */
    public function contribute(Step $step, CheckoutContext $context, PlaceOrderBuilder $order): void
    {
        $step->contribute($context, $order);

        $all = $this->fields->all();
        $answers = $this->values($context);
        $rows = [];

        foreach ($this->places->of($step->key()) as $place) {
            foreach ($this->shown($place->key, $all, $answers) as $field) {
                [$value, $problem] = $this->answers->answer($field, $answers[$field->key] ?? null);

                if ($value !== null && $problem === null) {
                    $rows[] = [
                        'key' => $field->key,
                        'type' => $field->type,
                        'label' => $this->answers->label($field, $context->locale),
                        'value' => $value,
                        'text' => $this->answers->text($field, $value, $context->locale),
                    ];
                }
            }
        }

        $order->fields($rows);
    }

    /**
     * The labels of the filled fields of a place, for the one line of a collapsed step (§4.2).
     *
     * @return list<string>
     */
    public function filled(string $place, CheckoutContext $context): array
    {
        $answers = $this->values($context);
        $labels = [];

        foreach ($this->shown($place, $this->fields->all(), $answers) as $field) {
            if ($this->answers->answer($field, $answers[$field->key] ?? null)[0] !== null) {
                $labels[] = $this->answers->label($field, $context->locale);
            }
        }

        return $labels;
    }

    /**
     * How many fields each place of a step may ask now, the ones behind a checkbox counted —
     * `orders_checkout_steps` (§8.3).
     *
     * @return array<string, int> by the key of the place, in the order registered
     */
    public function counts(string $step): array
    {
        $all = $this->fields->all();
        $counts = [];

        foreach ($this->places->of($step) as $place) {
            $counts[$place->key] = 0;

            foreach ($this->groups($place->key, $all) as [, $dependents]) {
                $counts[$place->key] += 1 + count($dependents);
            }
        }

        return $counts;
    }

    /**
     * The fields of a step's places for a cart without what was answered (§8.3): a draft is a
     * person who has not ordered yet, so `orders_checkout_preview` says whether a field is shown and
     * filled — never its value.
     *
     * @return list<array{key: string, place: string, required: bool, shown: bool, filled: bool}>
     */
    public function states(Step $step, CheckoutContext $context): array
    {
        $all = $this->fields->all();
        $answers = $this->values($context);
        $filled = fn (OrderField $field): bool => $this->answers->answer($field, $answers[$field->key] ?? null)[0] !== null;
        $states = [];

        foreach ($this->places->of($step->key()) as $place) {
            foreach ($this->groups($place->key, $all) as [$field, $dependents]) {
                $ticked = $this->answers->answer($field, $answers[$field->key] ?? null)[0] === true;

                foreach ([$field, ...$dependents] as $one) {
                    $shown = $one === $field || $ticked;
                    $states[] = [
                        'key' => $one->key,
                        'place' => $place->key,
                        'required' => $one->required,
                        'shown' => $shown,
                        'filled' => $shown && $filled($one),
                    ];
                }
            }
        }

        return $states;
    }

    /**
     * The fields of a place as `checkout.parts.fields` prints them (§4.5): each field shown always,
     * a checkbox with the fields behind it as a group.
     *
     * @return list<array{field: array<string, mixed>, when: list<array<string, mixed>>}>
     */
    public function view(string $place, CheckoutContext $context): array
    {
        $answers = $this->values($context);
        $groups = [];

        foreach ($this->groups($place, $this->fields->all()) as [$field, $dependents]) {
            $groups[] = [
                'field' => $this->control($field, $answers, $context->locale, behind: false),
                'when' => array_map(fn (OrderField $dependent): array => $this->control($dependent, $answers, $context->locale, behind: true), $dependents),
            ];
        }

        return $groups;
    }

    /**
     * The answers the draft keeps, by the key of the field.
     *
     * @return array<string, mixed>
     */
    public function values(CheckoutContext $context): array
    {
        $steps = $context->saved('steps');
        $answers = is_array($steps) ? ($steps[self::DRAFT] ?? null) : null;

        return is_array($answers) ? $answers : [];
    }

    /**
     * One field for `<x-webx-field>`.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function control(OrderField $field, array $answers, string $locale, bool $behind): array
    {
        $value = $this->answers->answer($field, $answers[$field->key] ?? null)[0];
        $hint = $field->getTranslation('hint', $locale);

        return [
            'key' => $field->key,
            'name' => self::NAME.$field->key,
            'type' => $field->type,
            'label' => $this->answers->label($field, $locale),
            'hint' => is_string($hint) && $hint !== '' ? $hint : null,
            'value' => is_string($value) ? $value : null,
            'checked' => $value === true,
            'choices' => $field->type === OrderField::SELECT ? $this->answers->choices($field, $locale) : [],
            // Behind a checkbox — never the browser's `required`, nor its star: hidden, it would
            // keep the form from going; the server says it under the field (decision 3 of 06.11).
            'required' => $field->required && ! $behind,
            'optional' => ! $field->required && $field->type !== OrderField::CHECKBOX,
            'maxlength' => match ($field->type) {
                OrderField::TEXT => OrderField::MAX_TEXT,
                OrderField::TEXTAREA => OrderField::MAX_TEXTAREA,
                default => null,
            },
        ];
    }

    /**
     * The fields of a place that may be asked, each with the fields behind it: switched on, the
     * one behind a checkbox only while the checkbox is on and stands in the same place (§3.3).
     *
     * @param  Collection<int, OrderField>  $all
     * @return list<array{0: OrderField, 1: list<OrderField>}>
     */
    private function groups(string $place, Collection $all): array
    {
        if (! $this->places->has($place)) {
            return [];
        }

        $groups = [];

        foreach ($all as $field) {
            if ($field->active && $field->place === $place && $field->parent_id === null) {
                $groups[$field->id] = [$field, []];
            }
        }

        foreach ($all as $field) {
            if ($field->active && $field->place === $place && $field->parent_id !== null && isset($groups[$field->parent_id])
                && $groups[$field->parent_id][0]->type === OrderField::CHECKBOX) {
                $groups[$field->parent_id][1][] = $field;
            }
        }

        return array_values($groups);
    }

    /**
     * The fields of a place the buyer sees with these answers, in the order of the page: a field
     * behind a checkbox right after it, and only with it ticked.
     *
     * @param  Collection<int, OrderField>  $all
     * @param  array<string, mixed>  $answers
     * @return list<OrderField>
     */
    private function shown(string $place, Collection $all, array $answers): array
    {
        $shown = [];

        foreach ($this->groups($place, $all) as [$field, $dependents]) {
            $shown[] = $field;

            if ($dependents !== [] && $this->answers->answer($field, $answers[$field->key] ?? null)[0] === true) {
                array_push($shown, ...$dependents);
            }
        }

        return $shown;
    }
}
