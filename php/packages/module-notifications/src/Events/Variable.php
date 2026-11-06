<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Events;

use InvalidArgumentException;
use WebxUi\Templates\Variable as TemplateVariable;

/**
 * One value an event gives its templates (§5): a variable of `webx-ui/templates`, and who may read
 * it.
 *
 * With `address` it is also an addressee (decision 12): its value is an address of that kind
 * (`email`, `telegram`), and a channel of the event may name it as a recipient — the visitor who
 * filled the form, the customer who placed the order.
 *
 * With `internal` it is for the staff: a channel that writes to an addressee and uses it is saved
 * with a warning — the internal status of an order is not the buyer's to read. Not refused: a
 * channel for the manager and one for the buyer are made the same way.
 */
final readonly class Variable extends TemplateVariable
{
    /**
     * @param  string|list<array<string, string>>  $example
     * @param  array<string, string>  $fields  of a list: name => label
     */
    public function __construct(
        string $name,
        string $label,
        string|array $example = '',
        public ?string $address = null,
        array $fields = [],
        public bool $internal = false,
    ) {
        parent::__construct($name, $label, $example, $fields);

        if ($fields !== [] && $address !== null) {
            throw new InvalidArgumentException("[{$name}] is a list: it is not an address.");
        }
    }

    /**
     * @return array{name: string, label: string, example: string|list<array<string, string>>, address: string|null, fields: list<array{name: string, label: string}>, internal: bool}
     */
    public function toArray(): array
    {
        $variable = parent::toArray();

        return [
            'name' => $variable['name'],
            'label' => $variable['label'],
            'example' => $variable['example'],
            'address' => $this->address,
            'fields' => $variable['fields'],
            'internal' => $this->internal,
        ];
    }
}
