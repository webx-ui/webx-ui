<?php

declare(strict_types=1);

namespace WebxUi\Services\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Services\Models\Service;

/**
 * The query behind the list of services (§4.6): the whole catalogue, no pages.
 *
 * No paginator on purpose. A site has dozens of services, not thousands, and the list is where
 * they are put in order — a drag cannot cross a page boundary, and a drop on page two into a gap
 * nobody can see is not an order anybody chose.
 *
 * The order is decision 5 read out loud: narrowed to one category, the list is that category's
 * own order (`item_position`), and a drag writes it; otherwise it is the order of the whole list
 * (`position`). The other filters narrow without changing the order — the panel just stops
 * offering the drag, because the gaps between the rows it shows are rows it does not.
 */
final class ServiceList
{
    /**
     * @return Builder<Service>
     */
    public function build(Request $request): Builder
    {
        $query = Service::query()->with(['routes', 'categories', 'cover']);
        $category = $request->query('category');

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $this->searching($query, trim((string) $request->query('q', '')));
        $this->withStatus($query, (string) $request->query('status', ''));

        return $query->orderedIn(is_numeric($category) ? (int) $category : null);
    }

    /**
     * Services whose title or address contains the term, in any language the site has: the list
     * draws the title a service has, so one written only in English is on a Russian panel's
     * screen and has to be findable from it.
     *
     * @param  Builder<Service>  $query
     */
    private function searching(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(static function (Builder $nested) use ($like): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
    }

    /**
     * The four states of §4.6. Never published and taken off look the same in the columns; only
     * the history tells them apart, and "draft" on something that was live at breakfast is a lie.
     *
     * @param  Builder<Service>  $query
     */
    private function withStatus(Builder $query, string $status): void
    {
        $table = $query->getModel()->getTable();

        match ($status) {
            Service::STATUS_DRAFT => $query
                ->whereNull($table.'.published_at')
                ->whereDoesntHave('versions', static fn (Builder $version): Builder => $version->published()),
            Service::STATUS_UNPUBLISHED => $query
                ->whereNull($table.'.published_at')
                ->whereHas('versions', static fn (Builder $version): Builder => $version->published()),
            Service::STATUS_PUBLISHED => $query->whereNotNull($table.'.published_at')->whereNull($table.'.draft'),
            Service::STATUS_MODIFIED => $query->whereNotNull($table.'.published_at')->whereNotNull($table.'.draft'),
            default => $query,
        };
    }
}
