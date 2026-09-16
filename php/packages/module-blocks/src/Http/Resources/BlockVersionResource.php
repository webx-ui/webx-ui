<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * One version of a block type. The history lists summaries — number, who, when, why —
 * and asks for the content of one version by itself: a version is a few kilobytes and a
 * history is thirty of them.
 *
 * @mixin BlockVersion
 */
final class BlockVersionResource extends JsonResource
{
    /**
     * @param  array<int, string>  $authors
     */
    public function __construct(
        BlockVersion $version,
        private readonly array $authors = [],
        private readonly bool $withContent = false,
    ) {
        parent::__construct($version);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BlockVersion $version */
        $version = $this->resource;

        $payload = self::summary($version, $this->authors);

        if ($this->withContent) {
            $payload['content'] = $version->content();
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $authors
     * @return array<string, mixed>
     */
    public static function summary(BlockVersion $version, array $authors): array
    {
        return [
            'number' => $version->number,
            'source' => $version->source,
            'comment' => $version->comment,
            'author_id' => $version->author_id,
            'author' => $version->author_id === null ? null : ($authors[$version->author_id] ?? null),
            'created_at' => $version->created_at?->toAtomString(),
        ];
    }
}
