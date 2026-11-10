<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Illuminate\Validation\ValidationException;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Models\OrderField;

/**
 * An answer to an extra field (WEBX_UI_ORDER_FIELDS.md §3.1) — what it is worth, what is wrong with
 * it, and the words a row of the snapshot prints — for the checkout and for the manager alike.
 *
 * The manager's edit of an order (§6.1) is here too: the type, the length and the option checked as
 * the checkout does, the requirement and the condition not — the manager writes what the buyer said.
 */
final readonly class FieldAnswers
{
    public function __construct(
        private OrderFields $fields,
        private Locales $locales,
    ) {}

    /**
     * An answer as the draft and the order keep it, and what is wrong with it: null — not given
     * (an empty line, no choice, a checkbox not ticked).
     *
     * @return array{0: string|true|null, 1: 'invalid'|'too-long'|'option-gone'|null}
     */
    public function answer(OrderField $field, mixed $raw): array
    {
        if ($raw === null) {
            return [null, null];
        }

        if (! is_scalar($raw)) {
            return [null, 'invalid'];
        }

        if ($field->type === OrderField::CHECKBOX) {
            return [filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? true : null, null];
        }

        return self::typed($field->type, $raw, $field->optionValues());
    }

    /** The error under a field, in this language: the label of the field in it. */
    public function message(OrderField $field, string $problem, string $locale): string
    {
        return self::said($this->label($field, $locale), $field->type, $problem, $locale);
    }

    /** The label in this language, else the site's main one, else the key. */
    public function label(OrderField $field, string $locale): string
    {
        $labels = $field->getTranslations('label');
        $label = $labels[$locale] ?? $labels[$this->locales->fallback()] ?? null;

        return is_string($label) && $label !== '' ? $label : $field->key;
    }

    /** What the snapshot prints (§3.1): «Yes», the option's words, the text itself. */
    public function text(OrderField $field, string|true $value, string $locale): string
    {
        if ($value === true) {
            return (string) __('webx-orders::checkout.fields.yes', [], $locale);
        }

        if ($field->type === OrderField::SELECT) {
            return $this->choices($field, $locale)[$value] ?? $value;
        }

        return $value;
    }

    /**
     * The options of a list in this language: `value => label`.
     *
     * @return array<string, string>
     */
    public function choices(OrderField $field, string $locale): array
    {
        $choices = [];

        foreach ($field->options ?? [] as $option) {
            $label = $option['label'][$locale] ?? $option['label'][$this->locales->fallback()] ?? null;
            $choices[(string) $option['value']] = is_string($label) && $label !== '' ? $label : (string) $option['value'];
        }

        return $choices;
    }

    /**
     * The rows of an order after the manager's answers (§6.1): only the keys sent are touched. Null,
     * an empty line or `false` takes the row away; an answer as it was leaves the row as it was,
     * the snapshot's label with it; a new answer takes the label and the option's words of the
     * definition now, in the order's language, and a new row goes to the end. A row whose field is
     * gone keeps its label: a line or a text may still change, a checkbox and a list only go.
     *
     * The errors come under `fields.<key>` in the panel's language.
     *
     * @param  list<array{key: string, type: string, label: string, value: bool|string, text: string}>|null  $rows
     * @param  array<array-key, mixed>  $sent  `key => value | null`
     * @return list<array{key: string, type: string, label: string, value: bool|string, text: string}>|null
     *
     * @throws ValidationException
     */
    public function edit(?array $rows, array $sent, string $locale): ?array
    {
        $kept = [];

        foreach ($rows ?? [] as $row) {
            $kept[$row['key']] = $row;
        }

        $definitions = $this->fields->all()->keyBy('key');
        $errors = [];

        foreach ($sent as $key => $raw) {
            $key = (string) $key;
            $row = $kept[$key] ?? null;
            /** @var OrderField|null $field */
            $field = $definitions->get($key);
            $name = 'fields.'.$key;

            if (is_string($raw)) {
                $raw = trim(str_replace("\r\n", "\n", $raw));
            }

            if ($raw === null || $raw === '' || $raw === false) {
                unset($kept[$key]);

                continue;
            }

            // A new field comes only from a definition switched on: a field switched off is not
            // asked any more, but a row of it the order has is still the order's.
            if ($row === null && ($field === null || ! $field->active)) {
                $errors[$name] = [(string) __('webx-orders::panel.fields-unknown', ['field' => $key])];

                continue;
            }

            if ($row !== null && self::same($row, $raw)) {
                continue;
            }

            if ($field === null) {
                /** @var array{key: string, type: string, label: string, value: bool|string, text: string} $row */
                if (! in_array($row['type'], [OrderField::TEXT, OrderField::TEXTAREA], true)) {
                    $errors[$name] = [(string) __('webx-orders::panel.fields-clear-only', ['field' => $row['label']])];

                    continue;
                }

                [$value, $problem] = is_scalar($raw) ? self::typed($row['type'], $raw, []) : [null, 'invalid'];

                if ($problem !== null || ! is_string($value)) {
                    $errors[$name] = [self::said($row['label'], $row['type'], $problem ?? 'invalid', null)];

                    continue;
                }

                $kept[$key] = [...$row, 'value' => $value, 'text' => $value];

                continue;
            }

            [$value, $problem] = $this->answer($field, $raw);

            if ($problem !== null) {
                $errors[$name] = [self::said($this->label($field, app()->getLocale()), $field->type, $problem, null)];

                continue;
            }

            if ($value === null) {
                unset($kept[$key]);

                continue;
            }

            $kept[$key] = [
                'key' => $key,
                'type' => $field->type,
                'label' => $this->label($field, $locale),
                'value' => $value,
                'text' => $this->text($field, $value, $locale),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $kept === [] ? null : array_values($kept);
    }

    /**
     * A line, a text or a list's choice, checked — a checkbox is the caller's.
     *
     * @param  list<string>  $options
     * @return array{0: string|null, 1: 'invalid'|'too-long'|'option-gone'|null}
     */
    private static function typed(string $type, bool|float|int|string $raw, array $options): array
    {
        $value = trim(str_replace("\r\n", "\n", (string) $raw));

        if ($value === '') {
            return [null, null];
        }

        return match ($type) {
            OrderField::SELECT => in_array($value, $options, true) ? [$value, null] : [null, 'option-gone'],
            OrderField::TEXTAREA => mb_strlen($value) > OrderField::MAX_TEXTAREA ? [null, 'too-long'] : [$value, null],
            default => match (true) {
                // A line is one line: a newline in it came from no field of the page.
                str_contains($value, "\n") => [null, 'invalid'],
                mb_strlen($value) > OrderField::MAX_TEXT => [null, 'too-long'],
                default => [$value, null],
            },
        };
    }

    /**
     * Whether the answer is what the row holds: then the row, its label too, stays a snapshot.
     *
     * @param  array{key: string, type: string, label: string, value: bool|string, text: string}  $row
     */
    private static function same(array $row, mixed $raw): bool
    {
        if ($row['value'] === true) {
            return is_scalar($raw) && filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        return is_scalar($raw) && ! is_bool($raw) && (string) $raw === $row['value'];
    }

    private static function said(string $label, string $type, string $problem, ?string $locale): string
    {
        return (string) match ($problem) {
            'required' => __('webx-orders::checkout.errors.required', ['field' => $label], $locale),
            'too-long' => __('webx-orders::checkout.errors.too-long', [
                'field' => $label,
                'max' => $type === OrderField::TEXTAREA ? OrderField::MAX_TEXTAREA : OrderField::MAX_TEXT,
            ], $locale),
            'option-gone' => __('webx-orders::checkout.errors.option-gone', ['field' => $label], $locale),
            default => __('webx-orders::checkout.errors.invalid', ['field' => $label], $locale),
        };
    }
}
