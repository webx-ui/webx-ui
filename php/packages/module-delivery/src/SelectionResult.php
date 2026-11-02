<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use WebxUi\Delivery\Models\DeliverySelection;

/** What `Selections::put()` did: the row written, or the errors by field and nothing written. */
final readonly class SelectionResult
{
    /** @param  array<string, string>  $errors */
    private function __construct(
        public ?DeliverySelection $selection,
        public array $errors,
    ) {}

    public static function written(DeliverySelection $selection): self
    {
        return new self($selection, []);
    }

    /** @param  array<string, string>  $errors */
    public static function refused(array $errors): self
    {
        return new self(null, $errors);
    }

    public function ok(): bool
    {
        return $this->errors === [];
    }
}
