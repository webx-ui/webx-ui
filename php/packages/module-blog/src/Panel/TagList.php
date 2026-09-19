<?php

declare(strict_types=1);

namespace WebxUi\Blog\Panel;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Seo\TagIndexing;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\UrlNormaliser;

/**
 * The tags screen: a page of words, and the two numbers the filters beside it wear (§10).
 *
 * One class rather than a query in a controller, because the interesting filter is not a `where`
 * at all. "Not indexed" is the answer of {@see TagIndexing}, which asks `module-seo` whether a
 * rule matches the tag's address (§12) — so it cannot be asked of the database, and the list,
 * the count on the pill and the column in the row all have to be asking the same thing or the
 * screen contradicts itself.
 *
 * The addresses are read out of the loaded `routes` relation rather than one row at a time:
 * evaluating the rule needs the address of every tag that carries the flag, and a page of thirty
 * would otherwise cost thirty queries before it could draw one filter.
 */
final class TagList
{
    /** As many as a screen is worth scrolling; the rest is what the search is for. */
    public const PER_PAGE = 30;

    /** Most used first — what a duplicate is measured against. */
    public const SORT_ARTICLES = 'articles';

    /** Alphabetical, which is how "belt", "belts" and "belt drive" end up next to each other. */
    public const SORT_NAME = 'name';

    public function __construct(
        private readonly Locales $locales,
        private readonly TagIndexing $indexing,
    ) {}

    /**
     * A page of tags, the counts the filters show, and nothing else.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>, filters: array{total: int, empty: int, noindex: int}}
     */
    public function page(Request $request): array
    {
        $locale = $this->locales->current();
        $term = trim((string) $request->query('q', ''));

        $searched = $this->search($term);

        // Worked out once and used three times: the pill's count, the filter itself, and the
        // column of every row on the page that survives it.
        $outOfIndex = $this->outOfIndex($this->search($term), $locale);

        $query = $this->search($term)->withCount('articles');

        if ($request->boolean('empty')) {
            $query->whereDoesntHave('articles');
        }

        if ($request->boolean('noindex')) {
            $query->whereIn('id', $outOfIndex === [] ? [0] : $outOfIndex);
        }

        $page = $this->sorted($query, (string) $request->query('sort', ''), $locale)
            ->with($this->canonicalRoutes($locale))
            ->paginate(min(100, max(5, (int) $request->integer('per_page', self::PER_PAGE))));

        return [
            'data' => $this->rows($page, $locale, $outOfIndex),
            'meta' => $this->meta($page),
            'filters' => [
                'total' => (clone $searched)->count(),
                'empty' => (clone $searched)->whereDoesntHave('articles')->count(),
                'noindex' => count($outOfIndex),
            ],
        ];
    }

    /**
     * One tag, as a row of the same list.
     *
     * Used by everything that changes one — a rename, the switch on the index — so the screen
     * can put the answer straight into the row it came from rather than reloading the page and
     * losing the place somebody had scrolled to.
     *
     * @return array<string, mixed>
     */
    public function row(Tag $tag, ?string $locale = null): array
    {
        $locale ??= $this->locales->current();

        $tag->load($this->canonicalRoutes($locale));
        $tag->loadCount('articles');

        return $this->present($tag, $locale, null);
    }

    /**
     * One row of the screen.
     *
     * `$outOfIndex` is the answer already worked out for the whole page; `null` asks
     * {@see TagIndexing} about this one tag, which is what a rename or a switch needs and what
     * a page of thirty must not do thirty times.
     *
     * @param  list<int>|null  $outOfIndex
     * @return array<string, mixed>
     */
    private function present(Tag $tag, string $locale, ?array $outOfIndex): array
    {
        $path = $this->pathOf($tag, $locale);
        $url = $path === null ? null : $tag->urlOf($path, $locale);

        return [
            'id' => (int) $tag->getKey(),
            'title' => $this->name($tag, $locale),
            'titles' => $tag->getTranslations('title'),
            'slug' => (string) $tag->getTranslation('slug', $locale, fallback: false),
            // The address as a rule for it would be written: language prefix and all, so the
            // column and the SEO section are talking about the same string (§12).
            'path' => $url === null ? null : UrlNormaliser::normalise($url),
            'url' => $url,
            'noindex' => (bool) $tag->noindex,
            'indexing' => $outOfIndex === null
                ? $this->indexing->state($tag, $url)
                : $this->state($tag, $outOfIndex),
            'articles_count' => (int) ($tag->getAttribute('articles_count') ?? 0),
        ];
    }

