<?php

declare(strict_types=1);

namespace WebxUi\Media\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Media\Models\MediaDirectory;

/**
 * @mixin MediaDirectory
 */
final class DirectoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'depth' => $this->getDepth(),
            'is_root' => $this->isLibraryRoot(),
            // Only when it was asked for: the tree endpoint counts in one query, a single
            // folder's answer does not need it.
            'files_count' => $this->whenCounted('files'),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
