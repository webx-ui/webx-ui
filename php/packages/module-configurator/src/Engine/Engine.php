<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Engine;

/**
 * The configurator's engine: visibility, the choice, the sizes and the price (§6, §7).
 *
 * Pure functions over the schema document — no database, no language, no clock: the date a
 * discount is checked against is an argument. The JS copy in `@webx-ui/module-configurator`
 * implements the same text, and the cases in `resources/fixtures/pricing/` are what keeps the two
 * the same; this one is the truth (decision 3), the other only draws the number first.
 *
 * The schema is read defensively: a draft is saved with errors (§9), and a preview of it must not
 * throw. What is malformed counts as absent.
 */
final class Engine
{
    /** The step types a visitor chooses one option in. */
    public const array CHOICE_TYPES = ['cards', 'swatches', 'buttons'];

    /**
     * Resolve values against the schema in one pass, in the order of the document (§6.1).
     *
     * A step's value is the one given if it is a visible option, the first visible option if the
     * one given is not; a step not given at all starts from `$initial` (a block's preset over the
     * schema's `initial`, §6.3). A size given outside its limits is an error — or, with `$clamp`,
     * pulled inside them: the visitor's own stepper, never a request (§6.4).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $initial  null for the schema's own
     */
    public static function resolve(array $schema, array $values, ?array $initial = null, bool $clamp = false): State
    {
        $initial ??= self::map($schema['initial'] ?? null);
        $resolved = [];
        $hidden = [];
        $options = [];
        $limits = [];
        $errors = [];
        /** @var list<array<string, mixed>> $chosen the options chosen so far, in order */
        $chosen = [];

        foreach (self::steps($schema) as $step) {
            $key = (string) $step['key'];

            if (! self::visible($step, $resolved, $options)) {
                $hidden = [...$hidden, $key, ...self::fieldKeys($step)];

                continue;
            }

            if (in_array($step['type'] ?? null, self::CHOICE_TYPES, true)) {
                $visible = [];

                foreach (self::options($step) as $option) {
                    if (self::visible($option, $resolved, $options)) {
                        $visible[(string) $option['value']] = $option;
                    }
                }

                if ($visible === []) {
                    $hidden[] = $key;

                    continue;
                }

                $wanted = array_key_exists($key, $values) ? $values[$key] : ($initial[$key] ?? null);
                $wanted = is_scalar($wanted) ? (string) $wanted : null;
                $value = $wanted !== null && isset($visible[$wanted]) ? $wanted : (string) array_key_first($visible);

                $resolved[$key] = $value;
                $options[$key] = array_map(strval(...), array_keys($visible));
                $chosen[] = $visible[$value];

                continue;
            }

            $shown = 0;

            foreach (self::fields($step) as $field) {
                $name = (string) $field['key'];

                if (! self::visible($field, $resolved, $options)) {
                    $hidden[] = $name;

                    continue;
                }

                $shown++;
                [$min, $max] = self::limits($field, $chosen);
                $limits[$name] = [$min, $max];

                $given = array_key_exists($name, $values) ? self::float($values[$name]) : null;

                if (array_key_exists($name, $values) && ! $clamp) {
                    if ($given === null) {
                        $errors[] = ['path' => "values.{$name}", 'code' => 'values.number'];
                    } elseif ($given < $min || $given > $max) {
                        $errors[] = ['path' => "values.{$name}", 'code' => 'values.range'];
                    }
                }

                $value = $given ?? self::start($field, $name, $initial, $chosen, $min);
                $resolved[$name] = $given !== null && ! $clamp ? $value : max($min, min($max, $value));
            }

            if ($shown === 0) {
                $hidden[] = $key;
            }
        }

        return new State($resolved, $hidden, $options, $limits, $errors);
    }

    /**
     * The visitor chose an option (§6.2): the value, then the option's `defaults` into the sizes,
     * then everything resolved again and the sizes pulled into their new limits. Defaults are put
     * in only here — in `resolve` they would wipe out the size somebody typed on every recount.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>|null  $initial
     */
    public static function select(array $schema, State $state, string $step, string $value, ?array $initial = null): State
    {
        $values = $state->values;
        $values[$step] = $value;

        if (in_array($value, $state->options[$step] ?? [], true)) {
            foreach (self::steps($schema) as $one) {
                if ($one['key'] !== $step) {
                    continue;
                }

                foreach (self::options($one) as $option) {
                    if ((string) $option['value'] === $value) {
                        foreach (self::map($option['defaults'] ?? null) as $field => $number) {
                            if (self::float($number) !== null) {
                                $values[(string) $field] = self::float($number);
                            }
                        }
                    }
                }
            }
        }

        return self::resolve($schema, $values, $initial, clamp: true);
    }

    /**
     * The visitor changed a size with the stepper or the keyboard: pulled into its limits.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>|null  $initial
     */
    public static function set(array $schema, State $state, string $field, float $number, ?array $initial = null): State
    {
        $values = $state->values;
        $values[$field] = $number;

        return self::resolve($schema, $values, $initial, clamp: true);
    }