    /**
     * The tags that are out of the index by the rule the rendered page follows (§12).
     *
     * Only the ones carrying the flag are asked about — an open tag is open whatever the rules
     * say — and all of them at once, with their addresses already loaded.
     *
     * @param  Builder<Tag>  $query
     * @return list<int>
     */
    private function outOfIndex(Builder $query, string $locale): array
    {
        $ids = [];

        $tags = $query->where('noindex', true)->with($this->canonicalRoutes($locale))->get();

        foreach ($tags as $tag) {
            $path = $this->pathOf($tag, $locale);
            $url = $path === null ? null : $tag->urlOf($path, $locale);

            if ($this->indexing->state($tag, $url) === Tag::INDEXING_NOINDEX) {
                $ids[] = (int) $tag->getKey();
            }
        }

        return $ids;
    }

    /**
     * The title in every language and the address, because a tag is looked for by the word
     * somebody remembers rather than by the language the panel is open in.
     *
     * @return Builder<Tag>
     */
    private function search(string $term): Builder
    {
        $query = Tag::query();

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(static function (Builder $nested) use ($like): void {
            $nested->where(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('title', $like))
                ->orWhere(static fn (Builder $half): Builder => $half->whereTranslationLikeAny('slug', $like));
        });
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    private function sorted(Builder $query, string $sort, string $locale): Builder
    {
        if ($sort === self::SORT_NAME) {
            // By the word as this panel spells it. A tag with no title in this language sorts
            // by the empty string and lands at the top, which is where something to fix belongs.
            return $query->orderBy('title->'.$locale)->orderBy('id');
        }

        return $query->orderByDesc('articles_count')->orderBy('id');
    }

    /**
     * The canonical rows of the registry for one language, as an eager load.
     *
     * @return array<string, callable>
     */
    private function canonicalRoutes(string $locale): array
    {
        return [
            'routes' => static function ($relation) use ($locale): void {
                $relation->where('locale', $locale)->where('kind', Route::CANONICAL);
            },
        ];
    }

    /** The address the registry holds for this language, or null when the tag has none (§9). */
    private function pathOf(Tag $tag, string $locale): ?string
    {
        if (! $tag->relationLoaded('routes')) {
            return $tag->routeCanonical($locale)?->path;
        }

        return $tag->routes
            ->first(static fn (Route $route): bool => $route->locale === $locale && $route->kind === Route::CANONICAL)
            ?->path;
    }

    /**
     * @param  LengthAwarePaginator<int, Tag>  $page
     * @param  list<int>  $outOfIndex
     * @return list<array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $page, string $locale, array $outOfIndex): array
    {
        $rows = [];

        // The state comes out of the list worked out for the whole page rather than being asked
        // again row by row: the count on the pill and the word in the row are then the same
        // answer by construction and not by coincidence.
        foreach ($page->items() as $tag) {
            $rows[] = $this->present($tag, $locale, $outOfIndex);
        }

        return $rows;
    }

    /**
     * @param  list<int>  $outOfIndex
     */
    private function state(Tag $tag, array $outOfIndex): string
    {
        if (! $tag->noindex) {
            return Tag::INDEXING_OPEN;
        }

        return in_array((int) $tag->getKey(), $outOfIndex, true) ? Tag::INDEXING_NOINDEX : Tag::INDEXING_RULE;
    }

    /**
     * A tag named in one language and not in another is still a row somebody can click: its
     * address is the name it has everywhere, and an unnamed row is worse than one named after
     * its address.
     */
    private function name(Tag $tag, string $locale): string
    {
        $title = $tag->getTranslation('title', $locale);

        return is_string($title) && trim($title) !== ''
            ? $title
            : (string) $tag->getTranslation('slug', $locale);
    }

    /**
     * @param  LengthAwarePaginator<int, Tag>  $page
     * @return array<string, int|null>
     */
    private function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ];
    }
}
