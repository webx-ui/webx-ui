<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Recipes\Models\Recipe;

/**
 * The query behind the list of recipes (§5.9): the whole list, no pages, in the one order recipes
 * have (decision 4).
 *
 * No paginator on purpose, as with services: the list is where the order is dragged, and a drag
 * cannot cross a page boundary. The filters narrow without changing the order — the panel just
 * stops offering the drag, because the gaps between the rows it shows are rows it does not.
 */
final class RecipeList
{
    public function __construct(private readonly RelationTargets $targets) {}

    /**
     * @return Builder<Recipe>
     */
    public function build(Request $request): Builder
    {
        $query = Recipe::query()->with(['routes', 'categories']);

        if ($request->boolean('trashed')) {
            // The bin is a list of things to bring back, and the last thing thrown away is the
            // one somebody is looking for.
            return $query->onlyTrashed()->orderByDesc('deleted_at')->orderByDesc('id');
        }

        // `q`, as every list of the panel sends it; `search` is what §5.10 first called it.
        $this->searching($query, trim((string) $request->query('q', (string) $request->query('search', ''))));
        $this->withStatus($query, (string) $request->query('status', ''));

        foreach (['category' => 'categories', 'nutrient' => 'nutrients'] as $parameter => $relation) {
            $id = $request->query($parameter);

            if (is_numeric($id)) {
                $query->scopes(['inCategory' => [(int) $id, $relation]]);
            }
        }

        $service = $request->query('service');

        if (is_numeric($service) && $this->targets->has('service')) {
            $query->scopes(['relatedTo' => ['services', 'service', (int) $service]]);
        }

        return $query->scopes(['orderedIn' => [null]]);
    }

    /**
     * Title or address containing the term, in any language the site has.
     *
     * @param  Builder<Recipe>  $query
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
     * apart.
     *
     * @param  Builder<Recipe>  $query
     */
    private function withStatus(Builder $query, string $status): void
    {
        $table = $query->getModel()->getTable();

        match ($status) {
            Recipe::STATUS_DRAFT => $query
                ->whereNull($table.'.published_at')
                ->whereDoesntHave('versions', static fn (Builder $version): Builder => $version->published()),
            Recipe::STATUS_UNPUBLISHED => $query
                ->whereNull($table.'.published_at')
                ->whereHas('versions', static fn (Builder $version): Builder => $version->published()),
            Recipe::STATUS_PUBLISHED => $query->whereNotNull($table.'.published_at')->whereNull($table.'.draft'),
            Recipe::STATUS_MODIFIED => $query->whereNotNull($table.'.published_at')->whereNotNull($table.'.draft'),
            default => $query,
        };
    }
}
