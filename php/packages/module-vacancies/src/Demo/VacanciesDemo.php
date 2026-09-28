<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Demo;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\FormOptions;
use WebxUi\Localization\Locales;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;

/**
 * Three categories and seven vacancies (§4.13): development, sales, support.
 *
 * Each vacancy is there to show one rule: an office job with a monthly range in hryvnias, a remote
 * contract paid by the hour in dollars — in English only and in no category, so the last group of
 * the careers page shows —, a hybrid part-time job with its salary in words only, one in two
 * categories, one closed by hand, one past its last day and one still a draft.
 *
 * The days are counted from the moment the demo is seeded, not written down: a demo with fixed
 * days is a demo of closed vacancies a month later, and the careers page would stand empty.
 *
 * What else there is decides the rest, which is why {@see requires()} is worked out rather than
 * written down: with `module-inbox`, the demo makes its own form "Job application" — name, email,
 * phone, a CV, a letter — and chooses it in the open vacancies. No pictures, so no library.
 */
final class VacanciesDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * The modules whose demo has to be there before this one's. Named only when installed — a
     * name `requires()` gives that is not installed skips the whole demo, and vacancies have
     * everything to show without any of them.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->withInbox() ? ['inbox'] : [];
    }

    public function seed(DemoLedger $ledger): void
    {
        // Vacancies with anything in them are somebody's vacancies.
        if (Vacancy::withTrashed()->exists() || VacancyCategory::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();

        /** @var array<string, VacancyCategory> $categories */
        $categories = [];
        $position = 0;

        foreach ($this->list($document['categories'] ?? null) as $input) {
            $category = $this->category($input, $position++, $ledger);

            if ($category !== null) {
                $categories[(string) $input['key']] = $category;
            }
        }

        $form = $this->withInbox() && is_array($document['form'] ?? null) ? $this->form($document['form'], $ledger) : null;

        // One "today" for every day, in the zone a vacancy is open in, so the demo's vacancies keep
        // their distances from each other.
        $today = Carbon::now((string) config('app.timezone', 'UTC'))->startOfDay();
        $position = 0;

        foreach ($this->list($document['vacancies'] ?? null) as $input) {
            $this->vacancy($input, $position++, $today, $categories, $form, $ledger);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function category(array $input, int $position, DemoLedger $ledger): ?VacancyCategory
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($key === '' || $title === null) {
            return null;
        }

        $category = VacancyCategory::query()->create([
            'title' => $title,
            'slug' => array_fill_keys(array_keys($title), $key),
            'is_visible' => true,
            'position' => $position,
        ]);

        $ledger->created($category, $key);

        return $category;
    }

    /**
     * The form candidates answer with — through the journal, field by field, as the inbox's own
     * demo makes its contact form. A form by this name that is already there is the site's own:
     * it is chosen, not made again, and not written down as the demo's.
     *
     * @param  array<string, mixed>  $input
     */
    private function form(array $input, DemoLedger $ledger): ?int
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : '';

        if ($slug === '') {
            return null;
        }

        $existing = Form::query()->where('slug', $slug)->value('id');

        if (is_numeric($existing)) {
            return (int) $existing;
        }

        $form = Form::query()->create([
            'slug' => $slug,
            'title' => $this->words($input['title'] ?? null),
            'is_enabled' => true,
            'options' => $this->options(is_array($input['options'] ?? null) ? $input['options'] : []),
            'position' => (int) Form::query()->max('position') + 1,
        ]);

        $ledger->created($form, $slug);

        $position = 0;

        foreach ($this->list($input['fields'] ?? null) as $field) {
            $position += 10;

            // The fields go in the journal too: they are rows of their own.
            $ledger->created(Field::query()->create([
                'form_id' => $form->getKey(),
                'name' => $field['name'] ?? null,
                'type' => $field['type'] ?? 'text',
                'title' => $this->words($field['title'] ?? null),
                'placeholder' => $this->words($field['placeholder'] ?? null),
                'help' => $this->words($field['help'] ?? null),
                'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
                'is_enabled' => true,
                'is_required' => (bool) ($field['is_required'] ?? false),
                'is_fullsize' => (bool) ($field['is_fullsize'] ?? true),
                'in_table' => (bool) ($field['in_table'] ?? false),
                'position' => $position,
            ]), (string) ($field['name'] ?? 'field'));
        }

        return (int) $form->getKey();
    }

    /**
     * The settings of the form, its words in the site's languages, through the gate the panel and
     * an agent use: a key it does not know is dropped here as it would be there.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function options(array $options): array
    {
        foreach (FormOptions::TRANSLATED as $key) {
            if (isset($options[$key])) {
                $options[$key] = $this->words($options[$key]);
            }
        }

        return FormOptions::clean($options);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, VacancyCategory>  $categories
     */
    private function vacancy(array $input, int $position, Carbon $today, array $categories, ?int $form, DemoLedger $ledger): void
    {
        $slug = is_string($input['slug'] ?? null) ? $input['slug'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($slug === '' || $title === null) {
            return;
        }

        $vacancy = new Vacancy([
            'title' => $title,
            // The same slug in every language the vacancy is written in: no slug, no address there.
            'slug' => array_fill_keys(array_keys($title), $slug),
            'lead' => $this->words($input['lead'] ?? null),
            'workplace' => is_string($input['workplace'] ?? null) ? $input['workplace'] : Vacancy::ONSITE,
            'city' => $this->words($input['city'] ?? null),
            'address' => $this->words($input['address'] ?? null),
            'country' => is_string($input['country'] ?? null) ? $input['country'] : null,
            'employment_types' => $this->list($input['employment_types'] ?? null, strings: true),
            'salary' => $this->words($input['salary'] ?? null),
            'salary_min' => $this->number($input['salary_min'] ?? null),
            'salary_max' => $this->number($input['salary_max'] ?? null),
            'salary_unit' => is_string($input['salary_unit'] ?? null) ? $input['salary_unit'] : null,
            'salary_currency' => is_string($input['salary_currency'] ?? null) ? $input['salary_currency'] : null,
            'description' => $this->words($input['description'] ?? null),
            'duties' => $this->lines($input['duties'] ?? null),
            'requirements' => $this->lines($input['requirements'] ?? null),
            'benefits' => $this->lines($input['benefits'] ?? null),
            'is_closed' => ($input['is_closed'] ?? false) === true,
            'valid_through' => $this->day($input['valid_in_days'] ?? null, $today),
            'posted_at' => $this->day($input['posted_in_days'] ?? null, $today),
            'position' => $position,
        ]);
        $vacancy->save();

        $ledger->created($vacancy, $slug);

        $filed = array_values(array_filter(array_map(
            static fn (string $key): ?int => isset($categories[$key]) ? (int) $categories[$key]->getKey() : null,
            $this->list($input['categories'] ?? null, strings: true),
        )));

        if ($filed !== []) {
            $vacancy->syncCategories($filed);
        }

        // The form only where a candidate can still answer: a closed vacancy prints no form.
        if ($form !== null && ($input['form'] ?? false) === true) {
            $vacancy->syncRelated(Vacancy::FORM, Vacancy::FORM_TARGET, [$form]);
        }

        if (($input['draft'] ?? false) === true) {
            return;
        }

        // Published after the categories and the form, so the first version records them; the
        // day it was put up is already written, so publishing keeps it rather than stamping today.
        $vacancy->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');
        $ledger->createdVersionsOf($vacancy);
    }

    /** Days from today as `Y-m-d`, in the zone a vacancy is open in; nothing for nothing. */
    private function day(mixed $days, Carbon $today): ?string
    {
        return is_int($days) ? $today->copy()->addDays($days)->toDateString() : null;
    }

    private function number(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * A list of lines as the column holds it: each `{ text: { en, ru } }`, the site's languages
     * only; a line with none of them left is dropped.
     *
     * @return list<array{text: array<string, string>}>|null
     */
    private function lines(mixed $value): ?array
    {
        $lines = [];

        foreach ($this->list($value) as $line) {
            $text = $this->words($line);

            if ($text !== null) {
                $lines[] = ['text' => $text];
            }
        }

        return $lines === [] ? null : $lines;
    }

    private function withInbox(): bool
    {
        return $this->modules->has('inbox') && class_exists(Form::class);
    }

    /**
     * The languages of the demo that the site has. A site with neither gets the English under its
     * own default, as the other demos do, rather than nameless vacancies.
     *
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        if (! is_array($text)) {
            return null;
        }

        $codes = $this->locales->codes();
        $words = [];

        foreach ($text as $locale => $value) {
            if (is_string($value) && trim($value) !== '' && in_array((string) $locale, $codes, true)) {
                $words[(string) $locale] = trim($value);
            }
        }

        if ($words === [] && is_string($text['en'] ?? null) && trim($text['en']) !== '') {
            return [$this->locales->defaultCode() => trim($text['en'])];
        }

        return $words === [] ? null : $words;
    }

    /**
     * @return ($strings is true ? list<string> : list<array<string, mixed>>)
     */
    private function list(mixed $value, bool $strings = false): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, $strings ? is_string(...) : is_array(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/vacancies.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('vacancies.json is not a set of vacancies.');
        }

        return $document;
    }
}
