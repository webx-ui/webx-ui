<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

/**
 * One place of the extra fields (WEBX_UI_ORDER_FIELDS.md §4.1): its key, the step whose form it
 * stands in, and the words the panel names it by — a translation key or words as they are.
 */
final readonly class FieldPlace
{
    public function __construct(
        public string $key,
        public string $step,
        public string $label,
    ) {}

    /** The words in the language of whoever reads them. */
    public function name(): string
    {
        $words = __($this->label);

        return is_string($words) ? $words : $this->label;
    }
}
