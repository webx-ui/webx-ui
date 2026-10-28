<?php

declare(strict_types=1);

namespace WebxUi\Users\Fields;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Users\Models\User;

/**
 * The values of the site's fields, checked the same way wherever they come from (spec §10.3,
 * §10.4): the registration and the profile on the site, the card in the panel, `users_update` of
 * MCP. The rules are the type's of the panel (`FieldType::rules()`), the field's own `rules`,
 * `required` by the setting (§10.5) and `unique`; what is kept is what the type's `store()` makes.
 *
 * A form of the site sends every field it draws, so every field asked for is checked — an empty
 * one too. The panel and an agent send what they change, and only that is checked.
 */
final readonly class FieldValues
{
    /** The name of the site's forms: `fields[club-card]`, so no field of the site meets a built-in one. */
    public const string INPUT = 'fields';

    public function __construct(
        private UserFields $fields,
        private Requirements $requirements,
        private FieldTypes $types,
        private ValidationFactory $validator,
    ) {}

    /**
     * The fields of a place, as the place asks for them: on the site without the hidden ones.
     *
     * @return list<UserField>
     */
    public function asked(Placement $placement): array
    {
        $fields = $this->fields->for($placement);

        if (! $placement->onSite()) {
            return $fields;
        }

        return array_values(array_filter(
            $fields,
            fn (UserField $field): bool => $this->requirements->of($field->name) !== Requirement::Hidden,
        ));
    }

    /**
     * What a form of the site draws for each field of a place, for `<x-webx-field>`: its type on the
     * site, its control's name, its words, its choices, whether it is required, what it holds now.
     * A field of the profile the visitor may not change comes as `readonly` with its value as text.
     *
     * @return list<array{field: UserField, type: string, name: string, label: string, hint: ?string, choices: array<string, string>, required: bool, value: mixed, readonly: bool}>
     */
    public function controls(Placement $placement, ?User $user = null): array
    {
        $controls = [];

        foreach ($this->asked($placement) as $field) {
            $choices = [];

            foreach (is_array($field->props['options'] ?? null) ? $field->props['options'] : [] as $option) {
                $value = is_array($option) ? ($option['value'] ?? null) : $option;

                if (is_scalar($value)) {
                    $label = is_array($option) ? ($option['label'] ?? $value) : $value;
                    $choices[(string) $value] = (string) __(is_scalar($label) ? (string) $label : (string) $value);
                }
            }

            $controls[] = [
                'field' => $field,
                'type' => (string) $this->fields->siteTypeOf($field),
                'name' => self::INPUT.'.'.$field->name,
                'label' => $field->labelText(),
                'hint' => $field->hintText(),
                'choices' => $choices,
                'required' => $this->requirements->required($field->name),
                'value' => $user?->extra($field->name),
                'readonly' => $placement === Placement::Profile && ! $field->visitorEditable,
            ];
        }

        return $controls;
    }

    /**
     * The rules of one field: its type's, its own, the setting's `required`.
     *
     * @return list<mixed>
     */
    public function rules(UserField $field): array
    {
        $node = $field->node();
        $rules = [...($this->types->get($field->type)?->rules($node) ?? []), ...$field->rules];

        return $this->requirements->required($field->name) ? ['required', ...$rules] : $rules;
    }

    /**
     * The rules of a site's form by the names of its controls, for `FormRules` — what the browser
     * checks before the server does.
     *
     * @return array<string, list<mixed>>
     */
    public function formRules(Placement $placement): array
    {
        $rules = [];

        foreach ($this->asked($placement) as $field) {
            if ($placement === Placement::Profile && ! $field->visitorEditable) {
                continue;
            }

            $rules[self::INPUT.'.'.$field->name] = $this->rules($field);
        }

        return $rules;
    }

    /**
     * The words of the validator for each field: its label.
     *
     * @return array<string, string>
     */
    public function attributes(Placement $placement, string $prefix = self::INPUT.'.'): array
    {
        $attributes = [];

        foreach ($this->fields->for($placement) as $field) {
            $attributes[$prefix.$field->name] = $field->labelText();
        }

        return $attributes;
    }

    /**
     * Check what came in for a place and say what to keep in `extra`, by name. A field of the
     * profile the visitor may not change is dropped from what they send; a field that is not of
     * the place is dropped too, whoever sends it.
     *
     * @param  array<array-key, mixed>  $input  name => value
     * @param  bool  $whole  a form of the site, which sends every field it draws
     * @param  string  $prefix  what the errors are keyed by before the name: `fields.` for a form, nothing for MCP
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, Placement $placement, ?User $user = null, bool $whole = true, string $prefix = self::INPUT.'.'): array
    {
        $stored = [];
        $errors = [];

        foreach ($this->asked($placement) as $field) {
            $name = $field->name;

            if ($placement === Placement::Profile && ! $field->visitorEditable) {
                continue;
            }

            if (! $whole && ! array_key_exists($name, $input)) {
                continue;
            }

            $value = $input[$name] ?? null;
            $value = $value === '' || $value === [] ? null : $value;

            $validator = $this->validator->make(['value' => $value], ['value' => $this->rules($field)], [], ['value' => $field->labelText()]);

            if ($validator->fails()) {
                $errors[$prefix.$name] = array_values(array_unique($validator->errors()->all()));

                continue;
            }

            $type = $this->types->get($field->type);
            $value = $type === null ? $value : $type->store($value, $field->node());

            if ($field->unique && $value !== null && $this->taken($field, $value, $user)) {
                $errors[$prefix.$name] = [(string) trans('webx-users::auth.value_taken')];

                continue;
            }

            $stored[$name] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $stored;
    }

    /**
     * Whether another visitor holds the value: through the column `webx:users:index-field` made
     * when the site has it, through the JSON of `extra` otherwise — under a lock of the rows
     * read, which is the most a value without an index gets (§10.3).
     */
    public function taken(UserField $field, mixed $value, ?User $user = null): bool
    {
        $column = $field->indexColumn();
        $query = User::query();

        if (Schema::connection($query->getModel()->getConnectionName())->hasColumn('users', $column)) {
            $query->where($column, is_bool($value) ? (int) $value : $value);
        } else {
            $query->where('extra->'.$field->name, $value)->lockForUpdate();
        }

        if ($user !== null && $user->exists) {
            $query->whereKeyNot($user->getKey());
        }

        return $query->exists();
    }
}