    /**
     * The price of a resolved state (§7.2): sums, then simple percents, then the discount, then the
     * rounding of the total.
     *
     * Every line is rounded to a cent the moment it appears and the sums are of rounded lines, so
     * that what the breakdown shows adds up to what it says. A percent is of the subtotal — the
     * `fixed` and `perM2` lines — never of another percent. The discount is of what the percents
     * left, on `$today` (`Y-m-d`) up to and including its `until`.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function price(array $schema, State $state, string $today): Quote
    {
        $area = self::area($schema, $state);
        $lines = [];
        $subtotal = 0.0;

        foreach (self::chosenOptions($schema, $state) as [$step, $option]) {
            $price = self::map($option['price'] ?? null);
            $fixed = self::float($price['fixed'] ?? null) ?? 0.0;
            $rate = self::float($price['perM2'] ?? null) ?? 0.0;

            if ($fixed != 0.0) {
                $amount = self::roundTo($fixed, 0.01);
                $lines[] = ['step' => $step, 'option' => (string) $option['value'], 'kind' => 'fixed', 'amount' => $amount];
                $subtotal += $amount;
            }

            if ($rate != 0.0) {
                $amount = self::roundTo($rate * $area, 0.01);
                $lines[] = ['step' => $step, 'option' => (string) $option['value'], 'kind' => 'perM2', 'rate' => $rate, 'area' => $area, 'amount' => $amount];
                $subtotal += $amount;
            }
        }

        $subtotal = self::roundTo($subtotal, 0.01);
        $accumulated = $subtotal;

        foreach (self::chosenOptions($schema, $state) as [$step, $option]) {
            $percent = self::float(self::map($option['price'] ?? null)['percent'] ?? null) ?? 0.0;

            if ($percent != 0.0) {
                $amount = self::roundTo($subtotal * $percent / 100, 0.01);
                $lines[] = ['step' => $step, 'option' => (string) $option['value'], 'kind' => 'percent', 'rate' => $percent, 'amount' => $amount];
                $accumulated += $amount;
            }
        }

        $accumulated = self::roundTo($accumulated, 0.01);
        $after = $accumulated;
        $discount = self::discount($schema, $today);

        if ($discount !== null && $accumulated > 0) {
            $amount = -self::roundTo($accumulated * $discount / 100, 0.01);
            $lines[] = ['step' => null, 'option' => null, 'kind' => 'discount', 'rate' => $discount, 'amount' => $amount];
            $after = self::roundTo($accumulated + $amount, 0.01);
        }

        $step = self::float(self::map($schema['pricing'] ?? null)['round'] ?? null) ?? 1.0;
        $step = $step > 0 ? $step : 1.0;

        return new Quote(
            currency: is_string($schema['currency'] ?? null) ? $schema['currency'] : '',
            area: $area,
            subtotal: $subtotal,
            totalBeforeDiscount: max(0.0, self::roundTo($accumulated, $step)),
            total: max(0.0, self::roundTo($after, $step)),
            lines: $lines,
            round: $step,
            state: $state,
        );
    }

    /**
     * The one rounding both copies use (§7.3): half away from zero, by a step, without the
     * language's own `round()` — PHP and JS disagree on halves below zero. A step under 1 whose
     * inverse is whole (0.01, 0.5) multiplies and divides by that inverse instead, so that a cent
     * comes out as `3128.64` and not `3128.6400000000003`.
     */
    public static function roundTo(float $x, float $step): float
    {
        if ($x == 0.0 || $step <= 0) {
            return 0.0;
        }

        $inverse = 1 / $step;
        $whole = floor($inverse + 0.5);

        $rounded = $step < 1 && abs($inverse - $whole) < 1e-9
            ? floor(abs($x) * $whole + 0.5) / $whole
            : floor(abs($x) / $step + 0.5) * $step;

        if ($rounded == 0.0) {
            return 0.0;
        }

        return $x < 0 ? -$rounded : $rounded;
    }

