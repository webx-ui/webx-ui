<?php

declare(strict_types=1);

namespace WebxUi\Press\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Press\Models\Outlet;

/**
 * The query behind the list of outlets (§4.10): all of them, no pages.
 *
 * No paginator on purpose, as with reviews: the list is where outlets are put in order, and a drag
 * cannot cross a page boundary. A search narrows without changing the order.
 */
final class OutletList
{
    /**
     * @return Builder<Outlet>
     */
    public function build(Request $request): Builder
    {
        $query = Outlet::query()->with('articles');

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $term = trim((string) $request->query('search', ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            // In every language the site has, and in the articles too: "the one that ran the
            // interview about the clinic" is how somebody remembers an outlet.
            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                    ->orWhere('website_url', 'like', $like)
                    ->orWhereHas('articles', static fn (Builder $articles): Builder => $articles->whereTranslationLikeAny('title', $like));
            });
        }

        return $query->ordered();
    }
}
