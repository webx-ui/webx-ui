<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Routing\SiteUrl;

/**
 * {@see Thing} as something to link to, answering the way a real source does: the address in
 * the language asked for, and `available` as the module's own answer rather than the registry's.
 */
final class ThingLinkSource implements LinkSource
{
    public function type(): string
    {
        return 'thing';
    }

    public function model(): string
    {
        return Thing::class;
    }

    public function title(): string
    {
        return 'Things';
    }

    public function icon(): string
    {
        return 'file';
    }

    public function order(): int
    {
        return 100;
    }

    public function permission(): ?string
    {
        return null;
    }

    /**
     * @return list<LinkCandidate>
     */
    public function search(string $query, string $locale, int $limit): array
    {
        $things = Thing::query()
            ->when($query !== '', static fn (Builder $found): Builder => $found->where('slug', 'like', "%{$query}%"))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($this->candidates($things, $locale));
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

        return $this->candidates(Thing::query()->whereKey($ids)->get(), $locale);
    }

    /**
     * @param  EloquentCollection<int, Thing>  $things
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $things, string $locale): array
    {
        $site = app(SiteUrl::class);
        $candidates = [];

        foreach ($things as $thing) {
            $title = $thing->getTranslation('title', $locale, false);
            $id = (int) $thing->getKey();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: is_string($title) ? $title : '',
                url: $site->to($thing->slug, $locale),
                available: $thing->published,
                hint: null,
            );
        }

        return $candidates;
    }
}
