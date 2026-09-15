<?php

declare(strict_types=1);

namespace WebxUi\Routing\Aliases;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

/**
 * One address an entity used to have, as somebody outside the registry needs to see it.
 *
 * A flat value rather than the `Route` model, because the one reader is a panel screen in another
 * package: handing it the model would hand it the query builder, the relations and the table name
 * as well, and the next change to any of those would be a change to `module-seo`.
 *
 * @implements Arrayable<string, mixed>
 */
final class Alias implements Arrayable
{
    public function __construct(
        public readonly int $id,
        public readonly string $locale,
        /** The old address, as the registry spells it: no leading slash, `''` for the root. */
        public readonly string $path,
        /** The same address as a browser asks for it, language prefix and encoding included. */
        public readonly string $url,
        /** Where it leads now; null when the row it pointed at is gone. */
        public readonly ?string $target,
        public readonly ?string $targetUrl,
        /** The morph alias of what owns the address — `page`, `article`, `product`. */
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly ?Carbon $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
            'path' => $this->path,
            'url' => $this->url,
            'target' => $this->target,
            'target_url' => $this->targetUrl,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'created_at' => $this->createdAt?->toAtomString(),
        ];
    }
}
