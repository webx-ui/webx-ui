<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Parts;

/**
 * One field of a part: its name without the prefix, its type, the rules it is checked by and
 * where its allowed values come from — a list, or the tool or resource to ask for a reference
 * book too long to put here.
 */
final class PartField
{
    /**
     * @param  string  $type  `string`, `integer`, `number`, `boolean`, `id`, `ids`, `text`…
     * @param  string  $label  A translation key or words.
     * @param  list<string>  $rules  Readable rules, as Laravel spells them.
     * @param  list<mixed>|string|null  $values  The allowed values, or the tool/resource that lists them.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly array $rules = [],
        public readonly array|string|null $values = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'label' => (string) __($this->label),
            'rules' => $this->rules,
            'values' => $this->values,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
