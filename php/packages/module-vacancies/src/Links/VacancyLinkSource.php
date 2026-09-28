<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Routing\Models\Route;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * Vacancies, for whatever points at an entity: a menu entry, a link field. The hint is the city —
 * what tells "the same position in Lviv" from the one in Kyiv.
 */
final class VacancyLinkSource implements LinkSource
{
    public function type(): string
    {
        return Vacancy::TYPE;
    }

    public function model(): string
    {
        return Vacancy::class;
    }

    public function title(): string
    {
        return (string) __('webx-vacancies::module.vacancies');
    }

    public function icon(): string
    {
        return 'briefcase';
    }

    public function order(): int
    {
        return 250;
    }

    public function permission(): string
    {
        return 'vacancies.view';
    }

    public function search(string $query, string $locale, int $limit): array
    {
        $vacancies = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->where(static function (Builder $nested) use ($query): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
            }))
            ->orderBy('position')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($vacancies, $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Vacancy>
     */
    private function query(string $locale): Builder
    {
        return Vacancy::query()->with([
            'routes' => static fn ($routes) => $routes
                ->where('locale', $locale)
                ->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Vacancy>  $vacancies
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $vacancies, string $locale): array
    {
        $candidates = [];

        foreach ($vacancies as $vacancy) {
            $id = (int) $vacancy->getKey();
            $canonical = $vacancy->routes->first();
            $city = $vacancy->hasPlace() ? $vacancy->text('city', $locale) : '';

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: self::name($vacancy, $locale),
                url: $canonical instanceof Route ? $vacancy->urlOf($canonical->path, $locale) : null,
                available: $vacancy->isPublished() && $canonical instanceof Route,
                hint: $city === '' ? null : $city,
            );
        }

        return $candidates;
    }

    private static function name(Vacancy $vacancy, string $locale): string
    {
        foreach (['title', 'slug'] as $attribute) {
            $candidate = $vacancy->getTranslation($attribute, $locale);

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return '#'.$vacancy->getKey();
    }
}
