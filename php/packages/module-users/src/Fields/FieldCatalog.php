<?php

declare(strict_types=1);

namespace WebxUi\Users\Fields;

/**
 * The fields of the visitors as the panel and an agent are told them (spec §10.4, §17): the nodes
 * the card `project-fields` of `users.form` is given when the screen is handed out — no patch of
 * the site needed — and the list the resource `users://catalog` gives. Both are made from the
 * registry and the settings, never written by hand.
 */
final readonly class FieldCatalog
{
    /** The card of `users.form` the fields of the site go into. */
    public const string CARD = 'project-fields';

    public function __construct(
        private UserFields $fields,
        private Requirements $requirements,
    ) {}

    /**
     * The nodes of the fields with the place `panel`, required as the setting says.
     *
     * @return list<array<string, mixed>>
     */
    public function nodes(): array
    {
        return array_map(
            fn (UserField $field): array => $field->node($this->requirements->required($field->name)),
            $this->fields->for(Placement::Panel),
        );
    }

    /**
     * Every field of a visitor: the built-in ones the setting is for, then the site's — name,
     * type, places, what the setting asks, `unique`, `visitorEditable`, who declared it.
     *
     * @return list<array{name: string, type: string, label: string, placements: list<string>, requirement: string, unique: bool, visitor_editable: bool, source: string, built_in: bool}>
     */
    public function describe(): array
    {
        $all = [];

        foreach (Requirements::BUILT_IN as $name) {
            $all[] = [
                'name' => $name,
                'type' => 'wx-input',
                'label' => (string) trans("webx-users::auth.fields.{$name}"),
                'placements' => array_map(static fn (Placement $placement): string => $placement->value, Placement::cases()),
                'requirement' => $this->requirements->of($name)->value,
                'unique' => false,
                'visitor_editable' => true,
                'source' => 'users',
                'built_in' => true,
            ];
        }

        foreach ($this->fields->all() as $field) {
            $all[] = [
                'name' => $field->name,
                'type' => $field->type,
                'label' => $field->labelText(),
                'placements' => array_map(static fn (Placement $placement): string => $placement->value, $field->placements),
                'requirement' => $this->requirements->of($field->name)->value,
                'unique' => $field->unique,
                'visitor_editable' => $field->visitorEditable,
                'source' => $field->source ?? 'site',
                'built_in' => false,
            ];
        }

        return $all;
    }
}
