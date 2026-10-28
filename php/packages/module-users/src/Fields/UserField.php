<?php

declare(strict_types=1);

namespace WebxUi\Users\Fields;

use InvalidArgumentException;

/**
 * A field of the site on its visitors (spec §10.1): a club card, a date of birth, a company.
 * Declared by a module (`UserFields::register()` in `boot()`) or by the site (`webx-users.fields`),
 * stored under its name in `users.extra`, checked by its type of the panel's screens everywhere —
 * on the site, in the panel and through MCP.
 */
final readonly class UserField
{
    /**
     * @param  string  $type  a FieldTypes key: 'wx-input', 'wx-select', 'wx-date-picker', …
     * @param  array<string, mixed>  $props  the screen node's props: options, `type: email`, …
     * @param  list<Placement>  $placements
     * @param  list<string>  $rules  extra Laravel rules on top of the type's: 'max:40', 'regex:…'
     */
    public function __construct(
        public string $name,                       // the key in `extra`: [a-z0-9-], not a column name
        public string $type,
        public string $label,                      // a dictionary key or plain words
        public array $props = [],
        public bool $required = false,             // the default; the panel setting overrides (§10.5)
        public array $placements = [Placement::Registration, Placement::Profile, Placement::Panel],
        public array $rules = [],
        public bool $unique = false,               // no two visitors with the same value
        public bool $visitorEditable = true,       // false: shown in the profile, changed in the panel only
        public ?string $hint = null,
        public int $position = 100,
        public ?string $source = null,             // who declared it: a module id or 'site'
    ) {}

    /**
     * One entry of `webx-users.fields`: the keys of the constructor, places by their values. A
     * config is cached, so its rules are strings and nothing else.
     *
     * @param  array<array-key, mixed>  $entry
     */
    public static function fromConfig(array $entry, ?string $name = null): self
    {
        $name = $entry['name'] ?? $name;

        if (! is_string($name) || ! is_string($entry['type'] ?? null)) {
            throw new InvalidArgumentException('webx-users.fields: every field needs a `name` and a `type`.');
        }

        $placements = [];

        foreach ((array) ($entry['placements'] ?? ['registration', 'profile', 'panel']) as $placement) {
            $placements[] = $placement instanceof Placement
                ? $placement
                : Placement::tryFrom((string) $placement) ?? throw new InvalidArgumentException(
                    "webx-users.fields.{$name}: no place «{$placement}» — registration, profile or panel.",
                );
        }

        $rules = [];

        foreach ((array) ($entry['rules'] ?? []) as $rule) {
            if (! is_string($rule)) {
                throw new InvalidArgumentException("webx-users.fields.{$name}: rules are strings in a config — it is cached.");
            }

            $rules[] = $rule;
        }

        /** @var array<string, mixed> $props */
        $props = is_array($entry['props'] ?? null) ? $entry['props'] : [];

        return new self(
            name: $name,
            type: $entry['type'],
            label: is_string($entry['label'] ?? null) ? $entry['label'] : $name,
            props: $props,
            required: (bool) ($entry['required'] ?? false),
            placements: $placements,
            rules: $rules,
            unique: (bool) ($entry['unique'] ?? false),
            visitorEditable: (bool) ($entry['visitorEditable'] ?? true),
            hint: is_string($entry['hint'] ?? null) ? $entry['hint'] : null,
            position: is_numeric($entry['position'] ?? null) ? (int) $entry['position'] : 100,
            source: 'site',
        );
    }

    public function in(Placement $placement): bool
    {
        return in_array($placement, $this->placements, true);
    }

    /**
     * The field as a node of a screen: what `FieldTypes` checks it by, what the panel draws in the
     * card `project-fields`, what `extra()` reads it through. A label that is a dictionary key
     * (`webx-club::fields.card`) is translated by the panel like every other node's.
     *
     * @return array<string, mixed>
     */
    public function node(bool $required = false): array
    {
        $node = ['id' => 'field-'.$this->name, 'type' => $this->type, 'name' => $this->name, 'label' => self::words($this->label)];

        if ($this->hint !== null) {
            $node['help'] = self::words($this->hint);
        }

        $props = $this->props;

        if ($required) {
            $props['required'] = true;
        }

        if ($props !== []) {
            $node['props'] = $props;
        }

        return $node;
    }

    /** The column `webx:users:index-field` adds for a fast search by the field: `extra_club_card`. */
    public function indexColumn(): string
    {
        return 'extra_'.str_replace('-', '_', $this->name);
    }

    /** The label in the page's language: a dictionary key translated, plain words as they are. */
    public function labelText(): string
    {
        return (string) __($this->label);
    }

    public function hintText(): ?string
    {
        return $this->hint === null ? null : (string) __($this->hint);
    }

    private static function words(string $text): string
    {
        return str_contains($text, '::') && ! str_contains($text, ' ') ? 'trans::'.$text : $text;
    }
}
