<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Admin\Screens\ScreenRecord;
use WebxUi\Blocks\Facades\Preview;
use WebxUi\Seo\Fields;
use WebxUi\Vacancies\Http\Resources\VacancyResource;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Support\Salary;

/**
 * The editor's screen on the server side: what its fields hold, and what a save writes (§4.10,
 * §4.11).
 *
 * The screen is `vacancies.form`, keyed by field name. The application form is `wx-relations`,
 * sorted out of the values by {@see ScreenRecord} itself — and gone from the screen, and from the
 * values, where `module-inbox` is not installed. The SEO card is `module-seo`'s; everything
 * nobody here names is the project's and goes into `extra`.
 *
 * Three things are settled before the screen checks anything, because the screen would say them
 * in the wrong place or not at all: a line of a list is refused under its own row
 * (`duties.<n>.text.<language>`) and an empty one is dropped; a currency the site has since taken
 * out of its list is let through when it is the one the vacancy already has; and a form that does
 * not exist is refused rather than quietly dropped, the way `wx-relations` drops it.
 */
final class VacancyForm
{
    /**
     * The vacancy's own fields.
     *
     * @var list<string>
     */
    public const OWN = [
        'title', 'slug', 'lead', 'workplace', 'city', 'address', 'country', 'employment_types', 'salary',
        'salary_min', 'salary_max', 'salary_unit', 'salary_currency', 'description', 'duties', 'requirements',
        'benefits', 'is_closed', 'valid_through', 'posted_at',
    ];

    /** The longest line of a list — the `maxlength` of its input on the screen. */
    public const LINE = 500;

    /**
     * The screen's fields that are not the vacancy's text: the categories, and the SEO card.
     *
     * @var list<string>
     */
    private const TAKEN = ['categories', Fields::SCREEN];

    public function __construct(
        private readonly ScreenRecord $record,
        private readonly VacancyWriter $writer,
        private readonly Salary $salary,
        private readonly RelationTargets $targets,
    ) {}

    /**
     * A new vacancy, not saved yet: its title and address, on site, full time, in the first
     * currency of the site and its country (§4.11) — what the panel's "New vacancy" and an
     * agent's `vacancies_create` both start from.
     *
     * @param  array<string, string>|string  $title
     * @param  array<string, string>|string|null  $slug
     */
    public function blank(array|string $title, array|string|null $slug): Vacancy
    {
        $country = strtoupper(trim((string) config('webx-vacancies.country', '')));

        return new Vacancy([
            'title' => $title,
            'slug' => $slug,
            'workplace' => Vacancy::ONSITE,
            'employment_types' => ['FULL_TIME'],
            'salary_currency' => $this->salary->defaultCurrency(),
            'country' => preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null,
        ]);
    }

    /**
     * A vacancy and everything its editor needs around it (§4.11): the record, the values of the
     * screen, the revision those values are, the prefix of its address and a link to the draft —
     * minted per response, because it is signed and short-lived. No link without `module-blocks`,
     * which draws previews.
     *
     * @return array<string, mixed>
     */
    public function describe(Vacancy $vacancy, ?int $adminId = null): array
    {
        return [
            'vacancy' => new VacancyResource($vacancy),
            'values' => $this->values($vacancy),
            'revision' => Revision::of($vacancy),
            'prefix' => (string) config('webx-vacancies.prefix', 'careers'),
            'preview_url' => class_exists(Preview::class) ? Preview::url($vacancy, $adminId) : null,
        ];
    }

    /**
     * What the form opens with: the draft laid over the columns — what the editor was last
     * working on. The categories and the form wait in the draft too.
     *
     * @return array<string, mixed>
     */
    public function values(Vacancy $vacancy): array
    {
        $shown = $vacancy->hasDraft() ? $vacancy->withDraft() : $vacancy;

        return [
            // The project's fields first, so that none of them can stand in for one of the
            // vacancy's own.
            ...($shown->extraRaw() ?? []),
            'title' => $shown->getTranslations('title'),
            'slug' => $shown->getTranslations('slug'),
            'lead' => $shown->getTranslations('lead'),
            'workplace' => $shown->workplace,
            'city' => $shown->getTranslations('city'),
            'address' => $shown->getTranslations('address'),
            'country' => $shown->country,
            'employment_types' => $shown->employment(),
            'salary' => $shown->getTranslations('salary'),
            'salary_min' => $shown->salary_min,
            'salary_max' => $shown->salary_max,
            'salary_unit' => $shown->salary_unit,
            'salary_currency' => $shown->salary_currency,
            'description' => $shown->getTranslations('description'),
            'duties' => array_values($shown->duties ?? []),
            'requirements' => array_values($shown->requirements ?? []),
            'benefits' => array_values($shown->benefits ?? []),
            'is_closed' => (bool) $shown->is_closed,
            'valid_through' => $shown->valid_through?->toDateString(),
            'posted_at' => $shown->posted_at?->toDateString(),
            'categories' => $vacancy->draftedCategoryIds(),
            ...$this->record->relationValues(Vacancy::SCREEN, $vacancy),
            Fields::SCREEN => $vacancy->seoValue(),
        ];
    }

