<?php

declare(strict_types=1);

namespace WebxUi\Team\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Team\Models\Member;

/**
 * The query behind the list of the team (§5.7): everybody, no pages.
 *
 * No paginator on purpose, as with the reviews: the list is where people are put in order, and a
 * drag cannot cross a page boundary. There is one order (decision 5); a search narrows without
 * changing it.
 */
final class MemberList
{
    /**
     * @return Builder<Member>
     */
    public function build(Request $request): Builder
    {
        $query = Member::query();

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        $term = trim((string) $request->query('search', ''));

        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            // In every language the site has, by the name or the job: "the orthodontist" is how
            // somebody remembers a person as often as by name.
            $query->where(static function (Builder $nested) use ($like): void {
                $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('name', $like))
                    ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('job_title', $like));
            });
        }

        return $query->orderBy('position')->orderBy('id');
    }
}
