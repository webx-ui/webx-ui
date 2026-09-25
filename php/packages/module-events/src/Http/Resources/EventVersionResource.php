<?php

declare(strict_types=1);

namespace WebxUi\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Admin\Versions\EntityVersion;

/**
 * One line of the history — without the payload: an event with its documents weighs kilobytes, and
 * the list is read to find the publication to go back to, not to read thirty of them.
 *
 * @mixin EntityVersion
 */
final class EventVersionResource extends JsonResource
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
            // Who, and failing that where from: events are written by people and by agents.
            'author' => $version->author_id === null ? null : ($this->authors[$version->author_id] ?? null),
            'source' => $version->source,
            'comment' => $version->comment,
            'is_pinned' => $version->is_pinned,
        ];
    }
}
