<?php

declare(strict_types=1);

namespace WebxUi\Banners\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Panel\BannerNames;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;

/**
 * One banner as a row of the panel's list (§5.6):
 * `{ id, title, thumb, video, enabled, position, updated_at, deleted_at }`.
 *
 * The picture is only its thumbnail — a string or null — and the video only whether there is
 * one: the row shows a mark, not a player. The controller loads the library rows of the whole
 * list first, so this is not a query per row.
 *
 * @mixin Banner
 */
final class BannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Banner $banner */
        $banner = $this->resource;

        return [
            'id' => (int) $banner->getKey(),
            'title' => BannerNames::of($banner, app(Locales::class)),
            'thumb' => $this->thumb($banner),
            'video' => $banner->mediaPath('video') !== null,
            'enabled' => $banner->enabled,
            'position' => (int) $banner->position,
            'updated_at' => $banner->updated_at?->toAtomString(),
            'deleted_at' => $banner->deleted_at?->toAtomString(),
        ];
    }

    private function thumb(Banner $banner): ?string
    {
        if ($banner->mediaPath('image') === null) {
            return null;
        }

        $image = app(MediaValues::class)->resolve($banner->image);
        $thumb = $image['thumb'] ?? $image['url'] ?? null;

        return is_string($thumb) && $thumb !== '' ? $thumb : null;
    }
}
