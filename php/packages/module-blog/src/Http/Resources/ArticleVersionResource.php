<?php

declare(strict_types=1);

namespace WebxUi\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Admin\Versions\EntityVersion;

/**
 * One line of the history.
 *
 * Without the payload: the list is read to find the publication to go back to, and an article
 * made of blocks weighs kilobytes — thirty of them would be a megabyte of JSON to draw thirty
 * dates. What restoring does with the payload is the server's business anyway.
 *
 * @mixin EntityVersion
 */
final class ArticleVersionResource extends JsonResource
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
            // Who, and failing that where from: an article is written by people and by agents,
            // and "who did this" is the first question the history is opened with.
            'author' => $version->author_id === null ? null : ($this->authors[$version->author_id] ?? null),
            'source' => $version->source,
            'comment' => $version->comment,
            'is_pinned' => $version->is_pinned,
        ];
    }
}
