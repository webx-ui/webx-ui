<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use DateTimeInterface;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * What an editor read, as a short string, so a save can say whether somebody wrote in between.
 *
 * A hash of the vacancy as it is being edited — the draft where there is one, the columns where
 * there is not — with the categories and the application form as the editor last left them: they
 * wait in the draft like the text, and a form chosen by somebody else is a change to the vacancy
 * as much as a new salary is.
 */
final class Revision
{
    /** The columns of the content, in this order. */
    private const CONTENT = [
        'title', 'slug', 'lead', 'workplace', 'city', 'address', 'country', 'employment_types', 'salary',
        'salary_min', 'salary_max', 'salary_unit', 'salary_currency', 'description', 'duties', 'requirements',
        'benefits', 'is_closed', 'valid_through', 'posted_at', 'extra',
    ];

    public static function of(Vacancy $vacancy): string
    {
        $shown = $vacancy->hasDraft() ? $vacancy->withDraft() : $vacancy;

        $content = [];

        foreach (self::CONTENT as $column) {
            $value = $shown->getAttribute($column);
            $content[$column] = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        $content['published_at'] = $vacancy->published_at?->toAtomString();
        $content['categories'] = $vacancy->draftedCategoryIds();
        $content['form'] = $vacancy->draftedRelatedIds(Vacancy::FORM);

        // The SEO card is saved live, outside the draft, and is an edit like any other: two
        // people who each rewrote the description have to find out about it.
        $content['seo'] = $vacancy->seoValue();

        return substr(sha1(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }
}
