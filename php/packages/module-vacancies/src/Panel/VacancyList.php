<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * The query behind the list of vacancies (§4.10): the whole list, no pages — the order is dragged
 * by hand, and a site has dozens of vacancies, not thousands.
 *
 * The state is the first filter and the one that is always on: the open ones by default, the
 * closed ones — by hand or expired — or all of them, in the one order vacancies have. The bin is
 * its own list and knows no state.
 */
final class VacancyList
{
    /**
     * @return Builder<Vacancy>
     */
    public function build(Request $request): Builder
    {
        $query = Vacancy::query()->with(['routes', 'categories']);

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $this->searching($query, trim((string) $request->query('q', '')));
        $this->withStatus($query, (string) $request->query('status', ''));

        $category = $request->query('category');

        if (is_numeric($category)) {
            $query->scopes(['inCategory' => [(int) $category]]);
        }

        match ((string) $request->query('state', 'open')) {
            'closed' => $query->scopes(['closed']),
            'all' => $query,
            default => $query->scopes(['open']),
        };

        return $query->scopes(['byPosition']);
    }

    /**
     * Title or address containing the term, in any language the site has.
     *
     * @param  Builder<Vacancy>  $query
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
     * Never published and taken off look the same in the columns; only the history tells them
     * apart. `published` is everything on the site, with edits waiting or without; `modified` only
     * the ones with edits.
     *
     * @param  Builder<Vacancy>  $query
     */
    private function withStatus(Builder $query, string $status): void
    {
        $table = $query->getModel()->getTable();

        match ($status) {
            Vacancy::STATUS_DRAFT => $query
                ->whereNull($table.'.published_at')
                ->whereDoesntHave('versions', static fn (Builder $version): Builder => $version->published()),
            Vacancy::STATUS_UNPUBLISHED => $query
                ->whereNull($table.'.published_at')
                ->whereHas('versions', static fn (Builder $version): Builder => $version->published()),
            Vacancy::STATUS_PUBLISHED => $query->whereNotNull($table.'.published_at'),
            Vacancy::STATUS_MODIFIED => $query->whereNotNull($table.'.published_at')->whereNotNull($table.'.draft'),
            default => $query,
        };
    }
}
