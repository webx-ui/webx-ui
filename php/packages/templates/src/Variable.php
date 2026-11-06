<?php

declare(strict_types=1);

namespace WebxUi\Templates;

use InvalidArgumentException;

/**
 * One value a template may use: `form`, `fields`, or a family like `field.*` — any `field.<name>`.
 * The one source of what the editor suggests: a name, a label, an example, the fields of a list.
 *
 * With `fields` it is a list: the items of an order, each with `name`, `qty`, `price` — a template
 * goes over it with `{{#items}}…{{/items}}`, and its example is a list of such rows.
 *
 * Whoever needs more of a variable extends it, the way a notification's says who may read it.
 */
readonly class Variable
{
    /**
     * @param  string|list<array<string, string>>  $example
     * @param  array<string, string>  $fields  of a list: name => label
     */
    public function __construct(
        public string $name,
        public string $label,
        public string|array $example = '',
        public array $fields = [],
    ) {
        if (preg_match('/^[a-z0-9_]+(\.([a-z0-9_]+|\*))?$/', $name) !== 1) {
            throw new InvalidArgumentException("[{$name}] is not a variable name: lowercase letters, digits and _, one dot at most.");
        }

        foreach (array_keys($fields) as $field) {
            if (preg_match('/^[a-z0-9_]+$/', (string) $field) !== 1) {
                throw new InvalidArgumentException("[{$name}.{$field}] is not a field of a list: lowercase letters, digits and _.");
            }
        }

        if ($fields !== [] && str_contains($name, '.')) {
            throw new InvalidArgumentException("[{$name}] is a list: its name has no dot.");
        }

        if ($fields === [] && is_array($example)) {
            throw new InvalidArgumentException("[{$name}] has a list for an example, but no fields.");
        }
    }

    /** `field.*`: stands for every `field.<name>`. */
    public function isFamily(): bool
    {
        return str_ends_with($this->name, '.*');
    }

    /** `{{#items}}…{{/items}}`: rows with fields, not a text. */
    public function isList(): bool
    {
        return $this->fields !== [];
    }

    /** Whether a name used in a template is this variable. */
    public function matches(string $name): bool
    {
        if ($this->isFamily()) {
            return str_starts_with($name, substr($this->name, 0, -1)) && strlen($name) > strlen($this->name) - 1;
        }

        return $name === $this->name;
    }

    /**
     * @return array{name: string, label: string, example: string|list<array<string, string>>, fields: list<array{name: string, label: string}>}
     */
    public function toArray(): array
    {
        $fields = [];

        foreach ($this->fields as $field => $label) {
            $fields[] = ['name' => (string) $field, 'label' => (string) __($label)];
        }

        return [
            'name' => $this->name,
            'label' => (string) __($this->label),
            'example' => $this->example,
            'fields' => $fields,
        ];
    }
}
