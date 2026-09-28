<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Mcp;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Support\Salary;

/**
 * What an agent reads before it writes a vacancy (§4.12): the categories with their slugs and
 * their open vacancies, and only a count of the closed ones.
 *
 * The open ones are what an agent is about to duplicate by mistake — "a PHP developer in Kyiv"
 * written twice is the thing to catch — so each category lists them in the one order vacancies
 * have, with where the work is, the application form and whether they are on the site. The closed
 * ones pile up and are only counted; `vacancies_list` with `state: closed` has them. Drafts are in
 * it and say so. A vacancy in two categories is listed under both: that is where a reader meets it.
 *
 * The slug of a category is here although the category has no page: it is the key of the careers
 * page's filter, and what an agent names a category by. And the site's currencies and country,
 * without which the numbers of a salary cannot be written.
 */
final class VacanciesResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'vacancies://catalog',
                'Vacancies catalog',
                'Every vacancy category in its order, with its slug — the key of the careers page\'s filter — and its '
                .'open vacancies: their addresses, where the work is, the city, whether they are on the site, the '
                .'languages they are written in and their application form; how many of its vacancies are closed; the '
                .'vacancies in no category at the end; the site\'s currencies and the country of a new vacancy. Read it '
                .'before creating a vacancy or a category, so that you reuse rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $prefix = (string) config('webx-vacancies.prefix', 'careers');
        $locale = $locales->current();

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'prefix' => $prefix,
            // Null when the module's index is switched off: a page may stand at that address.
            'index_url' => (bool) config('webx-vacancies.index', true) ? url($prefix) : null,
            'currencies' => $this->container->make(Salary::class)->currencies(),
            'country' => $this->country(),
            'categories' => VacancyCategory::query()->ordered()->get()->map(fn (VacancyCategory $category): array => [
                'id' => (int) $category->getKey(),
                'title' => $category->displayName($locale),
                'slug' => (string) $category->getTranslation('slug', $locale),
                'visible' => (bool) $category->is_visible,
                'open' => $this->rows(
                    $this->inCategory((int) $category->getKey())->scopes(['open', 'byPosition'])->with('routes')->get()->all(),
                    $locales,
                ),
                'closed_count' => $this->inCategory((int) $category->getKey())->scopes(['closed'])->count(),
            ])->values()->all(),
            'uncategorised' => [
                'open' => $this->rows(
                    Vacancy::query()->whereDoesntHave('categories')->scopes(['open', 'byPosition'])->with('routes')->get()->all(),
                    $locales,
                ),
                'closed_count' => Vacancy::query()->whereDoesntHave('categories')->scopes(['closed'])->count(),
            ],
        ];
    }

    /**
     * @return Builder<Vacancy>
     */
    private function inCategory(int $category): Builder
    {
        return Vacancy::query()->scopes(['inCategory' => [$category]]);
    }

    /**
     * @param  list<Vacancy>  $vacancies
     * @return list<array<string, mixed>>
     */
    private function rows(array $vacancies, Locales $locales): array
    {
        $locale = $locales->current();
        $codes = $locales->codes();
        $target = $this->container->make(RelationTargets::class)->find(Vacancy::FORM_TARGET);

        return array_map(static function (Vacancy $vacancy) use ($locale, $codes, $target): array {
            $shown = $vacancy->hasDraft() ? $vacancy->withDraft() : $vacancy;

            $row = [
                'id' => (int) $vacancy->getKey(),
                'title' => (string) $shown->getTranslation('title', $locale),
                'url' => $vacancy->hasUrlIn($locale) ? $vacancy->url($locale) : null,
                'workplace' => $shown->workplace,
                'city' => $shown->text('city', $locale),
                'status' => $vacancy->status(),
                'written_in' => array_values(array_filter(
                    $codes,
                    static fn (string $code): bool => trim((string) $shown->getTranslation('title', $code, false)) !== '',
                )),
            ];

            // Only where there are forms to choose: a key that is always null reads as "missing".
            if ($target !== null) {
                $ids = $vacancy->draftedRelatedIds(Vacancy::FORM);
                $slug = $ids === [] ? null : $target->query()->whereKey($ids[0])->value('slug');
                $row['form'] = is_string($slug) ? $slug : null;
            }

            return $row;
        }, $vacancies);
    }

    private function country(): ?string
    {
        $country = strtoupper(trim((string) config('webx-vacancies.country', '')));

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }
}
