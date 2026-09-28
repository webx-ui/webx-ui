<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use Illuminate\Database\ConnectionInterface;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * "Duplicate" (decision 20): "the same position in Lviv" is a copy, not a vacancy from nothing.
 *
 * The copy is a draft that was never published: every field of the vacancy as its editor last
 * left it, its categories and its application form, the SEO card — and no history, because the
 * history of the original is not the copy's. Not closed, and with no day it was put up: that is
 * its first publication's to set. The title stays; the address takes the next free `-2`, `-3` in
 * every language. It stands right after the original.
 *
 * One transaction: a copy refused half way — its address taken meanwhile — leaves no row behind,
 * and the list is not left shifted for it.
 */
final class Duplicate
{
    /** What is copied as it is. */
    private const COPIED = [
        'workplace', 'country', 'employment_types', 'salary_min', 'salary_max', 'salary_unit', 'salary_currency',
        'duties', 'requirements', 'benefits', 'valid_through',
    ];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly RelationTargets $targets,
    ) {}

    public function of(Vacancy $source): Vacancy
    {
        return $this->db->transaction(function () use ($source): Vacancy {
            $shown = $source->hasDraft() ? $source->withDraft() : $source;

            // Room right after the original, the bin counted: it keeps its places too.
            Vacancy::query()->withTrashed()->where('position', '>', $source->position)->increment('position');

            $copy = new Vacancy;

            foreach (Vacancy::TRANSLATED as $field) {
                $copy->setAttribute($field, $shown->getTranslations($field));
            }

            $copy->setAttribute('slug', $this->freeSlugs($shown->getTranslations('slug')));

            foreach (self::COPIED as $field) {
                $copy->setAttribute($field, $shown->getAttribute($field));
            }

            $copy->setAttribute('is_closed', false);
            $copy->setAttribute('posted_at', null);
            $copy->setAttribute('position', $source->position + 1);

            $extra = $shown->extraRaw();

            if (is_array($extra) && $extra !== []) {
                $copy->setAttribute($copy->extraColumn(), $extra);
            }

            $copy->save();

            // Straight into the rows: the copy has never been on the site, so there is no
            // published state for a draft to differ from.
            $copy->syncCategories($source->draftedCategoryIds());

            $form = $source->draftedRelatedIds(Vacancy::FORM);

            // Only what somebody can see: without `module-inbox` the choice waits on the original.
            if ($form !== [] && $this->targets->has(Vacancy::FORM_TARGET)) {
                $copy->syncRelated(Vacancy::FORM, Vacancy::FORM_TARGET, $form);
            }

            $seo = $source->seoValue();

            if ($seo !== []) {
                $copy->saveSeo($seo);
            }

            return $copy->refresh();
        });
    }

    /**
     * Every language's slug with the first free suffix — `developer-2`, or `developer-3` when that
     * is taken — the registry refuses a taken address, and asking it first is cheaper than a
     * refusal.
     *
     * @param  array<string, mixed>  $slugs
     * @return array<string, string>
     */
    private function freeSlugs(array $slugs): array
    {
        $prefix = UrlNormaliser::key((string) config('webx-vacancies.prefix', 'careers'));
        $free = [];

        foreach ($slugs as $locale => $slug) {
            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $base = trim($slug);
            $number = 2;

            while ($this->taken($prefix.'/'.$base.'-'.$number, (string) $locale)) {
                $number++;
            }

            $free[(string) $locale] = $base.'-'.$number;
        }

        return $free;
    }

    private function taken(string $path, string $locale): bool
    {
        return Route::query()->where('locale', $locale)->where('path', UrlNormaliser::key($path))->exists();
    }
}
