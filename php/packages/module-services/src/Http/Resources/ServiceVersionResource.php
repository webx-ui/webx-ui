<?php

declare(strict_types=1);

namespace WebxUi\Services\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Admin\Versions\EntityVersion;

/**
 * One line of the history — without the payload: a service made of blocks weighs kilobytes, and
 * the list is read to find the publication to go back to, not to read thirty of them.
 *
 * @mixin EntityVersion
 */
final class ServiceVersionResource extends JsonResource
{
    /**
     * @param  array<int, string>  $authors  Author id → name, looked up once for the whole list.
     */
    public function __construct(EntityVersion $version, private readonly array $authors = [])
    {
        parent::__construct($version);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EntityVersion $version */
        $version = $this->resource;

        return [
            'number' => $version->number,
            'created_at' => $version->created_at?->toAtomString(),
            // Who, and failing that where from: services are written by people and by agents.
            'author' => $version->author_id === null ? null : ($this->authors[$version->author_id] ?? null),
            'source' => $version->source,
            'comment' => $version->comment,
            'is_pinned' => $version->is_pinned,
        ];
    }
}
