<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Settings;

/** One option of a carrier's settings field, from its account (§13.1). */
final readonly class SettingOption
{
    public function __construct(
        /** The carrier's id: a Ref. */
        public string $value,
        /** As the admin sees it: «Sender A, Town A». */
        public string $label,
        /** A second line: a phone, a tax id. */
        public ?string $hint = null,
    ) {}

    /** @return array{value: string, label: string, hint: string|null} */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label, 'hint' => $this->hint];
    }
}
