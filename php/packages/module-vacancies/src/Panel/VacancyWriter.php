<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Localization\Locales;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Support\Day;
use WebxUi\Vacancies\Support\Salary;

/**
 * Where a saved vacancy goes: all of it into the draft.
 *
 * The categories wait in the draft with the text — `category_ids` — and publishing writes them
 * into the link table. Put back the way the site has them, they leave the draft, so a save that
 * changes nothing does not mark the vacancy "changed". The draft is always built from the one
 * there is ({@see Vacancy::draftValues()}): the relations wait in it under their own key, and a
 * draft built from scratch would drop them.
 *
 * What a single field cannot check is checked here, against the vacancy as it will be after the
 * save (§4.11): the salary as a range with a unit and a currency the site has, the country as a
 * code, and the last day not before the day it was put up.
 */
final class VacancyWriter
{
    public function __construct(
        private readonly Locales $locales,
        private readonly Salary $salary,
    ) {}

    /**
     * @param  array<string, mixed>  $columns  The vacancy's own fields, only the ones that were sent.
     * @param  list<int>|null  $categories  Null leaves them alone; an empty list clears them.
     *
     * @throws ValidationException
     */
    public function save(Vacancy $vacancy, array $columns, ?array $categories = null, ?int $authorId = null): Vacancy
    {
        if ($columns === [] && $categories === null) {
            return $vacancy;
        }

        $values = $this->draft($vacancy, $columns);

        $this->check($values, $this->savedCurrency($vacancy));

        if ($categories !== null) {
            if ($categories === $vacancy->categoryIds()) {
                unset($values[Vacancy::DRAFT_CATEGORIES]);
            } else {
                $values[Vacancy::DRAFT_CATEGORIES] = $categories;
            }
        }

        $vacancy->saveDraft($values, $authorId);

        return $vacancy;
    }

    /**
     * What one field cannot say about itself, each refusal under the field the editor has to
     * change (§4.11).
     *
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function check(array $values, ?string $savedCurrency = null): void
    {
        $errors = [];

        $min = $values['salary_min'] ?? null;
        $max = $values['salary_max'] ?? null;

        if (is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
            $errors['salary_max'] = (string) __('webx-vacancies::errors.salary-range');
        }

        if ((is_numeric($min) || is_numeric($max)) && ! in_array($values['salary_unit'] ?? null, Vacancy::UNITS, true)) {
            $errors['salary_unit'] = (string) __('webx-vacancies::errors.salary-unit');
        }

        $currency = $values['salary_currency'] ?? null;

        if (is_string($currency) && ! array_key_exists($currency, $this->salary->currencies()) && $currency !== $savedCurrency) {
            $errors['salary_currency'] = (string) __('webx-vacancies::errors.currency', [
                'currencies' => implode(', ', array_keys($this->salary->currencies())),
            ]);
        }

        $country = $values['country'] ?? null;

        if (is_string($country) && preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            $errors['country'] = (string) __('webx-vacancies::errors.country');
        }

        $through = Day::from($values['valid_through'] ?? null);
        $posted = Day::from($values['posted_at'] ?? null);

        if ($through !== null && $posted !== null && $through < $posted) {
            $errors['valid_through'] = (string) __('webx-vacancies::errors.valid-before-posted');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The whole draft, with what was sent laid over what the editor is looking at.
     *
     * A translated field travels as its whole map from the form and as one string from an agent
     * speaking one language; neither is ever written over the whole field. A day is kept as
     * `Y-m-d`, a code upper-case, a number with two decimals at most.
     *
     * @param  array<string, mixed>  $columns
     * @return array<string, mixed>
     */
    public function draft(Vacancy $vacancy, array $columns): array
    {
        $locale = $this->locales->current();
        $values = $vacancy->hasDraft() ? $vacancy->draftValues() : $this->published($vacancy);

        foreach ($columns as $field => $value) {
            $values[$field] = match (true) {
                in_array($field, Vacancy::TRANSLATED, true) => $this->translated($values[$field] ?? null, $value, $locale),
                in_array($field, Vacancy::LISTS, true) => self::lines($value),
                $field === 'workplace' => is_string($value) && in_array($value, Vacancy::WORKPLACES, true) ? $value : Vacancy::ONSITE,
                $field === 'employment_types' => self::employment($value),
                $field === 'salary_min', $field === 'salary_max' => is_numeric($value) ? round((float) $value, 2) : null,
                $field === 'salary_unit', $field === 'salary_currency', $field === 'country' => self::code($value),
                $field === 'is_closed' => (bool) $value,
                $field === 'valid_through', $field === 'posted_at' => Day::from($value),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * The currency the vacancy already has — the draft's, else the site's. A currency taken out of
     * the config does not lock a vacancy that has it (§4.9): it saves as it is.
     */
    public function savedCurrency(Vacancy $vacancy): ?string
    {
        $drafted = $vacancy->draftValues()['salary_currency'] ?? null;

        if (is_string($drafted) && $drafted !== '') {
            return $drafted;
        }

        return is_string($vacancy->salary_currency) && $vacancy->salary_currency !== '' ? $vacancy->salary_currency : null;
    }

    /**
     * The lines of one list, each `{ text: { en: … } }`, without the ones that say nothing in any
     * language (decision 18).
     *
     * @return list<array{text: array<string, string>}>
     */
    public static function lines(mixed $value): array
    {
        $lines = [];

        foreach (is_array($value) ? $value : [] as $row) {
            $text = is_array($row) ? ($row['text'] ?? null) : $row;
            $map = [];

            foreach (is_array($text) ? $text : [] as $locale => $words) {
                if (is_string($words) && trim($words) !== '') {
                    $map[(string) $locale] = trim($words);
                }
            }

            if ($map !== []) {
                $lines[] = ['text' => $map];
            }
        }

        return $lines;
    }

    /**
     * The kinds of employment it names — Google's codes only, each once.
     *
     * @return list<string>
     */
    private static function employment(mixed $value): array
    {
        $given = is_array($value) ? $value : [];

        return array_values(array_filter(Vacancy::EMPLOYMENT, static fn (string $kind): bool => in_array($kind, $given, true)));
    }

    private static function code(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? strtoupper(trim($value)) : null;
    }

    private function translated(mixed $current, mixed $value, string $locale): mixed
    {
        if (is_array($value)) {
            return is_array($current) ? [...$current, ...$value] : $value;
        }

        $map = is_array($current) ? $current : ($current === null ? [] : [$locale => $current]);
        $map[$locale] = $value;

        return $map;
    }

    /**
     * The vacancy as the site has it — the starting point for a draft that does not exist yet.
     *
     * @return array<string, mixed>
     */
    private function published(Vacancy $vacancy): array
    {
        $values = [
            'workplace' => $vacancy->workplace,
            'country' => $vacancy->country,
            'employment_types' => $vacancy->employment_types ?? [],
            'salary_min' => $vacancy->salary_min,
            'salary_max' => $vacancy->salary_max,
            'salary_unit' => $vacancy->salary_unit,
            'salary_currency' => $vacancy->salary_currency,
            'duties' => $vacancy->duties ?? [],
            'requirements' => $vacancy->requirements ?? [],
            'benefits' => $vacancy->benefits ?? [],
            'is_closed' => (bool) $vacancy->is_closed,
            'valid_through' => $vacancy->valid_through?->toDateString(),
            'posted_at' => $vacancy->posted_at?->toDateString(),
            'extra' => $vacancy->extraRaw(),
        ];

        foreach (Vacancy::TRANSLATED as $field) {
            $values[$field] = $vacancy->getTranslations($field);
        }

        return $values;
    }
}
