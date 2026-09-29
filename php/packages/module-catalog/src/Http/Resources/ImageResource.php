<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Catalog\Models\ProductImage;

/**
 * One picture of the gallery as the editor shows it: the file, a preview, the size, and `alt`
 * and `title` in every language — the editor writes all of them at once (§11.1).
 *
 * @mixin ProductImage
 */
final class ImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductImage $image */
        $image = $this->resource;

        return [
            'id' => $image->id,
            'path' => $image->path,
            'url' => $image->url(),
            'thumb' => $image->thumbUrl(320, 320),
            'alt' => (object) $image->getTranslations('alt'),
            'title' => (object) $image->getTranslations('title'),
            'width' => $image->width,
            'height' => $image->height,
            'size' => $image->size,
            'position' => $image->position,
        ];
    }
}