    /**
     * The area: the formula of the chosen option of the main step on the visible sizes, never
     * below 0 (§7.1). No main step, or a hidden one — 0.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function area(array $schema, State $state): float
    {
        $main = self::mainStep($schema);

        if ($main === null || $state->choice($main) === null) {
            return 0.0;
        }

        foreach (self::chosenOptions($schema, $state) as [$step, $option]) {
            if ($step !== $main || ! is_string($option['area'] ?? null)) {
                continue;
            }

            try {
                $area = Formula::evaluate(Formula::parse($option['area']), $state->sizes());
            } catch (FormulaError) {
                return 0.0;
            }

            return is_finite($area) && $area > 0 ? $area : 0.0;
        }

        return 0.0;
    }

    /**
     * The main step: the first choice step any option of which has an `area`.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function mainStep(array $schema): ?string
    {
        foreach (self::steps($schema) as $step) {
            if (! in_array($step['type'] ?? null, self::CHOICE_TYPES, true)) {
                continue;
            }

            foreach (self::options($step) as $option) {
                if (array_key_exists('area', $option)) {
                    return (string) $step['key'];
                }
            }
        }

        return null;
    }

    /**
     * The discount in force on a day, as a percent; null when there is none or it is over.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function discount(array $schema, string $today): ?float
    {
        $discount = self::map($schema['discount'] ?? null);
        $percent = self::float($discount['percent'] ?? null);
        $until = $discount['until'] ?? null;

        if ($percent === null || $percent <= 0 || $percent >= 100) {
            return null;
        }

        // ISO dates compare as strings; the last day is still a day of the discount.
        if (is_string($until) && $until !== '' && strcmp($today, $until) > 0) {
            return null;
        }

        return $percent;
    }

    /**
     * Whether a step, an option or a field is visible (§5.6): its `when` holds — every step it
     * names visible and chosen one of the values — and its `unless` would not.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, string|float>  $resolved
     * @param  array<string, list<string>>  $options
     */
    public static function visible(array $item, array $resolved, array $options): bool
    {
        $holds = static function (mixed $condition) use ($resolved, $options): bool {
            foreach (self::map($condition) as $step => $values) {
                $step = (string) $step;

                if (! isset($options[$step]) || ! is_array($values) || ! in_array($resolved[$step] ?? null, array_map(strval(...), array_filter($values, is_scalar(...))), true)) {
                    return false;
                }
            }

            return true;
        };

        if (array_key_exists('when', $item) && ! $holds($item['when'])) {
            return false;
        }

        return ! (array_key_exists('unless', $item) && $holds($item['unless']));
    }

    /**
     * Each visible choice step with its chosen option, in the order of the document.
     *
     * @param  array<string, mixed>  $schema
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public static function chosenOptions(array $schema, State $state): array
    {
        $chosen = [];

        foreach (self::steps($schema) as $step) {
            $value = $state->choice((string) $step['key']);

            if ($value === null) {
                continue;
            }

            foreach (self::options($step) as $option) {
                if ((string) $option['value'] === $value) {
                    $chosen[] = [(string) $step['key'], $option];

                    break;
                }
            }
        }

        return $chosen;
    }

    /**
     * The steps that have a key, skipping what is not a step at all.
     *
     * @param  array<string, mixed>  $schema
     * @return list<array<string, mixed>>
     */
    public static function steps(array $schema): array
    {
        $steps = [];

        foreach (is_array($schema['steps'] ?? null) ? $schema['steps'] : [] as $step) {
            if (is_array($step) && is_string($step['key'] ?? null) && $step['key'] !== '') {
                $steps[] = $step;
            }
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<array<string, mixed>>
     */
    public static function options(array $step): array
    {
        $options = [];

        foreach (is_array($step['options'] ?? null) ? $step['options'] : [] as $option) {
            if (is_array($option) && is_scalar($option['value'] ?? null) && (string) $option['value'] !== '') {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<array<string, mixed>>
     */
    public static function fields(array $step): array
    {
        if (($step['type'] ?? null) !== 'dimensions') {
            return [];
        }

        $fields = [];

        foreach (is_array($step['fields'] ?? null) ? $step['fields'] : [] as $field) {
            if (is_array($field) && is_string($field['key'] ?? null) && $field['key'] !== '') {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * A number as JSON should show it: whole numbers without a fraction; anything else as it is.
     */
    public static function number(mixed $value): mixed
    {
        if (is_float($value) && is_finite($value) && floor($value) === $value && abs($value) < 1e15) {
            return (int) $value;
        }

        return $value;
    }

    /** A number, from a number or a numeric string; null for anything else. */
    public static function float(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        return is_string($value) && is_numeric(trim($value)) ? (float) trim($value) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return list<string>
     */
    private static function fieldKeys(array $step): array
    {
        return array_map(static fn (array $field): string => (string) $field['key'], self::fields($step));
    }

    /**
     * The limits of a field: its own, replaced by the `limits` of the options chosen above it —
     * the later step wins.
     *
     * @param  array<string, mixed>  $field
     * @param  list<array<string, mixed>>  $chosen
     * @return array{0: float, 1: float}
     */
    private static function limits(array $field, array $chosen): array
    {
        $min = self::float($field['min'] ?? null) ?? 0.0;
        $max = self::float($field['max'] ?? null) ?? $min;

        foreach ($chosen as $option) {
            $limit = self::map($option['limits'] ?? null)[(string) $field['key']] ?? null;

            if (is_array($limit) && self::float($limit[0] ?? null) !== null && self::float($limit[1] ?? null) !== null) {
                $min = (float) self::float($limit[0]);
                $max = (float) self::float($limit[1]);
            }
        }

        return [$min, max($min, $max)];
    }

    /**
     * Where a size not given starts (§6.3): the preset or `initial`, the `defaults` of the options
     * chosen above (the later step wins), the field's own `default`, its minimum.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $initial
     * @param  list<array<string, mixed>>  $chosen
     */
    private static function start(array $field, string $name, array $initial, array $chosen, float $min): float
    {
        $start = self::float($initial[$name] ?? null);

        if ($start !== null) {
            return $start;
        }

        foreach (array_reverse($chosen) as $option) {
            $default = self::float(self::map($option['defaults'] ?? null)[$name] ?? null);

            if ($default !== null) {
                return $default;
            }
        }

        return self::float($field['default'] ?? null) ?? $min;
    }
}
