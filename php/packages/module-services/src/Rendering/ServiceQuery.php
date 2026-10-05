<?php

declare(strict_types=1);

namespace WebxUi\Services\Rendering;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * `services()` — the services a template may show, as cards rather than models.
 *
 *     services()->in('implants')->take(6)          // one category, in its own order
 *     services()->in($category)                    // an editor's choice; empty is every service
 *     services()->only([12, 7, 30])                // these, in this order
 *     services()->except($service)->take(3)        // "other services" on a service page
 *     services()->categories()                     // the catalogue, grouped
 *
 * What a reader may see is not a step: published, out of the bin and written in the language
 * being read — a service without a slug in it has no address there, and a card that leads to a
 * 404 is worse than a shorter list. The shape of a card is {@see Cards}, the same one a
 * `wx-collection` field hands over, so a block can move between the two without its markup
 * changing. The steps themselves and their rules are {@see RecordQuery}'s.
 *
 * @extends RecordQuery<Service>
 */
final class ServiceQuery extends RecordQuery
{
    /**
     * Only what is filed under these categories: an id, a slug, a category, or a list of them.
     *
     * Nothing — null, an empty string or list — is no filter at all, because that is what an
     * editor's untouched field sends and "every service" is what it means. A slug nobody has is
     * not nothing: it matches no service rather than all of them. One category lists its
     * services in its own order.
     *
     * @param  int|string|ServiceCategory|iterable<int|string|ServiceCategory>|null  $categories
     */
    public function in(int|string|ServiceCategory|iterable|null $categories): self
    {
        return $this->withCategories($categories);
    }

    /**
     * The visible categories, in their order, each with the services it lists in its own order —
     * the catalogue as the index prints it. `in()` narrows the categories, `except()` and the
     * language apply to the services inside, and `take()` to each category rather than the
     * whole: a grouped list cut off after six is one category and a half.
     *
     * A category with nothing left to show is left out: a heading over nothing is not a group.
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

        $query = ServiceCategory::query()
            ->visible()
            ->ordered()
            ->with([
                'cover',
                'routes',
                'services' => static function (Relation $services) use ($only, $except): void {
                    $services->whereNotNull('services.published_at');

                    if ($except !== []) {
                        $services->whereNotIn('services.id', $except);
                    }

                    if ($only !== null) {
                        $services->whereIn('services.id', $only === [] ? [0] : $only);
                    }

                    $services->orderBy('service_category_service.item_position')
                        ->orderBy('services.position')
                        ->orderBy('services.id');
                },
                ...array_map(static fn (string $relation): string => 'services.'.$relation, Cards::RELATIONS),
            ]);

        if ($ids !== null) {
            $query->whereIn('service_categories.id', $ids);
        }

        /** @var EloquentCollection<int, ServiceCategory> $categories */
        $categories = $query->get();
        $groups = [];

        foreach ($categories as $category) {
            if (! $category->hasUrlIn($locale)) {
                continue;
            }

            $services = $category->services->filter(static fn (Service $service): bool => $service->hasUrlIn($locale));

            if ($this->limit() !== null) {
                $services = $services->take($this->limit());
            }

            if ($services->isEmpty()) {
                continue;
            }

            $groups[] = $this->cardMaker()->category($category, $services->values()->all(), $locale);
        }

        return $groups;
    }

    protected function newQuery(string $locale): Builder
    {
        return Service::query()->visible()->with(Cards::RELATIONS);
    }

    protected function shownIn(Model $record, string $locale): bool
    {
        return $record->hasUrlIn($locale);
    }

    protected function categoryModel(): string
    {
        return ServiceCategory::class;
    }

    protected function cards(array $records, string $locale): array
    {
        return $this->cardMaker()->services($records, $locale);
    }

    private function cardMaker(): Cards
    {
        return Container::getInstance()->make(Cards::class);
    }
}
