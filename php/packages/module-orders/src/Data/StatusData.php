<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

use WebxUi\Orders\Models\OrderStatus;

/** An internal status of that moment; `milestone` — what moving into it sets, if anything. */
final readonly class StatusData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $color,
        public ?string $milestone,
    ) {}

    public static function of(OrderStatus $status, string $locale): self
    {
        return new self(
            $status->id,
            (string) $status->getTranslation('name', $locale),
            $status->color->value,
            $status->sets_milestone?->value,
        );
    }
}
