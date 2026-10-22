<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Rendering;

use Illuminate\Support\Carbon;
use WebxUi\Admin\Shortcodes\PageText;
use WebxUi\Localization\Locales;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;
use WebxUi\Vacancies\Support\Salary;

/**
 * Everything the parts of a vacancy page print, worked out once (§4.6) — so a part a site
 * publishes and rewrites is markup over plain data, and no part asks the database for itself.
 *
 *     $vacancy        the model — for `extra()`, SEO and anything a site's part wants of it
 *     $title, $lead   the lead with its shortcodes resolved — print it with {{ }}
 *     $closed         closed by hand or expired — the page says so and offers no application
 *     $workplace      onsite | remote | hybrid
 *     $city, $address words; '' for remote work
 *     $employment     the kinds of employment in words
 *     $salary         the editor's words, or the numbers as words where there are none; '' — none
 *     $valid_through  the last day in words, or ''
 *     $categories     the names of the visible categories — no links: they have no pages
 *     $description    HTML as stored (the field type cleaned it on the way in), shortcodes replaced
 *     $duties, $requirements, $benefits   the lines written in this language, shortcodes resolved
 *     $form           the slug of the application form, or null — a site prints it itself
 */
final class VacancyPage
{
    public function __construct(
        private readonly Cards $cards,
        private readonly Salary $salary,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Vacancy $vacancy): array
    {
        $locale = $this->locales->current();
        $place = $vacancy->hasPlace();
        $salary = $vacancy->text('salary', $locale);
        $text = app(PageText::class);
        // The lead and the lines of the lists are text the panel offers shortcodes in.
        $lines = static fn (string $field): array => array_map($text->text(...), $vacancy->lines($field, $locale));

        return [
            'vacancy' => $vacancy,
            'title' => $vacancy->text('title', $locale),
            'lead' => $text->text($vacancy->text('lead', $locale)),
            'closed' => $vacancy->isClosed(),
            'workplace' => $vacancy->workplace,
            'city' => $place ? $vacancy->text('city', $locale) : '',
            'address' => $place ? $vacancy->text('address', $locale) : '',
            'employment' => $this->cards->employment($vacancy, $locale),
            'salary' => $salary !== '' ? $salary : $this->salary->line($vacancy, $locale),
            'valid_through' => $vacancy->valid_through === null ? '' : self::day($vacancy->valid_through, $locale),
            'categories' => array_map(
                static fn (VacancyCategory $category): string => $category->displayName($locale),
                $this->cards->visibleCategories($vacancy),
            ),
            'description' => $vacancy->descriptionHtml($locale),
            'duties' => $lines('duties'),
            'requirements' => $lines('requirements'),
            'benefits' => $lines('benefits'),
            'form' => $vacancy->formSlug($locale),
        ];
    }

    /** "30 November 2026", in the language of the page. */
    public static function day(Carbon $day, string $locale): string
    {
        return $day->copy()->locale($locale)->isoFormat('LL');
    }
}