    /**
     * Check what came in against the screen and write it — the panel's door and an agent's alike.
     * The caller holds the transaction: a refusal half way must not leave half a save.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): bool)|null  $can
     *
     * @throws ValidationException
     */
    public function save(Vacancy $vacancy, array $input, ?callable $can = null, ?int $authorId = null): Vacancy
    {
        $input = $this->settle($vacancy, $input);

        $split = $this->record->split(Vacancy::SCREEN, $input, self::OWN, self::TAKEN, $can);
        $stored = [...$split->own, ...$split->taken];

        $columns = $split->own;

        // A field a project patched onto the screen goes into `extra`, and into the draft with the
        // text around it. Laid over what the editor is looking at, so a tab nobody opened keeps
        // its fields.
        if ($split->extra !== []) {
            $columns['extra'] = $this->record->merge(Vacancy::SCREEN, $this->currentExtra($vacancy), $split->extra);
        }

        $categories = null;

        if (array_key_exists('categories', $stored)) {
            $categories = is_array($stored['categories']) ? array_values(array_map(intval(...), $stored['categories'])) : [];
        }

        $this->writer->save($vacancy, $columns, $categories, $authorId);

        $this->record->saveRelations($vacancy, $split);

        // Only when it travelled — a save of another tab must not empty a card nobody opened.
        if (array_key_exists(Fields::SCREEN, $stored)) {
            $value = $stored[Fields::SCREEN];

            $vacancy->saveSeo(is_array($value) ? $value : null);
        }

        return $vacancy->refresh();
    }

    /**
     * What the screen is given to check: the lists checked row by row and emptied of empty lines,
     * the country upper-case, a currency the vacancy already has left out (and so left as it is),
     * a form that does not exist refused.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function settle(Vacancy $vacancy, array $input): array
    {
        $errors = [];

        foreach (Vacancy::LISTS as $list) {
            if (! array_key_exists($list, $input)) {
                continue;
            }

            foreach (is_array($input[$list]) ? array_values($input[$list]) : [] as $n => $row) {
                $text = is_array($row) ? ($row['text'] ?? null) : null;

                foreach (is_array($text) ? $text : [] as $locale => $words) {
                    if ($words !== null && (! is_string($words) || mb_strlen($words) > self::LINE)) {
                        $errors["{$list}.{$n}.text.{$locale}"] = (string) __('webx-vacancies::errors.line', ['max' => self::LINE]);
                    }
                }
            }

            $input[$list] = VacancyWriter::lines($input[$list]);
        }

        if (is_string($input['country'] ?? null)) {
            $country = strtoupper(trim($input['country']));
            $input['country'] = $country === '' ? null : $country;
        }

        $currency = $input['salary_currency'] ?? null;

        if (is_string($currency)
            && ! array_key_exists($currency, $this->salary->currencies())
            && $currency === $this->writer->savedCurrency($vacancy)) {
            unset($input['salary_currency']);
        }

        $form = $input[Vacancy::FORM] ?? null;
        $target = $this->targets->find(Vacancy::FORM_TARGET);

        if (is_array($form) && $form !== [] && $target !== null) {
            $ids = array_values(array_unique(array_map(intval(...), array_filter($form, is_numeric(...)))));
            $found = $target->query()->whereKey($ids)->count();

            if ($found !== count($ids) || count($ids) !== count($form)) {
                $errors[Vacancy::FORM] = (string) __('webx-vacancies::errors.unknown-form');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $input;
    }

    /**
     * The project's fields as the editor last left them.
     *
     * @return array<string, mixed>|null
     */
    private function currentExtra(Vacancy $vacancy): ?array
    {
        $draft = $vacancy->draftValues();

        if (array_key_exists('extra', $draft)) {
            return is_array($draft['extra']) ? $draft['extra'] : null;
        }

        return $vacancy->extraRaw();
    }
}
