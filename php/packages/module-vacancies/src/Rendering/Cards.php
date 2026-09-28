<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Rendering;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Routing\Models\Route;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Support\Salary;

/**
 * A vacancy as a template reads it — plain data, not the model (§4.8).
 *
 *     id, url, title, lead      in the language asked for; `lead` is plain text
 *     workplace                 onsite | remote | hybrid
 *     city, address             words in this language, '' where there are none (and for remote)
 *     employment_types          the codes: ['FULL_TIME', 'PART_TIME']
 *     employment                the same in words of this language
 *     salary                    the editor's words, '' where there are none
 *     salary_range              { min, max, unit, currency, symbol } — or null without a
 *                               currency, a unit and a number
 *     valid_through, posted_at  `Y-m-d`, or null
 *     closed                    closed by hand or past its last day
 *     categories                ids of the visible categories, in the order chosen
 *     category_names            their names, in the same order
 *     form                      the slug of the application form when it is switched on, or null
 *     fields                    the project's own fields (a patch on `vacancies.form`), by name
 *
 * Not the model, because a model in a template is the draft one call away and a query per card
 * nobody sees: every card here is built from what was loaded with the list.
 */
final class Cards
{
    /** What a list of vacancies is loaded with, so that no card goes back to the database. */
    public const RELATIONS = ['routes', 'categories'];

    public function __construct(
        private readonly Salary $salary,
        private readonly Translator $translator,
    ) {}

    /**
     * @param  list<Vacancy>  $vacancies
     * @return list<array<string, mixed>>
     */
    public function vacancies(array $vacancies, string $locale): array
    {
        if ($vacancies !== []) {
            // Every application form of the list in one query rather than one per card.
            Relations::load($vacancies, Vacancy::FORM);
        }

        return array_map(fn (Vacancy $vacancy): array => $this->vacancy($vacancy, $locale), $vacancies);
    }

    /**
     * The kinds of employment in words of a language, in Google's order.
     *
     * @return list<string>
     */
    public function employment(Vacancy $vacancy, string $locale): array
    {
        return array_map(
            fn (string $kind): string => (string) $this->translator->get('webx-vacancies::vacancy.employment.'.$kind, [], $locale),
            $vacancy->employment(),
        );
    }

    /**
     * The categories a reader sees, in the order chosen.
     *
     * @return list<VacancyCategory>
     */
    public function visibleCategories(Vacancy $vacancy): array
    {
        return array_values($vacancy->shownCategories()
            ->filter(static fn (VacancyCategory $category): bool => $category->is_visible && ! $category->trashed())
            ->all());
    }

    /** @return array<string, mixed> */
    private function vacancy(Vacancy $vacancy, string $locale): array
    {
        $fields = [];

        foreach (array_keys((array) ($vacancy->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $vacancy->extra((string) $name, $locale);
        }

        $categories = $this->visibleCategories($vacancy);
        $place = $vacancy->hasPlace();

        return [
            'id' => (int) $vacancy->getKey(),
            'url' => $this->url($vacancy, $locale),
            'title' => $vacancy->text('title', $locale),
            'lead' => $vacancy->text('lead', $locale),
            'workplace' => $vacancy->workplace,
            'city' => $place ? $vacancy->text('city', $locale) : '',
            'address' => $place ? $vacancy->text('address', $locale) : '',
            'employment_types' => $vacancy->employment(),
            'employment' => $this->employment($vacancy, $locale),
            'salary' => $vacancy->text('salary', $locale),
            'salary_range' => $this->salary->range($vacancy),
            'valid_through' => $vacancy->valid_through?->toDateString(),
            'posted_at' => $vacancy->posted_at?->toDateString(),
            'closed' => $vacancy->isClosed(),
            'categories' => array_map(static fn (VacancyCategory $category): int => (int) $category->getKey(), $categories),
            'category_names' => array_map(static fn (VacancyCategory $category): string => $category->displayName($locale), $categories),
            'form' => $vacancy->formSlug($locale),
            'fields' => $fields,
        ];
    }

    /**
     * From the loaded registry rows when the list loaded them: `url()` would ask the registry
     * again for every card.
     */
    private function url(Vacancy $vacancy, string $locale): string
    {
        if (! $vacancy->relationLoaded('routes')) {
            return (string) $vacancy->url($locale);
        }

        /** @var Collection<int, Route> $routes */
        $routes = $vacancy->getRelation('routes');
        $row = $routes->first(
            static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL,
        );

        return $row instanceof Route ? (string) $vacancy->urlOf($row->path, $locale) : (string) $vacancy->url($locale);
    }
}
