<?php

declare(strict_types=1);

namespace WebxUi\Events\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Events\Models\Event;

/**
 * The query behind the list of events (§4.9): a page at a time — there is no order to drag, and
 * the past ones pile up for years.
 *
 * "When" is the first filter and the one that is always on: the events to come by default, in the
 * order they come; the past ones from the last; all of them the way `events()->all()` lists them.
 */
final class EventList
{
    public const PER_PAGE = 20;

    public function __construct(private readonly RelationTargets $targets) {}

    /**
     * @return Builder<Event>
     */
    public function build(Request $request): Builder
    {
        $query = Event::query()->with(['routes', 'categories']);

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        // `q`, as every list of the panel sends it; `search` as some of the older ones did.
        $this->searching($query, trim((string) $request->query('q', (string) $request->query('search', ''))));
        $this->withStatus($query, (string) $request->query('status', ''));

        $category = $request->query('category');

        if (is_numeric($category)) {
            $query->scopes(['inCategory' => [(int) $category]]);
        }

        $service = $request->query('service');

        if (is_numeric($service) && $this->targets->has('service')) {
            $query->scopes(['relatedTo' => [Event::SERVICES, 'service', (int) $service]]);
        }

        return $query->scopes(match ((string) $request->query('when', 'upcoming')) {
            'past' => ['past'],
            'all' => ['byDate'],
            default => ['upcoming'],
        });
    }

    /**
     * Title or address containing the term, in any language the site has.
     *
     * @param  Builder<Event>  $query
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
     * @param  Builder<Event>  $query
     */
    private function withStatus(Builder $query, string $status): void
    {
        $table = $query->getModel()->getTable();

        match ($status) {
            Event::STATUS_DRAFT => $query
                ->whereNull($table.'.published_at')
                ->whereDoesntHave('versions', static fn (Builder $version): Builder => $version->published()),
            Event::STATUS_UNPUBLISHED => $query
                ->whereNull($table.'.published_at')
                ->whereHas('versions', static fn (Builder $version): Builder => $version->published()),
            // Live is live, edits waiting or not: the panel's filter says "on the site".
            Event::STATUS_PUBLISHED => $query->whereNotNull($table.'.published_at'),
            Event::STATUS_MODIFIED => $query->whereNotNull($table.'.published_at')->whereNotNull($table.'.draft'),
            default => $query,
        };
    }
}
