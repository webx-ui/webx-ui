<?php

declare(strict_types=1);

namespace WebxUi\Services\Rendering;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

/**
 * What the public pages list, each in its own order (§4.4).
 *
 * The index and a category page never share an order, and that is decision 5 of the spec rather
 * than an accident: the index groups by category and lists each group the way that category was
 * dragged, a category page does the same for itself, and the whole-list `position` only decides
 * the group of services that is in no category a reader can see.
 */
final class Catalogue
{
    public function __construct(private readonly Seo $seo) {}

    /**
     * The visible categories, in their order, each with its visible services in the order of that
     * category. Three queries whatever the size — the services come in with the categories.
     *
     * @return EloquentCollection<int, ServiceCategory>
     */
    public function categories(): EloquentCollection
    {
        /** @var EloquentCollection<int, ServiceCategory> $categories */
        $categories = ServiceCategory::query()
            ->visible()
            ->ordered()
            ->with([
                'services' => static function (Relation $services): void {
                    $services->whereNotNull('services.published_at')
                        ->orderBy('service_category.item_position')
                        ->orderBy('services.position')
                        ->orderBy('services.id');
                },
                'services.cover',
            ])
            ->get();

        return $categories;
    }

    /**
     * The visible services no visible category lists — the group at the end of the index.
     *
     * "No visible category" rather than "no category": a service filed only under a hidden one
     * would otherwise be on the site and nowhere on the index.
     *
     * @return EloquentCollection<int, Service>
     */
    public function uncategorised(): EloquentCollection
    {
        /** @var EloquentCollection<int, Service> $services */
        $services = Service::query()
            ->visible()
            ->whereDoesntHave('categories', static fn (Builder $categories): Builder => $categories->where('service_categories.is_visible', true))
            ->with('cover')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return $services;
    }

    /**
     * The visible services of one category, in the order it was dragged into.
     *
     * @return EloquentCollection<int, Service>
     */
    public function in(ServiceCategory $category): EloquentCollection
    {
        /** @var EloquentCollection<int, Service> $services */
        $services = Service::query()
            ->visible()
            ->orderedIn((int) $category->getKey())
            ->with('cover')
            ->get();

        return $services;
    }

    /**
     * An `ItemList` of what this page lists (§4.5): about the page rather than about an entity, so
     * the handler pushes it. A service listed under two categories is one item.
     *
     * @param  iterable<Service>  $services
     */
    public function pushItemList(iterable $services): void
    {
        $urls = [];

        foreach ($services as $service) {
            $url = $service->url();

            if (! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        if ($urls === []) {
            return;
        }

        $this->seo->push([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(
                static fn (string $url, int $i): array => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $url],
                $urls,
                array_keys($urls),
            ),
        ]);
    }
}
