<?php

declare(strict_types=1);

namespace WebxUi\Banners\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Media\Screens\MediaFiles;

/**
 * `banners()` — the banners a template may show, as cards rather than models (§5.3 of the spec).
 *
 *     banners('hero')                      // the banners of one place, in its order
 *     banners(['hero', 'promo'])           // places in the order named, each in its own order
 *     banners('hero')->take(3)             // the first three a reader of this page may see
 *     banners()->only([5, 2])              // these, in this order
 *
 * A place is not a category of {@see RecordQuery}: the engine filters categories through a pivot,
 * and a banner's place is a column — so `in()` is a step of this class, by key rather than by a
 * translated slug, and `withCategories()` is never called.
 *
 * Who a reader may see is not a step: turned on, out of the bin, written in the language of the
 * page or not written at all (decision 12), and with its picture still in the library. No
 * "random": which of a place's banners to show is the site's script's choice (decision 10).
 *
 * @extends RecordQuery<Banner>
 */
final class BannerQuery extends RecordQuery
{
    /** @var list<int>|null The places of `in()` as ids, in the order named; worked out by {@see narrow()}. */
    private ?array $placeIds = null;

    /**
     * Only the banners of these places — a key, an id, a place, or a list of them, in the order
     * they are to be listed. Nothing is no filter; a place that does not exist is a filter nothing
     * passes, so a typo in a key is an empty slider rather than every banner of the site.
     *
     * @param  string|int|Place|iterable<string|int|Place>|null  $places
     */
    public function in(string|int|Place|iterable|null $places): self
    {
        $given = [];

        foreach (is_iterable($places) ? $places : [$places] as $place) {
            if ($place instanceof Place) {
                $given[] = (int) $place->getKey();
            } elseif (is_int($place) || (is_string($place) && ctype_digit($place))) {
                $given[] = (int) $place;
            } elseif (is_string($place) && trim($place) !== '') {
                $given[] = trim($place);
            }
        }

        return $this->withStep('places', $given === [] ? null : $given);
    }

    /**
     * The records, without the ones whose picture is gone from the library — before the limit,
     * so that "the first three" are three that will be printed.
     *
     * @return EloquentCollection<int, Banner>
     */
    public function models(): EloquentCollection
    {
        $limit = $this->limit();

        if ($limit !== null) {
            return new EloquentCollection(array_slice($this->take(null)->models()->all(), 0, $limit));
        }

        $banners = parent::models()->all();
        $files = Container::getInstance()->make(MediaFiles::class);

        $files->load(array_values(array_filter(array_map(
            static fn (Banner $banner): ?string => $banner->mediaPath('image'),
            $banners,
        ))));

        return new EloquentCollection(array_values(array_filter(
            $banners,
            static function (Banner $banner) use ($files): bool {
                $path = $banner->mediaPath('image');

                return $path !== null && $files->find($path) !== null;
            },
        )));
    }

    protected function newQuery(string $locale): Builder
    {
        return Banner::query()->visible()->with('place');
    }

    protected function narrow(Builder $query, string $locale): void
    {
        $this->placeIds = $this->resolvePlaces();

        if ($this->placeIds !== null) {
            $query->whereIn($query->getModel()->qualifyColumn('place_id'), $this->placeIds);
        }
    }

    /** The places in the order `in()` named them (decision 15), then the order inside each. */
    protected function order(Builder $query, ?int $category): void
    {
        $model = $query->getModel();

        if ($this->placeIds !== null && count($this->placeIds) > 1) {
            $cases = implode(' ', array_fill(0, count($this->placeIds), 'when ? then ?'));
            $bindings = [];

            foreach ($this->placeIds as $rank => $id) {
                $bindings[] = $id;
                $bindings[] = $rank;
            }

            $query->orderByRaw('case '.$query->getQuery()->getGrammar()->wrap($model->qualifyColumn('place_id')).' '.$cases.' end', $bindings);
        }

        $query->orderBy($model->qualifyColumn('position'))->orderBy($model->qualifyColumn('id'));
    }

    /**
     * @param  list<Banner>  $records
     * @return list<array<string, mixed>>
     */
    protected function cards(array $records, string $locale): array
    {
        return Container::getInstance()->make(Cards::class)->banners($records, $locale);
    }

    /** @param  Banner  $record */
    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->writtenIn($locale);
    }

    /**
     * The places asked for as ids, in the order named: null for no filter, and a list without the
     * ones that do not exist — a declared place that has no row yet has no banners either.
     *
     * @return list<int>|null
     */
    private function resolvePlaces(): ?array
    {
        /** @var list<int|string>|null $given */
        $given = $this->step('places');

        if ($given === null) {
            return null;
        }

        $keys = array_values(array_filter($given, is_string(...)));
        $byKey = $keys === [] ? [] : Place::query()->whereIn('key', $keys)->pluck('id', 'key')->all();

        $ids = [];

        foreach ($given as $place) {
            $id = is_int($place) ? $place : ($byKey[$place] ?? null);

            if ($id !== null && ! in_array((int) $id, $ids, true)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
