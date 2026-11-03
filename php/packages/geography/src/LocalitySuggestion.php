<?php

declare(strict_types=1);

namespace WebxUi\Geography;

/** One line of the locality suggestions, in the language it was asked for. */
final readonly class LocalitySuggestion
{
    public function __construct(
        public int $id,
        public string $name,
        public string $kind,
        public ?string $district,
        public ?string $region,
        /** "Town D, Region B": what tells two places of one name apart. */
        public string $label,
    ) {}

    /** @return array{id: int, name: string, kind: string, district: string|null, region: string|null, label: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'district' => $this->district,
            'region' => $this->region,
            'label' => $this->label,
        ];
    }
}
