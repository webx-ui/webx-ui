<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;

/**
 * One module's categories, as something to link to — for categories that have an address.
 *
 * A category without one (a FAQ section) has nothing to link to, and its module simply does not
 * register this. One that has one uses `HasUrl` of `webx-ui/routing`, which the frame does not
 * require — so this class names that trait's methods rather than its classes.
 *
 * A category has no draft and no date; what it has instead is `is_visible`, and hidden means it
 * answers 404. So that flag is the whole of `available` here.
 */
final readonly class CategoryLinkSource implements LinkSource
{
    /**
     * @param  class-string<Model&Category>  $model
     * @param  Closure(): string  $title  Called when asked, so it is said in the panel's language.
     */
    public function __construct(
        private string $model,
        private string $type,
        private Closure $title,
        private string $icon = 'folder',
        private int $order = 200,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function title(): string
    {
        return ($this->title)();
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function order(): int
    {
        return $this->order;
    }

    /**
     * Whoever may edit the categories: there is no read-only screen of them to open, so anybody
     * allowed to link to one is somebody allowed to edit them.
     */
    public function permission(): string
    {
        return ($this->model)::categoryKind()->manage;
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $found = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->scopes(['matching' => [$query]]))
            // The order an editor put them in, which is the order they stand in on the site.
            ->scopes(['ordered'])
            ->limit($limit)
            ->get();

        return array_values($this->candidates($found, $locale));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, LinkCandidate>
     */
    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Model&Category>
     */
    private function query(string $locale): Builder
    {
        return ($this->model)::query()->with(['routes' => static fn ($routes) => $routes
            ->where('locale', $locale)
            ->where('kind', 'canonical')]);
    }

    /**
     * @param  EloquentCollection<int, Model&Category>  $categories
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $categories, string $locale): array
    {
        $candidates = [];

        foreach ($categories as $category) {
            $id = (int) $category->getKey();
            $canonical = $category->getRelation('routes')->first();
            $path = $canonical instanceof Model ? (string) $canonical->getAttribute('path') : null;

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: $category->displayName($locale),
                url: $path !== null && method_exists($category, 'urlOf') ? $category->urlOf($path, $locale) : null,
                available: (bool) $category->getAttribute('is_visible') && $path !== null,
            );
        }

        return $candidates;
    }
}
