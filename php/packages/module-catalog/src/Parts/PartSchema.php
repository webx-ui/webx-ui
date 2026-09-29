<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Parts;

/**
 * What a part of the product form holds, told to whoever writes it without the form in front of
 * them: an agent reading the resource of the form's parts (§12.2) before it calls
 * `catalog_products_update`.
 */
final class PartSchema
{
    /**
     * @param  string  $label  A translation key or words.
     * @param  string  $module  The panel module the part belongs to.
     * @param  list<PartField>  $fields
     */
    public function __construct(
        public readonly string $label,
        public readonly string $module,
        public readonly array $fields,
    ) {}

    /**
     * @return array{label: string, module: string, fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'label' => (string) __($this->label),
            'module' => $this->module,
            'fields' => array_map(static fn (PartField $field): array => $field->toArray(), $this->fields),
        ];
    }
}
