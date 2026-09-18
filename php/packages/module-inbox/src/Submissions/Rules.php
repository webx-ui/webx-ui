<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Submissions;

use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Storage\FileStore;

/**
 * The rules a form is, as the fields somebody dragged into it (§6.3).
 *
 * Everything is under `fields.<name>`, which is also how it arrives — `fields[email]` — so an
 * error lands under the input that caused it with nothing in between to translate names.
 *
 * Only the live fields are here. A disabled field that still validated would refuse a
 * submission over something the visitor was never shown, which is a form that cannot be
 * submitted and cannot be debugged.
 */
final class Rules
{
    public function __construct(private readonly FileStore $files) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Form $form): array
    {
        $rules = [];

        foreach ($form->liveFields as $field) {
            foreach ($this->field($field) as $key => $rule) {
                $rules[$key] = $rule;
            }
        }

        return $rules;
    }

    /**
     * The labels a message uses, so a refusal reads "Your e-mail is required" rather than
     * "fields.email".
     *
     * @return array<string, string>
     */
    public function attributes(Form $form): array
    {
        $attributes = [];

        foreach ($form->liveFields as $field) {
            $label = (string) $field->title;

            if ($label !== '') {
                $attributes['fields.'.$field->key()] = $label;
                $attributes['fields.'.$field->key().'.*'] = $label;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function field(Field $field): array
    {
        $key = 'fields.'.$field->key();
        $presence = $field->is_required ? 'required' : 'nullable';

        return match ($field->type) {
            FieldType::Email => [$key => [$presence, 'string', 'email', 'max:255']],
            FieldType::Tel => [$key => array_values(array_filter([
                $presence,
                'string',
                'max:64',
                $this->pattern($field),
            ]))],
            FieldType::Date => [$key => array_values(array_filter([
                $presence,
                'date',
                $this->bound($field, 'min', 'after_or_equal'),
                $this->bound($field, 'max', 'before_or_equal'),
            ]))],
            FieldType::Select, FieldType::Radio => [$key => [$presence, 'string', $this->oneOf($field)]],
            FieldType::Checkbox => $this->checkbox($field, $key),
            // `accepted` and not `boolean`: an unticked checkbox is not in the request at all,
            // and a required consent has to fail on that rather than on its shape.
            FieldType::Consent => [$key => $field->is_required ? ['accepted'] : ['nullable']],
            FieldType::File => $this->file($field, $key),
            default => [$key => [$presence, 'string', 'max:'.$this->maxLength($field)]],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function checkbox(Field $field, string $key): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable', 'array'];

        foreach (['min', 'max'] as $option) {
            $bound = $field->option($option);

            if (is_numeric($bound) && (int) $bound > 0) {
                $rules[] = $option.':'.(int) $bound;
            }
        }

        return [
            $key => $rules,
            $key.'.*' => ['string', $this->oneOf($field)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(Field $field, string $key): array
    {
        $one = [
            'file',
            'max:'.$this->files->maxSize($field),
            // `mimes` rather than `mimetypes`: it is written in extensions, which is what the
            // configuration says and what the refusal says, and Laravel still checks the
            // file's real type rather than trusting its name.
            'mimes:'.implode(',', $this->files->extensions($field)),
        ];

        if (! $field->isMultiple()) {
            return [$key => [$field->is_required ? 'required' : 'nullable', ...$one]];
        }

        return [
            $key => [$field->is_required ? 'required' : 'nullable', 'array', 'max:'.$this->files->maxFiles()],
            $key.'.*' => ['required', ...$one],
        ];
    }

    /** `in:` over the values the field was given, so an answer nobody was offered is refused. */
    private function oneOf(Field $field): string
    {
        $choices = array_keys($field->choices());

        // A field with no choices at all accepts nothing rather than everything: it is a
        // half-written field, and letting arbitrary text through it would put arbitrary text
        // in a column the panel draws as a badge.
        return 'in:'.implode(',', $choices);
    }

    private function pattern(Field $field): ?string
    {
        $pattern = $field->option('pattern');

        if (! is_string($pattern) || trim($pattern) === '') {
            return null;
        }

        // The form's own HTML pattern, which has no delimiters and is anchored implicitly by
        // the browser; both have to be added for it to mean the same thing here.
        return 'regex:/^(?:'.str_replace('/', '\/', $pattern).')$/u';
    }

    private function bound(Field $field, string $option, string $rule): ?string
    {
        $value = $field->option($option);

        return is_string($value) && $value !== '' ? $rule.':'.$value : null;
    }

    private function maxLength(Field $field): int
    {
        $maxlength = $field->option('maxlength');

        if (is_numeric($maxlength) && (int) $maxlength > 0) {
            return (int) $maxlength;
        }

        // A textarea holds a letter; everything else holds a line. Both are ceilings against
        // a robot pasting a novel, not limits anybody should meet.
        return $field->type === FieldType::Textarea ? 20000 : 1000;
    }
}
