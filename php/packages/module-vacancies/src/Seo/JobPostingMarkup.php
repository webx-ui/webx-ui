<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Seo;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Carbon;
use WebxUi\Seo\Panel\DefaultsSource;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Support\Salary;

/**
 * schema.org `JobPosting` for the page of an open vacancy (§4.7) — what Google shows in its job
 * search, to any site. {@see Vacancy::structuredData()} asks only for an open one.
 *
 * Nothing without an organisation to hire for: Google refuses a posting without
 * `hiringOrganization`, and a page is better off with no markup than with half of it. The
 * description is the whole of the page's text — the description and the three lists under their
 * headings — because Google reads the full description from this property and nowhere else. The
 * salary is the numbers an editor wrote for machines; the words on the page are for people and
 * never go in here.
 */
final class JobPostingMarkup
{
    public function __construct(
        private readonly DefaultsSource $defaults,
        private readonly Salary $salary,
        private readonly Translator $translator,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function of(Vacancy $vacancy, string $locale): ?array
    {
        $organisation = $this->defaults->organizationId($locale);

        if ($organisation === null) {
            return null;
        }

        $markup = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $vacancy->text('title', $locale),
            'description' => $this->description($vacancy, $locale),
            'hiringOrganization' => ['@id' => $organisation],
        ];

        if ($vacancy->posted_at !== null) {
            $markup['datePosted'] = $vacancy->posted_at->toDateString();
        }

        if ($vacancy->valid_through !== null) {
            // The whole of the last day where the site is.
            $markup['validThrough'] = Carbon::parse(
                $vacancy->valid_through->toDateString().' 23:59:59',
                (string) config('app.timezone', 'UTC'),
            )->toAtomString();
        }

        $employment = $vacancy->employment();

        if ($employment !== []) {
            $markup['employmentType'] = $employment;
        }

        foreach (['duties' => 'responsibilities', 'requirements' => 'qualifications', 'benefits' => 'jobBenefits'] as $field => $property) {
            $lines = $vacancy->lines($field, $locale);

            if ($lines !== []) {
                $markup[$property] = implode("\n", $lines);
            }
        }

        return [...$markup, ...$this->place($vacancy, $locale), ...$this->baseSalary($vacancy)];
    }

    /**
     * The description, then each list under its heading — HTML, which is what Google takes here.
     * The lead where nothing else is written: the property is required.
     */
    private function description(Vacancy $vacancy, string $locale): string
    {
        $parts = [];
        $html = trim($vacancy->descriptionHtml($locale));

        if ($html !== '') {
            $parts[] = $html;
        }

        foreach (Vacancy::LISTS as $field) {
            $lines = $vacancy->lines($field, $locale);

            if ($lines === []) {
                continue;
            }

            $heading = (string) $this->translator->get('webx-vacancies::vacancy.'.$field, [], $locale);
            $items = implode('', array_map(static fn (string $line): string => '<li>'.e($line).'</li>', $lines));

            $parts[] = '<h2>'.e($heading).'</h2><ul>'.$items.'</ul>';
        }

        if ($parts === []) {
            return e($vacancy->text('lead', $locale));
        }

        return implode("\n", $parts);
    }

    /**
     * Where the work is (decision 16): a place for on site and hybrid, `TELECOMMUTE` for remote and
     * hybrid, with the country the applicant has to be in when one is named.
     *
     * @return array<string, mixed>
     */
    private function place(Vacancy $vacancy, string $locale): array
    {
        $country = is_string($vacancy->country) && $vacancy->country !== '' ? $vacancy->country : null;
        $place = [];

        if ($vacancy->hasPlace()) {
            $address = array_filter([
                'streetAddress' => $vacancy->text('address', $locale),
                'addressLocality' => $vacancy->text('city', $locale),
                'addressCountry' => $country ?? '',
            ], static fn (string $value): bool => $value !== '');

            if ($address !== []) {
                $place['jobLocation'] = [
                    '@type' => 'Place',
                    'address' => ['@type' => 'PostalAddress', ...$address],
                ];
            }
        }

        if ($vacancy->allowsRemote()) {
            $place['jobLocationType'] = 'TELECOMMUTE';

            if ($country !== null) {
                $place['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => $country];
            }
        }

        return $place;
    }

    /**
     * A `MonetaryAmount` — only with a currency, a unit and at least one number: anything less is
     * no salary a machine can read.
     *
     * @return array<string, mixed>
     */
    private function baseSalary(Vacancy $vacancy): array
    {
        $range = $this->salary->range($vacancy);

        if ($range === null) {
            return [];
        }

        $value = ['@type' => 'QuantitativeValue', 'unitText' => $range['unit']];

        if ($range['min'] !== null && $range['max'] !== null && $range['min'] !== $range['max']) {
            $value['minValue'] = $range['min'];
            $value['maxValue'] = $range['max'];
        } else {
            $value['value'] = $range['min'] ?? $range['max'];
        }

        return ['baseSalary' => [
            '@type' => 'MonetaryAmount',
            'currency' => $range['currency'],
            'value' => $value,
        ]];
    }
}
