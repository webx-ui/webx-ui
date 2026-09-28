<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Tariffs\Models\Tariff;

/**
 * The query behind the list of tariffs (§5.4): all of them, no pages.
 *
 * No paginator on purpose, as with reviews: the list is where tariffs are put in order, and a drag
 * cannot cross a page boundary. Narrowed to one group, the list is that group's own order
 * (`item_position`) and a drag writes it; otherwise it is the order of the whole list. A search
 * narrows without changing the order.
 */
final class TariffList
{
    /**
     * @return Builder<Tariff>
     */
    public function build(Request $request): Builder
    {
        $query = Tariff::query()->with('categories');
        $category = $request->query('category');

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $term = trim((string) $request->query('search', ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            // In every language the site has, and in the badge and the description as well as the
            // name: "the one with 30 hours" is how somebody remembers a tariff.
            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('name', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('badge', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('description', $like));
            });
        }

        return $query->orderedIn(is_numeric($category) ? (int) $category : null);
    }
}
