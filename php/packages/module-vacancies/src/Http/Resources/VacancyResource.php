<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Panel\Revision;

/**
 * One vacancy as the panel knows it (§4.11): a row of the list, and the `vacancy` of the form.
 *
 * The words — title, slug, where, the kinds of employment — and the categories are the draft's:
 * what the editor is working on. Whether it is closed, and the days that decide it, are the site's:
 * that is what the tabs of the list sort by, and a row on "Open" that called itself closed because
 * of an unpublished edit would be on the wrong tab. The address is the registry's, because that is
 * what the site answers at right now.
 *
 * @mixin Vacancy
 */
final class VacancyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Vacancy $vacancy */
        $vacancy = $this->resource;

        $locales = app(Locales::class);
        $locale = $locales->current();
        $fallback = $locales->defaultCode();
        $shown = $vacancy->hasDraft() ? $vacancy->withDraft() : $vacancy;
        $canonical = $this->canonical($vacancy, $locale);

        return [
            'id' => (int) $vacancy->getKey(),
            'title' => self::word($shown, 'title', [$locale, $fallback]) ?? '#'.$vacancy->getKey(),
            'slug' => (string) $shown->getTranslation('slug', $locale, fallback: false),
            // Null where the vacancy names no slug in this language: it has no address there.
            'path' => $canonical?->path,
            'url' => $canonical === null ? null : $vacancy->url($locale),
            'workplace' => $shown->workplace,
            'city' => self::word($shown, 'city', [$locale, $fallback]) ?? '',
            'employment_types' => $shown->employment(),
            'valid_through' => $vacancy->valid_through?->toDateString(),
            'posted_at' => $vacancy->posted_at?->toDateString(),
            'closed' => $vacancy->isClosed(),
            'closed_reason' => $vacancy->closedReason(),
            'status' => $vacancy->status(),
            'position' => (int) $vacancy->position,
            'categories' => $this->categories($vacancy, $locale),
            'published_at' => $vacancy->published_at?->toAtomString(),
            'updated_at' => $vacancy->updated_at?->toAtomString(),
            'deleted_at' => $vacancy->deleted_at?->toAtomString(),
            'revision' => Revision::of($vacancy),
        ];
    }

    /**
     * A translated column in the first of these languages it is written in, or null.
     *
     * @param  list<string>  $locales
     */
    private static function word(Vacancy $vacancy, string $attribute, array $locales): ?string
    {
        foreach ($locales as $locale) {
            $value = $vacancy->getTranslation($attribute, $locale, false);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function canonical(Vacancy $vacancy, string $locale): ?Route
    {
        if (! $vacancy->relationLoaded('routes')) {
            return $vacancy->routeCanonical($locale);
        }

        return $vacancy->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL);
    }

    /**
     * In the order they were put in. The draft's when the draft names them — the loaded rows
     * otherwise, so the list is one query for every row that has no draft.
     *
     * @return list<array{id: int, title: string}>
     */
    private function categories(Vacancy $vacancy, string $locale): array
    {
        $drafted = $vacancy->draftValues()[Vacancy::DRAFT_CATEGORIES] ?? null;

        if (is_array($drafted)) {
            $ids = array_values(array_map(intval(...), $drafted));
            $found = VacancyCategory::query()->whereKey($ids)->get()->keyBy(static fn (VacancyCategory $category): int => (int) $category->getKey());
            $categories = array_values(array_filter(array_map(static fn (int $id): ?VacancyCategory => $found->get($id), $ids)));
        } else {
            $categories = $vacancy->categories->all();
        }

        return array_map(static fn (VacancyCategory $category): array => [
            'id' => (int) $category->getKey(),
            'title' => $category->displayName($locale),
        ], $categories);
    }
}
