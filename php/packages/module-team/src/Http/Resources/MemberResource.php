<?php

declare(strict_types=1);

namespace WebxUi\Team\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;
use WebxUi\Team\Models\Member;
use WebxUi\Team\Panel\MemberNames;

/**
 * One person as a row of the panel's list (§5.7):
 * `{ id, name, job_title, initials, photo: { thumb } | null, published, position, updated_at, deleted_at }`.
 *
 * The photo is only its thumbnail: a list of forty people has no use for forty sets of sizes. The
 * controller loads the library rows of the whole list first, so this is not a query per row. The
 * initials are the site's own ({@see Member::initials()}), so the panel does not cut a Cyrillic
 * name by bytes on its own; '' for a person without a name.
 *
 * @mixin Member
 */
final class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Member $member */
        $member = $this->resource;

        $locales = app(Locales::class);
        $locale = $locales->current();
        $name = MemberNames::of($member, $locales);

        return [
            'id' => (int) $member->getKey(),
            'name' => $name,
            'job_title' => $member->wordsIn('job_title', $locale, $locales->defaultCode()),
            'initials' => Member::initials($member->wordsIn('name', $locale, $locales->defaultCode())),
            'photo' => $this->photo($member, $locale),
            'published' => $member->published,
            'position' => (int) $member->position,
            'updated_at' => $member->updated_at?->toAtomString(),
            'deleted_at' => $member->deleted_at?->toAtomString(),
        ];
    }

    /** @return array{thumb: string}|null */
    private function photo(Member $member, string $locale): ?array
    {
        if ($member->photoPath() === null) {
            return null;
        }

        $photo = app(MediaValues::class)->resolve($member->photo, $locale);
        $thumb = $photo['thumb'] ?? $photo['url'] ?? null;

        return is_string($thumb) && $thumb !== '' ? ['thumb' => $thumb] : null;
    }
}
