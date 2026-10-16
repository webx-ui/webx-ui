<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Events;

use InvalidArgumentException;

/**
 * One value an event gives its templates (§5): `form`, `fields`, or a family like `field.*` —
 * any `field.<name>`.
 *
 * With `address` it is also an addressee (decision 12): its value is an address of that kind
 * (`email`, `telegram`), and a channel of the event may name it as a recipient — the visitor who
 * filled the form, the customer who placed the order.
 */
final readonly class Variable
{
    public function __construct(
        public string $name,
        public string $label,
        public string $example = '',
        public ?string $address = null,
    ) {
        if (preg_match('/^[a-z0-9_]+(\.([a-z0-9_]+|\*))?$/', $name) !== 1) {
            throw new InvalidArgumentException("[{$name}] is not a variable name: lowercase letters, digits and _, one dot at most.");
        }
    }

    /** `field.*`: stands for every `field.<name>`. */
    public function isFamily(): bool
    {
        return str_ends_with($this->name, '.*');
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
     * @return array{name: string, label: string, example: string, address: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => (string) __($this->label),
            'example' => $this->example,
            'address' => $this->address,
        ];
    }
}
