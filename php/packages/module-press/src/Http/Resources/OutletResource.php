<?php

declare(strict_types=1);

namespace WebxUi\Press\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Panel\OutletNames;

/**
 * One outlet as a row of the panel's list (§4.10).
 *
 * `locales` is where the outlet would be seen — where it has an article a reader of that language
 * sees — not where it is seen: publishing is a flag of its own, so the list can say "published,
 * and seen in no language" about the row that is.
 *
 * The logo is only its thumbnail: a list of forty outlets has no use for forty sets of sizes. The
 * controller loads the library rows of the whole list first, so this is not a query per row.
 *
 * @mixin Outlet
 */
final class OutletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Outlet $outlet */
        $outlet = $this->resource;
        $locales = app(Locales::class);

        $outlet->loadMissing('articles');

        return [
            'id' => (int) $outlet->getKey(),
            'title' => OutletNames::of($outlet, $locales),
            'logo' => $this->logo($outlet, $locales->content()),
            'published' => $outlet->published,
            'featured' => $outlet->featured,
            'position' => (int) $outlet->position,
            'locales' => $outlet->localesWithArticles(),
            'articles_count' => $outlet->articles->count(),
            'updated_at' => $outlet->updated_at?->toAtomString(),
            'deleted_at' => $outlet->deleted_at?->toAtomString(),
        ];
    }

    /** @return array{thumb: string}|null */
    private function logo(Outlet $outlet, string $locale): ?array
    {
        if ($outlet->logoPath() === null) {
            return null;
        }

        $logo = app(MediaValues::class)->resolve($outlet->logo, $locale);
        $thumb = $logo['thumb'] ?? $logo['url'] ?? null;

        return is_string($thumb) && $thumb !== '' ? ['thumb' => $thumb] : null;
    }
}
