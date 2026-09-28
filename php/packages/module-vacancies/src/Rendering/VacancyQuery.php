<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Rendering;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Vacancies\Models\Vacancy;
use WebxUi\Vacancies\Models\VacancyCategory;

/**
 * `vacancies()` — the vacancies a template may show, as cards rather than models (§4.8).
 *
 *     vacancies()->take(6)                        // the first six open ones
 *     vacancies()->in('development')              // one category, by slug or id
 *     vacancies()->closed()                       // the ones that are over
 *     vacancies()->only([12, 7])                  // these, in this order
 *     vacancies()->except($vacancy)->take(3)
 *     vacancies()->groups()                       // the index: a group per category
 *
 * What a reader may see is not a step: published, out of the bin, titled and with an address in
 * the language being read. The open ones are what a query is until told otherwise; the order is
 * the one vacancies have, `position` — the same in a category as in the whole list — except with
 * `only()`, where the order given is the point. The shape of a card is {@see Cards}.
 *
 * @extends RecordQuery<Vacancy>
 */
final class VacancyQuery extends RecordQuery
{
    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const ALL = 'all';

    /** Only the open ones — what a query is until told otherwise. */
    public function open(): self
    {
        return $this->withStep('when', self::OPEN);
    }

    /** Only the closed ones, by hand or past their last day. */
    public function closed(): self
    {
        return $this->withStep('when', self::CLOSED);
    }

    /** Both. */
    public function all(): self
    {
        return $this->withStep('when', self::ALL);
    }

    /**
     * Only what is filed under these categories — any of them: an id, a slug, a category, or a
     * list. Nothing is no filter at all; a slug nobody has matches no vacancy.
     *
     * @param  int|string|VacancyCategory|iterable<int|string|VacancyCategory>|null  $categories
     */
    public function in(int|string|VacancyCategory|iterable|null $categories): self
    {
        return $this->withCategories($categories);
    }

    /**
     * The catalogue of the index (§4.5): a group per visible category, in the order of the
     * categories, each with its vacancies in the order of the list; a vacancy in two categories
     * stands in both. What has no visible category is the last group, "Other vacancies" — unless
     * the query is narrowed to categories, and then only those are groups. An empty group is not
     * a group. `take()` counts per group.
     *
     * @return list<array{id: int|null, slug: string|null, title: string, vacancies: list<array<string, mixed>>}>
     */
    public function groups(): array
    {
        $locale = $this->resolvedLocale();
        $vacancies = array_values($this->take(null)->models()->all());

        if ($vacancies === []) {
            return [];
        }

        $asked = $this->categoryIds($locale);
        $categories = VacancyCategory::query()->visible()->ordered()
            ->when($asked !== null, static fn (Builder $query): Builder => $query->whereKey($asked ?? []))
            ->get();

        $visible = [];

        foreach ($categories as $category) {
            $visible[(int) $category->getKey()] = true;
        }

        /** @var array<int, list<int>> $filed */
        $filed = [];

        foreach ($vacancies as $vacancy) {
            $filed[(int) $vacancy->getKey()] = array_values(array_filter(
                array_map(static fn (VacancyCategory $category): int => (int) $category->getKey(), $vacancy->categories->all()),
                static fn (int $id): bool => isset($visible[$id]),
            ));
        }

        $cards = Container::getInstance()->make(Cards::class);
        $groups = [];

        foreach ($categories as $category) {
            $id = (int) $category->getKey();
            $members = array_values(array_filter($vacancies, static fn (Vacancy $vacancy): bool => in_array($id, $filed[(int) $vacancy->getKey()], true)));

            if ($members === []) {
                continue;
            }

            $slug = $category->getTranslation('slug', $locale, false);

            $groups[] = [
                'id' => $id,
                'slug' => is_string($slug) && $slug !== '' ? $slug : null,
                'title' => $category->displayName($locale),
                'vacancies' => $cards->vacancies($this->limited($members), $locale),
            ];
        }

        if ($asked === null) {
            $others = array_values(array_filter($vacancies, static fn (Vacancy $vacancy): bool => $filed[(int) $vacancy->getKey()] === []));

            if ($others !== []) {
                $groups[] = [
                    'id' => null,
                    'slug' => null,
                    'title' => (string) Container::getInstance()->make(Translator::class)->get('webx-vacancies::site.other', [], $locale),
                    'vacancies' => $cards->vacancies($this->limited($others), $locale),
                ];
            }
        }

        return $groups;
    }

    protected function newQuery(string $locale): Builder
    {
        return Vacancy::query()->visible()->with(Cards::RELATIONS);
    }

    /** Titled and with an address in the language: the title and the slug are words. */
    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->hasUrlIn($locale) && $record->isVisible($locale);
    }

    protected function categoryModel(): string
    {
        return VacancyCategory::class;
    }

    /**
     * @param  Builder<covariant Vacancy>  $query
     */
    protected function narrow(Builder $query, string $locale): void
    {
        // Through `scopes()`: PHPStan finds no `open()` on a builder of a model it only knows as
        // covariant (CLAUDE.md §4 on scopes over a generic builder).
        match ($this->step('when', self::OPEN)) {
            self::CLOSED => $query->scopes(['closed']),
            self::ALL => $query,
            default => $query->scopes(['open']),
        };
    }

    /**
     * One order, the list's, whatever the category: a vacancy has no place of its own inside a
     * category.
     *
     * @param  Builder<covariant Vacancy>  $query
     */
    protected function order(Builder $query, ?int $category): void
    {
        $query->scopes(['byPosition']);
    }

    protected function cards(array $records, string $locale): array
    {
        return Container::getInstance()->make(Cards::class)->vacancies($records, $locale);
    }

    /**
     * @param  list<Vacancy>  $vacancies
     * @return list<Vacancy>
     */
    private function limited(array $vacancies): array
    {
        $limit = $this->limit();

        return $limit === null ? $vacancies : array_slice($vacancies, 0, $limit);
    }
}
