<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Events;

use InvalidArgumentException;

/**
 * One value an event gives its templates (§5): `form`, `fields`, or a family like `field.*` —
 * any `field.<name>`.
 *
 * With `fields` it is a list (§7): the items of an order, each with `name`, `qty`, `price` — a
 * template goes over it with `{{#items}}…{{/items}}`, and its example is a list of such rows.
 *
 * With `address` it is also an addressee (decision 12): its value is an address of that kind
 * (`email`, `telegram`), and a channel of the event may name it as a recipient — the visitor who
 * filled the form, the customer who placed the order.
 */
final readonly class Variable
{
    /**
     * @param  string|list<array<string, string>>  $example
     * @param  array<string, string>  $fields  of a list: name => label
     */
    public function __construct(
        public string $name,
        public string $label,
        public string|array $example = '',
        public ?string $address = null,
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

        if ($fields !== [] && (str_contains($name, '.') || $address !== null)) {
            throw new InvalidArgumentException("[{$name}] is a list: its name has no dot, and it is not an address.");
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
     * @return array{name: string, label: string, example: string|list<array<string, string>>, address: string|null, fields: list<array{name: string, label: string}>}
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
            'address' => $this->address,
            'fields' => $fields,
        ];
    }
}
