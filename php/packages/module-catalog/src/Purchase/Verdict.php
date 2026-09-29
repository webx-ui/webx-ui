<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Purchase;

/**
 * Whether a product can be bought, and if not, why (§7.5): the code for a template to choose its
 * button by, and the words for a reader.
 */
final class Verdict
{
    private function __construct(
        public readonly bool $purchasable,
        public readonly ?string $code = null,
        public readonly ?string $label = null,
    ) {}

    public static function yes(): self
    {
        return new self(true);
    }

    public static function no(string $code, string $label): self
    {
        return new self(false, $code, $label);
    }

    /**
     * @return array{purchasable: bool, code: string|null, label: string|null}
     */
    public function toArray(): array
    {
        return ['purchasable' => $this->purchasable, 'code' => $this->code, 'label' => $this->label];
    }
}
