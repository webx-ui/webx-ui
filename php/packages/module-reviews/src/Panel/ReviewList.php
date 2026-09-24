<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Reviews\Models\Review;

/**
 * The query behind the list of reviews (§4.7): all of them, no pages.
 *
 * No paginator on purpose, as with the FAQ: the list is where reviews are put in order, and a drag
 * cannot cross a page boundary.
 *
 * Narrowed to one category, the list is that category's own order (`item_position`) and a drag
 * writes it; otherwise it is the order of the whole list (decision 6). A search narrows without
 * changing the order.
 */
final class ReviewList
{
    /**
     * @return Builder<Review>
     */
    public function build(Request $request): Builder
    {
        $query = Review::query()->with('categories');
        $category = $request->query('category');

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $term = trim((string) $request->query('search', ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            // In every language the site has, and in the words as well as the name: "the one who
            // wrote about the night shift" is how somebody remembers a review.
            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('name', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('job_title', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('text', $like));
            });
        }

        return $query->orderedIn(is_numeric($category) ? (int) $category : null);
    }
}
