<?php

declare(strict_types=1);

namespace WebxUi\Faq\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Faq\Models\Question;

/**
 * The query behind the list of questions (§4.6): the whole FAQ, no pages.
 *
 * No paginator on purpose, as with services: a FAQ has dozens of questions, and the list is where
 * they are put in order — a drag cannot cross a page boundary.
 *
 * Narrowed to one category, the list is that category's own order (`item_position`) and a drag
 * writes it; otherwise it is the order of the whole list (decision 4). A search narrows without
 * changing the order — the panel stops offering the drag, because the gaps between the rows it
 * shows are rows it does not.
 */
final class QuestionList
{
    /**
     * @return Builder<Question>
     */
    public function build(Request $request): Builder
    {
        $query = Question::query()->with('categories');
        $category = $request->query('category');

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $term = trim((string) $request->query('search', ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            // In every language the site has: the list draws the question a record has, so one
            // written only in English is on a Russian panel's screen and has to be findable.
            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('question', $like))
                    ->orWhere('anchor', 'like', $like);
            });
        }

        return $query->orderedIn(is_numeric($category) ? (int) $category : null);
    }
}
