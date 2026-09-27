<?php

declare(strict_types=1);

namespace WebxUi\Press\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Press\Models\Outlet;
use WebxUi\Routing\Models\Route;

/**
 * Outlets, for whatever points at an entity: a menu entry, a link field, a block (§4.3). The hint
 * is the outlet's own site, which is what tells two outlets of one name apart.
 *
 * Registered only when outlets have pages (decision 11): without them there is nothing here to
 * point at.
 */
final class OutletLinkSource implements LinkSource
{
    public function type(): string
    {
        return Outlet::TYPE;
    }

    public function model(): string
    {
        return Outlet::class;
    }

    public function title(): string
    {
        return (string) __('webx-press::module.press');
    }

    public function icon(): string
    {
        return 'newspaper';
    }

    public function order(): int
    {
        return 260;
    }

    public function permission(): string
    {
        return 'press.view';
    }

    public function search(string $query, string $locale, int $limit): array
    {
        $outlets = $this->query($locale)
            ->when($query !== '', static fn (Builder $found): Builder => $found->where(static function (Builder $nested) use ($query): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
            }))
            ->ordered()
            ->limit($limit)
            ->get();

        return array_values($this->candidates($outlets, $locale));
    }

    public function resolve(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->candidates($this->query($locale)->whereKey($ids)->get(), $locale);
    }

    /**
     * @return Builder<Outlet>
     */
    private function query(string $locale): Builder
    {
        return Outlet::query()->with([
            'routes' => static fn ($routes) => $routes
                ->where('locale', $locale)
                ->where('kind', Route::CANONICAL),
        ]);
    }

    /**
     * @param  EloquentCollection<int, Outlet>  $outlets
     * @return array<int, LinkCandidate>
     */
    private function candidates(EloquentCollection $outlets, string $locale): array
    {
        $candidates = [];

        foreach ($outlets as $outlet) {
            $id = (int) $outlet->getKey();
            $canonical = $outlet->routes->first();
            $title = $outlet->displayTitle($locale);
            $website = $outlet->website();

            $candidates[$id] = new LinkCandidate(
                id: $id,
                title: $title !== '' ? $title : '#'.$id,
                url: $canonical instanceof Route ? $outlet->urlOf($canonical->path, $locale) : null,
                // No row in this language is an outlet with nothing to show in it (decision 7).
                available: $outlet->published && $canonical instanceof Route,
                hint: $website === null ? null : (parse_url($website, PHP_URL_HOST) ?: $website),
            );
        }

        return $candidates;
    }
}
