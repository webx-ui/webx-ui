<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

use WebxUi\Orders\Models\PublicStatus;

/** The status the buyer sees; `code` is what templates and agents go by. */
final readonly class PublicStatusData
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
    ) {}

    public static function of(PublicStatus $status, string $locale): self
    {
        return new self($status->id, $status->code, (string) $status->getTranslation('name', $locale));
    }
}
