<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;

/**
 * `tariffs()` — the tariffs a template may show, as cards rather than models (§4.2).
 *
 *     tariffs()->in($groups)->take(3)              // an editor's choice; empty is every tariff
 *     tariffs()->in(2)                             // one group, in its own order
 *     tariffs()->relatedTo('service', $service)    // what this service costs
 *     tariffs()->only([7, 3])                      // these, in this order
 *     tariffs()->except($tariff)                   // all but these
 *     tariffs()->categories()                      // the catalogue by group — tabs of a page of prices
 *
 * The steps and their meaning are those of `services()`, `reviews()` and `team()` — all of them
 * are a {@see RecordQuery}. Who a reader may see is not a step: published and out of the bin, in
 * every language (decision 12). The shape of a card is {@see Cards}, the same one a
 * `wx-collection` field hands over.
 *
 * Groups have no slugs, so a group is named by its id or by itself; a string is read as an id only
 * when it is one. Any other string is a filter nothing passes rather than no filter.
 *
 * @extends RecordQuery<Tariff>
 */
final class TariffQuery extends RecordQuery
{
    /**
     * Only what is in these groups: an id, a group, or a list of them. Nothing — null, an empty
     * string or list — is no filter at all, because that is what an editor's untouched field
     * sends and "every tariff" is what it means.
     *
     * @param  int|string|TariffCategory|iterable<int|string|TariffCategory>|null  $groups
     */
    public function in(int|string|TariffCategory|iterable|null $groups): self
    {
        return $this->withCategories($groups);
    }

    /**
     * Only the tariffs related to these records of another module: `relatedTo('service', 3)`.
     * An empty list is none — "the tariffs of no service" is not every tariff.
     *
     * @param  int|object|iterable<int|string|object>  $records
     */
    public function relatedTo(string $type, int|object|iterable $records): self
    {
        return $this->withRelated($type, $records);
    }

    /**
     * The visible groups, in their order, each with the tariffs it lists in its own order.
     * `in()` narrows the groups, `only()` and `except()` apply to the tariffs inside, and `take()`
     * to each group rather than the whole.
     *
     * A group with nothing left to show is left out: a tab over nothing is not a group.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $locale = $this->resolvedLocale();
        $ids = $this->categoryIds($locale);

        if ($ids === []) {
            return [];
        }

        $only = $this->onlyIds();
        $except = $this->exceptIds();

        $query = TariffCategory::query()
            ->visible()
            ->ordered()
            ->with([
                'tariffs' => static function (Relation $tariffs) use ($only, $except): void {
                    $tariffs->where('tariffs.published', true);

                    if ($except !== []) {
                        $tariffs->whereNotIn('tariffs.id', $except);
                    }

                    if ($only !== null) {
                        $tariffs->whereIn('tariffs.id', $only === [] ? [0] : $only);
                    }

                    $tariffs->orderBy('tariff_category_tariff.item_position')
                        ->orderBy('tariffs.position')
                        ->orderBy('tariffs.id');
                },
                ...array_map(static fn (string $relation): string => 'tariffs.'.$relation, Cards::RELATIONS),
            ]);

        if ($ids !== null) {
            $query->whereIn('tariff_categories.id', $ids);
        }

        /** @var EloquentCollection<int, TariffCategory> $categories */
        $categories = $query->get();
        $groups = [];

        foreach ($categories as $category) {
            /** @var EloquentCollection<int, Tariff> $tariffs */
            $tariffs = $category->tariffs;

            if ($this->limit() !== null) {
                $tariffs = $tariffs->take($this->limit());
            }

            if ($tariffs->isEmpty()) {
                continue;
            }

            $groups[] = $this->cardMaker()->category($category, $tariffs->values()->all(), $locale);
        }

        return $groups;
    }

    protected function newQuery(string $locale): Builder
    {
        return Tariff::query()->visible()->with(Cards::RELATIONS);
    }

    /**
     * @param  list<Tariff>  $records
     * @return list<array<string, mixed>>
     */
    protected function cards(array $records, string $locale): array
    {
        return $this->cardMaker()->tariffs($records, $locale);
    }

    /**
     * Nobody is hidden over a language (decision 12) — said here in so many words, so that a
     * later change to the default does not quietly start hiding tariffs.
     */
    protected function shownIn(Model $record, string $locale): bool
    {
        return true;
    }

    private function cardMaker(): Cards
    {
        return Container::getInstance()->make(Cards::class);
    }
}
